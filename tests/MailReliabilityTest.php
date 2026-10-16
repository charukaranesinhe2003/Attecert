<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Config;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Controllers\Admin\AuditController;
use App\Services\MailService;
use ReflectionMethod;
use ReflectionProperty;

final class MailReliabilityTest extends DatabaseTestCase
{
    private array $processes = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::assertMatchesRegularExpression('/^attecert_test_[a-f0-9]{16}$/', (string) $this->db->fetchColumn('SELECT DATABASE()'));
        $this->db->run('DELETE FROM email_logs');
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as [$process, $pipes]) {
            foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            if (is_resource($process)) {
                if (proc_get_status($process)['running']) proc_terminate($process);
                proc_close($process);
            }
        }
        $this->processes = [];
        parent::tearDown();
    }

    public function testQueueIsTransactionalAndCannotDeliverUntilBusinessCommit(): void
    {
        $listener = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($listener, false);
        $this->configureMail((int) substr($address, strrpos($address, ':') + 1));
        try {
            $result = $this->enqueue();
            self::assertSame('queued', $result['status']);
            self::assertSame('0', (string) $this->db->fetchColumn('SELECT attempts FROM mail_outbox WHERE email_log_id = ?', [$result['id']]));
            try {
                app(MailService::class)->processQueue();
                self::fail('Network delivery inside a transaction must be rejected.');
            } catch (\LogicException) {
                self::assertTrue($this->db->pdo()->inTransaction());
            }
            $this->db->pdo()->rollBack();
            self::assertNull($this->db->fetch('SELECT id FROM email_logs WHERE id = ?', [$result['id']]));
            self::assertSame([], app(MailService::class)->processQueue());
            $read = [$listener]; $write = []; $except = [];
            self::assertSame(0, stream_select($read, $write, $except, 0, 1000), 'Enqueue and rolled-back mail must never connect to SMTP.');
        } finally {
            fclose($listener);
        }
    }

    public function testActualSmtpDeliveryUsesFrozenAttachmentBodyAndSenderWithAuthentication(): void
    {
        $smtp = $this->smtp();
        $this->configureMail($smtp['port'], ['username' => 'fixture-user', 'password' => 'fixture-password', 'from_name' => 'Original Sender']);
        $attachment = storage_path('source.pdf');
        if (!is_dir(dirname($attachment))) mkdir(dirname($attachment), 0700, true);
        file_put_contents($attachment, '%PDF Frozen fixture attachment');
        $result = $this->enqueue($attachment);
        file_put_contents($attachment, '%PDF Changed after enqueue');
        $this->configureMail($smtp['port'], ['username' => 'fixture-user', 'password' => 'fixture-password', 'from_name' => 'Changed Sender']);
        $this->db->pdo()->commit();
        $sent = app(MailService::class)->processQueue(1);
        self::assertSame('sent', $sent[0]['status']);
        $received = $this->smtpResult($smtp);
        self::assertTrue($received['authenticated']);
        self::assertContains('DATA', $received['commands']);
        self::assertStringContainsString('Original Sender', $received['message']);
        self::assertStringNotContainsString('Changed Sender', $received['message']);
        self::assertStringContainsString(base64_encode('%PDF Frozen fixture attachment'), preg_replace('/\s+/', '', $received['message']));
        self::assertStringNotContainsString(base64_encode('%PDF Changed after enqueue'), $received['message']);
        self::assertStringContainsString('fixture-document.pdf', $received['message']);
        self::assertStringContainsString('Original recipient', quoted_printable_decode($received['message']));
        self::assertNotNull($this->db->fetchColumn('SELECT sent_at FROM email_logs WHERE id = ?', [$result['id']]));
        self::assertSame('sent', app(MailService::class)->resend($result['id'])['status']);
        self::assertSame([], app(MailService::class)->processQueue());
        self::assertSame('1', (string) $this->db->fetchColumn('SELECT attempts FROM mail_outbox WHERE email_log_id = ?', [$result['id']]));
    }

    public function testTemporaryRejectionBacksOffThenSucceedsOnLaterWorkerRun(): void
    {
        $smtp = $this->smtp('temporary');
        $this->configureMail($smtp['port']);
        $result = $this->enqueue();
        $this->db->pdo()->commit();
        self::assertSame('queued', app(MailService::class)->processQueue(1)[0]['status']);
        self::assertNotContains('DATA', $this->smtpResult($smtp)['commands']);
        self::assertSame([], app(MailService::class)->processQueue(1), 'Backoff prevents an immediate retry.');
        $smtp = $this->smtp();
        $this->configureMail($smtp['port']);
        $this->db->run('UPDATE mail_outbox SET available_at = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE email_log_id = ?', [$result['id']]);
        self::assertSame('sent', app(MailService::class)->processQueue(1)[0]['status']);
        self::assertContains('DATA', $this->smtpResult($smtp)['commands']);
        self::assertSame('2', (string) $this->db->fetchColumn('SELECT attempts FROM mail_outbox WHERE email_log_id = ?', [$result['id']]));
    }

    public function testConnectionRefusalHasBoundedRetriesAndNeverClaimsDelivery(): void
    {
        $listener = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($listener, false);
        $port = (int) substr($address, strrpos($address, ':') + 1);
        fclose($listener);
        $this->configureMail($port, ['timeout' => 1]);
        $result = $this->enqueue();
        $this->db->pdo()->commit();
        foreach (['queued', 'queued', 'failed'] as $expected) {
            self::assertSame($expected, app(MailService::class)->processQueue(1)[0]['status']);
            $this->db->run('UPDATE mail_outbox SET available_at = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE email_log_id = ?', [$result['id']]);
        }
        self::assertNull($this->db->fetchColumn('SELECT sent_at FROM email_logs WHERE id = ?', [$result['id']]));
        self::assertSame([], app(MailService::class)->processQueue());
        self::assertSame('3', (string) $this->db->fetchColumn('SELECT attempts FROM mail_outbox WHERE email_log_id = ?', [$result['id']]));
        self::assertSame('queued', app(MailService::class)->resend($result['id'])['status']);
        self::assertSame('0', (string) $this->db->fetchColumn('SELECT attempts FROM mail_outbox WHERE email_log_id = ?', [$result['id']]));
    }

    public function testPermanentRejectionRequiresManualRecovery(): void
    {
        $smtp = $this->smtp('permanent');
        $this->configureMail($smtp['port']);
        $this->enqueue();
        $this->db->pdo()->commit();
        self::assertSame('failed', app(MailService::class)->processQueue(1)[0]['status']);
        self::assertNotContains('DATA', $this->smtpResult($smtp)['commands']);
        self::assertSame([], app(MailService::class)->processQueue());
    }

    public function testSmtpDisconnectDuringDataIsUncertainAndRetryRequiresExplicitConfirmation(): void
    {
        $smtp = $this->smtp('disconnect');
        $this->configureMail($smtp['port']);
        $result = $this->enqueue();
        $this->db->pdo()->commit();
        self::assertSame('uncertain', app(MailService::class)->processQueue(1)[0]['status']);
        self::assertContains('DATA', $this->smtpResult($smtp)['commands']);
        self::assertSame('uncertain', app(MailService::class)->resend($result['id'])['status']);
        self::assertSame([], app(MailService::class)->processQueue());
        self::assertSame('queued', app(MailService::class)->resend($result['id'], true)['status']);
    }

    public function testInterruptedClaimIsRecoveredWithoutAnAutomaticDuplicate(): void
    {
        $this->configureMail(1);
        $result = $this->enqueue();
        $this->db->pdo()->commit();
        $mail = app(MailService::class);
        (new ReflectionMethod(MailService::class, 'claim'))->invoke($mail);
        self::assertSame('sending', $mail->resend($result['id'])['status']);
        self::assertSame(0, $mail->recoverInterrupted());
        $this->db->run('UPDATE mail_outbox SET claimed_at = DATE_SUB(NOW(), INTERVAL 31 MINUTE) WHERE email_log_id = ?', [$result['id']]);
        self::assertSame(1, $mail->recoverInterrupted());
        self::assertSame([], $mail->processQueue());
        self::assertSame('uncertain', $mail->resend($result['id'])['status']);
        self::assertGreaterThan(0, (int) $this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE entity_id = ? AND action = 'email_delivery_uncertain'", [$result['id']]));
    }

    public function testMissingSnapshotFailsWithoutAttemptingSmtp(): void
    {
        $this->configureMail(1);
        $result = $this->enqueue();
        $payload = json_decode($this->db->fetchColumn('SELECT payload FROM mail_outbox WHERE email_log_id = ?', [$result['id']]), true);
        unlink(storage_path('mail/' . $payload['body_path']));
        $this->db->pdo()->commit();
        self::assertSame('failed', app(MailService::class)->processQueue(1)[0]['status']);
        self::assertSame('failed', app(MailService::class)->resend($result['id'])['status']);
    }

    public function testTlsConfigurationNeverFallsBackToSendingPlaintext(): void
    {
        $smtp = $this->smtp();
        $this->configureMail($smtp['port'], ['encryption' => 'tls']);
        $this->enqueue();
        $this->db->pdo()->commit();
        self::assertNotSame('sent', app(MailService::class)->processQueue(1)[0]['status']);
        $received = $this->smtpResult($smtp);
        self::assertContains('STARTTLS', $received['commands']);
        self::assertNotContains('MAIL', $received['commands']);
        self::assertSame('', $received['message']);
    }

    public function testConcurrentWorkersDeliverOneQueuedMessageOnlyOnce(): void
    {
        $smtp = $this->smtp();
        $this->configureMail($smtp['port']);
        $result = $this->enqueue();
        $this->db->pdo()->commit();
        $configuration = (new ReflectionProperty(Config::class, 'items'))->getValue();
        $workers = [];
        for ($i = 0; $i < 2; $i++) {
            [$process, $pipes] = $this->process('mail_worker.php', $configuration);
            self::assertSame("READY\n", fgets($pipes[1]));
            $workers[] = [$process, $pipes];
        }
        foreach ($workers as [, $pipes]) { fwrite($pipes[0], "GO\n"); fflush($pipes[0]); }
        $sent = 0;
        foreach ($workers as [, $pipes]) {
            $response = json_decode(stream_get_contents($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($response['ok']);
            $sent += count(array_filter($response['results'], fn (array $row): bool => $row['status'] === 'sent'));
        }
        self::assertSame(1, $sent);
        self::assertContains('DATA', $this->smtpResult($smtp)['commands']);
        self::assertSame('1', (string) $this->db->fetchColumn('SELECT attempts FROM mail_outbox WHERE email_log_id = ?', [$result['id']]));
    }

    public function testCommercialSentAtIsWrittenOnlyAfterSmtpAndAdvancedStatusIsPreserved(): void
    {
        $quote = $this->db->insert('INSERT INTO quotations (number, case_id, customer_id, status, total) VALUES (?, ?, ?, ?, ?)', ['MAIL-QUOTE-' . bin2hex(random_bytes(3)), $this->caseId, $this->customerId, 'converted', '100.00']);
        $invoice = $this->db->insert('INSERT INTO invoices (number, quotation_id, case_id, customer_id, status, total, amount_paid) VALUES (?, ?, ?, ?, ?, ?, ?)', ['MAIL-INV-' . bin2hex(random_bytes(3)), $quote, $this->caseId, $this->customerId, 'paid', '100.00', '100.00']);
        foreach (['quotation' => $quote, 'invoice' => $invoice] as $type => $id) {
            $smtp = $this->smtp();
            $this->configureMail($smtp['port']);
            $log = app(MailService::class)->send('fixture@example.test', 'Commercial fixture', $type, ['name' => 'Fixture', 'number' => 'Fixture', 'total' => '100.00', 'due' => '2026-10-15', 'link' => 'http://127.0.0.1/fixture'], $type, $id);
            self::assertSame('queued', $log['status']);
            $table = $type === 'quotation' ? 'quotations' : 'invoices';
            self::assertNull($this->db->fetchColumn('SELECT sent_at FROM ' . $table . ' WHERE id = ?', [$id]));
            if ($this->db->pdo()->inTransaction()) $this->db->pdo()->commit();
            self::assertSame('sent', app(MailService::class)->processQueue(1)[0]['status']);
            self::assertContains('DATA', $this->smtpResult($smtp)['commands']);
            self::assertNotNull($this->db->fetchColumn('SELECT sent_at FROM ' . $table . ' WHERE id = ?', [$id]));
        }
        self::assertSame('converted', $this->db->fetchColumn('SELECT status FROM quotations WHERE id = ?', [$quote]));
        self::assertSame('paid', $this->db->fetchColumn('SELECT status FROM invoices WHERE id = ?', [$invoice]));
    }

    public function testOlderDeploymentLogsSnapshotUntilAdditiveMigrationBecomesAvailable(): void
    {
        $this->configureMail(1);
        $this->db->pdo()->commit();
        $this->db->run('RENAME TABLE mail_outbox TO mail_outbox_fixture_backup');
        try {
            $this->resetContainer();
            $result = $this->enqueue();
            self::assertSame('logged', $result['status']);
            self::assertStringContainsString('migration', $result['error']);
        } finally {
            $this->db->run('RENAME TABLE mail_outbox_fixture_backup TO mail_outbox');
            $this->resetContainer();
        }
        self::assertSame('queued', app(MailService::class)->resend($result['id'])['status']);
        self::assertNotNull($this->db->fetch('SELECT payload FROM mail_outbox WHERE email_log_id = ?', [$result['id']]));
    }

    public function testLegacySavedBodyIsRecoverableAndPrivatePathTraversalIsRejected(): void
    {
        $this->configureMail(1);
        $directory = storage_path('mail');
        if (!is_dir($directory)) mkdir($directory, 0700, true);
        file_put_contents($directory . '/legacy.html', '<p>Original legacy body</p>');
        $id = $this->db->insert('INSERT INTO email_logs (recipient, subject, template, status, body_path) VALUES (?, ?, ?, ?, ?)', ['fixture@example.test', 'Legacy fixture', 'contact-received', 'logged', 'legacy.html']);
        self::assertSame('queued', app(MailService::class)->resend($id)['status']);
        $id = $this->db->insert('INSERT INTO email_logs (recipient, subject, template, status, body_path) VALUES (?, ?, ?, ?, ?)', ['fixture@example.test', 'Unsafe path fixture', 'contact-received', 'logged', '../source.pdf']);
        self::assertSame('failed', app(MailService::class)->resend($id)['status']);
    }

    public function testEmailAuditFiltersRecordsAndEnforcesRelatedModuleRetryPermission(): void
    {
        $contact = $this->db->insert('INSERT INTO email_logs (recipient, subject, status, related_type) VALUES (?, ?, ?, ?)', ['contact@example.test', 'Hidden contact message', 'logged', 'contact']);
        $this->db->insert('INSERT INTO email_logs (recipient, subject, status, related_type) VALUES (?, ?, ?, ?)', ['invoice@example.test', 'Visible invoice message', 'logged', 'invoice']);
        $this->signInAsRole('finance_staff');
        $request = new Request('GET', '/admin/emails', ['q' => 'message', 'status' => 'logged'], [], [], $_SERVER, []);
        $response = app(AuditController::class)->emails($request);
        $html = (string) (new ReflectionProperty(Response::class, 'body'))->getValue($response);
        self::assertStringContainsString('Visible invoice message', $html);
        self::assertStringNotContainsString('Hidden contact message', $html);
        self::assertStringContainsString('<thead>', $html);
        try {
            app(AuditController::class)->resend(new Request('POST', '/admin/emails', [], [], [], $_SERVER, []), (string) $contact);
            self::fail('Finance may not retry customer contact messages.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->status());
        }
    }

    public function testUncertainRetryEndpointRequiresPostedConfirmationAndAuditsTheActor(): void
    {
        $this->configureMail(1);
        $result = $this->enqueue();
        $this->db->run("UPDATE email_logs SET status = 'uncertain' WHERE id = ?", [$result['id']]);
        $controller = app(AuditController::class);
        $controller->resend(new Request('POST', '/admin/emails', [], [], [], $_SERVER, []), (string) $result['id']);
        self::assertSame('uncertain', $this->db->fetchColumn('SELECT status FROM email_logs WHERE id = ?', [$result['id']]));
        $controller->resend(new Request('POST', '/admin/emails', [], ['confirmed' => '1'], [], $_SERVER, []), (string) $result['id']);
        self::assertSame('queued', $this->db->fetchColumn('SELECT status FROM email_logs WHERE id = ?', [$result['id']]));
        self::assertSame(2, (int) $this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE user_id = ? AND entity_id = ? AND action = 'email_retry_requested'", [$this->adminId, $result['id']]));
    }

    public function testTargetedWorkerLeavesOtherQueuedRecipientsUntouched(): void
    {
        $smtp = $this->smtp();
        $this->configureMail($smtp['port']);
        $other = $this->enqueue();
        $target = $this->enqueue();
        $this->db->pdo()->commit();
        $results = app(MailService::class)->processQueue(1, $target['id']);
        self::assertSame($target['id'], $results[0]['id']);
        self::assertSame('sent', $results[0]['status']);
        self::assertSame('queued', $this->db->fetchColumn('SELECT status FROM email_logs WHERE id = ?', [$other['id']]));
        self::assertSame('0', (string) $this->db->fetchColumn('SELECT attempts FROM mail_outbox WHERE email_log_id = ?', [$other['id']]));
        self::assertContains('DATA', $this->smtpResult($smtp)['commands']);
    }

    private function enqueue(?string $attachment = null): array
    {
        return app(MailService::class)->send('fixture-recipient@example.test', 'Fixture subject', 'contact-received', ['name' => 'Original recipient'], 'contact', null, $attachment, $attachment === null ? null : 'fixture-document.pdf');
    }

    private function configureMail(int $port, array $overrides = []): void
    {
        $property = new ReflectionProperty(Config::class, 'items');
        $items = $property->getValue();
        $items['mail'] = array_merge($items['mail'], ['host' => '127.0.0.1', 'port' => $port, 'encryption' => 'none', 'username' => '', 'password' => '', 'from_address' => 'fixture-sender@example.test', 'from_name' => 'Fixture Sender', 'timeout' => 2, 'max_attempts' => 3], $overrides);
        $property->setValue(null, $items);
    }

    private function smtp(string $mode = 'accept'): array
    {
        [$process, $pipes] = $this->process('smtp_server.php', ['mode' => $mode]);
        $ready = trim((string) fgets($pipes[1]));
        self::assertStringStartsWith('READY ', $ready);
        return ['process' => $process, 'pipes' => $pipes, 'port' => (int) substr($ready, 6)];
    }

    private function smtpResult(array $smtp): array
    {
        $result = json_decode((string) fgets($smtp['pipes'][1]), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($result);
        return $result;
    }

    private function process(string $file, array $configuration): array
    {
        $pipes = [];
        $process = proc_open([PHP_BINARY, base_path('tests/fixtures/' . $file)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        self::assertIsResource($process);
        foreach ([1, 2] as $index) stream_set_timeout($pipes[$index], 20);
        $this->processes[] = [$process, $pipes];
        fwrite($pipes[0], json_encode($configuration, JSON_THROW_ON_ERROR) . "\n");
        fflush($pipes[0]);
        return [$process, $pipes];
    }
}
