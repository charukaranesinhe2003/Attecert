<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/DatabaseTestCase.php';

use App\Controllers\Admin\ArticleController;
use App\Controllers\Admin\CountryController;
use App\Controllers\Admin\FaqController;
use App\Controllers\Admin\ServiceController;
use App\Controllers\Admin\UserController;
use App\Core\Request;
use App\Core\Response;
use DOMDocument;
use DOMXPath;
use ReflectionProperty;

final class AdminCatalogListsTest extends DatabaseTestCase
{
    public function testCatalogAndStaffListsPaginateSearchResultsAndRetainFilters(): void
    {
        for ($index = 1; $index <= 18; $index++) {
            $name = sprintf('ListFixture%02d', $index);
            $this->service($name);
            $this->country($name, 'X' . chr(64 + $index));
            $this->article($name);
            $this->faq($name);
            $this->staff($name);
        }
        foreach ([
            ServiceController::class => '/admin/services', CountryController::class => '/admin/countries',
            ArticleController::class => '/admin/articles', FaqController::class => '/admin/faqs', UserController::class => '/admin/users',
        ] as $class => $path) {
            $status = $class === ArticleController::class ? 'published' : 'active';
            $first = $this->html(app($class)->index($this->get($path, ['q' => 'ListFixture', 'status' => $status])));
            $this->assertSame(15, $this->xpath($first)->query('//table/tbody/tr')->length, $class);
            $this->assertStringContainsString('18 matching records', $first);
            $this->assertStringContainsString('q=ListFixture&amp;status=' . $status . '&amp;page=2', $first);
            $last = $this->html(app($class)->index($this->get($path, ['q' => 'ListFixture', 'status' => $status, 'page' => 99999])));
            $this->assertSame(3, $this->xpath($last)->query('//table/tbody/tr')->length, $class);
            $this->assertSame(1, $this->xpath($last)->query('//nav[contains(@class,"pagination")]//a[@aria-current="page" and text()="2"]')->length, $class);
        }
    }

    public function testStatusSearchAndDeletedRecordsAreConsistentAcrossCatalogLists(): void
    {
        $this->service('Catalog visible');
        $this->service('Catalog hidden', 0);
        $deleted = $this->service('Catalog deleted');
        $this->db->run('UPDATE services SET deleted_at = NOW() WHERE id = ?', [$deleted]);
        $this->country('Catalog visible', 'YA');
        $this->country('Catalog hidden', 'YB', 0);
        $deletedCountry = $this->country('Catalog deleted', 'YC');
        $this->db->run('UPDATE countries SET deleted_at = NOW() WHERE id = ?', [$deletedCountry]);
        $this->faq('Catalog visible');
        $this->faq('Catalog hidden', 0);
        $this->article('Catalog visible');
        $this->article('Catalog hidden', 'draft');
        $deletedArticle = $this->article('Catalog deleted');
        $this->db->run('UPDATE articles SET deleted_at = NOW() WHERE id = ?', [$deletedArticle]);
        foreach ([
            ServiceController::class => '/admin/services', CountryController::class => '/admin/countries',
            ArticleController::class => '/admin/articles', FaqController::class => '/admin/faqs',
        ] as $class => $path) {
            $status = $class === ArticleController::class ? 'draft' : 'inactive';
            $html = $this->html(app($class)->index($this->get($path, ['q' => 'Catalog', 'status' => $status])));
            $this->assertSame(1, $this->xpath($html)->query('//table/tbody/tr')->length, $class);
            $this->assertStringContainsString('Catalog hidden', $html);
            $this->assertStringNotContainsString('Catalog visible', $html);
            $this->assertStringNotContainsString('Catalog deleted', $html);
            $empty = $this->html(app($class)->index($this->get($path, ['q' => "' OR 1=1 --"])));
            $this->assertSame(0, $this->xpath($empty)->query('//table/tbody/tr')->length, $class);
            $this->assertStringContainsString(__('admin_lists.empty'), $empty);
        }
    }

    public function testStaffRoleFilterPreservesAllRolesAndExcludesCustomersAndDeletedStaff(): void
    {
        $id = $this->staff('Roster fixture');
        $this->db->run('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE code = ?', [$id, 'finance_staff']);
        $inactive = $this->staff('Roster inactive', 0);
        $deleted = $this->staff('Roster deleted');
        $this->db->run('UPDATE users SET deleted_at = NOW() WHERE id = ?', [$deleted]);
        $customer = $this->staff('Roster customer', 1, 'customer');
        $html = $this->html(app(UserController::class)->index($this->get('/admin/users', ['q' => 'Roster', 'role' => 'content_editor', 'status' => 'active'])));
        $this->assertSame(1, $this->xpath($html)->query('//table/tbody/tr')->length);
        $this->assertStringContainsString('content_editor, finance_staff', $html);
        $this->assertStringNotContainsString('Roster customer', $html);
        $this->assertStringNotContainsString('Roster inactive', $html);
        $this->assertStringNotContainsString('Roster deleted', $html);
        $this->assertStringContainsString('/admin/users/' . $id, $html);
        $this->assertStringNotContainsString('/admin/users/' . $customer . '"', $html);
        $inactiveHtml = $this->html(app(UserController::class)->index($this->get('/admin/users', ['q' => 'Roster', 'status' => 'inactive'])));
        $this->assertStringContainsString('/admin/users/' . $inactive, $inactiveHtml);
        $this->assertSame(1, $this->xpath($inactiveHtml)->query('//table/tbody/tr')->length);
    }

    public function testTranslatedListsUseEnglishFallbackAndTolerateMalformedFilterTypes(): void
    {
        $this->service('Fallback catalog');
        $this->country('Fallback catalog', 'ZA');
        $this->article('Fallback catalog');
        $this->faq('Fallback catalog');
        $_SESSION['locale'] = 'si';
        foreach ([
            ServiceController::class => '/admin/services', CountryController::class => '/admin/countries',
            ArticleController::class => '/admin/articles', FaqController::class => '/admin/faqs', UserController::class => '/admin/users',
        ] as $class => $path) {
            $html = $this->html(app($class)->index($this->get($path, ['q' => ['invalid'], 'status' => ['invalid'], 'role' => ['invalid']])));
            $this->assertStringContainsString('name="lang" value="si"', $html);
            if ($class !== UserController::class) {
                $this->assertStringContainsString('Fallback catalog', $html);
            }
        }
    }

    private function service(string $name, int $active = 1): int
    {
        $unique = bin2hex(random_bytes(6));
        $id = $this->db->insert('INSERT INTO services (code, slug, is_active) VALUES (?, ?, ?)', [$unique, $unique, $active]);
        $this->db->run('INSERT INTO service_translations (service_id, locale, name, summary) VALUES (?, ?, ?, ?)', [$id, 'en', $name, 'Fixture summary']);

        return $id;
    }

    private function country(string $name, string $code, int $active = 1): int
    {
        $id = $this->db->insert('INSERT INTO countries (code, slug, is_active) VALUES (?, ?, ?)', [$code, bin2hex(random_bytes(6)), $active]);
        $this->db->run('INSERT INTO country_translations (country_id, locale, name) VALUES (?, ?, ?)', [$id, 'en', $name]);

        return $id;
    }

    private function article(string $title, string $status = 'published'): int
    {
        $id = $this->db->insert('INSERT INTO articles (slug, status) VALUES (?, ?)', [bin2hex(random_bytes(6)), $status]);
        $this->db->run('INSERT INTO article_translations (article_id, locale, title) VALUES (?, ?, ?)', [$id, 'en', $title]);

        return $id;
    }

    private function faq(string $question, int $active = 1): int
    {
        $id = $this->db->insert('INSERT INTO faqs (is_active) VALUES (?)', [$active]);
        $this->db->run('INSERT INTO faq_translations (faq_id, locale, question, answer) VALUES (?, ?, ?, ?)', [$id, 'en', $question, 'Fixture answer']);

        return $id;
    }

    private function staff(string $name, int $active = 1, string $role = 'content_editor'): int
    {
        $id = $this->db->insert('INSERT INTO users (name, email, password_hash, is_active) VALUES (?, ?, ?, ?)', [$name, bin2hex(random_bytes(6)) . '@example.test', password_hash('Fixture-Only-123!', PASSWORD_DEFAULT), $active]);
        $this->db->run('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE code = ?', [$id, $role]);

        return $id;
    }

    private function get(string $path, array $query): Request
    {
        $_GET = $query;

        return new Request('GET', $path, $query, [], [], [], []);
    }

    private function html(Response $response): string
    {
        return (new ReflectionProperty(Response::class, 'body'))->getValue($response);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
