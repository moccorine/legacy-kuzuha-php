<?php

namespace App\Utils;

/**
 * Quote formatting utilities
 *
 * Provides optimized methods for quote formatting, replacing multiple
 * sequential regex operations with efficient string operations.
 */
class QuoteRegex
{
    /**
     * Remove nested quote markers (> >)
     *
     * @param string $text Text with potential nested quotes
     * @return string Text without nested quotes
     */
    public static function removeNestedQuotes(string $text): string
    {
        return preg_replace("/&gt; &gt;[^\r]++\r/", '', $text);
    }

    /**
     * Add quote prefix to all lines
     *
     * Uses str_replace instead of regex for better performance.
     *
     * @param string $text Text to quote
     * @return string Quoted text
     */
    public static function addQuotePrefix(string $text): string
    {
        return '> ' . str_replace("\r", "\r> ", $text) . "\r";
    }

    /**
     * Clean empty quote lines
     *
     * Matches original pattern: /\r>\s+\r/ and /\r>\s+\r$/
     *
     * @param string $text Quoted text
     * @return string Cleaned text
     */
    public static function cleanEmptyQuoteLines(string $text): string
    {
        // Remove empty quote lines (\r> with whitespace followed by \r)
        $text = preg_replace("/\r>\s+\r/", "\r", $text);
        // Remove trailing empty quote
        $text = preg_replace("/\r>\s+\r$/", "\r", $text);
        return $text;
    }

    /**
     * Format message as quote (full pipeline)
     *
     * Replaces 8 sequential regex operations with optimized pipeline.
     *
     * @param string $message Original message
     * @param bool $removeLinks Remove links from quote
     * @param string|null $followLinkBase Base URL for follow links to remove
     * @return string Formatted quote
     */
    public static function formatAsQuote(string $message, bool $removeLinks = true, ?string $followLinkBase = null): string
    {
        // Remove nested quotes
        $message = self::removeNestedQuotes($message);

        // Remove links if requested
        if ($removeLinks) {
            // Remove follow links if base URL provided
            if ($followLinkBase) {
                $pattern = "/<a\s+href=\"" . preg_quote($followLinkBase, '/') . "[^\"]*+\"[^>]*+>[^<]++<\/a>/i";
                $message = preg_replace($pattern, '', $message);
            }

            // Remove anchor tags but keep text
            $message = RegexPatterns::removeAnchorTags($message);

            // Remove image links
            $message = RegexPatterns::removeImageLinks($message);
        }

        // Add quote prefix (using fast str_replace)
        $message = self::addQuotePrefix($message);

        // Clean up empty quote lines
        $message = self::cleanEmptyQuoteLines($message);

        return $message;
    }
}
