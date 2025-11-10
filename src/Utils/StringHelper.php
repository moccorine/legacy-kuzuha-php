<?php

namespace App\Utils;

class StringHelper
{
    /**
     * Escape HTML special characters
     *
     * @param string $src Source string
     * @return string Escaped string
     */
    public static function htmlEscape(string $src): string
    {
        $src = htmlspecialchars($src, ENT_QUOTES);
        return $src;
    }

    /**
     * Decode HTML entities
     *
     * @param string $value Value to decode
     * @return string Decoded string
     */
    public static function htmlDecode(string $value): string
    {
        if (!preg_match("/^\w+$/", $value)) {
            $value = strtr($value, array_flip(get_html_translation_table(HTML_ENTITIES)));
            $value = preg_replace_callback('/&#([0-9]+);/m', fn ($matches) => chr($matches[1]), $value);
        }
        return $value;
    }

    /**
     * Fix number string (convert full-width to half-width)
     *
     * @param string|null $numberstr Number string
     * @return string Fixed number string
     */
    public static function fixNumberString(?string $numberstr): string
    {
        $numberstr = trim((string) $numberstr);
        $twobytenumstr =  ['０', '１', '２', '３', '４', '５', '６', '７', '８', '９', ];
        for ($i = 0; $i < count($twobytenumstr); $i++) {
            $numberstr = str_replace($twobytenumstr[$i], "$i", $numberstr);
        }
        return $numberstr;
    }

    /**
     * Escape URL
     *
     * @param string $src_url Source URL
     * @return string Escaped URL
     */
    public static function escapeUrl(string $src_url): string
    {
        $src_url = preg_replace('/script:/i', 'script', (string) $src_url);
        $src_url = urlencode($src_url);
        $src_url = str_replace('%2F', '/', $src_url);
        $src_url = str_replace('%3A', ':', $src_url);
        $src_url = str_replace('%3F', '?', $src_url);
        $src_url = str_replace('%3D', '=', $src_url);
        $src_url = str_replace('%26', '&', $src_url);
        $src_url = str_replace('%23', '#', $src_url);
        return $src_url;
    }

    /**
     * Convert 3-byte hex to base64
     *
     * @param string $hex Hex string
     * @return string Base64 string
     */
    public static function threeByteHexToBase64(string $hex): string
    {
        $bin = pack('H*', $hex);
        $b64 = base64_encode($bin);
        $b64 = str_replace('=', '', $b64);
        return $b64;
    }

    /**
     * Convert base64 to 3-byte hex
     *
     * @param string $b64 Base64 string
     * @return string Hex string
     */
    public static function base64ToThreeByteHex(string $b64): string
    {
        $bin = base64_decode($b64);
        $hex = bin2hex($bin);
        return $hex;
    }

    /**
     * Check value
     *
     * @param string $value Value to check
     * @return string Checked value
     */
    public static function checkValue(string $value): string
    {
        $value = trim($value);
        $value = str_replace("\0", '', $value);
        return $value;
    }

    /**
     * Convert image tags to text
     *
     * @param string $value Value containing image tags
     * @return string Converted value
     */
    public static function convertImageTag(string $value): string
    {
        if ($value == '') {
            return $value;
        }
        while (preg_match("/(<a href=[^>]+>)<img ([^>]+)>(<\/a>)/i", $value, $matches)) {
            if (preg_match('/alt="([^"]+)"/', $matches[2], $submatches)) {
                $altvalue = $submatches[1];
            } elseif (preg_match('/src="([^"]+)"/', $matches[2], $submatches)) {
                $altvalue = substr($submatches[1], strrpos($submatches[1], '/'));
            }
            $value = str_replace($matches[0], " [{$matches[1]}{$altvalue}{$matches[3]}] ", $value);
        }
        return $value;
    }

    /**
     * Remove non-alphanumeric characters from string
     *
     * @param string $text Input text
     * @return string Text with only alphanumeric characters
     */
    public static function removeNonAlphanumeric(string $text): string
    {
        return implode('', array_filter(str_split($text), 'ctype_alnum'));
    }

    /**
     * Clean message text by removing HTML and reference links
     *
     * @param string $message Message text
     * @return string Cleaned message text
     */
    public static function cleanMessageText(string $message): string
    {
        $message = HtmlHelper::removeReferenceLink($message);
        return RegexPatterns::stripHtmlTags(ltrim($message));
    }

    /**
     * Create message digest (first ~50 characters)
     *
     * @param string $message Message text
     * @param int $maxLength Maximum length of digest
     * @return string Message digest
     */
    public static function createMessageDigest(string $message, int $maxLength = 50): string
    {
        $lines = explode("\r", $message);
        $digest = $lines[0];

        for ($i = 1; $i < count($lines) - 1 && strlen($digest . $lines[$i]) < $maxLength; $i++) {
            $digest .= $lines[$i];
        }

        return $digest;
    }
}
