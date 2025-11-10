<?php

namespace App\Utils;

/**
 * HTML manipulation helper utilities
 */
class HtmlHelper
{
    /**
     * Remove reference link from message
     *
     * @param string $message Message HTML
     * @return string Message with reference link removed
     */
    public static function removeReferenceLink(string $message): string
    {
        return preg_replace('/<a href=[^>]+>Reference: [^<]+<\/a>/i', '', $message, 1);
    }

    /**
     * Check if message has reference link at the end
     *
     * @param string $message Message HTML
     * @return bool True if reference link exists at the end
     */
    public static function hasReferenceLinkAtEnd(string $message): bool
    {
        return (bool) preg_match('/\r\r<a href=[^<]+>Reference: [^<]+<\/a>$/', $message);
    }

    /**
     * Insert content before reference link at the end
     *
     * @param string $message Message HTML
     * @param string $content Content to insert
     * @return string Modified message
     */
    public static function insertBeforeReferenceLink(string $message, string $content): string
    {
        return preg_replace(
            '/(\r\r<a href=[^<]+>Reference: [^<]+<\/a>)$/',
            "\r\r{$content}$1",
            $message,
            1
        );
    }
}
