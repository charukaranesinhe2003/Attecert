<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\CasePaymentService;

final class CasePaymentController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private CasePaymentService $payments) { parent::__construct($validator); }

    public function store(Request $request, string $id): Response
    {
        $this->payments->record((int) $id, $request->all());
        flash('success', 'Payment added. Confirm receipt after checking that the money has arrived.');
        return $this->redirect('/admin/cases/' . $id . '#finance');
    }

    public function confirm(Request $request, string $id, string $paymentId): Response
    {
        $this->validate($request, ['confirmed' => 'required|in:1']);
        $result = $this->payments->confirm((int) $id, (int) $paymentId, $request->file('receipt_document'));
        if ($result['changed']) { flash_mail_result($result['delivery'], $result['message']); }
        else { flash('success', 'This payment was already confirmed.'); }
        return $this->redirect('/admin/cases/' . $id . '#finance');
    }

    public function cancel(Request $request, string $id, string $paymentId): Response
    {
        $this->validate($request, ['confirmed' => 'required|in:1']);
        $this->payments->cancel((int) $id, (int) $paymentId);
        flash('success', 'Pending payment cancelled.');
        return $this->redirect('/admin/cases/' . $id . '#finance');
    }
}
