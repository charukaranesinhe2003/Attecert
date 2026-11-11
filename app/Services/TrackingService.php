<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Core\Validator;

final class TrackingService
{
    public function __construct(private Database $db, private CaseAccessService $access, private AuthService $auth,
        private AuditService $audit, private WorkflowService $workflow, private MailService $mail,
        private NotificationService $notifications, private DocumentService $documents, private Validator $validator)
    {
    }

    private function authorize(int $caseId, bool $lock = false): array
    {
        if (!$this->auth->isStaff() || !$this->auth->can('cases.edit')) {
            throw new HttpException(403, 'You do not have permission to manage tracking.');
        }
        $this->access->requireCase($caseId, $lock);
        $case = $this->db->fetch('SELECT c.*, cu.name AS customer_name, cu.email AS customer_email, cu.user_id
            FROM cases c INNER JOIN customers cu ON cu.id = c.customer_id
            WHERE c.id = ? AND c.deleted_at IS NULL AND cu.deleted_at IS NULL', [$caseId]);
        if ($case === null) { throw new HttpException(404, 'Case not found.'); }
        return $case;
    }

    public function steps(int $caseId): array
    {
        $this->authorize($caseId);
        return $this->db->fetchAll('SELECT * FROM case_tracking_steps WHERE case_id = ? ORDER BY sort_order, id', [$caseId]);
    }

    public function settings(int $caseId, array $input): void
    {
        $data = $this->validator->validate($input, ['tracking_message' => 'nullable|max:600', 'tracking_expected_on' => 'nullable|date']);
        $this->db->transaction(function () use ($caseId, $data): void {
            $this->authorize($caseId, true);
            $this->db->run('UPDATE cases SET tracking_message = ?, tracking_expected_on = ?, updated_at = NOW() WHERE id = ?',
                [$data['tracking_message'], $data['tracking_expected_on'], $caseId]);
            $this->audit->log($this->auth->id(), 'tracking_details_updated', 'case', $caseId);
        });
    }

    public function saveStep(int $caseId, ?int $stepId, array $input, ?array $file = null): ?array
    {
        $data = $this->validator->validate($input, ['title' => 'required|min:2|max:160',
            'public_description' => 'nullable|max:1000', 'step_date' => 'nullable|date',
            'state' => 'required|in:pending,current,completed', 'sort_order' => 'required|integer|min:1|max:1000']);
        return $this->db->transaction(function () use ($caseId, $stepId, $data, $file): ?array {
            $case = $this->authorize($caseId, true);
            $same = false;
            $hasFile = $file !== null && $file !== [] && ($file['error'] ?? null) !== UPLOAD_ERR_NO_FILE;
            if ($hasFile && !$this->auth->can('documents.upload')) { throw new HttpException(403); }
            if ($stepId !== null) {
                $step = $this->db->fetch('SELECT * FROM case_tracking_steps WHERE id = ? AND case_id = ? FOR UPDATE', [$stepId, $caseId]);
                if ($step === null) { throw new HttpException(404, 'Tracking step not found.'); }
                $same = true;
                foreach (['title', 'public_description', 'step_date', 'state', 'sort_order'] as $field) {
                    if ((string) $step[$field] !== (string) $data[$field]) { $same = false; break; }
                }
                if ($same && !$hasFile) { return null; }
            } elseif ((int) $this->db->fetchColumn('SELECT COUNT(*) FROM case_tracking_steps WHERE case_id = ?', [$caseId]) >= 100) {
                throw new ValidationException(['title' => 'A case can have up to 100 tracking steps.']);
            }
            if ($data['state'] === 'current') {
                $this->db->run("UPDATE case_tracking_steps SET state = 'pending', updated_by = ? WHERE case_id = ? AND state = 'current' AND id <> ?",
                    [$this->auth->id(), $caseId, $stepId ?? 0]);
            }
            if ($stepId === null) {
                $stepId = $this->db->insert('INSERT INTO case_tracking_steps (case_id, title, public_description, step_date, state, sort_order, created_by, updated_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$caseId, $data['title'], $data['public_description'], $data['step_date'], $data['state'], $data['sort_order'], $this->auth->id(), $this->auth->id()]);
            } else {
                $this->db->run('UPDATE case_tracking_steps SET title = ?, public_description = ?, step_date = ?, state = ?, sort_order = ?, updated_by = ? WHERE id = ? AND case_id = ?',
                    [$data['title'], $data['public_description'], $data['step_date'], $data['state'], $data['sort_order'], $this->auth->id(), $stepId, $caseId]);
            }
            if ($hasFile) {
                $documentId = $this->documents->optionalUpload($caseId, $file, 'tracking_step', 'Document for step: ' . $data['title'], (int) $this->auth->id());
                $this->db->run('UPDATE documents SET tracking_step_id = ?, customer_visible = 1 WHERE id = ?', [$stepId, $documentId]);
            }
            $attachments = [];
            foreach ($this->db->fetchAll('SELECT id FROM documents WHERE case_id = ? AND tracking_step_id = ? AND customer_visible = 1 AND deleted_at IS NULL ORDER BY id', [$caseId, $stepId]) as $document) {
                $attachments[] = $this->documents->download((int) $document['id'], null, (int) $this->auth->id(), true);
            }
            $attachment = array_shift($attachments);
            $this->db->run('UPDATE cases SET updated_at = NOW() WHERE id = ?', [$caseId]);
            $this->audit->log($this->auth->id(), 'tracking_step_saved', 'case', $caseId, ['step_id' => $stepId, 'state' => $data['state']]);
            app(ProgressHistoryService::class)->record($caseId, $data['title'], $data['public_description'], $data['state'], $data['step_date'], $stepId,
                null, $same ? 'document_added' : 'step_updated');
            return $this->notify($case, $same ? 'Document added: ' . $data['title'] : $data['title'], $data['public_description'] ?? '', $data['state'], $data['step_date'], $attachment, $attachments);
        });
    }

    public function deleteStep(int $caseId, int $stepId): array
    {
        return $this->db->transaction(function () use ($caseId, $stepId): array {
            $case = $this->authorize($caseId, true);
            $step = $this->db->fetch('SELECT * FROM case_tracking_steps WHERE id = ? AND case_id = ? FOR UPDATE', [$stepId, $caseId]);
            if ($step === null) { throw new HttpException(404, 'Tracking step not found.'); }
            app(ProgressHistoryService::class)->record($caseId, $step['title'], $step['public_description'], $step['state'], $step['step_date'], $stepId, null, 'step_removed');
            if ($this->db->run('DELETE FROM case_tracking_steps WHERE id = ? AND case_id = ?', [$stepId, $caseId])->rowCount() !== 1) {
                throw new HttpException(404, 'Tracking step not found.');
            }
            $this->db->run('UPDATE cases SET updated_at = NOW() WHERE id = ?', [$caseId]);
            $this->audit->log($this->auth->id(), 'tracking_step_deleted', 'case', $caseId, ['step_id' => $stepId]);
            return $this->notify($case, 'Step removed: ' . $step['title'], 'This step was removed from the current plan. Previous updates remain in your tracking history.', 'status');
        });
    }

    public function status(int $caseId, array $input, bool $confirmed): array
    {
        if (array_key_exists('status_label', $input)) {
            $label = $this->validator->validate($input, ['status_label' => 'required|max:120'])['status_label'];
            foreach ($this->workflow->statuses() as $status) {
                if (mb_strtolower($label) === mb_strtolower((string) ($status['name'] ?: $status['code'])) || $label === $status['code']) {
                    unset($input['status_label']);
                    $input['status'] = $status['code'];
                    return $this->status($caseId, $input, $confirmed);
                }
            }
            return $this->customStatus($caseId, $label, $input, $confirmed);
        }
        $data = $this->validator->validate($input, ['status' => 'required|max:40', 'notes' => 'nullable|max:2000']);
        return $this->db->transaction(function () use ($caseId, $data, $confirmed): array {
            $case = $this->authorize($caseId, true);
            $changed = $this->workflow->change($caseId, $data['status'], $this->auth->id(), $data['notes'], true, false, $confirmed);
            if (!$changed) { return ['changed' => false, 'delivery' => null]; }
            $name = $this->db->fetchColumn("SELECT COALESCE(t.name, s.code) FROM workflow_statuses s
                LEFT JOIN workflow_status_translations t ON t.status_id = s.id AND t.locale = 'en' WHERE s.code = ?", [$data['status']]);
            return ['changed' => true, 'delivery' => $this->notify($case, (string) $name, $data['notes'] ?? '', 'status')];
        });
    }

    private function customStatus(int $caseId, string $label, array $input, bool $confirmed): array
    {
        $data = $this->validator->validate($input, ['notes' => 'nullable|max:2000']);
        return $this->db->transaction(function () use ($caseId, $label, $data, $confirmed): array {
            $case = $this->authorize($caseId, true);
            if (!$this->auth->can('cases.status_update')) { throw new HttpException(403); }
            if ($case['custom_status'] === $label) { return ['changed' => false, 'delivery' => null]; }
            $terminal = (bool) $this->db->fetchColumn('SELECT is_terminal FROM workflow_statuses WHERE id = ?', [$case['status_id']]);
            if ($terminal && (!$confirmed || trim((string) $data['notes']) === '')) {
                throw new \RuntimeException(__('admin_workflow.reversal_required'));
            }
            // Custom labels belong to this case, so another staff member cannot discover them in a global list.
            if ($terminal) {
                $case['status_id'] = $this->workflow->idFor('under_review');
                $this->db->run('UPDATE cases SET status_id = ?, completed_at = NULL, cancelled_at = NULL WHERE id = ?', [$case['status_id'], $caseId]);
            }
            $this->db->run('UPDATE cases SET custom_status = ?, updated_at = NOW() WHERE id = ?', [$label, $caseId]);
            $historyId = $this->db->insert('INSERT INTO case_status_history (case_id, status_id, staff_id, notes, customer_visible, custom_status)
                VALUES (?, ?, ?, ?, 1, ?)', [$caseId, $case['status_id'], $this->auth->id(), $data['notes'], $label]);
            app(ProgressHistoryService::class)->record($caseId, $label, '', 'status', null, null, $historyId, 'status_updated');
            $this->audit->log($this->auth->id(), 'case_custom_status_changed', 'case', $caseId, ['status' => $label]);
            return ['changed' => true, 'delivery' => $this->notify($case, $label, $data['notes'] ?? '', 'status')];
        });
    }

    private function notify(array $case, string $title, string $message, string $state, ?string $stepDate = null, ?array $attachment = null, array $attachments = []): array
    {
        $result = app(CustomerUpdateService::class)->send((int) $case['id'], $title, $message, $state, $stepDate, [], $attachment, $attachments);
        $this->audit->log($this->auth->id(), 'tracking_update_emailed', 'case', (int) $case['id'], ['mail_status' => $result['status'], 'sms_status' => $result['sms']['status']]);
        return $result;
    }

    public function uploadDocument(int $caseId, array $file, string $description): array
    {
        return $this->db->transaction(function () use ($caseId, $file, $description): array {
            $this->authorize($caseId, true);
            $this->validator->validate(['description' => $description], ['description' => 'nullable|max:255']);
            $id = $this->documents->store($caseId, null, $file, 'completed', 'other', $description, false, $this->auth->id());
            return $this->sendDocument($caseId, $id);
        });
    }

    public function sendDocument(int $caseId, int $documentId): array
    {
        return $this->db->transaction(function () use ($caseId, $documentId): array {
            $case = $this->authorize($caseId, true);
            $document = $this->db->fetch("SELECT * FROM documents WHERE id = ? AND case_id = ? AND deleted_at IS NULL AND category IN ('customer', 'completed') FOR UPDATE", [$documentId, $caseId]);
            if ($document === null) { throw new HttpException(404, 'Customer document not found.'); }
            $file = $this->documents->download($documentId, null, (int) $this->auth->id(), true);
            $this->db->run('UPDATE documents SET customer_visible = 1 WHERE id = ?', [$documentId]);
            $result = $this->mail->send($case['customer_email'], 'Document for ' . $case['number'], 'document-delivery', [
                'name' => $case['customer_name'], 'number' => $case['number'], 'filename' => $file['name'],
                'description' => $document['description'] ?? '', 'link' => absolute_url('/portal/documents'),
            ], 'document', $documentId, $file['path'], $file['name']);
            if ($case['user_id'] !== null) {
                $this->notifications->notify((int) $case['user_id'], 'Document available', $file['name'] . ' · ' . $case['number'], '/portal/documents');
            }
            $this->audit->log($this->auth->id(), 'tracking_document_emailed', 'document', $documentId, ['case_id' => $caseId, 'mail_status' => $result['status']]);
            return $result;
        });
    }
}
