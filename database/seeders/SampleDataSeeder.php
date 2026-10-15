<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;
use App\Services\NumberService;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Operational sample data for a Colombo attestation desk.
 * Requires the catalog seed (roles, services, countries, statuses).
 * Safe to run once; skips when settings.sample_dataset is already set.
 */
final class SampleDataSeeder
{
    private NumberService $numbers;
    private DateTimeZone $zone;

    /** @var array<string, int> */
    private array $status = [];

    /** @var array<string, int> */
    private array $service = [];

    /** @var array<string, int> */
    private array $country = [];

    /** @var array<string, int> */
    private array $docType = [];

    /** @var array<string, int> */
    private array $staff = [];

    public function __construct(private Database $db)
    {
        $this->numbers = new NumberService($db);
        $this->zone = new DateTimeZone('Asia/Colombo');
    }

    public function run(): void
    {
        if (!$this->db->fetch('SELECT id FROM roles LIMIT 1')) {
            (new DatabaseSeeder($this->db))->run();
        }
        if ((string) $this->db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key = 'sample_dataset'") === '1') {
            echo "Sample data already loaded. Nothing changed.\n";

            return;
        }

        $this->loadCatalog();
        $this->staff();
        $customers = $this->customers();
        $this->operations($customers);
        $this->db->run(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            ['sample_dataset', '1']
        );
        echo "Sample data loaded.\n";
        echo "Staff password: Sample@12345\n";
        echo "Portal customers use the same password. See customer emails ending in @example.lk\n";
    }

    private function loadCatalog(): void
    {
        foreach ($this->db->fetchAll('SELECT id, code FROM workflow_statuses') as $row) {
            $this->status[$row['code']] = (int) $row['id'];
        }
        foreach ($this->db->fetchAll('SELECT id, code FROM services') as $row) {
            $this->service[$row['code']] = (int) $row['id'];
        }
        foreach ($this->db->fetchAll('SELECT id, code FROM countries') as $row) {
            $this->country[$row['code']] = (int) $row['id'];
        }
        foreach ($this->db->fetchAll('SELECT id, code FROM document_types') as $row) {
            $this->docType[$row['code']] = (int) $row['id'];
        }
        $admin = $this->db->fetch('SELECT id FROM users WHERE email = ?', ['admin@attecert.local']);
        if ($admin) {
            $this->staff['admin'] = (int) $admin['id'];
        }
    }

    private function staff(): void
    {
        $people = [
            ['dilani', 'Dilani Fernando', 'dilani.fernando@attecert.lk', '077 234 1180', 'manager'],
            ['ruwan', 'Ruwan Jayawardena', 'ruwan.jayawardena@attecert.lk', '071 558 2044', 'processing_staff'],
            ['sanduni', 'Sanduni Wickramasinghe', 'sanduni.wickramasinghe@attecert.lk', '076 441 9021', 'finance_staff'],
            ['tharushi', 'Tharushi Silva', 'tharushi.silva@attecert.lk', '075 220 6638', 'customer_support'],
            ['kasun', 'Kasun Bandara', 'kasun.bandara@attecert.lk', '077 901 3345', 'content_editor'],
        ];
        $hash = password_hash('Sample@12345', PASSWORD_DEFAULT);
        foreach ($people as [$key, $name, $email, $phone, $role]) {
            $existing = $this->db->fetch('SELECT id FROM users WHERE email = ?', [$email]);
            if ($existing) {
                $this->staff[$key] = (int) $existing['id'];
                continue;
            }
            $id = $this->db->insert(
                'INSERT INTO users (name, email, phone, password_hash, locale, email_verified_at, last_login_at, last_login_ip) VALUES (?, ?, ?, ?, \'en\', ?, ?, ?)',
                [$name, $email, $phone, $hash, $this->at('-40 days'), $this->at('-1 day 09:15'), '112.134.88.21']
            );
            $roleId = (int) $this->db->fetchColumn('SELECT id FROM roles WHERE code = ?', [$role]);
            $this->db->run('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [$id, $roleId]);
            $this->staff[$key] = $id;
        }
    }

    /** @return array<string, int> */
    private function customers(): array
    {
        $rows = [
            ['nimal_kandy', 'Nadeesha Perera', 'nadeesha.perera@example.lk', '077 612 4408', '077 612 4408', '18, Dharmaraja Mawatha', 'Kandy', true, 'en'],
            ['ishara', 'Ishara Gunasekara', 'ishara.gunasekara@example.lk', '071 334 9082', '071 334 9082', '42/5, Wijerama Mawatha', 'Colombo 07', true, 'en'],
            ['fathima', 'Fathima Rizwana', 'fathima.rizwana@example.lk', '076 220 5519', '076 220 5519', '91, Messenger Street', 'Colombo 12', true, 'en'],
            ['dinesh', 'Dinesh Rajapaksha', 'dinesh.rajapaksha@example.lk', '075 118 7740', '075 118 7740', '7, Beach Road', 'Negombo', true, 'si'],
            ['mevan', 'Mevan Abeysekera', 'mevan.abeysekera@example.lk', '077 845 2201', null, '26, Lighthouse Street', 'Galle', true, 'en'],
            ['priya', 'Priya Selvarajah', 'priya.selvarajah@example.lk', '077 290 6614', '077 290 6614', '14, Hospital Road', 'Jaffna', true, 'ta'],
            ['chamodi', 'Chamodi Weerasinghe', 'chamodi.weerasinghe@example.lk', '071 902 3388', '071 902 3388', '55, Kandy Road', 'Kadawatha', false, 'si'],
            ['asela', 'Asela Madushanka', 'asela.madushanka@example.lk', '076 551 0094', null, '3, Station Road', 'Kurunegala', true, 'si'],
            ['nuwan', 'Nuwan Hettiarachchi', 'nuwan.hettiarachchi@example.lk', '077 441 2286', '077 441 2286', '88, Galle Road', 'Mount Lavinia', true, 'en'],
            ['sachini', 'Sachini Ekanayake', 'sachini.ekanayake@example.lk', '070 228 4417', '070 228 4417', '12, Matara Road', 'Galle', true, 'en'],
            ['harsha', 'Harsha de Silva', 'harsha.desilva@example.lk', '077 773 1902', null, '21, Baseline Road', 'Colombo 09', false, 'en'],
            ['amal', 'Amal Jayasuriya', 'amal.jayasuriya@example.lk', '071 660 2841', '071 660 2841', '6, Temple Road', 'Maharagama', true, 'en'],
        ];
        $hash = password_hash('Sample@12345', PASSWORD_DEFAULT);
        $roleId = (int) $this->db->fetchColumn('SELECT id FROM roles WHERE code = ?', ['customer']);
        $ids = [];
        foreach ($rows as [$key, $name, $email, $phone, $whatsapp, $address, $city, $portal, $locale]) {
            $existing = $this->db->fetch('SELECT id FROM customers WHERE email = ?', [$email]);
            if ($existing) {
                $ids[$key] = (int) $existing['id'];
                continue;
            }
            $userId = null;
            if ($portal) {
                $userId = $this->db->insert(
                    'INSERT INTO users (name, email, phone, password_hash, locale, email_verified_at, last_login_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$name, $email, $phone, $hash, $locale, $this->at('-20 days'), $this->at('-2 days 19:40')]
                );
                $this->db->run('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, $roleId]);
            }
            $ids[$key] = $this->db->insert(
                'INSERT INTO customers (user_id, name, email, phone, whatsapp, address, city, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $userId,
                    $name,
                    $email,
                    $phone,
                    $whatsapp,
                    $address,
                    $city,
                    $portal ? 'Portal account opened from the website.' : 'Walk-in. Prefers WhatsApp updates.',
                    $this->at('-' . (18 + count($ids)) . ' days'),
                ]
            );
        }

        return $ids;
    }

    /** @param array<string, int> $customers */
    private function operations(array $customers): void
    {
        $admin = $this->staff['admin'];
        $ruwan = $this->staff['ruwan'];
        $sanduni = $this->staff['sanduni'];
        $dilani = $this->staff['dilani'];
        $tharushi = $this->staff['tharushi'];

        $this->quoteRequest($customers, 'Ishara Gunasekara', 'ishara.gunasekara@example.lk', '071 334 9082', 'AE', 'DEGREE', ['TRANS', 'MOFA-ATT', 'EMB-LEG'], 'converted', 'Employment visa file for Dubai. University of Colombo BSc.', -16);
        $this->openRequest('Malith Senanayake', 'malith.senanayake@gmail.com', '077 512 8830', 'QA', 'POLICE', ['MOFA-ATT', 'EMB-LEG'], 'new', 'Police clearance for a Qatar work visa. Issued last month.', -1);
        $this->openRequest('Anjali Fernando', 'anjali.fernando@yahoo.com', '076 330 4412', 'GB', 'BIRTH', ['APOS'], 'new', 'Birth certificate for a UK spouse visa. Not sure if apostille is enough.', -2);
        $this->openRequest('Ravindu Karunaratne', 'ravindu.k@outlook.com', '071 778 2094', 'KW', 'DEGREE', ['TRANS', 'MOFA-ATT', 'EMB-LEG'], 'contacted', 'Degree and transcript for Kuwait. Asked us to call after 6 pm.', -4);
        $this->openRequest('Shop promo', 'deals@mailinator.test', '070 000 1111', 'AE', 'OTHER', ['EMB-SVC'], 'spam', 'Buy followers and cheap attestation. WhatsApp only.', -3);
        $this->openRequest('Kumari Alwis', 'kumari.alwis@gmail.com', '077 221 6508', 'AU', 'MARRIAGE', ['MOFA-ATT'], 'closed', 'Decided to submit the marriage certificate herself.', -12);

        $this->case($customers['nimal_kandy'], [
            'country' => 'AE', 'type' => 'DEGREE', 'title' => 'BSc (Hons) Computer Science — University of Peradeniya',
            'summary' => 'Dubai employment file. Holder name matches the passport. Needs translation, MOFA, then UAE embassy.',
            'status' => 'quotation_prepared', 'priority' => 'normal', 'assignee' => $dilani, 'by' => $dilani,
            'source' => 'admin', 'opened' => -6, 'services' => [
                ['TRANS', 1, '3500.00'], ['MOFA-ATT', 1, '3500.00'], ['EMB-LEG', 1, '12000.00'],
            ],
            'quote' => ['status' => 'draft', 'discount' => '0.00', 'daysAgo' => 1],
            'notes' => 'Draft quote ready. Dilani to call before sending — customer asked about courier to Kandy.',
            'customer_notes' => 'Please WhatsApp me when the quotation is ready.',
            'history' => ['new_request', 'under_review', 'documents_received', 'quotation_prepared'],
        ]);

        $this->case($customers['ishara'], [
            'country' => 'AE', 'type' => 'DEGREE', 'title' => 'Bachelor of Business Administration — University of Colombo',
            'summary' => 'UAE residence visa. Original degree plus transcript in one envelope.',
            'status' => 'awaiting_confirmation', 'priority' => 'high', 'assignee' => $dilani, 'by' => $sanduni,
            'source' => 'quote_request', 'opened' => -15, 'services' => [
                ['TRANS', 1, '3500.00'], ['MOFA-ATT', 1, '3500.00'], ['EMB-LEG', 1, '12000.00'], ['COURIER', 1, '1500.00'],
            ],
            'quote' => ['status' => 'sent', 'discount' => '500.00', 'daysAgo' => 4, 'sent' => true],
            'notes' => 'Sent with a Rs. 500 courtesy discount. Valid until the date on the quotation.',
            'history' => ['new_request', 'documents_received', 'quotation_prepared', 'quotation_sent', 'awaiting_confirmation'],
        ]);

        $this->case($customers['fathima'], [
            'country' => 'QA', 'type' => 'DEGREE', 'title' => 'MBBS — University of Kelaniya',
            'summary' => 'Qatar medical council. Embassy fee confirmed with the Qatar Embassy in Colombo.',
            'status' => 'awaiting_payment', 'priority' => 'high', 'assignee' => $sanduni, 'by' => $sanduni,
            'source' => 'admin', 'opened' => -12, 'services' => [
                ['TRANS', 1, '4500.00'], ['MOFA-ATT', 1, '3500.00'], ['EMB-LEG', 1, '14500.00'],
            ],
            'quote' => ['status' => 'converted', 'discount' => '0.00', 'daysAgo' => 8, 'sent' => true, 'accepted' => true],
            'invoice' => ['status' => 'sent', 'paid' => '0.00', 'dueDays' => -3, 'sent' => true],
            'notes' => 'Invoice emailed. Customer said salary lands on the 18th.',
            'history' => ['new_request', 'documents_received', 'quotation_sent', 'confirmed', 'invoice_generated', 'awaiting_payment'],
        ]);

        $this->case($customers['dinesh'], [
            'country' => 'KW', 'type' => 'POLICE', 'title' => 'Police clearance certificate — issued March 2026',
            'summary' => 'Kuwait family visa. Clearance is still within the usual six-month window.',
            'status' => 'awaiting_payment', 'priority' => 'normal', 'assignee' => $sanduni, 'by' => $sanduni,
            'source' => 'portal', 'opened' => -20, 'services' => [
                ['MOFA-ATT', 1, '3500.00'], ['EMB-LEG', 1, '12000.00'],
            ],
            'quote' => ['status' => 'converted', 'discount' => '0.00', 'daysAgo' => 14, 'sent' => true, 'accepted' => true],
            'invoice' => ['status' => 'partially_paid', 'paid' => '8000.00', 'dueDays' => 4, 'sent' => true],
            'payment' => ['amount' => '8000.00', 'method' => 'bank_transfer', 'reference' => 'HNB-TT-8842193', 'daysAgo' => 2],
            'notes' => 'Part payment received at HNB. Balance to be settled before embassy submission.',
            'history' => ['new_request', 'documents_received', 'quotation_sent', 'confirmed', 'invoice_generated', 'awaiting_payment'],
        ]);

        $this->case($customers['mevan'], [
            'country' => 'AE', 'type' => 'MARRIAGE', 'title' => 'Marriage certificate — Registrar General, Galle',
            'summary' => 'Spouse sponsorship in Abu Dhabi. Names checked against both passports.',
            'status' => 'mofa_processing', 'priority' => 'normal', 'assignee' => $ruwan, 'by' => $ruwan,
            'source' => 'admin', 'opened' => -18, 'services' => [
                ['DOC-ATT', 1, '4500.00'], ['MOFA-ATT', 1, '3500.00'], ['EMB-LEG', 1, '12000.00'],
            ],
            'quote' => ['status' => 'converted', 'discount' => '0.00', 'daysAgo' => 16, 'sent' => true, 'accepted' => true],
            'invoice' => ['status' => 'paid', 'paid' => 'full', 'dueDays' => 10, 'sent' => true],
            'payment' => ['amount' => 'full', 'method' => 'bank_transfer', 'reference' => 'SAMPATH-20260928-4412', 'daysAgo' => 11],
            'mofa' => ['status' => 'processing', 'reference' => 'MFA/ATTEST/2026/18421', 'submitted' => -6, 'expected' => 2],
            'docs' => [
                ['customer', 'certificate', 'marriage-certificate-galle.pdf', 1],
                ['customer', 'passport', 'passport-bio-mevan.pdf', 1],
                ['internal', 'mofa', 'mofa-submission-slip.pdf', 0],
            ],
            'notes' => 'MOFA counter accepted the file on the first visit. Expected return noted on the slip.',
            'history' => ['new_request', 'documents_received', 'quotation_sent', 'confirmed', 'payment_received', 'submitted_to_mofa', 'mofa_processing'],
        ]);

        $this->case($customers['priya'], [
            'country' => 'CA', 'type' => 'BIRTH', 'title' => 'Birth certificate — Jaffna',
            'summary' => 'Canadian study permit. Apostille path confirmed with the receiving university.',
            'status' => 'translation_processing', 'priority' => 'normal', 'assignee' => $ruwan, 'by' => $dilani,
            'source' => 'portal', 'opened' => -9, 'services' => [
                ['TRANS', 1, '3500.00'], ['APOS', 1, '8500.00'],
            ],
            'quote' => ['status' => 'converted', 'discount' => '0.00', 'daysAgo' => 7, 'sent' => true, 'accepted' => true],
            'invoice' => ['status' => 'paid', 'paid' => 'full', 'dueDays' => 5, 'sent' => true],
            'payment' => ['amount' => 'full', 'method' => 'online', 'reference' => 'PAYHERE-9F21C', 'daysAgo' => 6],
            'docs' => [
                ['customer', 'certificate', 'birth-certificate-jaffna.pdf', 1],
            ],
            'notes' => 'Translator has the Tamil original. English certificate expected tomorrow.',
            'history' => ['new_request', 'documents_received', 'quotation_sent', 'payment_received', 'translation_processing'],
        ]);

        $this->case($customers['asela'], [
            'country' => 'QA', 'type' => 'DEGREE', 'title' => 'National Diploma in Engineering — NDT',
            'summary' => 'Qatar construction site. Embassy appointment booked.',
            'status' => 'embassy_processing', 'priority' => 'high', 'assignee' => $ruwan, 'by' => $ruwan,
            'source' => 'admin', 'opened' => -28, 'services' => [
                ['MOFA-ATT', 1, '3500.00'], ['EMB-LEG', 1, '13500.00'], ['COURIER', 1, '1500.00'],
            ],
            'quote' => ['status' => 'converted', 'discount' => '0.00', 'daysAgo' => 24, 'sent' => true, 'accepted' => true],
            'invoice' => ['status' => 'paid', 'paid' => 'full', 'dueDays' => 20, 'sent' => true],
            'payment' => ['amount' => 'full', 'method' => 'cash', 'reference' => 'CASH-DESK-1044', 'daysAgo' => 22],
            'mofa' => ['status' => 'completed', 'reference' => 'MFA/ATTEST/2026/17102', 'submitted' => -18, 'expected' => -14, 'completed' => -12],
            'embassy' => ['status' => 'processing', 'name' => 'Embassy of the State of Qatar, Colombo', 'reference' => 'QAT-COL-55281', 'submitted' => -8, 'expected' => 3],
            'docs' => [
                ['customer', 'certificate', 'ndt-diploma.pdf', 1],
                ['completed', 'mofa', 'mofa-attested-ndt.pdf', 1],
                ['internal', 'embassy', 'qatar-embassy-receipt.pdf', 0],
            ],
            'notes' => 'MOFA stamp is clean. Embassy asked for a colour copy of the passport, already attached.',
            'history' => ['documents_received', 'payment_received', 'mofa_processing', 'mofa_completed', 'submitted_to_embassy', 'embassy_processing'],
        ]);

        $this->case($customers['nuwan'], [
            'country' => 'GB', 'type' => 'DEGREE', 'title' => 'LLB — Open University of Sri Lanka',
            'summary' => 'UK solicitor qualification check. Apostille accepted by the SRA.',
            'status' => 'ready_for_collection', 'priority' => 'normal', 'assignee' => $ruwan, 'by' => $ruwan,
            'source' => 'portal', 'opened' => -22, 'services' => [
                ['APOS', 1, '9500.00'], ['COURIER', 1, '1800.00'],
            ],
            'quote' => ['status' => 'converted', 'discount' => '0.00', 'daysAgo' => 20, 'sent' => true, 'accepted' => true],
            'invoice' => ['status' => 'paid', 'paid' => 'full', 'dueDays' => 16, 'sent' => true],
            'payment' => ['amount' => 'full', 'method' => 'card', 'reference' => 'VISA-4412', 'daysAgo' => 18],
            'docs' => [
                ['customer', 'certificate', 'llb-certificate.pdf', 1],
                ['completed', 'certificate', 'apostille-llb.pdf', 0],
            ],
            'notes' => 'Apostille is back. Hold release until Nuwan confirms the courier address in Mount Lavinia.',
            'history' => ['documents_received', 'payment_received', 'verification', 'ready_for_collection'],
        ]);

        $this->case($customers['sachini'], [
            'country' => 'AU', 'type' => 'BIRTH', 'title' => 'Birth certificate — Matara',
            'summary' => 'Australian partner visa. Completed and downloaded by the customer.',
            'status' => 'completed', 'priority' => 'normal', 'assignee' => $dilani, 'by' => $dilani,
            'source' => 'portal', 'opened' => -40, 'completed' => -4, 'services' => [
                ['DOC-ATT', 1, '4500.00'], ['MOFA-ATT', 1, '3500.00'],
            ],
            'quote' => ['status' => 'converted', 'discount' => '0.00', 'daysAgo' => 36, 'sent' => true, 'accepted' => true],
            'invoice' => ['status' => 'paid', 'paid' => 'full', 'dueDays' => 30, 'sent' => true],
            'payment' => ['amount' => 'full', 'method' => 'bank_transfer', 'reference' => 'BOC-992811', 'daysAgo' => 34],
            'mofa' => ['status' => 'completed', 'reference' => 'MFA/ATTEST/2026/16044', 'submitted' => -24, 'expected' => -20, 'completed' => -18],
            'docs' => [
                ['customer', 'certificate', 'birth-certificate-matara.pdf', 1],
                ['completed', 'mofa', 'mofa-attested-birth.pdf', 1],
            ],
            'notes' => 'Customer collected a scan from the portal and confirmed the original can be couriered.',
            'history' => ['documents_received', 'payment_received', 'mofa_completed', 'ready_for_delivery', 'completed'],
        ]);

        $this->case($customers['harsha'], [
            'country' => 'AE', 'type' => 'OTHER', 'title' => 'Experience letter — John Keells Holdings',
            'summary' => 'Customer paused the file after the employer asked for a fresh letter.',
            'status' => 'cancelled', 'priority' => 'low', 'assignee' => $tharushi, 'by' => $tharushi,
            'source' => 'admin', 'opened' => -11, 'cancelled' => -2, 'services' => [
                ['MOFA-ATT', 1, '3500.00'],
            ],
            'quote' => ['status' => 'rejected', 'discount' => '0.00', 'daysAgo' => 8, 'sent' => true, 'rejected' => 'Employer will reissue the letter next month.'],
            'notes' => 'Closed without an invoice. Reopen if the new letter arrives.',
            'history' => ['new_request', 'quotation_sent', 'cancelled'],
        ]);

        $this->case($customers['amal'], [
            'country' => 'AE', 'type' => 'DEGREE', 'title' => 'BSc Management — University of Sri Jayewardenepura',
            'summary' => 'Second file for the same customer. Transcript only, degree already attested last year.',
            'status' => 'awaiting_documents', 'priority' => 'normal', 'assignee' => $tharushi, 'by' => $tharushi,
            'source' => 'support', 'opened' => -3, 'services' => [
                ['MOFA-ATT', 1, '3500.00'], ['EMB-LEG', 1, '12000.00'],
            ],
            'notes' => 'Waiting for a colour scan of the transcript. Passport copy is already on the earlier case.',
            'customer_notes' => 'I will upload the transcript tonight.',
            'history' => ['new_request', 'awaiting_documents'],
        ]);

        $this->case($customers['chamodi'], [
            'country' => 'KW', 'type' => 'MARRIAGE', 'title' => 'Marriage certificate — Kadawatha',
            'summary' => 'Kuwait family visa. No portal account. Husband will pay at the counter.',
            'status' => 'documents_received', 'priority' => 'normal', 'assignee' => $dilani, 'by' => $dilani,
            'source' => 'walk_in', 'opened' => -2, 'services' => [
                ['TRANS', 1, '3500.00'], ['MOFA-ATT', 1, '3500.00'], ['EMB-LEG', 1, '12000.00'],
            ],
            'docs' => [
                ['customer', 'certificate', 'marriage-certificate-kadawatha.pdf', 1],
                ['customer', 'passport', 'passport-chamodi.pdf', 1],
            ],
            'notes' => 'Originals received at the front desk and logged in the safe.',
            'history' => ['new_request', 'documents_received'],
        ]);

        $this->messages($customers);
        $this->auditAndMail();
    }

    private function openRequest(string $name, string $email, string $phone, string $country, string $type, array $services, string $status, string $message, int $days): void
    {
        $id = $this->db->insert(
            'INSERT INTO quote_requests (name, email, phone, whatsapp, country_id, document_type_id, document_count, message, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
            [$name, $email, $phone, $phone, $this->country[$country], $this->docType[$type], $message, $status, $this->at($days . ' days')]
        );
        foreach ($services as $code) {
            $this->db->run('INSERT INTO quote_request_services (quote_request_id, service_id) VALUES (?, ?)', [$id, $this->service[$code]]);
        }
    }

    /** @param array<string, int> $customers */
    private function quoteRequest(array $customers, string $name, string $email, string $phone, string $country, string $type, array $services, string $status, string $message, int $days): int
    {
        $id = $this->db->insert(
            'INSERT INTO quote_requests (name, email, phone, whatsapp, country_id, document_type_id, document_count, message, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
            [$name, $email, $phone, $phone, $this->country[$country], $this->docType[$type], $message, $status, $this->at($days . ' days')]
        );
        foreach ($services as $code) {
            $this->db->run('INSERT INTO quote_request_services (quote_request_id, service_id) VALUES (?, ?)', [$id, $this->service[$code]]);
        }

        return $id;
    }

    /** @param array<string, mixed> $spec */
    private function case(int $customerId, array $spec): void
    {
        $customer = $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$customerId]);
        $opened = $this->at(((int) $spec['opened']) . ' days 10:20');
        $caseId = $this->db->insert(
            'INSERT INTO cases (number, customer_id, country_id, document_type_id, document_title, summary, status_id, priority, assigned_to, source, estimated_next, internal_notes, customer_notes, currency, created_by, completed_at, cancelled_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'LKR\', ?, ?, ?, ?)',
            [
                $this->numbers->next('case', 'ATT'),
                $customerId,
                $this->country[$spec['country']],
                $this->docType[$spec['type']],
                $spec['title'],
                $spec['summary'],
                $this->status[$spec['status']],
                $spec['priority'],
                $spec['assignee'],
                $spec['source'],
                $spec['notes'] ?? null,
                $spec['notes'] ?? null,
                $spec['customer_notes'] ?? null,
                $spec['by'],
                isset($spec['completed']) ? $this->at(((int) $spec['completed']) . ' days 16:00') : null,
                isset($spec['cancelled']) ? $this->at(((int) $spec['cancelled']) . ' days 11:30') : null,
                $opened,
            ]
        );

        $lines = [];
        foreach ($spec['services'] as [$code, $qty, $price]) {
            $service = $this->db->fetch('SELECT pricing_type FROM services WHERE code = ?', [$code]);
            $this->db->insert(
                'INSERT INTO case_services (case_id, service_id, quantity, pricing_type, unit_price, currency) VALUES (?, ?, ?, ?, ?, \'LKR\')',
                [$caseId, $this->service[$code], $qty, $service['pricing_type'], $price]
            );
            $lines[] = [$code, (string) $qty, (string) $price];
        }

        $day = (int) $spec['opened'];
        foreach ($spec['history'] as $code) {
            $day += 1;
            $this->db->insert(
                'INSERT INTO case_status_history (case_id, status_id, staff_id, notes, customer_visible, created_at) VALUES (?, ?, ?, ?, 1, ?)',
                [$caseId, $this->status[$code], $spec['by'], $this->historyNote($code), $this->at($day . ' days 11:05')]
            );
        }

        $quotationId = null;
        if (isset($spec['quote'])) {
            $quotationId = $this->quotation($caseId, $customerId, (int) $customer['user_id'], $lines, $spec['quote'], (int) $spec['by']);
        }
        if (isset($spec['invoice']) && $quotationId) {
            $invoiceId = $this->invoice($caseId, $customerId, $quotationId, $lines, $spec['invoice'], (int) $spec['by']);
            if (isset($spec['payment'])) {
                $this->payment($invoiceId, $caseId, $customerId, $lines, $spec['invoice'], $spec['payment'], (int) $spec['by']);
            }
        }
        if (isset($spec['mofa'])) {
            $this->mofa($caseId, $spec['mofa'], (int) $spec['assignee']);
        }
        if (isset($spec['embassy'])) {
            $this->embassy($caseId, $spec['country'], $spec['embassy'], (int) $spec['assignee']);
        }
        foreach ($spec['docs'] ?? [] as [$category, $sub, $name, $visible]) {
            $this->document($caseId, $category, $sub, $name, (bool) $visible, (int) $spec['assignee']);
        }

        if (!empty($customer['user_id'])) {
            $number = (string) $this->db->fetchColumn('SELECT number FROM cases WHERE id = ?', [$caseId]);
            $this->db->insert(
                'INSERT INTO notifications (user_id, title, body, link, read_at, created_at) VALUES (?, ?, ?, ?, ?, ?)',
                [
                    (int) $customer['user_id'],
                    'Tracking code ' . $number,
                    'Your document tracking code is ' . $number . '. Use it any time to follow this file.',
                    '/portal/applications/' . $caseId,
                    $spec['status'] === 'completed' ? $this->at('-3 days') : null,
                    $opened,
                ]
            );
        }
    }

    /** @param list<array{0:string,1:string,2:string}> $lines
     *  @param array<string, mixed> $quote
     */
    private function quotation(int $caseId, int $customerId, int $userId, array $lines, array $quote, int $staffId): int
    {
        $subtotal = 0.0;
        foreach ($lines as [, $qty, $price]) {
            $subtotal += (float) $qty * (float) $price;
        }
        $discount = (float) $quote['discount'];
        $total = max(0, $subtotal - $discount);
        $created = $this->at('-' . (int) $quote['daysAgo'] . ' days 14:10');
        $id = $this->db->insert(
            'INSERT INTO quotations (number, case_id, customer_id, version, status, currency, subtotal, discount_amount, tax_rate, tax_amount, additional_amount, total, valid_until, notes, terms, issued_at, sent_at, viewed_at, accepted_at, rejected_at, rejection_reason, accepted_by, created_by, created_at)
             VALUES (?, ?, ?, 1, ?, \'LKR\', ?, ?, 0, 0, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $this->numbers->next('quotation', 'QT'),
                $caseId,
                $customerId,
                $quote['status'],
                $this->money($subtotal),
                $this->money($discount),
                $this->money($total),
                $this->date((14 - (int) $quote['daysAgo']) . ' days'),
                'Government and embassy fees are confirmed for this document only.',
                'This quotation is valid for the period shown. Government and embassy fees can change before submission.',
                in_array($quote['status'], ['draft'], true) ? null : $created,
                !empty($quote['sent']) ? $created : null,
                !empty($quote['sent']) ? $this->at('-' . max(0, (int) $quote['daysAgo'] - 1) . ' days 09:40') : null,
                !empty($quote['accepted']) ? $this->at('-' . max(0, (int) $quote['daysAgo'] - 1) . ' days 18:12') : null,
                isset($quote['rejected']) ? $this->at('-1 days 10:00') : null,
                $quote['rejected'] ?? null,
                !empty($quote['accepted']) && $userId > 0 ? $userId : null,
                $staffId,
                $created,
            ]
        );
        foreach ($lines as [$code, $qty, $price]) {
            $name = (string) $this->db->fetchColumn(
                'SELECT name FROM service_translations WHERE service_id = ? AND locale = \'en\'',
                [$this->service[$code]]
            );
            $line = (float) $qty * (float) $price;
            $this->db->insert(
                'INSERT INTO quotation_items (quotation_id, service_id, description, quantity, unit_price, line_total, pricing_type) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$id, $this->service[$code], $name, $qty, $price, $this->money($line), (float) $price > 0 ? 'fixed' : 'custom']
            );
        }
        $this->db->insert(
            'INSERT INTO quotation_versions (quotation_id, version, snapshot, created_by, created_at) VALUES (?, 1, ?, ?, ?)',
            [$id, json_encode(['total' => $this->money($total), 'status' => $quote['status']], JSON_THROW_ON_ERROR), $staffId, $created]
        );

        return $id;
    }

    /** @param list<array{0:string,1:string,2:string}> $lines
     *  @param array<string, mixed> $invoice
     */
    private function invoice(int $caseId, int $customerId, int $quotationId, array $lines, array $invoice, int $staffId): int
    {
        $subtotal = 0.0;
        foreach ($lines as [, $qty, $price]) {
            $subtotal += (float) $qty * (float) $price;
        }
        $quote = $this->db->fetch('SELECT discount_amount FROM quotations WHERE id = ?', [$quotationId]);
        $discount = (float) ($quote['discount_amount'] ?? 0);
        $total = max(0, $subtotal - $discount);
        $paid = ($invoice['paid'] ?? '0') === 'full' ? $total : (float) $invoice['paid'];
        $issued = $this->at(((int) $invoice['dueDays'] >= 0 ? -((int) $invoice['dueDays'] + 7) : (int) $invoice['dueDays'] - 7) . ' days 15:00');
        $id = $this->db->insert(
            'INSERT INTO invoices (number, case_id, customer_id, quotation_id, status, currency, subtotal, discount_amount, tax_amount, additional_amount, total, amount_paid, issued_at, due_at, sent_at, notes, terms, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, \'LKR\', ?, ?, 0, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $this->numbers->next('invoice', 'INV'),
                $caseId,
                $customerId,
                $quotationId,
                $invoice['status'],
                $this->money($subtotal),
                $this->money($discount),
                $this->money($total),
                $this->money($paid),
                $issued,
                $this->date(((int) $invoice['dueDays']) . ' days'),
                !empty($invoice['sent']) ? $issued : null,
                'Raised from the accepted quotation. No retyping of the service lines.',
                'Please quote the invoice number with your payment. Processing starts after payment is recorded.',
                $staffId,
                $issued,
            ]
        );
        foreach ($lines as [$code, $qty, $price]) {
            $name = (string) $this->db->fetchColumn(
                'SELECT name FROM service_translations WHERE service_id = ? AND locale = \'en\'',
                [$this->service[$code]]
            );
            $this->db->insert(
                'INSERT INTO invoice_items (invoice_id, service_id, description, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)',
                [$id, $this->service[$code], $name, $qty, $price, $this->money((float) $qty * (float) $price)]
            );
        }

        return $id;
    }

    /** @param list<array{0:string,1:string,2:string}> $lines
     *  @param array<string, mixed> $invoice
     *  @param array<string, mixed> $payment
     */
    private function payment(int $invoiceId, int $caseId, int $customerId, array $lines, array $invoice, array $payment, int $staffId): void
    {
        $amount = $payment['amount'] === 'full'
            ? (float) $this->db->fetchColumn('SELECT total FROM invoices WHERE id = ?', [$invoiceId])
            : (float) $payment['amount'];
        $this->db->insert(
            'INSERT INTO payments (number, invoice_id, case_id, customer_id, amount, currency, method, status, paid_at, reference, notes, recorded_by, created_at)
             VALUES (?, ?, ?, ?, ?, \'LKR\', ?, \'completed\', ?, ?, ?, ?, ?)',
            [
                $this->numbers->next('payment', 'PAY'),
                $invoiceId,
                $caseId,
                $customerId,
                $this->money($amount),
                $payment['method'],
                $this->date('-' . (int) $payment['daysAgo'] . ' days'),
                $payment['reference'],
                'Recorded from the bank advice / counter receipt.',
                $staffId,
                $this->at('-' . (int) $payment['daysAgo'] . ' days 16:20'),
            ]
        );
    }

    /** @param array<string, mixed> $mofa */
    private function mofa(int $caseId, array $mofa, int $staffId): void
    {
        $this->db->insert(
            'INSERT INTO mofa_processes (case_id, reference_no, submitted_at, expected_at, completed_at, status, notes, updated_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $caseId,
                $mofa['reference'],
                $this->date(((int) $mofa['submitted']) . ' days'),
                $this->date(((int) $mofa['expected']) . ' days'),
                isset($mofa['completed']) ? $this->date(((int) $mofa['completed']) . ' days') : null,
                $mofa['status'],
                'Submitted at the Ministry of Foreign Affairs, Colombo. Holder copy kept with the file.',
                $staffId,
                $this->at(((int) $mofa['submitted']) . ' days 09:30'),
            ]
        );
    }

    /** @param array<string, mixed> $embassy */
    private function embassy(int $caseId, string $country, array $embassy, int $staffId): void
    {
        $this->db->insert(
            'INSERT INTO embassy_processes (case_id, embassy_name, country_id, reference_no, submitted_at, expected_at, completed_at, status, notes, updated_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $caseId,
                $embassy['name'],
                $this->country[$country],
                $embassy['reference'],
                $this->date(((int) $embassy['submitted']) . ' days'),
                $this->date(((int) $embassy['expected']) . ' days'),
                isset($embassy['completed']) ? $this->date(((int) $embassy['completed']) . ' days') : null,
                $embassy['status'],
                'Appointment attended by processing staff. Receipt filed with the case.',
                $staffId,
                $this->at(((int) $embassy['submitted']) . ' days 08:50'),
            ]
        );
    }

    private function document(int $caseId, string $category, string $subcategory, string $name, bool $visible, int $staffId): void
    {
        $folder = 'case-' . $caseId;
        $stored = bin2hex(random_bytes(8)) . '.pdf';
        $relative = $folder . '/' . $stored;
        $directory = base_path('storage/documents/' . $folder);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create sample document folder.');
        }
        $bytes = $this->pdf($name);
        file_put_contents($directory . '/' . $stored, $bytes);
        $this->db->insert(
            'INSERT INTO documents (case_id, category, subcategory, original_name, stored_name, mime, size_bytes, description, customer_visible, uploaded_by, created_at)
             VALUES (?, ?, ?, ?, ?, \'application/pdf\', ?, ?, ?, ?, ?)',
            [$caseId, $category, $subcategory, $name, $relative, strlen($bytes), pathinfo($name, PATHINFO_FILENAME), $visible ? 1 : 0, $staffId, $this->at('-2 days 13:15')]
        );
    }

    /** @param array<string, int> $customers */
    private function messages(array $customers): void
    {
        $supportUser = $this->db->fetch('SELECT user_id, name, email, phone FROM customers WHERE id = ?', [$customers['ishara']]);
        $rows = [
            ['Malith Senanayake', 'malith.senanayake@gmail.com', '077 512 8830', 'Police clearance for Qatar', 'I submitted a quote request yesterday. Can someone confirm if the clearance is still valid for the embassy?', 'new', 'guest', null, null, -1],
            ['Anjali Fernando', 'anjali.fernando@yahoo.com', '076 330 4412', 'UK birth certificate', 'Do I need to bring the original to Colombo or is a certified copy enough for the apostille?', 'new', 'guest', null, null, -2],
            [(string) $supportUser['name'], (string) $supportUser['email'], (string) $supportUser['phone'], 'Quotation follow-up', 'I received the quotation for the Colombo BBA. Please confirm the courier charge covers delivery to Colombo 07.', 'contacted', 'support', (int) $supportUser['user_id'], $customers['ishara'], -1],
            ['Priya Selvarajah', 'priya.selvarajah@example.lk', '077 290 6614', 'Translation timing', 'The university asked for the English birth certificate before Friday. Is the translation still on track?', 'new', 'support', (int) $this->db->fetchColumn('SELECT user_id FROM customers WHERE id = ?', [$customers['priya']]), $customers['priya'], 0],
        ];
        foreach ($rows as [$name, $email, $phone, $subject, $message, $status, $source, $userId, $customerId, $days]) {
            $this->db->insert(
                'INSERT INTO contact_messages (name, email, phone, subject, message, status, source, user_id, customer_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$name, $email, $phone, $subject, $message, $status, $source, $userId ?: null, $customerId, $this->at($days . ' days 17:05')]
            );
        }

        $this->db->insert(
            'INSERT INTO notifications (user_id, title, body, link, created_at) VALUES (?, ?, ?, ?, ?)',
            [$this->staff['admin'], 'New quote request', 'Malith Senanayake asked about a Qatar police clearance.', '/admin/quote-requests', $this->at('-1 days 09:12')]
        );
        $this->db->insert(
            'INSERT INTO notifications (user_id, title, body, link, created_at) VALUES (?, ?, ?, ?, ?)',
            [$this->staff['sanduni'], 'Invoice overdue', 'Fathima Rizwana\'s Qatar invoice is past the due date.', '/admin/invoices', $this->at('-1 days 08:05')]
        );
    }

    private function auditAndMail(): void
    {
        $events = [
            [$this->staff['dilani'], 'case_updated', 'case', 'Updated internal notes and assignment.'],
            [$this->staff['sanduni'], 'quotation_emailed', 'quotation', 'Quotation emailed to the customer.'],
            [$this->staff['sanduni'], 'invoice_created', 'invoice', 'Invoice created from the accepted quotation.'],
            [$this->staff['sanduni'], 'payment_recorded', 'payment', 'Bank transfer matched to the invoice.'],
            [$this->staff['ruwan'], 'status_changed', 'case', 'Moved the file to MOFA processing.'],
            [$this->staff['tharushi'], 'message_updated', 'contact', 'Marked a guest enquiry as contacted.'],
        ];
        $i = 6;
        foreach ($events as [$userId, $action, $entity, $note]) {
            $this->db->insert(
                'INSERT INTO audit_logs (user_id, action, entity, metadata, ip, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $userId,
                    $action,
                    $entity,
                    json_encode(['note' => $note], JSON_THROW_ON_ERROR),
                    '112.134.88.' . (20 + $i),
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/129.0.0.0',
                    $this->at('-' . $i . ' days 10:' . str_pad((string) (10 + $i), 2, '0', STR_PAD_LEFT)),
                ]
            );
            $i--;
        }

        $this->db->insert(
            'INSERT INTO email_logs (recipient, subject, template, status, related_type, created_at, sent_at) VALUES (?, ?, ?, \'sent\', ?, ?, ?)',
            ['ishara.gunasekara@example.lk', 'Quotation for your UAE degree file', 'quotation', 'quotation', $this->at('-4 days 14:12'), $this->at('-4 days 14:12')]
        );
        $this->db->insert(
            'INSERT INTO email_logs (recipient, subject, template, status, error_message, related_type, created_at) VALUES (?, ?, ?, \'failed\', ?, ?, ?)',
            ['fathima.rizwana@example.lk', 'Invoice for MBBS attestation', 'invoice', 'SMTP is not configured. Message saved to the mail log.', 'invoice', $this->at('-3 days 15:02')]
        );
    }

    private function historyNote(string $code): string
    {
        return match ($code) {
            'new_request' => 'Request logged and given a tracking number.',
            'under_review' => 'Checked the destination and the document type.',
            'awaiting_documents' => 'Asked the customer for a clearer scan.',
            'documents_received' => 'Scans checked against the holder name.',
            'quotation_prepared' => 'Quotation drafted from the selected services.',
            'quotation_sent' => 'Quotation emailed to the customer.',
            'awaiting_confirmation' => 'Waiting for the customer to accept.',
            'confirmed' => 'Customer accepted the quotation.',
            'invoice_generated' => 'Invoice raised from the accepted quotation.',
            'awaiting_payment' => 'Invoice sent. Payment not yet complete.',
            'payment_received' => 'Payment matched to the invoice.',
            'submitted_to_mofa' => 'File handed in at MOFA.',
            'mofa_processing' => 'MOFA reference recorded.',
            'mofa_completed' => 'MOFA attested copy received.',
            'submitted_to_embassy' => 'Embassy appointment attended.',
            'embassy_processing' => 'Embassy receipt filed.',
            'translation_processing' => 'Sent to the translator.',
            'ready_for_collection' => 'Completed file is in the office.',
            'ready_for_delivery' => 'Ready for courier or portal download.',
            'completed' => 'Customer confirmed receipt.',
            'cancelled' => 'Customer asked to stop this file.',
            default => 'Status updated.',
        };
    }

    private function pdf(string $title): string
    {
        $safe = preg_replace('/[^\x20-\x7E]/', '', $title) ?: 'AtteCert sample';

        return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 420 200]/Contents 4 0 R>>endobj\n4 0 obj<</Length 68>>stream\nBT /F1 12 Tf 40 120 Td (" . $safe . ") Tj ET\nendstream\nendobj\ntrailer<</Root 1 0 R>>\n%%EOF";
    }

    private function at(string $modify): string
    {
        return (new DateTimeImmutable('now', $this->zone))->modify($modify)->format('Y-m-d H:i:s');
    }

    private function date(string $modify): string
    {
        return (new DateTimeImmutable('today', $this->zone))->modify($modify)->format('Y-m-d');
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
