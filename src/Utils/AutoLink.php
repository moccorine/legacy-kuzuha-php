<?php

namespace App\Utils;

/**
 * Auto-link utility for converting URLs to HTML links
 */
class AutoLink
{
    /**
     * Convert URLs in text to clickable links
     * Supports http, https protocols only (ftp and news removed as unnecessary)
     *
     * @param string $text Text containing URLs
     * @return string Text with URLs converted to links
     */
    public static function convert(string $text): string
    {
        return preg_replace(
            "/((https?):\/\/[-_.,!~*'()a-zA-Z0-9;\/?:\@&=+\$,%#]+)/",
            '<a href="$1" target="link">$1</a>',
            $text
        );
    }
}
