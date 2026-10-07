<?php

declare(strict_types=1);

namespace App\Helpers;

final class Sanitizer
{
    public static function html(string $html): string
    {
        $html = strip_tags($html, '<p><br><strong><em><ul><ol><li><a><h2><h3><blockquote>');
        $html = preg_replace('/\son\w+\s*=\s*("|\').*?\1/i', '', $html) ?? $html;
        $html = preg_replace_callback('/<a\b([^>]*)>/i', static function (array $match): string {
            if (!preg_match('/href\s*=\s*("|\')(.*?)\1/i', $match[1], $href)) {
                return '<a>';
            }
            $url = trim($href[2]);
            if (!preg_match('#^(https?:|mailto:)#i', $url)) {
                return '<a>';
            }

            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" rel="noopener noreferrer">';
        }, $html) ?? $html;

        return $html;
    }

    public static function plain(?string $value): string
    {
        return trim(strip_tags((string) $value));
    }
}
