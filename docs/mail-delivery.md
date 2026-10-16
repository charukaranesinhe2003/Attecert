# Durable email delivery

Business requests save an email log and an outbox row in the existing database transaction. They never open an SMTP connection. Private `storage/mail` files retain the rendered HTML, sender, message ID and original attachment bytes; SHA-256 checks detect missing or changed snapshots. A rolled-back transaction has no deliverable outbox row. Back up private mail storage together with the database, and keep it outside the web document root.

Apply `database/migrations/003_mail_outbox.sql` through the normal migration procedure before enabling the worker. It only creates an additional table and leaves existing `email_logs` intact. Before migration, messages are honestly marked `logged`, with a migration-required explanation and a recoverable snapshot. Do not run seed/sample/flow commands against an existing installation.

After confirming the SMTP settings and reviewing queued recipients, run:

```powershell
C:\xampp\php\php.exe cli/mail-worker.php --limit=20
```

For an authorized single-message verification, add `--id=123` using that message's email-log ID. This restricts delivery to that queued entry and leaves other recipients untouched.

For deployment, schedule that command every minute in the application directory using the deployment's PHP executable. The worker is a bounded CLI command; the existing daily financial cron does not invoke it. No worker or task schedule is installed automatically. Protect the operating-system scheduler and `.env` as application secrets.

`MAIL_TIMEOUT` defaults to 30 seconds (bounded to 1–120). `MAIL_MAX_ATTEMPTS` defaults to 3 (bounded to 1–10). These optional settings do not overwrite existing environment configuration. Authentication, port and encryption use the existing SMTP configuration. An explicit `MAIL_ENCRYPTION=none` disables opportunistic TLS; explicit TLS/SSL is never silently downgraded. Certificate validation remains enabled.

Delivery states shown on the admin email page:

| State | Meaning and recovery |
| --- | --- |
| Queued | Waiting for a worker, or for a transient retry's backoff to expire. |
| Sending | A worker holds the single database claim. Another worker or retry cannot claim it. |
| Sent | SMTP accepted the message. This confirms provider acceptance, not arrival in the recipient's inbox. |
| Failed | Permanent rejection, invalid/missing payload, or exhausted retries. Resolve the cause, then queue a manual retry. |
| Saved only (`logged`) | SMTP or migration is unavailable. Review and queue a retry after resolving it; historical messages are never sent automatically just because configuration changes. |
| Check delivery (`uncertain`) | Connection lost while sending DATA, or a worker stopped for more than 30 minutes. Check provider delivery logs before confirming a retry; the provider might already have accepted it. |

Temporary connection/4xx failures retry after 60 then 120 seconds by default, when a later worker run occurs. Permanent 5xx responses stop automatically. Interrupted/ambiguous deliveries require explicit confirmation and are not blindly replayed. SMTP is not an exactly-once system: the claim prevents concurrent workers sending the same entry, while a stable message ID and manual review of ambiguous outcomes reduce duplicate risk.

Every worker claim, outcome, interrupted recovery and admin retry request is audited. Commercial `sent_at` timestamps are written only after SMTP acceptance. Invoice status advances from `issued` to `sent`; payment, cancellation, conversion and other advanced states remain intact. Successfully delivered logs cannot be retried through the recovery endpoint; use the original record's send action to intentionally create another message.

New entries retain exact attachments across retries. Old logs have no historical attachment metadata: on their first manual retry, quotation/invoice PDFs are reconstructed from the associated immutable commercial record and then snapshotted. Missing legacy HTML/PDFs fail clearly instead of sending an incomplete message. Restoring a backup must include matching private mail files and database rows.

The automated suite uses randomly named disposable databases and loopback-only SMTP servers. It verifies actual PHPMailer SMTP commands, authentication, MIME attachment bytes, plaintext/TLS configuration behavior, transient/permanent failures, interrupted delivery and concurrent claims. It does not prove delivery through the deployment's external provider or to a real mailbox; that needs a configured provider and an explicitly authorized recipient.
