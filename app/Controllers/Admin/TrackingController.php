<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CaseAccessService;
use App\Services\CaseService;
use App\Services\DocumentService;
use App\Services\TrackingService;
use App\Services\WorkflowService;

final class TrackingController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private CaseAccessService $access,
        private CaseService $cases, private TrackingService $tracking, private WorkflowService $workflow, private DocumentService $documents)
    {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $raw = $request->query('case_id', '');
        if ((!is_scalar($raw) && $raw !== null) || ($raw !== '' && $raw !== null && !preg_match('/^[1-9][0-9]*$/D', (string) $raw))) {
            throw new HttpException(404, 'Case not found.');
        }
        $case = null;
        if ((int) $raw > 0) {
            $this->access->requireCase((int) $raw);
            $case = $this->cases->find((int) $raw);
            if ($case === null) { throw new HttpException(404, 'Case not found.'); }
        }
        return $this->view('admin/tracking/index', [
            'title' => 'Tracking', 'pageHeading' => 'Tracking', 'active' => 'tracking',
            'pageDescription' => 'Manage the public timeline, update case status, and email documents to the customer.',
            'caseOptions' => $this->access->caseOptions(), 'selectedCase' => $case,
            'steps' => $case === null ? [] : $this->tracking->steps((int) $case['id']),
            'statuses' => $this->workflow->statuses(),
            'documents' => $case === null ? [] : array_values(array_filter($this->documents->forCase((int) $case['id']),
                static fn (array $doc): bool => in_array($doc['category'], ['customer', 'completed'], true))),
        ], 'layouts/admin');
    }

    public function settings(Request $request, string $id): Response
    {
        $this->tracking->settings((int) $id, $request->all());
        flash('success', 'Public tracking details saved.');
        return $this->returnToCase((int) $id, $request);
    }

    public function addStep(Request $request, string $id): Response
    {
        $delivery = $this->tracking->saveStep((int) $id, null, $request->all(), $request->file('step_document'));
        $this->saved('Tracking step added.', $delivery);
        return $this->returnToCase((int) $id, $request);
    }

    public function updateStep(Request $request, string $id, string $stepId): Response
    {
        $delivery = $this->tracking->saveStep((int) $id, (int) $stepId, $request->all(), $request->file('step_document'));
        $this->saved('Tracking step updated.', $delivery);
        return $this->returnToCase((int) $id, $request);
    }

    public function deleteStep(Request $request, string $id, string $stepId): Response
    {
        $this->validate($request, ['confirmed' => 'required|in:1']);
        $delivery = $this->tracking->deleteStep((int) $id, (int) $stepId);
        $this->saved('Tracking step deleted. Previous updates remain in the history.', $delivery);
        return $this->returnToCase((int) $id, $request);
    }

    public function status(Request $request, string $id): Response
    {
        try {
            $result = $this->tracking->status((int) $id, $request->all(), $request->input('confirm_reversal') === '1');
        } catch (\RuntimeException $exception) {
            if ($exception::class !== \RuntimeException::class) { throw $exception; }
            throw new \App\Core\ValidationException(['status' => $exception->getMessage()]);
        }
        $this->saved($result['changed'] ? 'Case status updated.' : 'The case already has this status.', $result['delivery']);
        return $this->returnToCase((int) $id, $request);
    }

    public function uploadDocument(Request $request, string $id): Response
    {
        $data = $this->validate($request, ['description' => 'nullable|max:255']);
        $delivery = $this->tracking->uploadDocument((int) $id, $request->file('document') ?? [], (string) ($data['description'] ?? ''));
        $this->saved('Document uploaded and available in the customer account.', $delivery);
        return $this->returnToCase((int) $id, $request);
    }

    public function sendDocument(Request $request, string $id): Response
    {
        $data = $this->validate($request, ['document_id' => 'required|integer|min:1']);
        $delivery = $this->tracking->sendDocument((int) $id, (int) $data['document_id']);
        $this->saved('Document available in the customer account.', $delivery);
        return $this->returnToCase((int) $id, $request);
    }

    private function saved(string $message, ?array $delivery): void
    {
        if ($delivery === null) { flash('success', $message); }
        else { flash_mail_result($delivery, $message); }
    }

    private function returnToCase(int $id, Request $request): Response
    {
        if ($request->input('return_to') === 'case') {
            return $this->redirect('/admin/cases/' . $id . '#overview');
        }
        return $this->redirect('/admin/tracking?case_id=' . $id);
    }
}
