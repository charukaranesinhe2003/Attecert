<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;

final class CustomerUpdateService
{
    public function __construct(private Database $db, private MailService $mail, private SmsService $sms, private NotificationService $notifications) {}

    public function send(int $caseId, string $title, string $message, string $state = 'status', ?string $stepDate = null, array $details = [], ?array $attachment = null, array $attachments = []): array
    {
        $case = $this->db->fetch("SELECT c.*, cu.name AS customer_name, cu.email AS customer_email,
            COALESCE(NULLIF(cu.phone, ''), u.phone) AS customer_phone, cu.user_id FROM cases c
            INNER JOIN customers cu ON cu.id = c.customer_id LEFT JOIN users u ON u.id = cu.user_id
            WHERE c.id = ? AND c.deleted_at IS NULL AND cu.deleted_at IS NULL", [$caseId]);
        if ($case === null) { throw new \App\Core\HttpException(404, 'Case not found.'); }
        $label = ['pending' => 'Pending', 'current' => 'In progress', 'completed' => 'Completed'][$state] ?? 'Status updated';
        $delivery = $this->mail->send($case['customer_email'], 'Tracking update: ' . $case['number'], 'tracking-update', [
            'name' => $case['customer_name'], 'number' => $case['number'], 'title' => $title,
            'message' => $message, 'state' => $state, 'stepDate' => $stepDate,
            'documentTitle' => $case['document_title'], 'expected' => $case['tracking_expected_on'], 'details' => $details,
            'progressHistory' => app(ProgressHistoryService::class)->forCase($caseId),
            'attachmentName' => $attachment['name'] ?? null,
            'attachmentNames' => array_merge($attachment === null ? [] : [$attachment['name']], array_column($attachments, 'name')),
            'link' => absolute_url('/track?number=' . rawurlencode($case['number'])),
        ], 'case', $caseId, $attachment['path'] ?? null, $attachment['name'] ?? null, null, $attachments);
        $summary = 'AtteCert ' . $case['number'] . ': ' . preg_replace('/\s+/u', ' ', strip_tags($title));
        $delivery['sms'] = $this->sms->queue($case['customer_phone'], $case['customer_name'], mb_substr($summary, 0, 115) . ' - ' . $label . '.', 'case', $caseId);
        if ($case['user_id'] !== null) {
            $this->notifications->notify((int) $case['user_id'], $title, $case['number'] . ': ' . $message, '/portal/applications/' . $caseId);
        }
        return $delivery;
    }
}
