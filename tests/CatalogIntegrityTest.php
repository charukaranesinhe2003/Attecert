<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/DatabaseTestCase.php';

use App\Controllers\Admin\ArticleController;
use App\Controllers\Admin\CountryController;
use App\Controllers\Admin\FaqController;
use App\Controllers\Admin\ServiceController;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use DOMDocument;
use DOMXPath;
use ReflectionProperty;

final class CatalogIntegrityTest extends DatabaseTestCase
{
    public function testServiceSavePreservesCategorySeoAndCountryPriceOverrides(): void
    {
        $link = $this->db->fetch('SELECT country_id, service_id FROM country_services ORDER BY country_id, service_id LIMIT 1');
        $this->assertNotNull($link);
        $id = (int) $link['service_id'];
        $this->db->run('UPDATE services SET category = ? WHERE id = ?', ['legalization', $id]);
        $this->db->run('UPDATE service_translations SET seo_title = ?, meta_description = ? WHERE service_id = ? AND locale = ?', ['Service SEO retained', 'Service meta retained', $id, 'en']);
        $this->db->run('UPDATE country_services SET price_override = ?, notes = ? WHERE country_id = ? AND service_id = ?', ['987.25', 'Keep agreed country price', $link['country_id'], $id]);
        $html = $this->body(app(ServiceController::class)->edit($this->get(), (string) $id));
        $this->assertStringContainsString('name="category" value="legalization"', $html);
        $this->assertStringContainsString('name="seo_title_en" value="Service SEO retained"', $html);
        app(ServiceController::class)->update($this->post($this->servicePayload($id)), (string) $id);
        $this->assertSame('legalization', $this->db->fetchColumn('SELECT category FROM services WHERE id = ?', [$id]));
        $this->assertSame('Service SEO retained', $this->db->fetchColumn('SELECT seo_title FROM service_translations WHERE service_id = ? AND locale = ?', [$id, 'en']));
        $pivot = $this->db->fetch('SELECT price_override, notes FROM country_services WHERE country_id = ? AND service_id = ?', [$link['country_id'], $id]);
        $this->assertSame('987.25', $pivot['price_override']);
        $this->assertSame('Keep agreed country price', $pivot['notes']);
    }

    public function testCountrySavePreservesSeoInactiveFlagAndServicePriceOverrides(): void
    {
        $link = $this->db->fetch('SELECT country_id, service_id FROM country_services ORDER BY country_id, service_id LIMIT 1');
        $id = (int) $link['country_id'];
        $this->db->run('UPDATE countries SET is_active = 0 WHERE id = ?', [$id]);
        $this->db->run('UPDATE country_translations SET seo_title = ?, meta_description = ? WHERE country_id = ? AND locale = ?', ['Country SEO retained', 'Country meta retained', $id, 'en']);
        $this->db->run('UPDATE country_services SET price_override = ?, notes = ? WHERE country_id = ? AND service_id = ?', ['456.75', 'Country notes retained', $id, $link['service_id']]);
        $html = $this->body(app(CountryController::class)->edit($this->get(), (string) $id));
        $this->assertSame(0, $this->checkedCount($html, 'is_active'));
        $this->assertStringContainsString('name="seo_title_en" value="Country SEO retained"', $html);
        app(CountryController::class)->update($this->post($this->countryPayload($id)), (string) $id);
        $this->assertSame(0, (int) $this->db->fetchColumn('SELECT is_active FROM countries WHERE id = ?', [$id]));
        $this->assertSame('Country SEO retained', $this->db->fetchColumn('SELECT seo_title FROM country_translations WHERE country_id = ? AND locale = ?', [$id, 'en']));
        $pivot = $this->db->fetch('SELECT price_override, notes FROM country_services WHERE country_id = ? AND service_id = ?', [$id, $link['service_id']]);
        $this->assertSame('456.75', $pivot['price_override']);
        $this->assertSame('Country notes retained', $pivot['notes']);
    }

    public function testPivotSyncRemovesDeselectedLinksAndAddsNewLinksWithoutOverwritingRetainedRows(): void
    {
        $country = (int) $this->db->fetchColumn('SELECT id FROM countries ORDER BY id LIMIT 1');
        $services = array_map('intval', array_column($this->db->fetchAll('SELECT id FROM services ORDER BY id LIMIT 3'), 'id'));
        $this->assertCount(3, $services);
        $this->db->run('DELETE FROM country_services WHERE country_id = ?', [$country]);
        foreach (array_slice($services, 0, 2) as $service) {
            $this->db->run('INSERT INTO country_services (country_id, service_id, price_override, notes) VALUES (?, ?, ?, ?)', [$country, $service, '123.45', 'Existing override']);
        }
        $data = $this->countryPayload($country);
        $data['service_id'] = [$services[0], $services[2]];
        app(CountryController::class)->update($this->post($data), (string) $country);
        $rows = $this->db->fetchAll('SELECT service_id, price_override, notes FROM country_services WHERE country_id = ? ORDER BY service_id', [$country]);
        $this->assertSame([$services[0], $services[2]], array_map('intval', array_column($rows, 'service_id')));
        $this->assertSame('123.45', $rows[0]['price_override']);
        $this->assertSame('Existing override', $rows[0]['notes']);
        $this->assertNull($rows[1]['price_override']);
    }

    public function testInactiveFaqAndFeaturedArticleRemainAccurateAfterSave(): void
    {
        $faq = (int) $this->db->fetchColumn('SELECT id FROM faqs ORDER BY id LIMIT 1');
        $this->db->run('UPDATE faqs SET is_active = 0 WHERE id = ?', [$faq]);
        $html = $this->body(app(FaqController::class)->edit($this->get(), (string) $faq));
        $this->assertSame(0, $this->checkedCount($html, 'is_active'));
        $data = $this->db->fetch('SELECT * FROM faqs WHERE id = ?', [$faq]);
        foreach ($this->db->fetchAll('SELECT * FROM faq_translations WHERE faq_id = ?', [$faq]) as $row) {
            $data['question_' . $row['locale']] = $row['question'];
            $data['answer_' . $row['locale']] = $row['answer'];
        }
        app(FaqController::class)->update($this->post($data), (string) $faq);
        $this->assertSame(0, (int) $this->db->fetchColumn('SELECT is_active FROM faqs WHERE id = ?', [$faq]));

        $article = (int) $this->db->fetchColumn('SELECT id FROM articles WHERE deleted_at IS NULL ORDER BY id LIMIT 1');
        $this->db->run('UPDATE articles SET featured = 1 WHERE id = ?', [$article]);
        $before = $this->db->fetch('SELECT image_path, published_at FROM articles WHERE id = ?', [$article]);
        $html = $this->body(app(ArticleController::class)->edit($this->get(), (string) $article));
        $this->assertSame(1, $this->checkedCount($html, 'featured'));
        app(ArticleController::class)->update($this->post($this->articlePayload($article)), (string) $article);
        $after = $this->db->fetch('SELECT featured, image_path, published_at FROM articles WHERE id = ?', [$article]);
        $this->assertSame(1, (int) $after['featured']);
        $this->assertSame($before['image_path'], $after['image_path']);
        $this->assertSame($before['published_at'], $after['published_at']);
    }

    public function testDuplicateCodesAndSlugsAreRejectedBeforeAnyRecordChanges(): void
    {
        $services = array_map('intval', array_column($this->db->fetchAll('SELECT id FROM services ORDER BY id LIMIT 2'), 'id'));
        foreach (['code', 'slug'] as $field) {
            $data = $this->servicePayload($services[0]);
            $data[$field] = $this->db->fetchColumn('SELECT ' . $field . ' FROM services WHERE id = ?', [$services[1]]);
            $this->assertInvalid($field, fn () => app(ServiceController::class)->update($this->post($data), (string) $services[0]));
        }
        $countries = array_map('intval', array_column($this->db->fetchAll('SELECT id FROM countries ORDER BY id LIMIT 2'), 'id'));
        foreach (['code', 'slug'] as $field) {
            $data = $this->countryPayload($countries[0]);
            $data[$field] = $this->db->fetchColumn('SELECT ' . $field . ' FROM countries WHERE id = ?', [$countries[1]]);
            $this->assertInvalid($field, fn () => app(CountryController::class)->update($this->post($data), (string) $countries[0]));
        }
        $article = (int) $this->db->fetchColumn('SELECT id FROM articles ORDER BY id LIMIT 1');
        $count = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM articles');
        $this->assertInvalid('slug', fn () => app(ArticleController::class)->store($this->post($this->articlePayload($article))));
        $this->assertSame($count, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM articles'));
    }

    public function testInvalidTranslationAndUnknownTargetsDoNotModifyCatalog(): void
    {
        $id = (int) $this->db->fetchColumn('SELECT id FROM services ORDER BY id LIMIT 1');
        $before = $this->db->fetch('SELECT code, category FROM services WHERE id = ?', [$id]);
        $data = $this->servicePayload($id);
        $data['category'] = 'Should never save';
        $data['seo_title_ta'] = str_repeat('x', 181);
        $this->assertInvalid('seo_title_ta', fn () => app(ServiceController::class)->update($this->post($data), (string) $id));
        $this->assertSame($before, $this->db->fetch('SELECT code, category FROM services WHERE id = ?', [$id]));
        foreach ([
            [ServiceController::class, ['code' => 'UNIQUE-NEW', 'name_en' => 'Missing service', 'slug' => 'missing-service', 'pricing_type' => 'custom']],
            [CountryController::class, ['code' => 'ZZ', 'name_en' => 'Missing country', 'slug' => 'missing-country']],
            [FaqController::class, ['question_en' => 'Missing FAQ?', 'answer_en' => 'Missing answer']],
            [ArticleController::class, ['title_en' => 'Missing article', 'status' => 'draft', 'slug' => 'missing-article']],
        ] as [$controller, $payload]) {
            try {
                app($controller)->update($this->post($payload), '99999999');
                $this->fail('Missing catalog target was accepted.');
            } catch (HttpException $exception) {
                $this->assertSame(404, $exception->status());
            }
        }
    }

    public function testCatalogFormsRetainAttemptedValuesAndErrors(): void
    {
        $_SESSION['_view_old'] = ['category' => 'Attempted category', 'seo_title_en' => 'Attempted SEO', 'is_active' => '0', 'country_id' => ''];
        $_SESSION['_view_errors'] = ['code' => 'Choose another code.'];
        $id = (int) $this->db->fetchColumn('SELECT id FROM services ORDER BY id LIMIT 1');
        $html = $this->body(app(ServiceController::class)->edit($this->get(), (string) $id));
        $this->assertStringContainsString('value="Attempted category"', $html);
        $this->assertStringContainsString('value="Attempted SEO"', $html);
        $this->assertStringContainsString('Choose another code.', $html);
        $this->assertSame(0, $this->checkedCount($html, 'is_active'));
        $this->assertSame(0, $this->checkedCount($html, 'country_id[]'));
    }

    private function servicePayload(int $id): array
    {
        $data = $this->db->fetch('SELECT * FROM services WHERE id = ?', [$id]);
        $data['country_id'] = array_column($this->db->fetchAll('SELECT country_id FROM country_services WHERE service_id = ?', [$id]), 'country_id');
        foreach ($this->db->fetchAll('SELECT * FROM service_translations WHERE service_id = ?', [$id]) as $row) {
            foreach (['name' => 'name', 'summary' => 'summary', 'description' => 'description', 'process' => 'process_text', 'documents' => 'documents_required', 'seo_title' => 'seo_title', 'meta' => 'meta_description'] as $input => $column) {
                $data[$input . '_' . $row['locale']] = $row[$column];
            }
        }

        return $data;
    }

    private function countryPayload(int $id): array
    {
        $data = $this->db->fetch('SELECT * FROM countries WHERE id = ?', [$id]);
        $data['service_id'] = array_column($this->db->fetchAll('SELECT service_id FROM country_services WHERE country_id = ?', [$id]), 'service_id');
        foreach ($this->db->fetchAll('SELECT * FROM country_translations WHERE country_id = ?', [$id]) as $row) {
            foreach (['name' => 'name', 'summary' => 'summary', 'requirements' => 'requirements', 'documents' => 'documents_required', 'process' => 'process_text', 'notes' => 'notes', 'processing' => 'processing_info', 'seo_title' => 'seo_title', 'meta' => 'meta_description'] as $input => $column) {
                $data[$input . '_' . $row['locale']] = $row[$column];
            }
        }

        return $data;
    }

    private function articlePayload(int $id): array
    {
        $data = $this->db->fetch('SELECT * FROM articles WHERE id = ?', [$id]);
        foreach ($this->db->fetchAll('SELECT * FROM article_translations WHERE article_id = ?', [$id]) as $row) {
            foreach (['title' => 'title', 'excerpt' => 'excerpt', 'content' => 'content', 'seo_title' => 'seo_title', 'meta' => 'meta_description'] as $input => $column) {
                $data[$input . '_' . $row['locale']] = $row[$column];
            }
        }

        return $data;
    }

    private function assertInvalid(string $field, callable $action): void
    {
        try {
            $action();
            $this->fail('Invalid catalog data was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    private function checkedCount(string $html, string $name): int
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return (new DOMXPath($document))->query('//input[@type="checkbox" and @name="' . $name . '" and @checked]')->length;
    }

    private function body(Response $response): string
    {
        return (string) (new ReflectionProperty(Response::class, 'body'))->getValue($response);
    }

    private function post(array $body): Request
    {
        return new Request('POST', '/admin', [], $body, [], ['REMOTE_ADDR' => '127.0.0.1'], []);
    }

    private function get(): Request
    {
        return new Request('GET', '/admin', [], [], [], [], []);
    }
}
