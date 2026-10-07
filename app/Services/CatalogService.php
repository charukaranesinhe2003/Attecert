<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class CatalogService
{
    public function __construct(private Database $db)
    {
    }

    public function services(bool $publicOnly = false, bool $featuredOnly = false): array
    {
        $sql = 'SELECT s.*, t.name, t.summary, t.description, t.process_text, t.documents_required, t.seo_title, t.meta_description
                FROM services s
                LEFT JOIN service_translations t ON t.service_id = s.id AND t.locale = ?
                WHERE s.deleted_at IS NULL';
        if ($publicOnly) {
            $sql .= ' AND s.is_active = 1 AND s.is_public = 1';
        }
        if ($featuredOnly) {
            $sql .= ' AND s.featured = 1';
        }
        $sql .= ' ORDER BY s.sort_order, s.id';

        return $this->withEnglishFallback($this->db->fetchAll($sql, [locale()]), 'services', 'service_id');
    }

    public function serviceBySlug(string $slug): ?array
    {
        $row = $this->db->fetch(
            'SELECT s.*, t.name, t.summary, t.description, t.process_text, t.documents_required, t.seo_title, t.meta_description
             FROM services s
             LEFT JOIN service_translations t ON t.service_id = s.id AND t.locale = ?
             WHERE s.slug = ? AND s.deleted_at IS NULL',
            [locale(), $slug]
        );

        return $row === null ? null : $this->withEnglishFallback([$row], 'services', 'service_id')[0];
    }

    public function service(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM services WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    public function serviceTranslations(int $id): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM service_translations WHERE service_id = ?', [$id]);
        $map = [];
        foreach ($rows as $row) {
            $map[$row['locale']] = $row;
        }

        return $map;
    }

    public function countries(bool $publicOnly = false): array
    {
        $sql = 'SELECT c.*, t.name, t.summary, t.requirements, t.documents_required, t.process_text, t.notes, t.processing_info, t.seo_title, t.meta_description
                FROM countries c
                LEFT JOIN country_translations t ON t.country_id = c.id AND t.locale = ?
                WHERE c.deleted_at IS NULL';
        if ($publicOnly) {
            $sql .= ' AND c.is_active = 1';
        }
        $sql .= ' ORDER BY c.sort_order, c.id';

        return $this->withEnglishFallback($this->db->fetchAll($sql, [locale()]), 'countries', 'country_id');
    }

    public function countryBySlug(string $slug): ?array
    {
        $row = $this->db->fetch(
            'SELECT c.*, t.name, t.summary, t.requirements, t.documents_required, t.process_text, t.notes, t.processing_info, t.seo_title, t.meta_description
             FROM countries c
             LEFT JOIN country_translations t ON t.country_id = c.id AND t.locale = ?
             WHERE c.slug = ? AND c.deleted_at IS NULL',
            [locale(), $slug]
        );
        if ($row === null) {
            return null;
        }
        $country = $this->withEnglishFallback([$row], 'countries', 'country_id')[0];
        $country['services'] = $this->db->fetchAll(
            'SELECT s.id, s.slug, s.pricing_type, s.fixed_price, s.currency, s.icon, st.name, st.summary, cs.notes
             FROM country_services cs
             INNER JOIN services s ON s.id = cs.service_id AND s.deleted_at IS NULL
             LEFT JOIN service_translations st ON st.service_id = s.id AND st.locale = ?
             WHERE cs.country_id = ?
             ORDER BY s.sort_order',
            [locale(), $country['id']]
        );
        $country['faqs'] = $this->faqs(false);

        return $country;
    }

    public function documentTypes(): array
    {
        return $this->db->fetchAll(
            'SELECT d.*, t.name
             FROM document_types d
             LEFT JOIN document_type_translations t ON t.document_type_id = d.id AND t.locale = ?
             WHERE d.is_active = 1
             ORDER BY d.sort_order',
            [locale()]
        );
    }

    public function faqs(bool $homeOnly = false): array
    {
        $sql = 'SELECT f.*, t.question, t.answer
                FROM faqs f
                LEFT JOIN faq_translations t ON t.faq_id = f.id AND t.locale = ?
                WHERE f.is_active = 1';
        if ($homeOnly) {
            $sql .= ' AND f.show_on_home = 1';
        }
        $sql .= ' ORDER BY f.sort_order, f.id';
        $rows = $this->db->fetchAll($sql, [locale()]);
        foreach ($rows as &$row) {
            if ($row['question'] === null) {
                $fallback = $this->db->fetch('SELECT question, answer FROM faq_translations WHERE faq_id = ? AND locale = \'en\'', [$row['id']]);
                $row['question'] = $fallback['question'] ?? '';
                $row['answer'] = $fallback['answer'] ?? '';
            }
        }

        return $rows;
    }

    public function articles(bool $publishedOnly = true, int $limit = 0): array
    {
        $sql = 'SELECT a.*, t.title, t.excerpt, t.content, t.seo_title, t.meta_description, u.name AS author_name
                FROM articles a
                LEFT JOIN article_translations t ON t.article_id = a.id AND t.locale = ?
                LEFT JOIN users u ON u.id = a.author_id
                WHERE a.deleted_at IS NULL';
        if ($publishedOnly) {
            $sql .= ' AND a.status = \'published\' AND (a.published_at IS NULL OR a.published_at <= NOW())';
        }
        $sql .= ' ORDER BY a.published_at DESC, a.id DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        return $this->db->fetchAll($sql, [locale()]);
    }

    public function articleBySlug(string $slug): ?array
    {
        return $this->db->fetch(
            'SELECT a.*, t.title, t.excerpt, t.content, t.seo_title, t.meta_description, u.name AS author_name
             FROM articles a
             LEFT JOIN article_translations t ON t.article_id = a.id AND t.locale = ?
             LEFT JOIN users u ON u.id = a.author_id
             WHERE a.slug = ? AND a.deleted_at IS NULL AND a.status = \'published\'',
            [locale(), $slug]
        );
    }

    public function priceFor(array $service, ?int $countryId = null): ?string
    {
        if ($countryId !== null) {
            $override = $this->db->fetchColumn(
                'SELECT price_override FROM country_services WHERE country_id = ? AND service_id = ? AND price_override IS NOT NULL',
                [$countryId, $service['id']]
            );
            if ($override !== false && $override !== null) {
                return (string) $override;
            }
        }
        if (($service['pricing_type'] ?? '') === 'fixed' && $service['fixed_price'] !== null) {
            return (string) $service['fixed_price'];
        }

        return null;
    }

    private function withEnglishFallback(array $rows, string $table, string $foreign): array
    {
        if (locale() === 'en') {
            return $rows;
        }
        foreach ($rows as &$row) {
            if (($row['name'] ?? null) !== null && $row['name'] !== '') {
                continue;
            }
            $fallback = $this->db->fetch(
                'SELECT * FROM ' . ($table === 'services' ? 'service_translations' : 'country_translations') . ' WHERE ' . $foreign . ' = ? AND locale = \'en\'',
                [$row['id']]
            );
            if ($fallback) {
                foreach ($fallback as $key => $value) {
                    if ($key === $foreign || $key === 'locale') {
                        continue;
                    }
                    if (!isset($row[$key]) || $row[$key] === null || $row[$key] === '') {
                        $row[$key] = $value;
                    }
                }
            }
        }

        return $rows;
    }
}
