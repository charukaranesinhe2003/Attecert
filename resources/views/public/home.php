<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <p class="eyebrow"><?= e(content_block('hero_eyebrow', 'Document attestation in Sri Lanka')) ?></p>
            <h1><?= e(content_block('hero_title', 'Attecert: Fast, Reliable Document Attestation in Sri Lanka')) ?></h1>
            <p class="lead"><?= e(content_block('hero_lead')) ?></p>
            <div class="hero-actions">
                <a class="btn btn-primary btn-lg" href="<?= e(url('/quote')) ?>"><?= e(__('nav.quote')) ?></a>
                <a class="btn btn-ghost btn-lg" href="<?= e(url('/track')) ?>"><?= e(__('nav.track_document')) ?></a>
            </div>
            <ul class="trust-row">
                <li><?= icon('shield', 'icon icon-sm') ?> <?= e(content_block('trust_1', 'Clear requirements')) ?></li>
                <li><?= icon('check', 'icon icon-sm') ?> <?= e(content_block('trust_2', 'Experienced handling')) ?></li>
                <li><?= icon('lock', 'icon icon-sm') ?> <?= e(content_block('trust_3', 'Secure document care')) ?></li>
            </ul>
        </div>
        <div class="hero-visual">
            <img src="<?= e(asset('images/team-meeting.webp')) ?>" alt="<?= e(__('home.hero_alt')) ?>" width="640" height="460" fetchpriority="high">
            <form class="track-card" method="post" action="<?= e(url('/track')) ?>">
                <?= csrf_field() ?>
                <div class="track-card-head">
                    <strong><?= e(__('track.title')) ?></strong>
                    <span><?= icon('search', 'icon icon-sm') ?></span>
                </div>
                <label class="sr-only" for="hero-track"><?= e(__('track.number')) ?></label>
                <div class="track-row">
                    <input id="hero-track" name="number" placeholder="<?= e(__('track.placeholder')) ?>" autocomplete="off" required>
                    <button class="btn btn-gold" type="submit"><?= e(__('track.button')) ?></button>
                </div>
            </form>
        </div>
    </div>
</section>
<form class="mobile-track" method="post" action="<?= e(url('/track')) ?>">
    <?= csrf_field() ?>
    <div class="container mobile-track-inner">
        <strong><?= e(__('track.title')) ?></strong>
        <div class="track-row">
            <label class="sr-only" for="mobile-track"><?= e(__('track.number')) ?></label>
            <input id="mobile-track" name="number" placeholder="<?= e(__('track.placeholder')) ?>" required>
            <button class="btn btn-gold" type="submit"><?= e(__('track.button')) ?></button>
        </div>
    </div>
</form>

<section class="care">
    <div class="container care-grid">
        <div>
            <h2><?= e(content_block('care_title', 'One careful team, every step of the way.')) ?></h2>
            <p><?= e(content_block('care_lead')) ?></p>
        </div>
        <ul class="check-list">
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <li><?= icon('check', 'icon icon-sm') ?> <?= e(content_block('care_' . $i)) ?></li>
            <?php endfor; ?>
        </ul>
        <div class="paper-card" aria-hidden="true">
            <div class="paper-lines"><span></span><span></span><span></span><span></span></div>
            <div class="paper-seal"><?= icon('check') ?></div>
        </div>
    </div>
</section>

<section class="section services-section" id="services">
    <div class="container">
        <div class="section-head">
            <div>
                <p class="eyebrow"><?= e(content_block('services_eyebrow', 'Our services')) ?></p>
                <h2><?= e(content_block('services_title', 'Document support for home and abroad')) ?></h2>
                <p><?= e(content_block('services_lead')) ?></p>
            </div>
            <a class="text-link" href="<?= e(url('/services')) ?>"><?= e(__('home.all_services')) ?> <?= icon('arrow', 'icon icon-sm') ?></a>
        </div>
        <div class="card-grid">
            <?php foreach ($services as $service): ?>
                <a class="service-card" href="<?= e(url('/services/' . $service['slug'])) ?>">
                    <span class="service-icon"><?= icon($service['icon'] ?: 'document') ?></span>
                    <h3><?= e($service['name']) ?></h3>
                    <p><?= e($service['summary']) ?></p>
                    <span class="card-more"><?= e(__('home.learn_more')) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section process">
    <div class="container">
        <div class="section-head">
            <div>
                <p class="eyebrow"><?= e(content_block('process_eyebrow', 'How it works')) ?></p>
                <h2><?= e(content_block('process_title', 'Four clear steps. No office-to-office guesswork.')) ?></h2>
                <p><?= e(content_block('process_lead')) ?></p>
            </div>
        </div>
        <ol class="steps">
            <?php
            $stepIcons = ['upload', 'search', 'stamp', 'send'];
            for ($i = 1; $i <= 4; $i++):
            ?>
                <li>
                    <span class="step-index"><?= $i ?></span>
                    <span class="step-icon"><?= icon($stepIcons[$i - 1]) ?></span>
                    <h3><?= e(content_block('process_' . $i . '_title')) ?></h3>
                    <p><?= e(content_block('process_' . $i . '_text')) ?></p>
                </li>
            <?php endfor; ?>
        </ol>
    </div>
</section>

<section class="track-band">
    <div class="container track-band-inner">
        <div>
            <span class="band-icon"><?= icon('search') ?></span>
            <div>
                <h2><?= e(content_block('track_title', 'Track your document')) ?></h2>
                <p><?= e(content_block('track_lead')) ?></p>
            </div>
        </div>
        <form method="post" action="<?= e(url('/track')) ?>">
            <?= csrf_field() ?>
            <label class="sr-only" for="band-track"><?= e(__('track.number')) ?></label>
            <input id="band-track" name="number" placeholder="<?= e(__('track.placeholder')) ?>" required>
            <button class="btn btn-navy" type="submit"><?= e(__('track.button')) ?></button>
        </form>
    </div>
</section>

<section class="section countries-section">
    <div class="container">
        <div class="section-head">
            <div>
                <p class="eyebrow"><?= e(content_block('countries_eyebrow', 'Destinations')) ?></p>
                <h2><?= e(content_block('countries_title', 'Preparing documents for another country?')) ?></h2>
                <p><?= e(content_block('countries_lead')) ?></p>
            </div>
            <a class="text-link" href="<?= e(url('/countries')) ?>"><?= e(__('home.all_countries')) ?> <?= icon('arrow', 'icon icon-sm') ?></a>
        </div>
        <div class="country-grid">
            <?php foreach ($countries as $country): ?>
                <a class="country-card" href="<?= e(url('/countries/' . $country['slug'])) ?>">
                    <img src="<?= e(asset('images/flags/' . strtolower($country['code']) . '.svg')) ?>" alt="" width="36" height="24">
                    <h3><?= e($country['name']) ?></h3>
                    <p><?= e(str_limit((string) $country['summary'], 90)) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section about-band">
    <div class="container about-grid">
        <div class="about-photo">
            <img src="<?= e(asset('images/team-meeting.webp')) ?>" alt="<?= e(__('home.about_alt')) ?>" width="560" height="420" loading="lazy">
            <div class="year-badge">
                <strong><?= e(content_block('about_year', '2022')) ?></strong>
                <span><?= e(content_block('about_year_label', 'Supporting documents since')) ?></span>
            </div>
        </div>
        <div>
            <p class="eyebrow"><?= e(content_block('about_eyebrow', 'About')) ?></p>
            <h2><?= e(content_block('about_title', 'Practical support for important documents')) ?></h2>
            <p><?= e(content_block('about_lead')) ?></p>
            <div class="stat-row">
                <?php for ($i = 1; $i <= 3; $i++): ?>
                    <article>
                        <h3><?= e(content_block('stat_' . $i . '_title')) ?></h3>
                        <p><?= e(content_block('stat_' . $i . '_text')) ?></p>
                    </article>
                <?php endfor; ?>
            </div>
            <a class="text-link" href="<?= e(url('/about')) ?>"><?= e(__('home.about_link')) ?> <?= icon('arrow', 'icon icon-sm') ?></a>
        </div>
    </div>
</section>

<aside class="notice">
    <div class="container notice-inner">
        <p><?= icon('alert', 'icon icon-sm') ?> <?= e(content_block('disclaimer')) ?></p>
        <a href="<?= e(url('/contact')) ?>"><?= e(__('home.ask_us')) ?></a>
    </div>
</aside>

<section class="section faq-section">
    <div class="container faq-grid">
        <div>
            <p class="eyebrow"><?= e(content_block('faq_eyebrow', 'FAQ')) ?></p>
            <h2><?= e(content_block('faq_title', 'Questions before you begin')) ?></h2>
            <p><?= e(content_block('faq_lead')) ?></p>
            <a class="help-card" href="<?= e(url('/quote')) ?>">
                <strong><?= e(content_block('faq_card_title', 'Not sure where to start?')) ?></strong>
                <span><?= e(content_block('faq_card_text')) ?></span>
                <em><?= e(__('nav.quote')) ?></em>
            </a>
        </div>
        <div class="accordion" data-accordion>
            <?php foreach ($faqs as $index => $faq): ?>
                <div class="accordion-item">
                    <h3>
                        <button type="button" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" data-accordion-button>
                            <?= e($faq['question']) ?>
                            <?= icon('chevron-down', 'icon icon-sm') ?>
                        </button>
                    </h3>
                    <div class="accordion-panel" <?= $index === 0 ? '' : 'hidden' ?>>
                        <p><?= e($faq['answer']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section guides-section">
    <div class="container">
        <div class="section-head">
            <div>
                <p class="eyebrow"><?= e(content_block('guides_eyebrow', 'Guides')) ?></p>
                <h2><?= e(content_block('guides_title', 'Useful guidance before you submit')) ?></h2>
                <p><?= e(content_block('guides_lead')) ?></p>
            </div>
            <a class="text-link" href="<?= e(url('/guides')) ?>"><?= e(__('home.all_guides')) ?> <?= icon('arrow', 'icon icon-sm') ?></a>
        </div>
        <div class="article-grid">
            <?php foreach ($articles as $article): ?>
                <article class="article-card">
                    <a href="<?= e(url('/guides/' . $article['slug'])) ?>">
                        <img src="<?= e(asset('images/' . ($article['image_path'] ?: 'guide-documents.webp'))) ?>" alt="" width="640" height="400" loading="lazy">
                        <div>
                            <h3><?= e($article['title']) ?></h3>
                            <p><?= e($article['excerpt']) ?></p>
                            <span><?= e(__('home.read_guide')) ?></span>
                        </div>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="quote-section" id="quote">
    <div class="container quote-grid">
        <div>
            <p class="eyebrow light"><?= e(content_block('quote_eyebrow', 'Request a quote')) ?></p>
            <h2><?= e(content_block('quote_title', "Tell us what you need. We'll map the next steps.")) ?></h2>
            <p><?= e(content_block('quote_lead')) ?></p>
            <ul class="contact-list light">
                <li><?= icon('phone', 'icon icon-sm') ?> <?= e(setting('phone', '')) ?></li>
                <li><?= icon('mail', 'icon icon-sm') ?> <?= e(setting('email', '')) ?></li>
                <li><?= icon('clock', 'icon icon-sm') ?> <?= e(setting('hours', '')) ?></li>
            </ul>
        </div>
        <form class="quote-card" method="post" action="<?= e(url('/quote')) ?>">
            <?= csrf_field() ?>
            <div class="quote-card-head">
                <h3><?= e(__('quote.form_title')) ?></h3>
                <span><?= e(__('quote.reply_time')) ?></span>
            </div>
            <?php if (error('name') || error('email') || error('phone') || error('consent')): ?>
                <p class="form-error" role="alert"><?= e(error('consent') ?: __('validation.failed')) ?></p>
            <?php endif; ?>
            <div class="form-grid">
                <label><?= e(__('fields.name')) ?><input name="name" value="<?= e((string) old('name')) ?>" required></label>
                <label><?= e(__('fields.phone')) ?><input name="phone" value="<?= e((string) old('phone')) ?>" required></label>
                <label><?= e(__('fields.email')) ?><input type="email" name="email" value="<?= e((string) old('email')) ?>" required></label>
                <label><?= e(__('fields.document_type_id')) ?>
                    <select name="document_type_id">
                        <option value=""><?= e(__('quote.choose')) ?></option>
                        <?php foreach ($documentTypes as $type): ?>
                            <option value="<?= e((string) $type['id']) ?>" <?= selected(old('document_type_id'), $type['id']) ?>><?= e($type['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><?= e(__('fields.country_id')) ?>
                    <select name="country_id">
                        <option value=""><?= e(__('quote.choose')) ?></option>
                        <?php foreach ($formCountries as $country): ?>
                            <option value="<?= e((string) $country['id']) ?>" <?= selected(old('country_id'), $country['id']) ?>><?= e($country['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="span-2"><?= e(__('fields.message')) ?><textarea name="message" rows="4"><?= e((string) old('message')) ?></textarea></label>
            </div>
            <label class="consent"><input type="checkbox" name="consent" value="1" required> <?= e(__('quote.consent')) ?></label>
            <button class="btn btn-primary btn-block" type="submit"><?= e(__('quote.submit')) ?></button>
        </form>
    </div>
</section>

<section class="final-cta">
    <div class="container final-cta-inner">
        <div>
            <h2><?= e(content_block('final_title', 'Ready to simplify your document process?')) ?></h2>
            <p><?= e(content_block('final_lead')) ?></p>
        </div>
        <div class="hero-actions">
            <a class="btn btn-light" href="<?= e(url('/#services')) ?>"><?= e(__('home.see_steps')) ?></a>
            <a class="btn btn-primary" href="<?= e(url('/quote')) ?>"><?= e(__('nav.quote')) ?></a>
        </div>
    </div>
</section>
