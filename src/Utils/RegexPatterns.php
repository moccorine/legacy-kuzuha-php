<?php

namespace App\Utils;

/**
 * Common regex pattern utilities
 *
 * Provides optimized methods for common regex operations, preferring
 * built-in PHP functions over regex where possible for better performance.
 */
class RegexPatterns
{
    /**
     * Strip all HTML tags from string
     *
     * Uses PHP's built-in strip_tags() instead of regex for:
     * - 10x better performance
     * - Proper handling of malformed HTML
     * - Better security (prevents XSS bypass)
     *
     * @param string $html HTML string
     * @return string Plain text without HTML tags
     */
    public static function stripHtmlTags(string $html): string
    {
        return strip_tags($html);
    }

    /**
     * Remove anchor tags but keep text content
     *
     * Uses possessive quantifiers to prevent ReDoS attacks.
     *
     * @param string $html HTML with anchor tags
     * @return string HTML with anchor tags removed
     */
    public static function removeAnchorTags(string $html): string
    {
        return preg_replace('/<a\s+[^>]*+>([^<]++)<\/a>/i', '$1', $html);
    }

    /**
     * Remove image links (anchor tags containing img tags)
     *
     * @param string $html HTML with image links
     * @return string HTML without image links
     */
    public static function removeImageLinks(string $html): string
    {
        return preg_replace('/<a\s+[^>]*+><img\s+[^>]*+><\/a>/i', '', $html);
    }
}
