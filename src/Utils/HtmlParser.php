<?php

namespace App\Utils;

use Symfony\Component\DomCrawler\Crawler;

/**
 * HTML parsing utility using Symfony DomCrawler
 */
class HtmlParser
{
    /**
     * Parse message from HTML buffer
     *
     * @param string $html HTML content
     * @return array Parsed message data
     */
    public static function parseMessage(string $html): array
    {
        $crawler = new Crawler($html);
        $message = [
            'USER' => '',
            'TITLE' => '',
            'MSG' => '',
            'NDATESTR' => '',
        ];

        // Extract user name
        try {
            $message['USER'] = $crawler->filter('.mun')->text();
        } catch (\Exception $e) {
            // Element not found
        }

        // Extract title
        try {
            $message['TITLE'] = $crawler->filter('.ms')->text();
        } catch (\Exception $e) {
            // Element not found
        }

        // Extract message content
        try {
            $message['MSG'] = $crawler->filter('blockquote pre')->text();
        } catch (\Exception $e) {
            // Element not found
        }

        // Extract date
        try {
            $dateText = $crawler->filter('.md')->text();
            if (preg_match('/投稿日：(\d+)\/(\d+)\/(\d+)[^\d]+(\d+)時(\d+)分(\d+)秒/', $dateText, $matches)) {
                $message['date_parts'] = [
                    'year' => $matches[1],
                    'month' => $matches[2],
                    'day' => $matches[3],
                    'hour' => $matches[4],
                    'minute' => $matches[5],
                    'second' => $matches[6],
                ];
            }
        } catch (\Exception $e) {
            // Element not found
        }

        return $message;
    }

    /**
     * Extract text from specific CSS selector
     *
     * @param string $html HTML content
     * @param string $selector CSS selector
     * @return string|null Extracted text or null if not found
     */
    public static function extractText(string $html, string $selector): ?string
    {
        try {
            $crawler = new Crawler($html);
            return $crawler->filter($selector)->text();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if HTML contains specific element
     *
     * @param string $html HTML content
     * @param string $selector CSS selector
     * @return bool True if element exists
     */
    public static function hasElement(string $html, string $selector): bool
    {
        try {
            $crawler = new Crawler($html);
            return $crawler->filter($selector)->count() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
