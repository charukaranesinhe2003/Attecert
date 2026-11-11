<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\CaseService;

final class TrackController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private CaseService $cases)
    {
        parent::__construct($validator);
    }

    public function show(Request $request): Response
    {
        $raw = $request->query('number', '');
        $number = is_string($raw) ? mb_substr(strtoupper(trim($raw)), 0, 24) : '';
        $otp = app(\App\Services\PublicOtpService::class);
        if ($otp->required() && ($number === '' || !$otp->trackingAllowed($number))) {
            return $this->view('public/action-verification', ['title' => 'Verify document tracking', 'active' => 'track', 'robots' => 'noindex',
                'purpose' => 'track', 'number' => $number, 'contact' => [],
                'pending' => $number === ($_SESSION['_public_tracking_number'] ?? '') ? $otp->pending('track') : null,
                'emailReady' => app(\App\Services\MailService::class)->smtpReady(), 'smsReady' => app(\App\Services\SmsService::class)->ready()]);
        }
        $result = $number !== '' ? $this->cases->track($number) : null;

        return $this->view('public/track', [
            'title' => __('track.title'),
            'description' => __('track.lead'),
            'active' => 'track',
            'robots' => 'noindex',
            'number' => $number,
            'result' => $result,
            'searched' => $number !== '',
        ]);
    }

    public function lookup(Request $request): Response
    {
        $data = $this->validate($request, ['number' => 'required|min:6|max:24']);
        $otp = app(\App\Services\PublicOtpService::class);
        if ($otp->required() && !$otp->trackingAllowed($data['number'])) {
            $otp->sendTracking($data + ['channel' => $request->input('channel', 'email')]);
            flash('success', 'If this reference and delivery method match our records, a verification code is queued for the customer.');
        }

        return $this->redirect('/track?number=' . rawurlencode(strtoupper((string) $data['number'])));
    }

    public function verify(Request $request): Response
    {
        $data = $this->validate($request, ['code' => 'required|min:6|max:6']);
        if (!app(\App\Services\PublicOtpService::class)->verify('track', (string) $data['code'])) {
            throw new \App\Core\ValidationException(['code' => 'This code is invalid or expired. Check the code or request a new one.']);
        }
        return $this->redirect('/track?number=' . rawurlencode($_SESSION['_public_tracking_number']));
    }
}
