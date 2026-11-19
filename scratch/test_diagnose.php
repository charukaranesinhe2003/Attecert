<?php
require_once __DIR__ . '/../bootstrap/autoload.php';
foreach (['app.php', 'database.php', 'mail.php', 'sms.php', 'verification.php'] as $cfg) {
    $res = include 'config/' . $cfg;
    echo $cfg . ': ' . (is_array($res) ? 'OK' : 'FAIL') . PHP_EOL;
}
