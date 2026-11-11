<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\AuthService;
use App\Services\CustomerOtpService;
use App\Services\SmsService;
use App\Services\MailService;

final class CustomerVerificationController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private AuthService $auth, private CustomerOtpService $otp,
        private SmsService $sms, private MailService $mail) { parent::__construct($validator); }
    public function show(): Response
    {
        if (!$this->auth->needsVerification()) { return $this->redirect($this->auth->homePath()); }
        return $this->view('auth/customer-otp', ['title' => 'Verify your account', 'robots' => 'noindex', 'verificationPage' => true,
            'email' => CustomerOtpService::mask($this->auth->user()['email']),
            'phone' => CustomerOtpService::mask((string) $this->auth->user()['phone']),
            'emailReady' => $this->mail->smtpReady(), 'smsReady' => $this->sms->ready()]);
    }
    public function send(Request $request): Response
    {
        $data = $this->validate($request, ['channel' => 'required|in:email,sms']);
        $this->otp->send($data['channel']);
        flash('success', 'Your verification code is queued for delivery. It expires in 10 minutes.');
        return $this->redirect('/verify-customer');
    }
    public function verify(Request $request): Response
    {
        $code = $request->input('code');
        if (!is_string($code) || !$this->otp->verify(trim($code))) {
            throw new ValidationException(['code' => 'The code is invalid, expired, or has too many attempts. Request a new code.']);
        }
        flash('success', 'Your account is verified.');
        return $this->redirect($this->auth->homePath());
    }
}
