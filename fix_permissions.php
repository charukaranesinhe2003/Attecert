<?php
declare(strict_types=1);

// Locate vendor folder
$vendorDirs = [
    __DIR__ . '/vendor',
    dirname(__DIR__) . '/vendor',
    '/home/azharbio/attecert.com/vendor'
];

$vendorPath = null;
foreach ($vendorDirs as $candidate) {
    if (is_dir($candidate)) {
        $vendorPath = realpath($candidate);
        break;
    }
}

header('Content-Type: text/plain; charset=utf-8');

if (!$vendorPath) {
    die("Vendor folder not found! Checked: " . implode(', ', $vendorDirs));
}

echo "Starting permission fix on: {$vendorPath}\n";

$dirsCount = 0;
$filesCount = 0;
$errors = [];

// Fix root vendor folder
if (!@chmod($vendorPath, 0755)) {
    $errors[] = "Failed to chmod 755 on {$vendorPath}";
} else {
    $dirsCount++;
}

try {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($vendorPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $path = $item->getPathname();
        if ($item->isDir()) {
            if (!@chmod($path, 0755)) {
                $errors[] = "Dir failed: {$path}";
            } else {
                $dirsCount++;
            }
        } elseif ($item->isFile()) {
            if (!@chmod($path, 0644)) {
                $errors[] = "File failed: {$path}";
            } else {
                $filesCount++;
            }
        }
    }
} catch (Throwable $e) {
    echo "Exception while iterating: " . $e->getMessage() . "\n";
}

echo "\n--- SUMMARY ---\n";
echo "Directories fixed to 0755: {$dirsCount}\n";
echo "Files fixed to 0644: {$filesCount}\n";

if (!empty($errors)) {
    echo "Errors encountered (" . count($errors) . "):\n";
    foreach (array_slice($errors, 0, 10) as $err) {
        echo " - {$err}\n";
    }
    if (count($errors) > 10) {
        echo " ... and " . (count($errors) - 10) . " more.\n";
    }
} else {
    echo "\nAll permissions set successfully!\n";
}

echo "\nSECURITY NOTE: Please DELETE this fix_permissions.php file from cPanel File Manager after running it.\n";
