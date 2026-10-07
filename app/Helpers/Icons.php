<?php

declare(strict_types=1);

namespace App\Helpers;

final class Icons
{
    public static function render(string $name, string $class = 'icon'): string
    {
        $paths = self::paths();
        $body = $paths[$name] ?? $paths['dot'];

        return '<svg class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $body . '</svg>';
    }

    private static function paths(): array
    {
        $s = 'fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"';

        return [
            'menu' => "<path {$s} d=\"M4 7h16M4 12h16M4 17h16\"/>",
            'close' => "<path {$s} d=\"M6 6l12 12M18 6L6 18\"/>",
            'search' => "<circle {$s} cx=\"11\" cy=\"11\" r=\"6\"/><path {$s} d=\"M20 20l-3.5-3.5\"/>",
            'phone' => "<path {$s} d=\"M7 3h3l1.5 4-2 1.5a12 12 0 0 0 6 6L17 13l4 1.5V18a2 2 0 0 1-2 2A15 15 0 0 1 4 5a2 2 0 0 1 2-2z\"/>",
            'mail' => "<rect {$s} x=\"3\" y=\"5\" width=\"18\" height=\"14\" rx=\"2\"/><path {$s} d=\"M4 7l8 6 8-6\"/>",
            'whatsapp' => "<path {$s} d=\"M5 19l1.2-3.4A8 8 0 1 1 8.6 18L5 19z\"/><path {$s} d=\"M9 10c.3 1.6 2.2 3.3 4 3.8\"/>",
            'arrow' => "<path {$s} d=\"M5 12h14M13 6l6 6-6 6\"/>",
            'arrow-up' => "<path {$s} d=\"M12 19V5M6 11l6-6 6 6\"/>",
            'user' => "<circle {$s} cx=\"12\" cy=\"8\" r=\"3\"/><path {$s} d=\"M5 19c1.5-3 3.8-4.5 7-4.5S17.5 16 19 19\"/>",
            'dashboard' => "<rect {$s} x=\"3\" y=\"3\" width=\"8\" height=\"8\" rx=\"1.5\"/><rect {$s} x=\"13\" y=\"3\" width=\"8\" height=\"5\" rx=\"1.5\"/><rect {$s} x=\"13\" y=\"10\" width=\"8\" height=\"11\" rx=\"1.5\"/><rect {$s} x=\"3\" y=\"13\" width=\"8\" height=\"8\" rx=\"1.5\"/>",
            'document' => "<path {$s} d=\"M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z\"/><path {$s} d=\"M14 3v6h6M8 13h8M8 17h6\"/>",
            'certificate' => "<circle {$s} cx=\"12\" cy=\"9\" r=\"5\"/><path {$s} d=\"M9 13.5V21l3-1.5L15 21v-7.5\"/>",
            'stamp' => "<circle {$s} cx=\"12\" cy=\"10\" r=\"6\"/><path {$s} d=\"M8 16v4h8v-4M9 10h6\"/>",
            'building' => "<path {$s} d=\"M4 20h16M6 20V6l6-3 6 3v14M10 10h.01M14 10h.01M10 14h.01M14 14h.01\"/>",
            'globe' => "<circle {$s} cx=\"12\" cy=\"12\" r=\"8\"/><path {$s} d=\"M4 12h16M12 4c2.5 2.4 2.5 13.6 0 16M12 4c-2.5 2.4-2.5 13.6 0 16\"/>",
            'translate' => "<path {$s} d=\"M4 5h9M8.5 5c0 6-3 9-6 10M5 9c1.2 1.6 3 2.8 5.2 3.4M13 19l4-10 4 10M14.5 16h5\"/>",
            'check' => "<path {$s} d=\"M5 12.5l4.2 4.2L19 7\"/>",
            'shield' => "<path {$s} d=\"M12 3l7 3v6c0 4.5-2.8 7.4-7 9-4.2-1.6-7-4.5-7-9V6l7-3z\"/><path {$s} d=\"M9 12l2 2 4-4\"/>",
            'clock' => "<circle {$s} cx=\"12\" cy=\"12\" r=\"8\"/><path {$s} d=\"M12 8v5l3 2\"/>",
            'upload' => "<path {$s} d=\"M12 16V5M8 9l4-4 4 4M5 19h14\"/>",
            'download' => "<path {$s} d=\"M12 5v11M8 12l4 4 4-4M5 19h14\"/>",
            'edit' => "<path {$s} d=\"M4 20h4l10-10-4-4L4 16v4zM13 7l4 4\"/>",
            'trash' => "<path {$s} d=\"M5 7h14M9 7V5h6v2M8 7l1 13h6l1-13\"/>",
            'eye' => "<path {$s} d=\"M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z\"/><circle {$s} cx=\"12\" cy=\"12\" r=\"2.5\"/>",
            'filter' => "<path {$s} d=\"M4 5h16l-6 7v6l-4 2v-8L4 5z\"/>",
            'bell' => "<path {$s} d=\"M6 16V10a6 6 0 1 1 12 0v6l1.5 2h-15L6 16zM10 19a2 2 0 0 0 4 0\"/>",
            'invoice' => "<path {$s} d=\"M7 3h10v18l-2-1.2L13 21l-2-1.2L9 21l-2-1.2V3z\"/><path {$s} d=\"M9 8h6M9 12h6M9 16h4\"/>",
            'quote' => "<path {$s} d=\"M7 17V7h10v8H10l-3 2z\"/><path {$s} d=\"M10 10h4M10 13h3\"/>",
            'payment' => "<rect {$s} x=\"3\" y=\"6\" width=\"18\" height=\"12\" rx=\"2\"/><path {$s} d=\"M3 10h18M7 15h4\"/>",
            'users' => "<circle {$s} cx=\"9\" cy=\"9\" r=\"3\"/><path {$s} d=\"M3.5 19c1-2.6 2.8-4 5.5-4s4.5 1.4 5.5 4\"/><circle {$s} cx=\"17\" cy=\"9\" r=\"2.2\"/><path {$s} d=\"M16.5 15c1.8.3 3.2 1.4 4 3.5\"/>",
            'flag' => "<path {$s} d=\"M6 21V4M6 5h11l-2 3 2 3H6\"/>",
            'pin' => "<path {$s} d=\"M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z\"/><circle {$s} cx=\"12\" cy=\"10\" r=\"2\"/>",
            'calendar' => "<rect {$s} x=\"4\" y=\"5\" width=\"16\" height=\"15\" rx=\"2\"/><path {$s} d=\"M8 3v4M16 3v4M4 10h16\"/>",
            'logout' => "<path {$s} d=\"M10 7V5a1 1 0 0 1 1-1h8v16h-8a1 1 0 0 1-1-1v-2M4 12h10M11 8l4 4-4 4\"/>",
            'plus' => "<path {$s} d=\"M12 5v14M5 12h14\"/>",
            'chevron' => "<path {$s} d=\"M9 6l6 6-6 6\"/>",
            'chevron-down' => "<path {$s} d=\"M6 9l6 6 6-6\"/>",
            'home' => "<path {$s} d=\"M4 11l8-7 8 7v9a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9z\"/>",
            'lock' => "<rect {$s} x=\"5\" y=\"11\" width=\"14\" height=\"9\" rx=\"2\"/><path {$s} d=\"M8 11V8a4 4 0 0 1 8 0v3\"/>",
            'send' => "<path {$s} d=\"M4 12l16-7-6 16-2.5-6.5L4 12z\"/>",
            'print' => "<path {$s} d=\"M7 8V4h10v4M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2\"/><rect {$s} x=\"7\" y=\"14\" width=\"10\" height=\"6\"/>",
            'alert' => "<circle {$s} cx=\"12\" cy=\"12\" r=\"8\"/><path {$s} d=\"M12 8v5M12 16h.01\"/>",
            'star' => "<path {$s} d=\"M12 3l2.4 5.2L20 9l-4 3.8.9 5.7L12 16.8 7.1 18.5 8 12.8 4 9l5.6-.8L12 3z\"/>",
            'file' => "<path {$s} d=\"M7 3h7l5 5v13H7z\"/><path {$s} d=\"M14 3v6h6\"/>",
            'dot' => "<circle cx=\"12\" cy=\"12\" r=\"3\" fill=\"currentColor\"/>",
            'language' => "<circle {$s} cx=\"12\" cy=\"12\" r=\"8\"/><path {$s} d=\"M3 12h18M12 4a12 12 0 0 1 0 16M12 4a12 12 0 0 0 0 16\"/>",
            'support' => "<path {$s} d=\"M5 16v-3a7 7 0 0 1 14 0v3\"/><rect {$s} x=\"3\" y=\"14\" width=\"4\" height=\"6\" rx=\"1\"/><rect {$s} x=\"17\" y=\"14\" width=\"4\" height=\"6\" rx=\"1\"/>",
            'chart' => "<path {$s} d=\"M4 19V5M4 19h16\"/><path {$s} d=\"M8 16v-4M12 16V8M16 16v-6\"/>",
            'settings' => "<circle {$s} cx=\"12\" cy=\"12\" r=\"3\"/><path {$s} d=\"M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M18.4 5.6L17 7M7 17l-1.4 1.4\"/>",
            'archive' => "<path {$s} d=\"M4 7h16v12H4zM3 4h18v3H3zM10 12h4\"/>",
        ];
    }
}
