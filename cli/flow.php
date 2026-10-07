<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/bootstrap/app.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

use App\Core\Database;
use App\Services\AuthService;
use App\Services\CaseService;
use App\Services\DocumentService;
use App\Services\IntakeService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\QuotationService;
use App\Services\WorkflowService;

$failed = 0;
$check = static function (bool $condition, string $message) use (&$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . PHP_EOL;
    if (!$condition) {
        $failed++;
    }
};

$db = app(Database::class);
$auth = app(AuthService::class);
$intake = app(IntakeService::class);
$cases = app(CaseService::class);
$quotes = app(QuotationService::class);
$invoices = app(InvoiceService::class);
$payments = app(PaymentService::class);
$documents = app(DocumentService::class);
$workflow = app(WorkflowService::class);

$admin = $auth->attempt('admin@attecert.local', 'Admin@12345', '127.0.0.1');
$check($admin['ok'] === true, 'admin login');
$adminId = (int) $auth->id();

$email = 'flow+' . bin2hex(random_bytes(3)) . '@example.com';
$uae = (int) $db->fetchColumn("SELECT id FROM countries WHERE code = 'AE'");
$degree = (int) $db->fetchColumn("SELECT id FROM document_types WHERE code = 'DEGREE'");
$translation = (int) $db->fetchColumn("SELECT id FROM services WHERE code = 'TRANS'");
$mofa = (int) $db->fetchColumn("SELECT id FROM services WHERE code = 'MOFA-ATT'");
$apostille = (int) $db->fetchColumn("SELECT id FROM services WHERE code = 'APOS'");

$quote = $intake->quote([
    'name' => 'Kasun Perera',
    'email' => $email,
    'phone' => '+94770001111',
    'whatsapp' => '+94770001111',
    'country_id' => $uae,
    'document_type_id' => $degree,
    'document_count' => 1,
    'message' => 'Degree certificate for UAE employment.',
], [$translation, $mofa, $apostille], []);
$requestId = (int) $quote['id'];
$check($requestId > 0 && str_starts_with((string) $quote['number'], 'ATT-'), 'quote request stored with tracking code');

$caseId = $cases->createFromQuote($requestId, $adminId);
$case = $cases->find($caseId);
$check($case !== null && str_starts_with($case['number'], 'ATT-'), 'case number ' . ($case['number'] ?? ''));
$check(count($case['services']) === 3, 'three services attached');

$lines = [];
foreach ($case['services'] as $service) {
    $lines[] = [
        'service_id' => (int) $service['service_id'],
        'quantity' => 1,
        'unit_price' => ((int) $service['service_id'] === $apostille) ? '5000' : '1',
        'description' => $service['name'],
    ];
}
$quotationId = $quotes->save($caseId, [
    'lines' => $lines,
    'discount_amount' => '0',
    'tax_rate' => '0',
    'additional_amount' => '0',
    'notes' => 'Confirmed path',
    'terms' => 'Valid 14 days',
    'validity_days' => 14,
], $adminId, true, true);
$bundle = $quotes->bundle($quotationId);
$check($bundle['quotation']['total'] === '12000.00', 'quotation total 12000, got ' . $bundle['quotation']['total']);
$fixed = null;
foreach ($bundle['items'] as $item) {
    if ((int) $item['service_id'] === $translation) {
        $fixed = $item['unit_price'];
    }
}
$check($fixed === '3500.00', 'fixed translation price preserved, got ' . $fixed);
$pdf = $quotes->pdfPath($quotationId);
$check(is_file($pdf) && filesize($pdf) > 1000, 'quotation pdf');
$mail = $quotes->send($quotationId, $adminId);
$check($mail['status'] === 'logged', 'quotation email reports missing SMTP instead of fake success');

session_write_close();
session_start();
$customerLogin = $auth->attempt($email, 'wrong-password', '127.0.0.1');
$check($customerLogin['ok'] === false, 'wrong password rejected');
$db->run('UPDATE users SET password_hash = ? WHERE email = ?', [password_hash('Customer@12345', PASSWORD_DEFAULT), $email]);
$customerLogin = $auth->attempt($email, 'Customer@12345', '127.0.0.1');
$check($customerLogin['ok'] === true, 'customer login');
$customer = $auth->customer();
$check($customer !== null && (int) $customer['id'] === (int) $case['customer_id'], 'customer owns case');
$quotes->markViewed($quotationId);
$invoiceId = $quotes->accept($quotationId, (int) $auth->id(), '127.0.0.1');
$invoice = $invoices->bundle($invoiceId);
$check($invoice['invoice']['total'] === '12000.00', 'invoice copied quotation total');
$check($invoice['items'][0]['unit_price'] !== null, 'invoice lines stored historically');
$invoicePdf = $invoices->pdfPath($invoiceId);
$check(is_file($invoicePdf), 'invoice pdf');
$invoiceMail = $invoices->send($invoiceId, $adminId);
$check(in_array($invoiceMail['status'], ['logged', 'sent'], true), 'invoice email attempted');

$paymentId = $payments->record($invoiceId, [
    'amount' => '12000.00',
    'method' => 'bank_transfer',
    'paid_at' => date('Y-m-d'),
    'reference' => 'BANK-100',
    'notes' => 'Test payment',
], $adminId);
$paid = $db->fetch('SELECT status, amount_paid FROM invoices WHERE id = ?', [$invoiceId]);
$check($paid['status'] === 'paid' && $paid['amount_paid'] === '12000.00', 'invoice marked paid');
$receipt = $payments->receiptPath($paymentId);
$check(is_file($receipt), 'receipt pdf');

$tmp = tempnam(sys_get_temp_dir(), 'att') . '.png';
$image = imagecreatetruecolor(20, 20);
imagepng($image, $tmp);
imagedestroy($image);
$docId = $documents->store($caseId, null, [
    'name' => 'mofa-final.png',
    'type' => 'image/png',
    'tmp_name' => $tmp,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tmp),
], 'completed', 'mofa', 'MOFA attested degree', true, $adminId);
$download = $documents->download($docId, (int) $customer['id'], (int) $auth->id(), false);
$check(is_file($download['path']), 'customer can download visible document');
$denied = false;
try {
    $documents->download($docId, 999999, (int) $auth->id(), false);
} catch (Throwable) {
    $denied = true;
}
$check($denied, 'other customer cannot download the document');

$workflow->change($caseId, 'submitted_to_embassy', $adminId, 'Embassy submitted', true, false);
$workflow->change($caseId, 'embassy_processing', $adminId, 'Embassy processing', true, false);
$workflow->change($caseId, 'completed', $adminId, 'Final file released', true, false);
$final = $cases->find($caseId);
$check($final['status_code'] === 'completed', 'case completed');
$public = $cases->track($final['number']);
$check($public !== null && !isset($public['customer_email']) && !isset($public['email']), 'public tracking hides contact data');
$audit = (int) $db->fetchColumn('SELECT COUNT(*) FROM audit_logs WHERE entity_id = ? AND action = ?', [$caseId, 'case_status_changed']);
$check($audit > 0, 'audit history recorded');

echo $failed === 0 ? "FLOW OK\n" : "FLOW FAILURES {$failed}\n";
exit($failed === 0 ? 0 : 1);
