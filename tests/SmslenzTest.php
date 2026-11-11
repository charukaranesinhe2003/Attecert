<?php
declare(strict_types=1);
namespace Tests;
require_once __DIR__ . '/DatabaseTestCase.php';
use App\Core\Config;
use App\Services\SmslenzClient;
use App\Services\SmsService;
use ReflectionProperty;

final class SmslenzTest extends DatabaseTestCase
{
    public function testPhoneNormalizationAndMissingConfiguration(): void
    {
        foreach (['0771234567', '94771234567', '+94 77 123 4567', '0094771234567'] as $number) {
            $this->assertSame('+94771234567', SmslenzClient::normalizePhone($number));
        }
        $this->assertNull(SmslenzClient::normalizePhone('not-a-phone'));
        $result = app(SmsService::class)->queue('0771234567', 'Customer', 'Update', 'case', $this->caseId);
        $this->assertSame('blocked', $result['status']);
        $this->assertSame(0, (int) $this->db->fetchColumn('SELECT attempts FROM sms_messages WHERE id = ?', [$result['id']]));
    }

    public function testActualJsonRequestAndProviderResponses(): void
    {
        foreach (['success' => 'sent', 'reject' => 'failed', 'invalid' => 'uncertain', 'error' => 'uncertain'] as $mode => $expected) {
            $pipes = [];
            $process = proc_open([PHP_BINARY, base_path('tests/fixtures/sms_server.php')], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            $this->assertIsResource($process);
            try {
                fwrite($pipes[0], $mode . "\n"); fclose($pipes[0]);
                $ready = trim(fgets($pipes[1]));
                $this->assertStringStartsWith('READY ', $ready);
                $prop = new ReflectionProperty(Config::class, 'items'); $items = $prop->getValue();
                $items['app']['env'] = 'test';
                $items['sms'] = ['base_url' => 'http://127.0.0.1:' . substr($ready, 6) . '/api', 'user_id' => 'fixture-user', 'api_key' => 'fixture-key', 'sender_id' => 'AtteCert', 'timeout' => 3];
                $prop->setValue(null, $items);
                $result = app(SmslenzClient::class)->send(['recipient' => '+94771234567', 'contact_name' => 'Customer', 'message' => 'Short update']);
                $this->assertSame($expected, $result['status']);
                $request = json_decode(trim(fgets($pipes[1])), true);
                $this->assertSame(['user_id' => 'fixture-user', 'api_key' => 'fixture-key', 'sender_id' => 'AtteCert', 'contact' => '+94771234567', 'message' => 'Short update', 'contact_name' => 'Customer'], $request);
                $this->assertStringNotContainsString('fixture-key', json_encode($result));
            } finally {
                foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
                proc_terminate($process); proc_close($process);
            }
        }
    }

    public function testWorkerRefusesNetworkDeliveryInsideBusinessTransaction(): void
    {
        $this->expectException(\LogicException::class);
        app(SmsService::class)->processQueue();
    }

    public function testWorkerExpiresOldCodesAndRecordsProviderAcceptanceWithoutResending(): void
    {
        $pipes = [];
        $process = proc_open([PHP_BINARY, base_path('tests/fixtures/sms_server.php')], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $this->assertIsResource($process);
        $ids = [];
        try {
            fwrite($pipes[0], "success\n"); fclose($pipes[0]);
            $ready = trim(fgets($pipes[1]));
            $prop = new ReflectionProperty(Config::class, 'items'); $items = $prop->getValue();
            $items['app']['env'] = 'test';
            $items['sms'] = ['base_url' => 'http://127.0.0.1:' . substr($ready, 6) . '/api', 'user_id' => 'fixture-user', 'api_key' => 'fixture-key', 'sender_id' => 'AtteCert', 'timeout' => 3];
            $prop->setValue(null, $items);
            $expired = app(SmsService::class)->queue('0771234567', 'Customer', 'Expired code', 'otp', 1, date('Y-m-d H:i:s', time() - 60));
            $fresh = app(SmsService::class)->queue('0771234567', 'Customer', 'Fresh update', 'case', $this->caseId);
            $ids = [$expired['id'], $fresh['id']];
            $this->db->pdo()->commit();
            $results = app(SmsService::class)->processQueue(2);
            $this->assertSame(['expired', 'sent'], array_column($results, 'status'));
            $this->assertSame('42', $this->db->fetchColumn('SELECT campaign_id FROM sms_messages WHERE id = ?', [$fresh['id']]));
            $this->assertSame([], app(SmsService::class)->processQueue(2));
            $request = json_decode(trim(fgets($pipes[1])), true);
            $this->assertSame('Fresh update', $request['message']);
        } finally {
            if ($ids !== [] && !$this->db->pdo()->inTransaction()) {
                $this->db->run('DELETE FROM sms_messages WHERE id IN (?, ?)', $ids);
                $this->db->pdo()->beginTransaction();
            }
            foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
            proc_terminate($process); proc_close($process);
        }
    }
}
