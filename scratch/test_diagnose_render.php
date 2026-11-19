<?php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/diagnose.php';
ob_start();
include __DIR__ . '/../diagnose.php';
$out = ob_get_clean();
echo 'Length: ' . strlen($out) . PHP_EOL;
echo 'Has Section 1: ' . (str_contains($out, '1. Root Directory') ? 'YES' : 'NO') . PHP_EOL;
echo 'Has Section 2: ' . (str_contains($out, '2. Config Files Check') ? 'YES' : 'NO') . PHP_EOL;
echo 'Has Section 3: ' . (str_contains($out, '3. Environment') ? 'YES' : 'NO') . PHP_EOL;
echo 'Has Section 4: ' . (str_contains($out, '4. Email') ? 'YES' : 'NO') . PHP_EOL;
echo 'Has Section 5: ' . (str_contains($out, '5. Recent Error Logs') ? 'YES' : 'NO') . PHP_EOL;
echo 'Has Section 6: ' . (str_contains($out, '6. Automatic Full Permission Fix') ? 'YES' : 'NO') . PHP_EOL;
