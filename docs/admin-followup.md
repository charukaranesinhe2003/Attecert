# Administration follow-up — implementation and verification

15 October 2026. This report supersedes the remaining implementation items in [the first completion report](admin-completion.md). The original architecture, verified defects, priorities and page proposals remain in [the investigation](admin-review.md).

## Result

The authorized staff credential workflow, case transition corrections, input validation, durable mail queue, operational list filters, admin translation coverage and accessibility improvements are implemented. The additive mail migration has been applied to the configured local database. Existing business records and environment settings were preserved.

External SMTP delivery is still unverified: the configured installation has no SMTP host. No real email was sent. The user supplied an authorized recipient for one clearly labelled test message; the test must remain restricted to that one entry when configuration is ready. Do not drain historical messages to perform the test.

The new browser verification attempt was blocked before navigation because automatic approval review could not complete after an account usage limit. The disposable preview was stopped and cleaned up. Earlier desktop/mobile browser checks are recorded in the first report; they do not validate every follow-up visual change.

## Changes, reasons and evidence

| Area | Previous problem | Implemented behavior and evidence |
| --- | --- | --- |
| Staff credentials | `must_change_password` did not restrict staff access; no dedicated password-change page existed. Older sessions could survive password replacement. | GET/POST `/admin/password`, AuthMiddleware access gate, current-password verification, confirmation, raw password handling, session/CSRF renewal and audited changes. Password changes/resets invalidate sessions holding the previous password fingerprint. AuthService, SecurityController, AuthController, Password helper and StaffSecurityTest. |
| Password storage | Bcrypt can silently ignore bytes beyond its limit. | New hashes prefer available Argon2id. Bcrypt fallback rejects values exceeding its byte limit. Existing bcrypt hashes still verify. Passwords are never flashed back to forms. A fresh login is required for existing sessions after deploying the fingerprint check. |
| Case transitions | Quotation, payment or processing events could undo later work or reopen closed cases. Manual corrections were indistinguishable from ordinary progress. | `WorkflowService::advance()` permits only forward automatic progress on active cases; duplicate/backward/closed-case events are no-ops. Manual backward or closed-case changes require an explicit confirmation and reason. Existing valid skips remain supported. Case lock, status history, audit and notification writes share a transaction. See [workflow rules](admin-workflow-rules.md). |
| Guidance and tracking | Next-step guidance assumed a universal MOFA-to-embassy pipeline; public history exposed staff identities and potentially personal notes. | NextStep follows selected service categories and latest actual processing records. Ready/closed/active stages take precedence over stale finance suggestions. Anonymous tracking returns visible status/time only; signed-in histories retain their authorized detail. WorkflowCompletionTest exercises the branches and privacy projection. |
| Configuration | Settings accepted invalid durations, currency, URLs and runtime upload limits. | Grouped labelled form, field errors and retained input; complete validation precedes transactional settings/audit writes. HTTP(S) links reject embedded credentials, currency uses three uppercase letters, day counts are whole values 1–3650 and upload limits respect PHP configuration. Missing fields in partial updates preserve current settings. |
| Money and quotations | Malformed/negative/overprecision values could reach BCMath or database writes; service currency could be silently relabelled. | Decimal validates two-decimal amounts and schema precision, positive quantities, tax 0–100 and duration bounds. Quote totals and lines are checked before writes; service/case currency mismatches fail without inventing exchange rates. Existing discount capping remains. Missing commercial records return 404 in the corrected paths; invoice cancellation needs a reason. |
| Communication | SMTP ran during business work, retry state was weak and a resend could lose original attachment content. | MailService persists an outbox entry and exact private HTML/attachment snapshots with hashes. Only the explicit CLI worker contacts SMTP after committing its claim. Bounded transient retries, permanent failures, interrupted/ambiguous delivery review, stable Message-ID, related-module recovery permissions and audited outcomes. Commercial `sent_at` is written only after SMTP acceptance. See [delivery operations](mail-delivery.md). |
| Operational lists | Contacts, quote requests, payments, users and catalog lists had limited filtering or unbounded output. | Search/status/source/method/date/role/category filters as applicable, stable ordering, database pagination, counts, empty states and mobile field labels. Contacts preserve triage context after status updates. A quote request cannot be labelled converted without an actual linked case. Audit and email lists also support bounded filters. |
| Translation editing | Only the initial keys were shown; failed edits were lost and reset behavior was unclear. | Real 20-key pagination and search across keys/English/custom text, confirmed per-locale reset and transactional audit. Failed locale values return only to their submitted row. Saving bundled defaults does not pin unnecessary database overrides. TranslationController, TranslationService and AdminCompletionTest. |
| Languages | New admin messages depended on English fallback. | English/Sinhala/Tamil overlays cover new namespaces and core admin, overview, fields and actions. Placeholder/UTF-8/key parity is tested directly against locale files, so fallback cannot conceal missing keys. Authored translations still need native-speaker copy review; dynamic business content retains its existing translation workflow. |
| Accessibility | Case sections lacked complete tab relationships/keyboard behavior; form errors and small status text needed improvement. | Linked tab/panel IDs, roving tabindex, arrow/Home/End behavior, named controls, table headers/captions, scoped payment/refund restoration, inline error relationships, visible focus, stronger text/badge contrast and reduced motion. See [the scoped accessibility review](admin-accessibility.md) for actual checks and limits. |
| Malformed input | Empty arrays could bypass optional scalar validation; array filters emitted PHP warnings. | Validator rejects nonscalar field values before the optional-empty shortcut. New list filters normalize scalar input; invalid payment date shapes return 422. Settings and translation restoration handle rejected input safely. The regression suite executes these cases with PHP warnings promoted to exceptions. |

## Routes, storage and compatibility

The only new staff route is GET/POST `/admin/password`, protected by authentication and staff access; POST also requires existing CSRF protection. Existing operations retain their routes and permissions. The first phase's confirmed document visibility endpoint remains in use.

`database/migrations/003_mail_outbox.sql` creates one additional InnoDB table keyed to `email_logs`. It was applied with `cli/migrate.php`: migrations 001 and 002 were skipped, 003 applied. It does not backfill or automatically deliver old logs. The configured outbox is empty after migration.

New private mail files reside under `storage/mail`; their database rows and files must be backed up together. Rolled-back business operations cannot produce a deliverable outbox row. Files written before a transaction rollback can remain orphaned; no destructive retention/purge policy was introduced. Historical logs without exact old attachment metadata reconstruct a commercial PDF once on manual retry and freeze subsequent attempts, with an honest failure if reconstruction is impossible.

No `.env`, dependency, Git index or commit changes were made by this task. No configured accounts were reset, no business rows were removed and no existing queue was sent. Optional development preview and tests use guarded disposable databases and separate storage.

## Changed modules

In addition to the first report's file inventory, follow-up work affects:

- Authentication: AuthService, AuthController, AuthMiddleware, UserController; new SecurityController, Password helper, password view and route/layout link.
- Workflow: WorkflowService, CaseService, NextStep, CaseController, ProcessController, PaymentService, QuotationService and case workspace markup.
- Validation/settings/finance: Validator, View, Decimal helper, SettingsController/SettingsService/settings view, InvoiceService/InvoiceController and PaymentController.
- Mail: MailService, AuditController/email log view, `config/mail.php`, migration 003 and `cli/mail-worker.php`; feedback in quotation/invoice/document/case controllers and shared helpers.
- Lists/locales: contacts/quote requests/payments/audit/translations/users/services/countries/articles/FAQs controllers and corresponding views; TranslationService; `resources/lang/admin/*.php`, shared list-filter partial, admin CSS and JavaScript.
- Verification: StaffSecurityTest, WorkflowCompletionTest, MailReliabilityTest and SMTP/worker fixtures, AdminCatalogListsTest, AdminCompletionTest, AdminAccessibilityTest, AdminLocalizationTest; test session setup and disposable preview compatibility.

## Actual verification

Final complete execution on PHP 8.2.12/MySQL 8.0.42: **112 tests, 4,806 assertions passed** in 59.301 seconds. The suite uses real schema/seed data in disposable databases; it covers intake-to-completion, authorization, password/session changes, transition corrections, public tracking privacy, money validation, concurrent payments/invoice creation, durable SMTP claims and failure modes, PDF/attachment integrity, settings, filtered lists, translation overrides/reset and rendered accessibility relationships.

The SMTP fixtures exercise real PHPMailer commands against loopback servers, including AUTH, attachment MIME bytes, TLS refusal, temporary/permanent rejection, disconnect ambiguity and competing workers. They do not prove delivery through an external provider.

PHP lint passed for **217 source files**. `node --check` passed for admin.js and public.js; `git diff --check` passed. Static color-pair calculations and rendered-view DOM assertions are recorded separately from browser checks in the accessibility review.

After migration, all eleven inspected table counts matched both the pre-follow-up and original baseline:

| Table | Rows |
| --- | ---: |
| users | 19 |
| customers | 15 |
| cases | 16 |
| quotations | 12 |
| invoices | 9 |
| payments | 8 |
| documents | 16 |
| quote_requests | 9 |
| contact_messages | 6 |
| audit_logs | 74 |
| email_logs | 20 |

`mail_outbox` has 0 rows; migration 003 is recorded. No generated test databases remained after cleanup. Matching counts are a preservation check, not a claim of a full database checksum.

## Remaining deployment and review work, in order

1. Configure the intended SMTP provider securely outside chat/source control, verify sender/TLS settings, then send the one authorized labelled test using `mail-worker.php --id=...`. Confirm provider acceptance and mailbox receipt separately. No external message has been sent in this task.
2. Enable the bounded mail worker every minute on the intended deployment host after reviewing queued recipients. No operating-system task was installed locally. Verify the existing daily quotation-expiry/overdue cron, backups and production roles on that host.
3. Complete fresh desktop/tablet/320px browser, keyboard, zoom and screen-reader checks when browser approval is available. Current source/DOM checks and earlier browser observations are useful evidence but do not establish WCAG conformance.
4. Obtain native Sinhala/Tamil copy review and check translated business content in the intended deployment. Existing sessions must sign in again following deployment of the password fingerprint change.
