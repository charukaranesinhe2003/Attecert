<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\NotificationService;

final class NotificationController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private AuthService $auth,
        private NotificationService $notifications
    ) {
        parent::__construct($validator);
    }

    public function index(): Response
    {
        return $this->view('customer/notifications', [
            'title' => __('portal.notifications'),
            'robots' => 'noindex',
            'active' => 'notifications',
            'notifications' => $this->notifications->forUser((int) $this->auth->id()),
        ], 'layouts/customer');
    }

    public function read(Request $request, string $id): Response
    {
        $this->notifications->markRead((int) $id, (int) $this->auth->id());

        return $this->redirect('/portal/notifications');
    }

    public function readAll(): Response
    {
        $this->notifications->markAll((int) $this->auth->id());
        flash('success', __('portal.notifications_read'));

        return $this->redirect('/portal/notifications');
    }
}
