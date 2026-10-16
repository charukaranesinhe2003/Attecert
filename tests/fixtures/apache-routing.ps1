param(
    [string]$ApachePath = 'C:\xampp\apache\bin\httpd.exe',
    [int]$RootPort = 18091,
    [int]$SubdirectoryPort = 18092
)

$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$cacheRoot = [IO.Path]::GetFullPath((Join-Path $projectRoot 'storage\cache'))
$fixtureRoot = [IO.Path]::GetFullPath((Join-Path $cacheRoot ('apache-routing-' + [guid]::NewGuid().ToString('N'))))
$server = $null

function Write-FixtureFile([string]$Path, [string]$Content) {
    [IO.Directory]::CreateDirectory([IO.Path]::GetDirectoryName($Path)) | Out-Null
    [IO.File]::WriteAllText($Path, $Content, [Text.UTF8Encoding]::new($false))
}

function New-ProjectFixture([string]$Path) {
    [IO.Directory]::CreateDirectory($Path) | Out-Null
    Copy-Item -LiteralPath (Join-Path $projectRoot '.htaccess') -Destination (Join-Path $Path '.htaccess')
    Write-FixtureFile (Join-Path $Path 'public\index.php') 'PUBLIC_FRONT_CONTROLLER_SENTINEL'
    Copy-Item -LiteralPath (Join-Path $projectRoot 'public\.htaccess') -Destination (Join-Path $Path 'public\.htaccess')
    Write-FixtureFile (Join-Path $Path 'index.php') 'ROOT_WRAPPER_SENTINEL'
    Write-FixtureFile (Join-Path $Path 'public\assets\test.css') 'ASSET_SENTINEL'
    Write-FixtureFile (Join-Path $Path 'app\private.txt') 'PRIVATE_SENTINEL'
    Write-FixtureFile (Join-Path $Path 'storage\private.txt') 'PRIVATE_SENTINEL'
    Write-FixtureFile (Join-Path $Path '.env') 'PRIVATE_SENTINEL'
    Write-FixtureFile (Join-Path $Path '.git\config') 'PRIVATE_SENTINEL'
    Write-FixtureFile (Join-Path $Path 'public\.env') 'PRIVATE_SENTINEL'
    Write-FixtureFile (Join-Path $Path 'public\.git\config') 'PRIVATE_SENTINEL'
    Write-FixtureFile (Join-Path $Path 'composer.json') 'ROOT_METADATA_SENTINEL'
}

function Read-TestResponse([string]$Url) {
    try {
        $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 5
        $body = if ($response.Content -is [byte[]]) { [Text.Encoding]::UTF8.GetString($response.Content) } else { [string]$response.Content }
        return @{ Status = [int]$response.StatusCode; Body = $body }
    } catch {
        if ($null -eq $_.Exception.Response) { throw }
        return @{ Status = [int]$_.Exception.Response.StatusCode; Body = '' }
    }
}

try {
    if (!(Test-Path -LiteralPath $ApachePath -PathType Leaf)) { throw 'Apache executable is unavailable.' }
    foreach ($port in @($RootPort, $SubdirectoryPort)) {
        $probe = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, $port)
        try { $probe.Start() } finally { $probe.Stop() }
    }
    New-ProjectFixture (Join-Path $fixtureRoot 'root-install')
    New-ProjectFixture (Join-Path $fixtureRoot 'subdirectory-install\Attecert')
    $apacheRoot = (Split-Path (Split-Path $ApachePath -Parent) -Parent).Replace('\', '/')
    $fixtureUnix = $fixtureRoot.Replace('\', '/')
    $configuration = @"
ServerRoot "$apacheRoot"
Listen 127.0.0.1:$RootPort
Listen 127.0.0.1:$SubdirectoryPort
ServerName 127.0.0.1
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule dir_module modules/mod_dir.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule headers_module modules/mod_headers.so
LoadModule mime_module modules/mod_mime.so
TypesConfig "$apacheRoot/conf/mime.types"
PidFile "$fixtureUnix/httpd.pid"
ErrorLog "$fixtureUnix/error.log"
<Directory />
    AllowOverride None
    Require all denied
</Directory>
<Directory "$fixtureUnix">
    AllowOverride All
    Require all granted
</Directory>
<VirtualHost 127.0.0.1:$RootPort>
    DocumentRoot "$fixtureUnix/root-install"
</VirtualHost>
<VirtualHost 127.0.0.1:$SubdirectoryPort>
    DocumentRoot "$fixtureUnix/subdirectory-install"
</VirtualHost>
"@
    $configurationPath = Join-Path $fixtureRoot 'httpd.conf'
    Write-FixtureFile $configurationPath $configuration
    & $ApachePath -t -f $configurationPath
    if ($LASTEXITCODE -ne 0) { throw 'Apache rejected the isolated configuration.' }
    $server = Start-Process -FilePath $ApachePath -ArgumentList @('-X', '-f', ('"' + $configurationPath + '"')) -WindowStyle Hidden -PassThru
    for ($attempt = 0; $attempt -lt 20; $attempt++) {
        if ($server.HasExited) { throw ('Apache exited: ' + (Get-Content -LiteralPath (Join-Path $fixtureRoot 'error.log') -Raw)) }
        try { $null = Read-TestResponse "http://127.0.0.1:$RootPort/"; break } catch { Start-Sleep -Milliseconds 100 }
    }
    $checks = 0
    foreach ($installation in @(@{ Port = $RootPort; Base = '' }, @{ Port = $SubdirectoryPort; Base = '/Attecert' })) {
        foreach ($route in @('/', '/admin/cases', '/public/', '/public/admin/cases', '/assets/test.css', '/public/assets/test.css', '/index.php')) {
            $url = 'http://127.0.0.1:' + $installation.Port + $installation.Base + $route
            $expected = if ($route.Contains('test.css')) { 'ASSET_SENTINEL' } else { 'PUBLIC_FRONT_CONTROLLER_SENTINEL' }
            $response = Read-TestResponse $url
            if ($response.Status -ne 200 -or $response.Body.Trim() -ne $expected) { throw "Routing mismatch: $url status $($response.Status), body $($response.Body)" }
            $checks++
        }
        foreach ($route in @('/app/private.txt', '/storage/private.txt', '/.env', '/.git/config', '/public/.env', '/public/.git/config')) {
            $url = 'http://127.0.0.1:' + $installation.Port + $installation.Base + $route
            $response = Read-TestResponse $url
            if ($response.Status -ne 403) { throw "Private path was not denied: $url status $($response.Status)" }
            $checks++
        }
        $url = 'http://127.0.0.1:' + $installation.Port + $installation.Base + '/composer.json'
        $response = Read-TestResponse $url
        if ($response.Body.Contains('ROOT_METADATA_SENTINEL')) { throw "Root metadata leaked: $url" }
        $checks++
    }
    Write-Output "Apache routing passed: $checks checks across root and subdirectory installations."
} catch {
    $errorLog = Join-Path $fixtureRoot 'error.log'
    if (Test-Path -LiteralPath $errorLog) { Write-Output (Get-Content -LiteralPath $errorLog -Raw) }
    throw
} finally {
    if ($null -ne $server -and !$server.HasExited) { Stop-Process -Id $server.Id -Force; $server.WaitForExit() }
    if ($fixtureRoot.StartsWith($cacheRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase) -and (Split-Path $fixtureRoot -Leaf).StartsWith('apache-routing-')) {
        if (Test-Path -LiteralPath $fixtureRoot) { Remove-Item -LiteralPath $fixtureRoot -Recurse -Force }
    } else { throw 'Refusing to clean up a fixture outside storage/cache.' }
}
