<?php
declare(strict_types=1);
namespace Tests;
require_once __DIR__ . '/DatabaseTestCase.php';
use App\Core\Config;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Services\TrackingService;
use App\Services\WorkflowService;
use App\Services\MailService;
use ReflectionProperty;

final class CustomerUpdatesAccessTest extends DatabaseTestCase
{
    public function testNavigationOrderAndAdminOnlyPagesCannotBeOpenedByStaff(): void
    {
        $html = $this->body($this->dispatch('/admin'));
        $dom = new \DOMDocument(); @$dom->loadHTML($html); $xpath = new \DOMXPath($dom);
        $links = $xpath->query('(//div[@class="admin-nav-group"])[1]/a');
        $hrefs = []; foreach ($links as $link) { $hrefs[] = $link->getAttribute('href'); }
        $this->assertSame(['/admin/operations', '/admin/quote-requests', '/admin/cases', '/admin/tracking', '/admin/documents', '/admin/processing', '/admin/customers', '/admin/contacts'], $hrefs);
        $this->assertStringContainsString('Assign staff', $html);
        $number = $this->db->fetchColumn('SELECT number FROM cases WHERE id = ?', [$this->caseId]);
        foreach (['manager', 'processing_staff', 'finance_staff', 'content_editor', 'customer_support'] as $role) {
            $this->signInAsRole($role);
            $html = $this->body($this->dispatch('/admin'));
            foreach (['documents', 'processing', 'customers', 'contacts'] as $page) {
                $this->assertStringNotContainsString('href="/admin/' . $page . '"', $html);
                try { $this->dispatch('/admin/' . $page); $this->fail('Admin-only page accessible to staff.'); }
                catch (HttpException $e) { $this->assertSame(403, $e->status()); }
            }
            try { $this->dispatch('/admin/customers/' . $this->customerId); $this->fail('Customer profile leaked.'); }
            catch (HttpException $e) { $this->assertSame(403, $e->status()); }
            $search = json_decode($this->body($this->dispatch('/admin/search', ['q' => $number])), true);
            $this->assertSame([], $search['results']);
        }
    }

    public function testEveryChangedTrackingStepQueuesBothChannelsWithShortSmsAndDetailedEmail(): void
    {
        $this->configure();
        $this->db->run("UPDATE customers SET phone = '0771234567' WHERE id = ?", [$this->customerId]);
        $input = ['title' => 'MOFA attestation', 'public_description' => 'Your original certificate has completed attestation. Please keep your receipt.',
            'step_date' => '2026-11-11', 'sort_order' => '1', 'state' => 'completed'];
        $result = app(TrackingService::class)->saveStep($this->caseId, null, $input);
        $this->assertSame('queued', $result['status']);
        $this->assertSame('queued', $result['sms']['status']);
        $sms = $this->db->fetch('SELECT * FROM sms_messages ORDER BY id DESC LIMIT 1');
        $this->assertSame('+94771234567', $sms['recipient']);
        $this->assertLessThanOrEqual(160, mb_strlen($sms['message']));
        $this->assertStringNotContainsString('original certificate', $sms['message']);
        $mail = $this->db->fetch("SELECT * FROM email_logs WHERE template = 'tracking-update' ORDER BY id DESC LIMIT 1");
        $body = file_get_contents(storage_path('mail/' . $mail['body_path']));
        $this->assertStringContainsString($input['public_description'], $body);
        $this->assertStringContainsString('11 Nov 2026', $body);
        $this->assertStringContainsString('View document tracking', $body);
        $step = (int) $this->db->fetchColumn('SELECT id FROM case_tracking_steps ORDER BY id DESC LIMIT 1');
        $this->assertNull(app(TrackingService::class)->saveStep($this->caseId, $step, $input));
        $this->assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM sms_messages'));
        $input['state'] = 'current';
        app(TrackingService::class)->saveStep($this->caseId, $step, $input);
        $this->assertSame(2, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM sms_messages'));
        app(WorkflowService::class)->change($this->caseId, 'ready_for_collection', $this->adminId, 'Ready to collect', true, true);
        $this->assertSame(3, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM sms_messages'));
    }

    public function testExpiredOtpEmailIsNotSentByWorker(): void
    {
        $this->configure();
        $mail = app(MailService::class)->send('customer@example.test', 'Expired code', 'customer-otp', ['name' => 'Customer', 'code' => '123456', 'minutes' => 10], 'user', $this->adminId, null, null, date('Y-m-d H:i:s', time() - 60));
        $this->db->pdo()->commit();
        try {
            $results = app(MailService::class)->processQueue(1, $mail['id']);
            $this->assertSame('failed', $results[0]['status']);
            $this->assertStringContainsString('expired', $results[0]['error']);
        } finally {
            $this->db->run('DELETE FROM email_logs WHERE id = ?', [$mail['id']]);
            $this->db->pdo()->beginTransaction();
        }
    }

    private function configure(): void
    {
        $prop = new ReflectionProperty(Config::class, 'items'); $items = $prop->getValue();
        $items['app']['env'] = 'test'; $items['mail']['host'] = '127.0.0.1'; $items['mail']['from_address'] = 'fixture@example.test';
        $items['sms'] = ['base_url' => 'http://127.0.0.1:1/api', 'user_id' => 'fixture', 'api_key' => 'fixture', 'sender_id' => 'AtteCert'];
        $prop->setValue(null, $items);
    }
    private function dispatch(string $path, array $query = []): Response
    {
        $router = new Router(); (require base_path('routes/web.php'))($router);
        return $router->dispatch(new Request('GET', $path, $query, [], [], ['HTTP_ACCEPT' => str_ends_with($path, '/search') ? 'application/json' : 'text/html'], []), app());
    }
    private function body(Response $r): string { return (new ReflectionProperty(Response::class, 'body'))->getValue($r); }
}
