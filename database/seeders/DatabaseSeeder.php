<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;
use App\Services\NumberService;

final class DatabaseSeeder
{
    public function __construct(private Database $db)
    {
    }

    public function run(): void
    {
        if ($this->db->fetch('SELECT id FROM roles LIMIT 1')) {
            echo "Seed skipped: data already exists.\n";

            return;
        }
        $this->roles();
        $this->settings();
        $this->statuses();
        $this->services();
        $this->countries();
        $this->documentTypes();
        $this->content();
        $this->faqs();
        $adminId = $this->users();
        $this->articles($adminId);
        $this->demoCase($adminId);
        echo "Seed complete.\n";
    }

    private function roles(): void
    {
        $permissions = [
            'customers.view' => 'View customers', 'customers.edit' => 'Edit customers',
            'cases.view' => 'View cases', 'cases.create' => 'Create cases', 'cases.edit' => 'Edit cases', 'cases.status_update' => 'Update case status',
            'documents.upload' => 'Upload documents', 'documents.download' => 'Download documents', 'documents.delete' => 'Archive documents',
            'quotations.create' => 'Create quotations', 'quotations.approve' => 'Approve discounts and issue quotations', 'quotations.send' => 'Email quotations',
            'invoices.create' => 'Create invoices', 'invoices.send' => 'Email invoices', 'invoices.cancel' => 'Cancel invoices',
            'payments.create' => 'Record payments', 'payments.view' => 'View payments',
            'services.manage' => 'Manage services', 'countries.manage' => 'Manage countries', 'content.manage' => 'Manage content',
            'translations.manage' => 'Manage translations', 'users.manage' => 'Manage staff', 'settings.manage' => 'Manage settings',
            'reports.view' => 'View reports', 'audit.view' => 'View audit log', 'quotes.manage' => 'Manage quote requests',
        ];
        $permissionIds = [];
        foreach ($permissions as $code => $name) {
            $permissionIds[$code] = $this->db->insert('INSERT INTO permissions (code, name) VALUES (?, ?)', [$code, $name]);
        }
        $roles = [
            'super_admin' => 'Super admin', 'admin' => 'Admin', 'manager' => 'Manager',
            'processing_staff' => 'Processing staff', 'finance_staff' => 'Finance staff',
            'content_editor' => 'Content editor', 'customer_support' => 'Customer support', 'customer' => 'Customer',
        ];
        $roleIds = [];
        foreach ($roles as $code => $name) {
            $roleIds[$code] = $this->db->insert('INSERT INTO roles (code, name) VALUES (?, ?)', [$code, $name]);
        }
        $map = [
            'super_admin' => array_keys($permissions),
            'admin' => array_values(array_filter(array_keys($permissions), static fn ($code) => $code !== 'settings.manage')),
            'manager' => ['customers.view', 'customers.edit', 'cases.view', 'cases.create', 'cases.edit', 'cases.status_update', 'documents.upload', 'documents.download', 'quotations.create', 'quotations.approve', 'quotations.send', 'invoices.create', 'invoices.send', 'payments.view', 'quotes.manage', 'reports.view'],
            'processing_staff' => ['customers.view', 'cases.view', 'cases.edit', 'cases.status_update', 'documents.upload', 'documents.download'],
            'finance_staff' => ['customers.view', 'cases.view', 'quotations.create', 'quotations.approve', 'quotations.send', 'invoices.create', 'invoices.send', 'invoices.cancel', 'payments.create', 'payments.view', 'reports.view', 'documents.download'],
            'content_editor' => ['content.manage', 'translations.manage', 'countries.manage'],
            'customer_support' => ['customers.view', 'customers.edit', 'cases.view', 'cases.create', 'quotes.manage', 'documents.download'],
            'customer' => [],
        ];
        foreach ($map as $role => $codes) {
            foreach ($codes as $code) {
                $this->db->run('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$roleIds[$role], $permissionIds[$code]]);
            }
        }
    }

    private function settings(): void
    {
        $values = [
            'company_name' => 'AtteCert',
            'email' => 'hello@attecert.lk',
            'phone' => '+94 11 000 0000',
            'whatsapp' => '94710000000',
            'address' => 'Colombo, Sri Lanka',
            'hours' => 'Monday to Friday, 9:00 to 17:00',
            'map_url' => 'https://maps.google.com/?q=Colombo',
            'registration' => '',
            'currency' => 'LKR',
            'quote_validity_days' => '14',
            'invoice_due_days' => '7',
            'quote_terms' => 'This quotation is valid for the period shown. Government and embassy fees can change before submission.',
            'invoice_terms' => 'Please quote the invoice number with your payment. Processing starts after payment is recorded.',
            'maintenance_mode' => '0',
            'email_verification' => '0',
            'upload_max_mb' => '10',
            'facebook' => '',
            'instagram' => '',
            'linkedin' => '',
        ];
        foreach ($values as $key => $value) {
            $this->db->run('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)', [$key, $value]);
        }
    }

    private function statuses(): void
    {
        $rows = [
            ['new_request', 'New request', 'නව ඉල්ලීම', 'புதிய கோரிக்கை', 10, 1, 0, 'neutral'],
            ['under_review', 'Under review', 'සමාලෝචනය යටතේ', 'மதிப்பாய்வில்', 20, 1, 0, 'warning'],
            ['awaiting_documents', 'Awaiting documents', 'ලේඛන බලාපොරොත්තුවෙන්', 'ஆவணங்களுக்காக காத்திருப்பு', 30, 1, 0, 'warning'],
            ['documents_received', 'Documents received', 'ලේඛන ලැබුණි', 'ஆவணங்கள் பெறப்பட்டன', 40, 1, 0, 'neutral'],
            ['quotation_prepared', 'Quotation prepared', 'මිල ගණන් සූදානම්', 'மதிப்பீடு தயார்', 50, 1, 0, 'neutral'],
            ['quotation_sent', 'Quotation sent', 'මිල ගණන් යවන ලදි', 'மதிப்பீடு அனுப்பப்பட்டது', 60, 1, 0, 'neutral'],
            ['awaiting_confirmation', 'Awaiting confirmation', 'තහවුරු කිරීම බලාපොරොත්තුවෙන්', 'உறுதிக்காக காத்திருப்பு', 70, 1, 0, 'warning'],
            ['confirmed', 'Confirmed', 'තහවුරු විය', 'உறுதி செய்யப்பட்டது', 80, 1, 0, 'success'],
            ['invoice_generated', 'Invoice generated', 'ඉන්වොයිසිය සූදානම්', 'விலைப்பட்டியல் உருவாக்கப்பட்டது', 90, 1, 0, 'neutral'],
            ['awaiting_payment', 'Awaiting payment', 'ගෙවීම බලාපොරොත්තුවෙන්', 'கட்டணத்துக்காக காத்திருப்பு', 100, 1, 0, 'warning'],
            ['payment_received', 'Payment received', 'ගෙවීම ලැබුණි', 'கட்டணம் பெறப்பட்டது', 110, 1, 0, 'success'],
            ['verification', 'Verification', 'සත්‍යාපනය', 'சரிபார்ப்பு', 120, 1, 0, 'warning'],
            ['submitted_to_mofa', 'Submitted to MOFA', 'විදේශ අමාත්‍යාංශයට භාර දුන්නා', 'MOFA-விடம் சமர்ப்பிக்கப்பட்டது', 130, 1, 0, 'warning'],
            ['mofa_processing', 'MOFA processing', 'විදේශ අමාත්‍යාංශ සැකසීම', 'MOFA செயலாக்கம்', 140, 1, 0, 'warning'],
            ['mofa_completed', 'MOFA completed', 'විදේශ අමාත්‍යාංශ කටයුතු නිමා', 'MOFA முடிந்தது', 150, 1, 0, 'success'],
            ['submitted_to_embassy', 'Submitted to embassy', 'තානාපති කාර්යාලයට භාර දුන්නා', 'தூதரகத்தில் சமர்ப்பிக்கப்பட்டது', 160, 1, 0, 'warning'],
            ['embassy_processing', 'Embassy processing', 'තානාපති කාර්යාල සැකසීම', 'தூதரக செயலாக்கம்', 170, 1, 0, 'warning'],
            ['embassy_completed', 'Embassy completed', 'තානාපති කාර්යාල කටයුතු නිමා', 'தூதரக பணி முடிந்தது', 180, 1, 0, 'success'],
            ['translation_processing', 'Translation processing', 'පරිවර්තනය සිදු වෙමින්', 'மொழிபெயர்ப்பு நடைபெறுகிறது', 190, 1, 0, 'warning'],
            ['ready_for_collection', 'Ready for collection', 'රැගෙන යාමට සූදානම්', 'பெற தயார்', 200, 1, 0, 'success'],
            ['ready_for_delivery', 'Ready for delivery', 'භාරදීමට සූදානම්', 'வழங்க தயார்', 210, 1, 0, 'success'],
            ['completed', 'Completed', 'නිමයි', 'நிறைவடைந்தது', 220, 1, 1, 'success'],
            ['cancelled', 'Cancelled', 'අවලංගුයි', 'ரத்து செய்யப்பட்டது', 230, 1, 1, 'danger'],
        ];
        foreach ($rows as [$code, $en, $si, $ta, $sort, $visible, $terminal, $tone]) {
            $id = $this->db->insert(
                'INSERT INTO workflow_statuses (code, sort_order, customer_visible, is_terminal, badge_tone) VALUES (?, ?, ?, ?, ?)',
                [$code, $sort, $visible, $terminal, $tone]
            );
            foreach (['en' => $en, 'si' => $si, 'ta' => $ta] as $locale => $name) {
                $this->db->run('INSERT INTO workflow_status_translations (status_id, locale, name, description) VALUES (?, ?, ?, ?)', [$id, $locale, $name, $name]);
            }
        }
    }

    private function services(): void
    {
        $items = [
            ['DOC-ATT', 'document-attestation', 'fixed', '4500.00', '3–7 working days', 'attestation', 1, 'certificate', 'Document Attestation', 'ලේඛන සහතික කිරීම', 'ஆவண சான்றிதழ்', 'Authentication so a document can be accepted by the next authority.', 'සහතිකය ඊළඟ අධිකාරිය පිළිගන්නා ලෙස සූදානම් කිරීම.', 'அடுத்த அதிகாரம் ஏற்கும்படி ஆவணத்தை தயார் செய்தல்.'],
            ['TRANS', 'official-translation', 'fixed', '3500.00', '2–5 working days', 'translation', 1, 'translate', 'Official Translation', 'නිල පරිවර්තනය', 'அதிகாரப்பூர்வ மொழிபெயர்ப்பு', 'Certified translation when the receiving authority needs another language.', 'ලබන අධිකාරියට වෙනත් භාෂාවක් අවශ්‍ය විට සහතික පරිවර්තනය.', 'பெறும் அதிகாரத்துக்கு வேறு மொழி தேவைப்பட்டால் சான்றளிக்கப்பட்ட மொழிபெயர்ப்பு.'],
            ['EMB-LEG', 'embassy-legalization', 'fixed', '12000.00', '5–15 working days', 'embassy', 1, 'building', 'Embassy Legalization', 'තානාපති කාර්යාල නීතිගත කිරීම', 'தூதரக சட்டப்பூர்வமாக்கல்', 'Legalization through the destination embassy or high commission.', 'ගමනාන්ත තානාපති කාර්යාලය හරහා නීතිගත කිරීම.', 'சேரும் நாட்டு தூதரகம் வழியாக சட்டப்பூர்வமாக்கல்.'],
            ['MOFA-APP', 'mofa-appointments', 'fixed', '2500.00', '1–5 working days', 'mofa', 1, 'calendar', 'MOFA Appointments', 'විදේශ අමාත්‍යාංශ වේලාවන්', 'MOFA நேரம்', 'Help preparing the Ministry of Foreign Affairs appointment and file.', 'විදේශ අමාත්‍යාංශ වේලාව සහ ගොනුව සූදානම් කිරීම.', 'வெளியுறவு அமைச்சக நேரம் மற்றும் கோப்பை தயார் செய்தல்.'],
            ['APOS', 'apostille', 'custom', null, 'Varies by document', 'apostille', 1, 'stamp', 'Apostille / Legalization', 'ඇපොස්ටිල් / නීතිගත කිරීම', 'அப்போஸ்டில் / சட்டப்பூர்வமாக்கல்', 'The route depends on whether the destination accepts an apostille.', 'ගමනාන්තය ඇපොස්ටිල් භාර ගන්නේද යන්න මත මාර්ගය වෙනස් වේ.', 'சேரும் நாடு அப்போஸ்டில் ஏற்கிறதா என்பதைப் பொறுத்து பாதை மாறும்.'],
            ['EMB-SVC', 'embassy-services', 'custom', null, 'Varies by mission', 'embassy', 1, 'globe', 'Embassy Services', 'තානාපති සේවා', 'தூதரக சேவைகள்', 'Mission-specific document handling beyond a standard legalization.', 'සාමාන්‍ය නීතිගත කිරීමෙන් ඔබ්බට තානාපති කාර්යාල සේවා.', 'சாதாரண சட்டப்பூர்வமாக்கலுக்கு அப்பால் தூதரக ஆவண சேவை.'],
            ['MOFA-ATT', 'mofa-attestation', 'fixed', '3500.00', '3–8 working days', 'mofa', 0, 'stamp', 'MOFA Attestation', 'විදේශ අමාත්‍යාංශ සහතිකය', 'MOFA சான்றிதழ்', 'Attestation at the Ministry of Foreign Affairs.', 'විදේශ අමාත්‍යාංශ සහතික කිරීම.', 'வெளியுறவு அமைச்சக சான்றிதழ்.'],
            ['COURIER', 'courier', 'fixed', '1500.00', '1–3 working days', 'delivery', 0, 'send', 'Courier', 'කුරියර්', 'கூரியர்', 'Delivery of the completed file.', 'සම්පූර්ණ ලේඛනය භාරදීම.', 'முடிந்த கோப்பை வழங்குதல்.'],
        ];
        $order = 1;
        foreach ($items as $item) {
            [$code, $slug, $pricing, $price, $days, $category, $featured, $icon, $en, $si, $ta, $sumEn, $sumSi, $sumTa] = $item;
            $id = $this->db->insert(
                'INSERT INTO services (code, slug, pricing_type, fixed_price, currency, processing_days, category, is_active, is_public, show_price, featured, sort_order, icon) VALUES (?, ?, ?, ?, \'LKR\', ?, ?, 1, 1, ?, ?, ?, ?)',
                [$code, $slug, $pricing, $price, $days, $category, $pricing === 'fixed' ? 1 : 0, $featured, $order++, $icon]
            );
            $copy = [
                'en' => [$en, $sumEn],
                'si' => [$si, $sumSi],
                'ta' => [$ta, $sumTa],
            ];
            foreach ($copy as $locale => [$name, $summary]) {
                $this->db->run(
                    'INSERT INTO service_translations (service_id, locale, name, summary, description, process_text, documents_required, seo_title, meta_description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$id, $locale, $name, $summary, $summary . ' We confirm the exact path before work starts.', "1. Share the document and destination.\n2. We confirm the authority path.\n3. The file is submitted and tracked.\n4. The completed file is returned in your account.", "A clear scan or original as the authority requires.\nIdentification for the holder.\nAny previous stamps already on the document.", $name . ' | AtteCert', $summary]
                );
            }
        }
    }

    private function countries(): void
    {
        $items = [
            ['AE', 'uae', 'UAE', 'එක්සත් අරාබි එමීර් රාජ්‍යය', 'ஐக்கிய அரபு எமிரேட்ஸ்', 'Employment and residence files often need MOFA attestation and UAE embassy legalization.'],
            ['QA', 'qatar', 'Qatar', 'කටාර්', 'கத்தார்', 'Certificates for Qatar commonly follow attestation before the Qatar embassy step.'],
            ['KW', 'kuwait', 'Kuwait', 'කුවේට්', 'குவைத்', 'Kuwait files are checked against the current embassy legalization path.'],
            ['GB', 'united-kingdom', 'United Kingdom', 'එක්සත් රාජධානිය', 'ஐக்கிய இராச்சியம்', 'UK use can require a different legalization or apostille route.'],
            ['CA', 'canada', 'Canada', 'කැනඩාව', 'கனடா', 'Canada accepts a defined authentication path that we confirm per document.'],
            ['AU', 'australia', 'Australia', 'ඕස්ට්‍රේලියාව', 'ஆஸ்திரேலியா', 'Australian authorities may ask for attestation or an apostille depending on the document.'],
        ];
        $order = 1;
        foreach ($items as [$code, $slug, $en, $si, $ta, $summary]) {
            $id = $this->db->insert('INSERT INTO countries (code, slug, is_active, featured, sort_order) VALUES (?, ?, 1, 1, ?)', [$code, $slug, $order++]);
            foreach (['en' => $en, 'si' => $si, 'ta' => $ta] as $locale => $name) {
                $this->db->run(
                    'INSERT INTO country_translations (country_id, locale, name, summary, requirements, documents_required, process_text, notes, processing_info, seo_title, meta_description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $id, $locale, $name, $summary,
                        "Confirm the receiving authority.\nCheck whether an apostille or embassy legalization is required.\nPrepare a clear original or certified copy.",
                        "The document to be attested.\nHolder identification.\nSupporting letters if the authority asks for them.",
                        "Review the document.\nComplete local attestation where required.\nSubmit to MOFA and, where needed, the embassy.\nReturn the completed file.",
                        'Requirements change. AtteCert confirms the current path before processing starts.',
                        'Often 5–15 working days after a complete file is received.',
                        $name . ' document requirements | AtteCert',
                        $summary,
                    ]
                );
            }
            $serviceIds = $this->db->fetchAll('SELECT id FROM services WHERE featured = 1');
            foreach ($serviceIds as $service) {
                $this->db->run('INSERT INTO country_services (country_id, service_id) VALUES (?, ?)', [$id, $service['id']]);
            }
        }
    }

    private function documentTypes(): void
    {
        $types = [
            ['DEGREE', 'Degree certificate', 'උපාධි සහතිකය', 'பட்டச் சான்றிதழ்'],
            ['BIRTH', 'Birth certificate', 'උප්පැන්න සහතිකය', 'பிறப்பு சான்றிதழ்'],
            ['MARRIAGE', 'Marriage certificate', 'විවාහ සහතිකය', 'திருமண சான்றிதழ்'],
            ['POLICE', 'Police clearance', 'පොලිස් වාර්තාව', 'காவல் சான்றிதழ்'],
            ['OTHER', 'Other document', 'වෙනත් ලේඛනය', 'பிற ஆவணம்'],
        ];
        $order = 1;
        foreach ($types as [$code, $en, $si, $ta]) {
            $id = $this->db->insert('INSERT INTO document_types (code, is_active, sort_order) VALUES (?, 1, ?)', [$code, $order++]);
            foreach (['en' => $en, 'si' => $si, 'ta' => $ta] as $locale => $name) {
                $this->db->run('INSERT INTO document_type_translations (document_type_id, locale, name) VALUES (?, ?, ?)', [$id, $locale, $name]);
            }
        }
    }

    private function content(): void
    {
        $blocks = [
            'topbar' => ['Document attestation and legalization', 'ලේඛන සහතික කිරීම සහ නීතිගත කිරීම', 'ஆவண சான்றிதழ் மற்றும் சட்டப்பூர்வமாக்கல்'],
            'footer_blurb' => ['AtteCert helps people in Sri Lanka prepare documents for official use at home and abroad.', 'ශ්‍රී ලංකාවේ සහ විදේශයන්හි නිල භාවිතය සඳහා ලේඛන සූදානම් කිරීමට AtteCert උපකාරී වේ.', 'இலங்கையிலும் வெளிநாட்டிலும் அதிகாரப்பூர்வ பயன்பாட்டுக்கு ஆவணங்களை AtteCert தயார் செய்கிறது.'],
            'hero_eyebrow' => ['Document attestation in Sri Lanka', 'ශ්‍රී ලංකාවේ ලේඛන සහතික කිරීම', 'இலங்கையில் ஆவண சான்றிதழ்'],
            'hero_title' => ['Attecert: Fast, Reliable Document Attestation in Sri Lanka', 'AtteCert: ශ්‍රී ලංකාවේ වේගවත්, විශ්වාසදායක ලේඛන සහතික කිරීම', 'AtteCert: இலங்கையில் விரைவான, நம்பகமான ஆவண சான்றிதழ்'],
            'hero_lead' => ['Send certificates, translations, and supporting papers to one team. We coordinate attestation, MOFA, embassy legalization, and delivery.', 'සහතික, පරිවර්තන, සහාය ලේඛන එක කණ්ඩායමකට එවන්න. සහතික කිරීම, විදේශ අමාත්‍යාංශය, තානාපති කාර්යාලය, සහ භාරදීම අපි සම්බන්ධීකරණය කරමු.', 'சான்றிதழ்கள், மொழிபெயர்ப்புகள், துணை ஆவணங்களை ஒரே குழுவிடம் அனுப்புங்கள். சான்றிதழ், MOFA, தூதரகம், வழங்கல் ஆகியவற்றை ஒருங்கிணைக்கிறோம்.'],
            'trust_1' => ['Clear requirements', 'පැහැදිලි අවශ්‍යතා', 'தெளிவான தேவைகள்'],
            'trust_2' => ['Experienced handling', 'පළපුරුදු හැසිරවීම', 'அனுபவமிக்க கையாளுதல்'],
            'trust_3' => ['Secure document care', 'ආරක්ෂිත ලේඛන රැකවරණය', 'பாதுகாப்பான ஆவண பராமரிப்பு'],
            'care_title' => ['One careful team, every step of the way.', 'සෑම පියවරකදීම එක සැලකිලිමත් කණ්ඩායමක්.', 'ஒவ்வொரு படியிலும் ஒரு கவனமுள்ள குழு.'],
            'care_lead' => ['From the first review to the final stamped document, the same team follows your case and keeps every file on one reference number.', 'පළමු සමාලෝචනයේ සිට අවසන් මුද්‍රාව දක්වා එකම කණ්ඩායම ඔබේ නඩුව ගෙන යයි.', 'முதல் மதிப்பாய்வு முதல் இறுதி முத்திரை வரை ஒரே குழு உங்கள் வழக்கை கையாளும்.'],
            'care_1' => ['Requirements checked before processing starts', 'සැකසීමට පෙර අවශ්‍යතා පරීක්ෂා කරයි', 'செயலாக்கத்துக்கு முன் தேவைகள் சரிபார்க்கப்படும்'],
            'care_2' => ['Fixed prices where a standard fee applies', 'සම්මත ගාස්තුවක් ඇති විට ස්ථිර මිල', 'நிலையான கட்டணம் உள்ள இடங்களில் நிலையான விலை'],
            'care_3' => ['Status updates you can track online', 'මාර්ගගතව බැලිය හැකි තත්ත්ව යාවත්කාලීන', 'இணையத்தில் காணக்கூடிய நிலை புதுப்பிப்பு'],
            'care_4' => ['Completed documents ready to download', 'බාගත කළ හැකි සම්පූර්ණ ලේඛන', 'பதிவிறக்க தயாரான முடிந்த ஆவணங்கள்'],
            'services_eyebrow' => ['Our services', 'අපේ සේවා', 'எங்கள் சேவைகள்'],
            'services_title' => ['Document support for home and abroad', 'දේශීය හා විදේශ ලේඛන සහාය', 'உள்நாட்டு மற்றும் வெளிநாட்டு ஆவண ஆதரவு'],
            'services_lead' => ['Choose the step your document needs. We confirm the exact path for your destination before work begins.', 'ඔබේ ලේඛනයට අවශ්‍ය පියවර තෝරන්න. වැඩ ආරම්භයට පෙර ගමනාන්ත මාර්ගය තහවුරු කරමු.', 'ஆவணத்துக்கு தேவையான படியை தேர்வு செய்க. வேலை தொடங்கும் முன் பாதையை உறுதி செய்கிறோம்.'],
            'process_eyebrow' => ['How it works', 'එය ක්‍රියා කරන ආකාරය', 'இது எப்படி செயல்படுகிறது'],
            'process_title' => ['Four clear steps. No office-to-office guesswork.', 'පැහැදිලි පියවර හතරක්. කාර්යාලයෙන් කාර්යාලයට අනුමාන නැත.', 'நான்கு தெளிவான படிகள். அலுவலகம் அலுவலகமாக ஓட வேண்டாம்.'],
            'process_lead' => ['You send the document. We review it, process the required authorities, and return the completed file.', 'ඔබ ලේඛනය එවයි. අපි සමාලෝචනය කර, අවශ්‍ය අධිකාරීන් හරහා සකසා, සම්පූර්ණ ගොනුව ආපසු දෙමු.', 'நீங்கள் ஆவணத்தை அனுப்புகிறீர்கள். நாங்கள் மதிப்பாய்வு செய்து, அதிகாரிகள் வழியாக செயலாக்கி, முடிந்த கோப்பை தருகிறோம்.'],
            'process_1_title' => ['Send us your documents', 'ලේඛන අප වෙත එවන්න', 'ஆவணங்களை எங்களுக்கு அனுப்புங்கள்'],
            'process_1_text' => ['Share the scan or original and the destination.', 'ස්කෑන් හෝ මුල් පිටපත සහ ගමනාන්තය දෙන්න.', 'ஸ்கேன் அல்லது அசலையும் சேரும் இடத்தையும் பகிருங்கள்.'],
            'process_2_title' => ['Review and confirmation', 'සමාලෝචනය සහ තහවුරු කිරීම', 'மதிப்பாய்வு மற்றும் உறுதி'],
            'process_2_text' => ['We confirm the path, the fee, and any missing papers.', 'මාර්ගය, ගාස්තුව, සහ අඩුපාඩු තහවුරු කරමු.', 'பாதை, கட்டணம், குறையும் ஆவணங்களை உறுதி செய்கிறோம்.'],
            'process_3_title' => ['We process them', 'අපි සකසමු', 'நாங்கள் செயலாக்குகிறோம்'],
            'process_3_text' => ['Attestation, MOFA, translation, or embassy work is tracked on one case.', 'සහතික කිරීම, විදේශ අමාත්‍යාංශය, පරිවර්තනය, තානාපති කාර්යාලය එක නඩුවක ලියැවෙයි.', 'சான்றிதழ், MOFA, மொழிபெயர்ப்பு, தூதரகம் ஒரே வழக்கில் பதிவாகும்.'],
            'process_4_title' => ['We deliver to you', 'ඔබ වෙත භාර දෙමු', 'உங்களிடம் வழங்குகிறோம்'],
            'process_4_text' => ['Collect the file or download it from your account.', 'ගොනුව ලබා ගන්න හෝ ගිණුමෙන් බාගන්න.', 'கோப்பை பெறுங்கள் அல்லது கணக்கிலிருந்து பதிவிறக்குங்கள்.'],
            'track_title' => ['Track your document', 'ඔබේ ලේඛනය සොයන්න', 'ஆவணத்தை கண்காணிக்கவும்'],
            'track_lead' => ['Enter the reference number from your quotation or case.', 'ඔබේ උපුටා දැක්වීමේ හෝ නඩු අංකය ඇතුළත් කරන්න.', 'மதிப்பீடு அல்லது வழக்கு எண்ணை உள்ளிடவும்.'],
            'countries_eyebrow' => ['Destinations', 'ගමනාන්ත', 'சேருமிடங்கள்'],
            'countries_title' => ['Preparing documents for another country?', 'වෙනත් රටක් සඳහා ලේඛන සූදානම් කරනවාද?', 'வேறொரு நாட்டுக்கு ஆவணங்களை தயார் செய்கிறீர்களா?'],
            'countries_lead' => ['See the usual path for a destination. Final requirements are confirmed against your document.', 'ගමනාන්තයේ සුපුරුදු මාර්ගය බලන්න. අවසන් අවශ්‍යතා ඔබේ ලේඛනයට අනුව තහවුරු වේ.', 'ஒரு நாட்டின் வழக்கமான பாதையை காணுங்கள். இறுதி தேவைகள் ஆவணத்துக்கு ஏற்ப உறுதி செய்யப்படும்.'],
            'about_eyebrow' => ['About', 'අප ගැන', 'எங்களை பற்றி'],
            'about_title' => ['Practical support for important documents', 'වැදගත් ලේඛන සඳහා ප්‍රායෝගික සහාය', 'முக்கிய ஆவணங்களுக்கு நடைமுறை ஆதரவு'],
            'about_lead' => ['AtteCert coordinates the steps between you and the authorities, so the file stays with one responsible team.', 'ඔබ සහ අධිකාරීන් අතර පියවර AtteCert සම්බන්ධීකරණය කරයි.', 'உங்களுக்கும் அதிகாரிகளுக்கும் இடையிலான படிகளை AtteCert ஒருங்கிணைக்கிறது.'],
            'about_year' => ['2022', '2022', '2022'],
            'about_year_label' => ['Supporting documents since', 'සිට ලේඛන සහාය', 'முதல் ஆவண ஆதரவு'],
            'stat_1_title' => ['Clear requirements', 'පැහැදිලි අවශ්‍යතා', 'தெளிவான தேவைகள்'],
            'stat_1_text' => ['We say what the authority is likely to ask for before you spend a day in a queue.', 'පෝලිමේ දවසක් ගත කිරීමට පෙර අධිකාරිය ඉල්ලන දේ කියමු.', 'வரிசையில் ஒரு நாள் கழிக்கும் முன் அதிகாரம் என்ன கேட்கும் என்று சொல்கிறோம்.'],
            'stat_2_title' => ['A tracked case', 'ලුහුබැඳිය හැකි නඩුවක්', 'கண்காணிக்கும் வழக்கு'],
            'stat_2_text' => ['Every quotation, invoice, and file stays attached to one reference number.', 'සෑම උපුටා දැක්වීමක්, ඉන්වොයිසියක්, ගොනුවක් එක අංකයකට බැඳේ.', 'ஒவ்வொரு மதிப்பீடு, விலைப்பட்டியல், கோப்பும் ஒரே எண்ணில் இருக்கும்.'],
            'stat_3_title' => ['Careful handling', 'සැලකිලිමත් හැසිරවීම', 'கவனமான கையாளுதல்'],
            'stat_3_text' => ['Completed files are released only to the customer account or authorised staff.', 'සම්පූර්ණ ගොනු පාරිභෝගික ගිණුමට හෝ බලයලත් කාර්ය මණ්ඩලයට පමණයි.', 'முடிந்த கோப்புகள் வாடிக்கையாளர் கணக்கு அல்லது அங்கீகரித்த ஊழியருக்கு மட்டுமே.'],
            'disclaimer' => ['Requirements can change by authority and destination. We confirm the current path before processing starts.', 'අවශ්‍යතා අධිකාරිය සහ ගමනාන්තය අනුව වෙනස් විය හැක. සැකසීමට පෙර වත්මන් මාර්ගය තහවුරු කරමු.', 'தேவைகள் அதிகாரம் மற்றும் நாட்டைப் பொறுத்து மாறலாம். செயலாக்கத்துக்கு முன் தற்போதைய பாதையை உறுதி செய்கிறோம்.'],
            'faq_eyebrow' => ['FAQ', 'ප්‍රශ්න', 'கேள்விகள்'],
            'faq_title' => ['Questions before you begin', 'ආරම්භයට පෙර ප්‍රශ්න', 'தொடங்கும் முன் கேள்விகள்'],
            'faq_lead' => ['A short briefing on documents, timing, and what we need from you.', 'ලේඛන, කාලය, සහ අපට අවශ්‍ය දේ පිළිබඳ කෙටි විස්තරයක්.', 'ஆவணங்கள், நேரம், உங்களிடம் தேவையானவை பற்றிய சுருக்க விளக்கம்.'],
            'faq_card_title' => ['Not sure where to start?', 'කොතැනින් පටන් ගන්නද යන්න විශ්වාස නැද්ද?', 'எங்கு தொடங்குவது என்று தெரியவில்லையா?'],
            'faq_card_text' => ['Tell us the document and destination. We will map the next steps.', 'ලේඛනය සහ ගමනාන්තය කියන්න. ඊළඟ පියවර සකසමු.', 'ஆவணத்தையும் சேரும் இடத்தையும் சொல்லுங்கள். அடுத்த படிகளை வரைவோம்.'],
            'guides_eyebrow' => ['Guides', 'මාර්ගෝපදේශ', 'வழிகாட்டிகள்'],
            'guides_title' => ['Useful guidance before you submit', 'භාර දීමට පෙර ප්‍රයෝජනවත් මඟ පෙන්වීම', 'சமர்ப்பிக்கும் முன் பயனுள்ள வழிகாட்டுதல்'],
            'guides_lead' => ['Short notes on certificates, destinations, and when a translation is required.', 'සහතික, ගමනාන්ත, සහ පරිවර්තනය අවශ්‍ය විට පිළිබඳ කෙටි සටහන්.', 'சான்றிதழ்கள், நாடுகள், மொழிபெயர்ப்பு தேவைப்படும் நேரம் பற்றிய குறிப்புகள்.'],
            'quote_eyebrow' => ['Request a quote', 'මිල ගණන් ඉල්ලන්න', 'மதிப்பீடு கோருங்கள்'],
            'quote_title' => ["Tell us what you need. We'll map the next steps.", 'ඔබට අවශ්‍ය දේ කියන්න. ඊළඟ පියවර අපි සකසමු.', 'உங்களுக்கு தேவதை சொல்லுங்கள். அடுத்த படிகளை வரைவோம்.'],
            'quote_lead' => ['Share the document, destination, and how we can reach you. A coordinator will confirm the path and the fee.', 'ලේඛනය, ගමනාන්තය, සහ සම්බන්ධ විය හැකි ආකාරය දෙන්න. සම්බන්ධීකාරකයෙක් මාර්ගය සහ ගාස්තුව තහවුරු කරයි.', 'ஆவணம், சேருமிடம், தொடர்பு வழியை பகிருங்கள். ஒருங்கிணைப்பாளர் பாதையையும் கட்டணத்தையும் உறுதி செய்வார்.'],
            'final_title' => ['Ready to simplify your document process?', 'ඔබේ ලේඛන ක්‍රියාවලිය සරල කිරීමට සූදානම්ද?', 'ஆவண செயல்முறையை எளிதாக்க தயாரா?'],
            'final_lead' => ['Share the document and destination. We will confirm the path, the fee, and the timing.', 'ලේඛනය සහ ගමනාන්තය දෙන්න. මාර්ගය, ගාස්තුව, කාලය තහවුරු කරමු.', 'ஆவணத்தையும் சேருமிடத்தையும் பகிருங்கள். பாதை, கட்டணம், நேரத்தை உறுதி செய்வோம்.'],
        ];
        foreach ($blocks as $key => $values) {
            $id = $this->db->insert('INSERT INTO content_blocks (block_key, group_name) VALUES (?, ?)', [$key, 'home']);
            foreach (['en', 'si', 'ta'] as $index => $locale) {
                $this->db->run('INSERT INTO content_block_translations (block_id, locale, value) VALUES (?, ?, ?)', [$id, $locale, $values[$index]]);
            }
        }
    }

    private function faqs(): void
    {
        $faqs = [
            ['Which documents can you handle?', 'Birth, marriage, degree, and police certificates are common. Other documents are reviewed before we confirm a path.', true],
            ['Do I need the original?', 'Some authorities accept a certified copy and some require the original. We tell you which one before submission.', true],
            ['How long does it take?', 'A straightforward file is often completed in one to three weeks. Embassy and MOFA queues can add time.', true],
            ['Can I track the file?', 'Yes. Each case has a reference number and a customer timeline that shows the steps we mark as visible.', true],
            ['When is a translation required?', 'When the receiving authority does not accept the original language. We confirm that before ordering a translation.', true],
            ['How do fees work?', 'Standard services have fixed prices. Anything that depends on the document is quoted before you confirm.', true],
        ];
        $order = 1;
        foreach ($faqs as [$question, $answer, $home]) {
            $id = $this->db->insert('INSERT INTO faqs (category, sort_order, is_active, show_on_home) VALUES (?, ?, 1, ?)', ['General', $order++, $home ? 1 : 0]);
            $this->db->run('INSERT INTO faq_translations (faq_id, locale, question, answer) VALUES (?, \'en\', ?, ?)', [$id, $question, $answer]);
            $this->db->run('INSERT INTO faq_translations (faq_id, locale, question, answer) VALUES (?, \'si\', ?, ?)', [$id, $question, $answer]);
            $this->db->run('INSERT INTO faq_translations (faq_id, locale, question, answer) VALUES (?, \'ta\', ?, ?)', [$id, $question, $answer]);
        }
    }

    private function users(): int
    {
        $adminId = $this->db->insert(
            'INSERT INTO users (name, email, phone, password_hash, locale, email_verified_at, must_change_password) VALUES (?, ?, ?, ?, \'en\', NOW(), 1)',
            ['AtteCert Admin', 'admin@attecert.local', '+94 11 000 0000', password_hash('Admin@12345', PASSWORD_DEFAULT)]
        );
        $roleId = (int) $this->db->fetchColumn('SELECT id FROM roles WHERE code = ?', ['super_admin']);
        $this->db->run('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [$adminId, $roleId]);

        $customerUser = $this->db->insert(
            'INSERT INTO users (name, email, phone, password_hash, locale, email_verified_at) VALUES (?, ?, ?, ?, \'en\', NOW())',
            ['Nimal Perera', 'customer@attecert.local', '+94 77 000 0000', password_hash('Customer@12345', PASSWORD_DEFAULT)]
        );
        $customerRole = (int) $this->db->fetchColumn('SELECT id FROM roles WHERE code = ?', ['customer']);
        $this->db->run('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [$customerUser, $customerRole]);
        $this->db->insert(
            'INSERT INTO customers (user_id, name, email, phone, city) VALUES (?, ?, ?, ?, ?)',
            [$customerUser, 'Nimal Perera', 'customer@attecert.local', '+94 77 000 0000', 'Colombo']
        );

        return $adminId;
    }

    private function articles(int $authorId): void
    {
        $posts = [
            ['checklist-before-attestation', 'guide-checklist.webp', 'A simple checklist before sending certificates for attestation', 'What to check on names, copies, and previous stamps before you hand a certificate over.'],
            ['uae-document-plan', 'guide-travel.webp', 'Planning documents for the UAE: what to prepare', 'A practical order of steps for a Sri Lankan certificate that needs to be used in the UAE.'],
            ['when-translation-is-required', 'guide-documents.webp', 'When official translation may be required', 'How to tell whether the receiving authority is likely to ask for a certified translation.'],
        ];
        foreach ($posts as [$slug, $image, $title, $excerpt]) {
            $id = $this->db->insert(
                'INSERT INTO articles (slug, status, featured, image_path, author_id, published_at) VALUES (?, \'published\', 1, ?, ?, NOW())',
                [$slug, $image, $authorId]
            );
            $content = '<p>' . $excerpt . '</p><p>Requirements change by authority. AtteCert confirms the current path for your document before processing starts. Keep the holder name consistent across the certificate, passport, and application.</p><h2>Before you submit</h2><ul><li>Check the spelling of the holder name.</li><li>Keep a clear scan.</li><li>Tell us the destination and the purpose.</li></ul>';
            foreach (['en', 'si', 'ta'] as $locale) {
                $this->db->run(
                    'INSERT INTO article_translations (article_id, locale, title, excerpt, content, seo_title, meta_description) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$id, $locale, $title, $excerpt, $content, $title . ' | AtteCert', $excerpt]
                );
            }
        }
    }

    private function demoCase(int $adminId): void
    {
        $customerId = (int) $this->db->fetchColumn('SELECT id FROM customers WHERE email = ?', ['customer@attecert.local']);
        $countryId = (int) $this->db->fetchColumn('SELECT id FROM countries WHERE code = ?', ['AE']);
        $typeId = (int) $this->db->fetchColumn('SELECT id FROM document_types WHERE code = ?', ['DEGREE']);
        $statusId = (int) $this->db->fetchColumn('SELECT id FROM workflow_statuses WHERE code = ?', ['mofa_processing']);
        $numbers = new NumberService($this->db);
        $caseId = $this->db->insert(
            'INSERT INTO cases (number, customer_id, country_id, document_type_id, document_title, summary, status_id, source, estimated_next, currency, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$numbers->next('case', 'ATT'), $customerId, $countryId, $typeId, 'Degree certificate', 'UAE employment file', $statusId, 'seed', 'MOFA completion, then embassy legalization', 'LKR', $adminId]
        );
        foreach (['TRANS', 'MOFA-ATT', 'EMB-LEG'] as $code) {
            $service = $this->db->fetch('SELECT * FROM services WHERE code = ?', [$code]);
            $this->db->insert(
                'INSERT INTO case_services (case_id, service_id, quantity, pricing_type, unit_price, currency) VALUES (?, ?, 1, ?, ?, ?)',
                [$caseId, $service['id'], $service['pricing_type'], $service['fixed_price'], 'LKR']
            );
        }
        foreach (['documents_received', 'payment_received', 'submitted_to_mofa', 'mofa_processing'] as $code) {
            $id = (int) $this->db->fetchColumn('SELECT id FROM workflow_statuses WHERE code = ?', [$code]);
            $this->db->insert(
                'INSERT INTO case_status_history (case_id, status_id, staff_id, notes, customer_visible) VALUES (?, ?, ?, ?, 1)',
                [$caseId, $id, $adminId, 'Demo timeline']
            );
        }
    }
}
