<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogService;
use App\Services\IntakeService;

final class QuoteController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private CatalogService $catalog,
        private IntakeService $intake
    ) {
        parent::__construct($validator);
    }

    public function form(): Response
    {
        $otp = app(\App\Services\PublicOtpService::class);
        $contact = $otp->quoteContact();
        if ($otp->required() && $contact === null) { return $this->verificationPage(); }
        return $this->view('public/quote', [
            'title' => __('quote.title'),
            'description' => content_block('quote_lead'),
            'active' => 'quote',
            'countries' => $this->catalog->countries(true),
            'services' => $this->catalog->services(true),
            'documentTypes' => $this->catalog->documentTypes(),
            'verifiedContact' => $contact,
        ]);
    }

    public function submit(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|min:2|max:160',
            'email' => 'required|email|max:190',
            'phone' => 'required|min:6|max:40',
            'whatsapp' => 'nullable|max:40',
            'country_id' => 'nullable|numeric',
            'document_type_id' => 'nullable|numeric',
            'document_count' => 'nullable|numeric|min:1|max:100',
            'message' => 'nullable|max:4000',
        ]);
        if (empty($request->input('consent'))) {
            throw new \App\Core\ValidationException(['consent' => __('validation.consent')], $request->all());
        }
        if ($customer = app(\App\Services\AuthService::class)->customer()) {
            $data['email'] = $customer['email'];
            $data['name'] = trim((string) $data['name']) !== '' ? $data['name'] : $customer['name'];
            $data['phone'] = trim((string) ($data['phone'] ?? '')) !== '' ? $data['phone'] : (string) $customer['phone'];
        }
        $serviceIds = array_map('intval', (array) $request->input('services', []));
        $result = app(\App\Services\PublicOtpService::class)->withQuote($data,
            fn (): array => $this->intake->quote($data, $serviceIds, $request->files('attachments')));
        $_SESSION['_track_popup'] = $result['number'];
        flash('success', __('quote.sent_with_code', ['number' => $result['number']]));

        return $this->redirect('/quote');
    }

    private function verificationPage(): Response
    {
        $otp = app(\App\Services\PublicOtpService::class);
        return $this->view('public/action-verification', ['title' => 'Verify your quote request', 'active' => 'quote', 'robots' => 'noindex',
            'purpose' => 'quote', 'number' => '', 'pending' => $otp->pending('quote'),
            'contact' => $_SESSION['_public_quote_contact'] ?? [],
            'emailReady' => app(\App\Services\MailService::class)->smtpReady(), 'smsReady' => app(\App\Services\SmsService::class)->ready()]);
    }

    public function sendVerification(Request $request): Response
    {
        $input = $request->all();
        if ($customer = app(\App\Services\AuthService::class)->customer()) { $input['email'] = $customer['email']; }
        app(\App\Services\PublicOtpService::class)->sendQuote($input);
        flash('success', 'Your verification code is queued for delivery.');
        return $this->redirect('/quote');
    }

    public function verify(Request $request): Response
    {
        $data = $this->validate($request, ['code' => 'required|min:6|max:6']);
        if (!app(\App\Services\PublicOtpService::class)->verify('quote', (string) $data['code'])) {
            throw new \App\Core\ValidationException(['code' => 'This code is invalid or expired. Check the code or request a new one.']);
        }
        flash('success', 'Contact verified. You can now request your quotation.');
        return $this->redirect('/quote');
    }

    public function resetVerification(): Response
    {
        app(\App\Services\PublicOtpService::class)->resetQuote();
        return $this->redirect('/quote');
    }
}
