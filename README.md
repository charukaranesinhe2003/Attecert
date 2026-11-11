# AtteCert

Document attestation, legalization, translation, quotation, invoice, tracking, and administration platform for AtteCert. The public site, customer portal, and staff console share one case record from the first enquiry through MOFA, embassy processing, and secure download.

## Requirements

- PHP 8.2 or newer with `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, and `zip`
- MySQL 8 or a compatible server
- Composer 2
- A writable `storage/` directory

## Installation

1. Clone the project.
2. Install dependencies:

```bash
composer install
```

3. Copy the environment file and edit the database settings:

```bash
copy .env.example .env
```

4. Create the database, tables, and development data:

```bash
php cli/migrate.php
php cli/seed.php
```

5. Make sure the web server user can write to:

- `storage/documents`
- `storage/invoices`
- `storage/quotations`
- `storage/receipts`
- `storage/logs`
- `storage/cache`
- `storage/mail`

6. Point the site root at `public/`, or start the built-in server:

```bash
php -S 127.0.0.1:8090 -t public public/router.php
```

7. Open `http://127.0.0.1:8090`.

On Windows PowerShell, run these commands from the local project folder (for
example, `D:\Chandila\XAMP\htdocs\Attecert`). A Linux hosting path such as
`/home/azharbio/attecert.com` is not a path on your Windows PC.

## Development accounts

The seeder creates local accounts. Change them before any shared or production use.

| Role | Email | Password |
| --- | --- | --- |
| Super admin | `admin@attecert.local` | `Admin@12345` |
| Sample customer | `customer@attecert.local` | `Customer@12345` |

The admin account is marked to change its password. The sample customer has case `ATT-2026-000001` for public tracking.

Staff marked to change a password must complete `/admin/password` before using other authenticated features. Password changes and resets invalidate older sessions. Deploying the session fingerprint check requires existing users to sign in again; it does not change their stored passwords.

## Staff and Operations

In **Staff & roles**, administrators can create staff with a role and temporary password, change roles or account status, reset another staff member's password, and delete staff. Staff change their own password from **Change password**. New and reset passwords must be changed at the next sign-in; resetting a password revokes existing sessions and reset links. Administrators cannot delete themselves or remove the last active super administrator. Only a super administrator can manage another super administrator.

Quote-to-case conversion reuses existing customer records and preserves existing login credentials, roles, and account status. Emails reserved by deleted logins, or by archived customer profiles, can still receive a new contact-only customer record for case processing. Conversion does not restore deleted accounts or profiles, and it does not grant login access to those contacts. Their case-opening email links to public tracking instead of a sign-in page.

Use **Assign staff** (`/admin/operations`) and its **Cases** and **Quote requests** tabs to search work, filter by assigned staff or unassigned records, and assign, reassign, or unassign work. Only active staff accounts can receive assignments. Deleting staff removes their login access and returns their cases and quote requests to the unassigned queue while preserving history. Quote-request assignments are independent of existing case assignments. Converting an unconverted request creates a case assigned to the request's assignee; opening an already converted request does not reassign its case.

The `admin` and `super_admin` roles retain full case and quote-request access. All other staff roles, including manager and content editor, can access only their assigned cases and their related documents, quotations, invoices, payments, and processing details, plus quote requests assigned to them. Assigned staff can update request status, download submitted request attachments, and convert requests to cases. They cannot reassign work or access global customers, reports, website administration, staff management, settings, contacts, or email logs. Lists, searches, direct record links, and downloads enforce the same assignment policy. Staff without assignments have an empty workspace. These features use PHP controllers and forms. Run `php cli/migrate.php` when updating an existing installation to apply `004_quote_request_assignments.sql`.

## Tracking management

The default case page provides independent **Steps & status**, **Documents (optional)**, and **Payments** controls. Add or customize steps, set their progress, type any case-specific status (or choose a standard status), and upload/email customer documents from this page. Detailed service, processing, quotation, and historical finance controls remain under **Case details**. Custom status labels are scoped to their case. Automatic financial/process events preserve a manually chosen custom status. Reopening a closed case still requires confirmation and a reason.

Every step supports an optional PDF/image, including pending, current, and completed steps. Save without a file to update progress, or add a file to an existing step without changing its status. Uploaded files remain associated with their step, are attached unchanged to the update email, and are available in the customer's authenticated documents. Every changed step queues a short SMS and a formatted email including previous progress. The case, customer account, public tracker, and update emails retain earlier status and step snapshots after edits or removal from the current plan. Public history excludes private messages, contact details, payment amounts, references, and file downloads.

Payment confirmation accepts an optional **payment confirmation PDF**. Confirming without a file remains supported; a PDF can be added later without counting the payment again. The original PDF is saved, emailed to the customer, and used for receipt downloads. Confirmation PDFs are retained with the payment record. Apply `011_step_documents_payment_receipts.sql` with `php cli/migrate.php` when updating an existing installation.

New cases receive a unique reference such as `KASS/Q/2026111101`: `KASS/Q/`, the date in Asia/Colombo, and a daily counter starting at `01`. The counter grows past `99` and is serialized in MySQL for concurrent requests. Existing and archived references stay reserved; existing tracking links keep working. Uploaded quotation PDFs remain unchanged.

In **Payments**, add an amount, method, date, and optional reference. An invoice is optional. Entries remain **pending** until an administrator or the assigned staff member checks the funds and selects **Confirm received & notify**. Confirmation records who received the payment and when, makes a PDF receipt available, and queues a short SMS plus a formatted email summary. Pending entries can be cancelled. They do not count as received funds or invoice payments. Invoice-linked confirmations recheck the current balance under a lock. Repeated submissions or confirmations do not duplicate a payment or its notifications. Apply `010_simple_case_workflow.sql` using `php cli/migrate.php` on an existing installation.

Use the **Tracking** navigation tab (`/admin/tracking`) to select a case, edit its public notice and expected date, and add, edit, order, or remove tracking steps. Each step has a public title and description, optional date and document, and pending/in-progress/completed state. Current steps appear alongside the complete previous progress history on `/track`. The public page shows destination, started/updated/expected dates, case status, notice, and the timeline. Private case notes, customer contact details, payment values, and document downloads are not included in anonymous tracking.

Administrators can manage all cases; other staff can manage only cases assigned to them. Assignment checks also apply to direct URLs, step IDs, and document IDs. Every new or changed tracking step automatically queues a short SMS and a formatted detailed email. Saving identical values does not send duplicate notifications. Overall public case-status changes create SMS, email, and account notifications; backward or reopened statuses require a reason and confirmation. **Upload & email a document** attaches the original PDF/image and makes it available in the customer's authenticated documents. Existing customer/completed documents can also be emailed; internal files cannot be sent through this page. Delivery uses the existing mail outbox, SMTP settings, and mail worker. Missing SMTP is reported as saved-only. Apply migration `006_case_tracking.sql` with `php cli/migrate.php` when updating an installation.

## Uploaded quotations

Create quotations manually outside the app, then use **Quote requests → Upload & email quotation** on the relevant request, or the quotation tab under **Case details**. Upload a PDF and enter the exact quoted total and validity. The Documents tab lists saved files and does not include the quotation upload form. Request uploads are tied to the selected request and customer; only administrators and the request's assigned staff can open the request, upload its quotations, download them, or email them again. A new case is created when an unconverted request receives its first quotation. An existing case's assignment stays unchanged; request staff can manage the request's quotations without gaining access to unrelated case details. Case-based uploads retain their existing case-assignment checks. The original PDF is stored unchanged and is used for staff downloads, customer downloads, and email attachments. Uploading a revision retains earlier files and supersedes pending quotations. Quotation files are retained with their quotation history. Apply `007_request_quotations.sql` with `php cli/migrate.php` when updating an installation.

The former quotation builder no longer creates quotations through the web app. Existing quotation records and downloads remain available. Acceptance and invoicing use the total entered by staff. Apply `005_uploaded_quotations.sql` using `php cli/migrate.php` when updating an existing installation. Email is queued through the mail worker described below; missing SMTP settings are reported rather than claiming delivery.

## Quote requests and tracking verification

Public quote requests and document tracking require OTP verification by default (`PUBLIC_OTP_REQUIRED=true` in `.env`). Guests can request quotations without creating an account: verify an email address or mobile number first, then complete the request using those verified contact details. A successful quote submission consumes its verification grant; another request requires verification again. Changing the verified contact requires another code.

Tracking accepts a reference and sends a code only to the customer email/mobile already registered with that case. A posted email or phone cannot redirect delivery. No progress, document title, customer identity, or saved contact is shown before verification. Unknown references use the same generic response. Tracking grants last 30 minutes and are bound to the browser, purpose, case, and saved contact. Changing the saved contact invalidates access. Codes expire after 10 minutes, allow 5 attempts, and have a 60-second resend interval. Destination/session/IP limits also apply; resending invalidates older codes. Missing SMS/SMTP configuration never bypasses verification.

Apply migration `012_public_action_otps.sql` with `php cli/migrate.php` and configure the existing SMSlenz/SMTP settings and notification worker below. OTP email/SMS delivery uses the same durable queues; expired OTP messages are discarded by the workers. Verification screens provide email/SMS selection, resend controls, code entry, unavailable-provider feedback, and responsive layouts.

Step updates attach every customer-visible document linked to that step, including documents uploaded during earlier updates. The original filenames and bytes are frozen in the email outbox. Multiple attachments survive queued delivery and retries; missing or changed saved attachments fail instead of sending incomplete mail. Private and archived documents are excluded. Admin forms share consistent button sizes, spacing, file controls, typography, and responsive grids across mobile, tablet, and desktop layouts.

## Customer OTP, SMSlenz, and notification delivery

Customer OTP verification is enabled with `CUSTOMER_OTP_REQUIRED=true`. Customers must verify either their email or their mobile number before accessing the portal. Staff accounts are exempt. Existing customers also complete OTP once; an old email-verification flag alone does not bypass the new check. Codes last 10 minutes, allow 5 attempts, can be requested once every 60 seconds, and have a limit of 5 requests per account per hour. Codes are single-use and bound to the account, destination, and current password. Resending invalidates older codes. Changing an SMS-verified phone requires verification again unless the customer has verified through email. Missing delivery credentials never bypass verification.

The operations menu is ordered **Assign staff → Quote requests → Cases → Tracking → Documents → Processing → Customers → Support messages**. The last four pages are admin-only, including direct URLs. Staff can still work on documents and processing within an assigned case; customer directories and unrelated customer information remain unavailable. Quote-request assignments continue to control request access separately from case assignments.

The integration follows the [official SMSlenz Send SMS API](https://smslenz.lk/developers/api). Configure these server-side `.env` values using your real account details (the example credentials from chat are not used):

```dotenv
CUSTOMER_OTP_REQUIRED=true
SMSLENZ_BASE_URL=https://smslenz.lk/api
SMSLENZ_USER_ID=
SMSLENZ_API_KEY=
SMSLENZ_SENDER_ID=AtteCert
SMSLENZ_TIMEOUT=15
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-custom-mailbox@example.com
MAIL_FROM_NAME=AtteCert
MAIL_TIMEOUT=30
MAIL_MAX_ATTEMPTS=3
```

Use an approved SMSlenz sender ID, and enable PHP cURL. The worker sends JSON containing `user_id`, `api_key`, `sender_id`, `contact`, `message`, and `contact_name`; local Sri Lankan mobile numbers are normalized to international format. API keys stay in environment configuration and are not stored in message logs. Provider success is recorded only for a successful HTTP/JSON response with `success: true` and `data.status: success`. A provider acceptance is not a handset-delivery receipt. Rejected requests are recorded as failed. Ambiguous transport failures and interrupted deliveries are marked uncertain and are not automatically retried, to avoid duplicate charges/messages.

Apply migrations with `php cli/migrate.php`. Schedule this single PHP command every minute, with the application as the working directory:

```powershell
C:\xampp\php\php.exe "F:\CHANDILA\AtteCert\Attecert\cli\notification-worker.php" --limit=20
```

This handles both the existing mail outbox and the SMS queue outside case-saving transactions. Separate `cli/mail-worker.php` and `cli/sms-worker.php` commands are also available. Expired OTP messages are not delivered. Messages blocked by missing SMS credentials are not silently sent later when credentials are added; new notifications use the configured service. Queued SMS contains customer messages and verification text, and saved email files may contain verification codes; protect the database and private storage accordingly. Neither codes nor credentials are returned in verification responses or repopulated into forms. Keep the app's `storage/` directory inaccessible from the web.

## Email

SMTP is read only from the environment. Do not put mail passwords in source code.

```
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hello@attecert.lk
MAIL_FROM_NAME=AtteCert
```

Until `MAIL_HOST` is set, send actions stay honest: the message is saved under `storage/mail`, recorded in Email logs, and the screen says SMTP is not configured. Staff can resend after credentials are added.

With SMTP configured, send actions queue durable messages. Apply migration `003_mail_outbox.sql` with `php cli/migrate.php`, then schedule `php cli/mail-worker.php --limit=20` every minute in the application directory. The worker sends outside business transactions and uses bounded retries. Review recipients before enabling it; old saved-only logs are not delivered automatically. For one authorized test, restrict delivery with `--id=<email-log-id>`. See [mail delivery operations](docs/mail-delivery.md) for ambiguous-delivery recovery and deployment details.

## Scheduled task

Run once a day so quotations expire and unpaid invoices become overdue:

```bash
php cli/cron.php
```

## Languages

English, Sinhala, and Tamil are included. Add another locale by creating `resources/lang/{code}.php`, registering it in `config/app.php`, and adding translation rows for services, countries, FAQs, articles, and statuses. Staff can override interface strings in Admin → Translations and edit public copy in Website content.

## What the case connects

A case number such as `ATT-2026-000001` is the centre of the workflow. It links the customer, destination, selected services, status history, uploaded quotation, invoice, payments, MOFA record, embassy record, and files. Quotation totals are entered by staff to match their uploaded PDF. Later price changes do not rewrite issued invoices.

Public tracking shows the case number, destination, visible status timeline, and next step. It does not show contact details, staff notes, payment values, or files. Downloads require the signed-in customer or an authorised staff user and are written to the download log.

## Backup

Back up three things together:

- the MySQL database
- `storage/documents`, `storage/invoices`, `storage/quotations`, and `storage/receipts`
- private `storage/mail` snapshots, backed up with their matching database rows
- the server `.env` file, stored outside the repository

There is no in-app backup button. A button that only displayed success would not be a backup.

## Tests

```bash
vendor/bin/phpunit
php cli/flow.php
```

`cli/flow.php` runs the business path: quote request, case, fixed and custom prices, quotation PDF, customer acceptance, invoice, payment, MOFA file upload, authorised download, embassy completion, and audit records.

`cli/flow.php` writes records and files to the configured application database and storage, including its fixture account. Do not run it against data you need to preserve.

The regression suite has an opt-in integration mode that creates its own randomly named MySQL database (`attecert_test_...`) and isolated storage, disables SMTP, and cleans up its fixtures. The configured database credentials need permission to create and drop test databases. On PowerShell:

```powershell
$env:ATTECERT_INTEGRATION = '1'
php vendor/phpunit/phpunit/phpunit
```

Without `ATTECERT_INTEGRATION=1`, database tests are skipped. The integration suite includes ownership and staff permissions, suspension, financial bounds and concurrent writes, quotation revisions, dashboard drilldowns, document release, catalog preservation, reports and PDF generation. Existing application records are never used as write fixtures.

For local browser QA only, run `php tests/preview.php` with the same integration flag. It serves disposable seeded data on `http://127.0.0.1:8091`; no real email is sent. Create the `stop-preview` file at the exact path printed by the command to stop the server and clean up its test database/files. Do not expose this development preview publicly.

See [the administration review](docs/admin-review.md), [first completion report](docs/admin-completion.md), [follow-up report](docs/admin-followup.md) and [scoped accessibility review](docs/admin-accessibility.md) for findings, implemented improvements, actual verification and deployment requirements.

## Production

Set `APP_ENV=production`, `APP_DEBUG=false`, and `APP_URL` to the public HTTPS origin. Use a dedicated database user. Serve only the `public/` directory. Replace the seeded passwords and the placeholder Colombo phone, email, and WhatsApp number in Admin → Settings. The landing-page screenshot did not include readable contact details, so those values are settings rather than hard-coded claims.

### Installing dependencies on cPanel

The `vendor/` directory is excluded from Git, so it must be generated on the
hosting account (or uploaded there) before the site can run. In cPanel, use
the Composer interface if available and select the directory containing
`composer.json`. If you have SSH or cPanel Terminal access, run the following
on the hosting server, replacing the directory with the actual project path:

```bash
cd ~/attecert.com
composer install --no-dev --optimize-autoloader
```

If `composer` is not recognized in cPanel Terminal, use cPanel's Composer
interface or ask the hosting provider for its Composer 2 command. Running
`composer install` in Windows PowerShell only creates `vendor/` on your local
PC; it does not install dependencies on the hosting server. Confirm that
`vendor/autoload.php` exists in the deployed project directory before loading
the website.
