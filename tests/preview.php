<?php

declare(strict_types=1);

// Optional local visual QA. Only serves a fresh test database; never the configured one.
require dirname(__DIR__) . '/vendor/autoload.php';

if (getenv('ATTECERT_INTEGRATION') !== '1' || PHP_SAPI !== 'cli') {
    exit("Run from CLI with ATTECERT_INTEGRATION=1.\n");
}
class PreviewFixture extends \Tests\DatabaseTestCase {}
PreviewFixture::setUpBeforeClass();
$process = null;
try {
    $db = app(\App\Core\Database::class);
    $name = (string) $db->fetchColumn('SELECT DATABASE()');
    $adminId = (int) $db->fetchColumn('SELECT id FROM users ORDER BY id LIMIT 1');
    $db->run('UPDATE users SET must_change_password = 0 WHERE id = ?', [$adminId]);
    $caseId = (int) $db->fetchColumn('SELECT id FROM cases ORDER BY id LIMIT 1');
    $customerId = (int) $db->fetchColumn('SELECT customer_id FROM cases WHERE id = ?', [$caseId]);
    $_SESSION = ['locale' => 'en', 'user_id' => $adminId];
    app(\App\Services\CaseService::class)->create(['customer_id' => $customerId, 'country_id' => null, 'document_type_id' => null, 'assigned_to' => null, 'document_title' => 'New certificate review', 'source' => 'admin'], [], $adminId);
    $quote = app(\App\Services\QuotationService::class)->save($caseId, ['lines' => [['description' => 'Attestation review', 'quantity' => 1, 'unit_price' => 100]]], $adminId, false, true);
    $invoice = app(\App\Services\InvoiceService::class)->createFromQuotation($quote, $adminId);
    $db->run('UPDATE invoices SET due_at = DATE_SUB(CURDATE(), INTERVAL 2 DAY) WHERE id = ?', [$invoice]);
    $tmp = storage_path('preview.png');
    if (!is_dir(dirname($tmp))) mkdir(dirname($tmp), 0775, true);
    $image = imagecreatetruecolor(20, 20);
    imagepng($image, $tmp);
    imagedestroy($image);
    app(\App\Services\DocumentService::class)->store($caseId, null, ['name' => 'Completed-certificate.png', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)], 'completed', 'mofa', 'Review before release', false, $adminId);
    $db->run('INSERT INTO mofa_processes (case_id, status, expected_at, updated_by) VALUES (?, ?, DATE_SUB(CURDATE(), INTERVAL 1 DAY), ?)', [$caseId, 'processing', $adminId]);
    putenv('ATTECERT_PREVIEW_DATABASE=' . $name);
    putenv('ATTECERT_PREVIEW_STORAGE=' . storage_path());
    $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:8091', '-t', base_path('public'), base_path('tests/preview-router.php')], [STDIN, STDOUT, STDERR], $pipes, base_path());
    if (!is_resource($process)) throw new RuntimeException('Could not start visual QA server.');
    echo "Preview ready at http://127.0.0.1:8091/admin\n";
    echo 'Stop marker: ' . storage_path('stop-preview') . "\n";
    while (proc_get_status($process)['running'] && !is_file(storage_path('stop-preview'))) {
        usleep(250000);
    }
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    PreviewFixture::tearDownAfterClass();
}
