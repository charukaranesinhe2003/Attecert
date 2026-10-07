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

## Development accounts

The seeder creates local accounts. Change them before any shared or production use.

| Role | Email | Password |
| --- | --- | --- |
| Super admin | `admin@attecert.local` | `Admin@12345` |
| Sample customer | `customer@attecert.local` | `Customer@12345` |

The admin account is marked to change its password. The sample customer has case `ATT-2026-000001` for public tracking.

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

## Scheduled task

Run once a day so quotations expire and unpaid invoices become overdue:

```bash
php cli/cron.php
```

## Languages

English, Sinhala, and Tamil are included. Add another locale by creating `resources/lang/{code}.php`, registering it in `config/app.php`, and adding translation rows for services, countries, FAQs, articles, and statuses. Staff can override interface strings in Admin → Translations and edit public copy in Website content.

## What the case connects

A case number such as `ATT-2026-000001` is the centre of the workflow. It links the customer, destination, selected services, status history, quotation, invoice, payments, MOFA record, embassy record, and files. Fixed service prices are copied onto the quotation. Later price changes do not rewrite issued invoices.

Public tracking shows the case number, destination, visible status timeline, and next step. It does not show contact details, staff notes, payment values, or files. Downloads require the signed-in customer or an authorised staff user and are written to the download log.

## Backup

Back up three things together:

- the MySQL database
- `storage/documents`, `storage/invoices`, `storage/quotations`, and `storage/receipts`
- the server `.env` file, stored outside the repository

There is no in-app backup button. A button that only displayed success would not be a backup.

## Tests

```bash
vendor/bin/phpunit
php cli/flow.php
```

`cli/flow.php` runs the business path: quote request, case, fixed and custom prices, quotation PDF, customer acceptance, invoice, payment, MOFA file upload, authorised download, embassy completion, and audit records.

## Production

Set `APP_ENV=production`, `APP_DEBUG=false`, and `APP_URL` to the public HTTPS origin. Use a dedicated database user. Serve only the `public/` directory. Replace the seeded passwords and the placeholder Colombo phone, email, and WhatsApp number in Admin → Settings. The landing-page screenshot did not include readable contact details, so those values are settings rather than hard-coded claims.
