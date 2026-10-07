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
            'accountFrame' => true,
        ]);
    }

    public function feed(Request $request): Response
    {
        $userId = (int) $this->auth->id();
        $items = [];
        foreach ($this->notifications->forUser($userId, 8) as $note) {
            $items[] = [
                'id' => (int) $note['id'],
                'title' => $note['title'],
                'body' => $note['body'],
                'link' => $note['link'] ? url((string) $note['link']) : null,
                'read' => $note['read_at'] !== null,
                'time' => time_ago($note['created_at']),
            ];
        }

        return Response::json([
            'unread' => $this->notifications->unreadCount($userId),
            'items' => $items,
        ]);
    }

    public function read(Request $request, string $id): Response
    {
        $this->notifications->markRead((int) $id, (int) $this->auth->id());
        if ($request->wantsJson()) {
            return Response::json(['ok' => true, 'unread' => $this->notifications->unreadCount((int) $this->auth->id())]);
        }

        return $this->redirect('/portal/notifications');
    }

    public function readAll(): Response
    {
        $this->notifications->markAll((int) $this->auth->id());
        flash('success', __('portal.notifications_read'));

        return $this->redirect('/portal/notifications');
    }
}
