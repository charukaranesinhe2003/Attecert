<?php

declare(strict_types=1);

// Explicit/scheduled delivery only. Daily financial cron does not invoke this command.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/bootstrap/app.php';

$options = getopt('', ['limit:', 'id:']);
$limit = max(1, min(100, (int) ($options['limit'] ?? 20)));
$id = isset($options['id']) ? filter_var($options['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
if ($id === false) {
    fwrite(STDERR, "The optional --id must be a positive email log ID.\n");
    exit(1);
}
$mail = app(App\Services\MailService::class);
if (!$mail->smtpReady()) {
    fwrite(STDERR, "SMTP is not configured. No messages were delivered.\n");
    exit(1);
}
try {
    $results = $mail->processQueue($limit, $id);
    $counts = array_count_values(array_column($results, 'status'));
    echo 'Mail worker: ' . count($results) . ' attempted; ' . json_encode($counts) . ".\n";
    exit(isset($counts['failed']) || isset($counts['uncertain']) ? 1 : 0);
} catch (Throwable) {
    fwrite(STDERR, "Mail worker stopped. Check migration/database availability and the email audit page.\n");
    exit(1);
}
