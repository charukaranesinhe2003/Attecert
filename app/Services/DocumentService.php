<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;

final class DocumentService
{
    private const MIME = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private Database $db,
        private AuditService $audit,
        private NotificationService $notifications,
        private MailService $mail
    ) {
    }

    public function store(?int $caseId, ?int $quoteRequestId, array $file, string $category, ?string $subcategory, ?string $description, bool $visible, ?int $userId): int
    {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new HttpException(422, __('documents.upload_failed'));
        }
        $max = (int) setting('upload_max_mb', config('app.upload_max_mb', 10)) * 1024 * 1024;
        if (($file['size'] ?? 0) <= 0 || $file['size'] > $max) {
            throw new HttpException(422, __('documents.too_large'));
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if (!isset(self::MIME[$mime])) {
            throw new HttpException(422, __('documents.bad_type'));
        }
        $extension = self::MIME[$mime];
        $original = basename((string) $file['name']);
        $originalExt = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowedExt = $extension === 'jpg' ? ['jpg', 'jpeg'] : [$extension];
        if ($originalExt !== '' && !in_array($originalExt, $allowedExt, true)) {
            throw new HttpException(422, __('documents.bad_type'));
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $extension;
        $folder = $caseId ? 'case-' . $caseId : 'request-' . (int) $quoteRequestId;
        $directory = base_path('storage/documents/' . $folder);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create document storage.');
        }
        $target = $directory . '/' . $stored;
        $storedOk = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $target)
            : copy($file['tmp_name'], $target);
        if (!$storedOk) {
            throw new \RuntimeException('Unable to store the uploaded file.');
        }
        $id = $this->db->insert(
            'INSERT INTO documents (case_id, quote_request_id, category, subcategory, original_name, stored_name, mime, size_bytes, description, customer_visible, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$caseId, $quoteRequestId, $category, $subcategory, $original, $folder . '/' . $stored, $mime, (int) $file['size'], $description, $visible ? 1 : 0, $userId]
        );
        $this->audit->log($userId, 'document_uploaded', 'document', $id, [
            'case_id' => $caseId,
            'category' => $category,
            'name' => $original,
        ]);
        if ($visible && $caseId !== null) {
            $case = $this->db->fetch(
                'SELECT c.number, cu.user_id, cu.email, cu.name FROM cases c INNER JOIN customers cu ON cu.id = c.customer_id WHERE c.id = ?',
                [$caseId]
            );
            if ($case !== null) {
                $this->notifications->notify(
                    $case['user_id'] !== null ? (int) $case['user_id'] : null,
                    __('notifications.document_title'),
                    __('notifications.document_body', ['number' => $case['number']]),
                    '/portal/documents'
                );
                $this->mail->send(
                    $case['email'],
                    __('mail.document_subject', ['number' => $case['number']]),
                    'document-ready',
                    ['name' => $case['name'], 'number' => $case['number'], 'link' => absolute_url('/portal/documents')],
                    'document',
                    $id
                );
            }
        }

        return $id;
    }

    public function forCase(int $caseId): array
    {
        return $this->db->fetchAll(
            'SELECT d.*, u.name AS uploader_name
             FROM documents d
             LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.case_id = ? AND d.deleted_at IS NULL
             ORDER BY d.created_at DESC',
            [$caseId]
        );
    }

    public function forCustomer(int $customerId): array
    {
        return $this->db->fetchAll(
            'SELECT d.*, c.number AS case_number
             FROM documents d
             INNER JOIN cases c ON c.id = d.case_id
             WHERE c.customer_id = ? AND d.deleted_at IS NULL AND d.customer_visible = 1
             ORDER BY d.created_at DESC',
            [$customerId]
        );
    }

    public function download(int $documentId, ?int $customerId, int $userId, bool $staff): array
    {
        $sql = 'SELECT d.*, c.customer_id, c.number
                FROM documents d
                LEFT JOIN cases c ON c.id = d.case_id
                WHERE d.id = ? AND d.deleted_at IS NULL';
        $document = $this->db->fetch($sql, [$documentId]);
        if ($document === null) {
            throw new HttpException(404, 'Document not found.');
        }
        if (!$staff) {
            if ($customerId === null || (int) $document['customer_id'] !== $customerId || (int) $document['customer_visible'] !== 1) {
                throw new HttpException(403, 'You cannot download this document.');
            }
        }
        $path = base_path('storage/documents/' . $document['stored_name']);
        $real = realpath($path);
        $root = realpath(base_path('storage/documents'));
        if ($real === false || $root === false || !str_starts_with($real, $root) || !is_file($real)) {
            throw new HttpException(404, 'File is no longer available.');
        }
        $this->db->insert(
            'INSERT INTO document_download_logs (document_id, user_id, ip) VALUES (?, ?, ?)',
            [$documentId, $userId, client_ip()]
        );
        $this->audit->log($userId, 'document_downloaded', 'document', $documentId);

        return ['path' => $real, 'name' => $document['original_name'], 'mime' => $document['mime']];
    }

    public function archive(int $documentId, int $userId): void
    {
        $this->db->run('UPDATE documents SET deleted_at = NOW() WHERE id = ?', [$documentId]);
        $this->audit->log($userId, 'document_archived', 'document', $documentId);
    }
}
