<?php

namespace App\Utils;

/**
 * Validation regex patterns
 * 
 * Pre-compiled regex patterns for common validation tasks.
 * Uses possessive quantifiers to prevent ReDoS attacks.
 */
class ValidationRegex
{
    // Pre-compiled patterns as constants
    private const FILENAME_PATTERN = '/^[\w.]++$/';
    private const HEX_COLOR_PATTERN = '/^[0-9a-fA-F]{6}$/';
    private const NUMERIC_PATTERN = '/^\d++$/';
    private const URL_PROTOCOL_PATTERN = '/^(https?):\/\//';
    
    /**
     * Validate filename (alphanumeric, dot, underscore only)
     * 
     * @param string $filename Filename to validate
     * @return bool True if valid
     */
    public static function isValidFilename(string $filename): bool
    {
        return (bool) preg_match(self::FILENAME_PATTERN, $filename);
    }
    
    /**
     * Validate hex color code (6 digits)
     * 
     * @param string $color Color code to validate
     * @return bool True if valid hex color
     */
    public static function isValidHexColor(string $color): bool
    {
        return strlen($color) === 6 
            && (bool) preg_match(self::HEX_COLOR_PATTERN, $color);
    }
    
    /**
     * Check if string is numeric only
     * 
     * @param string $value String to check
     * @return bool True if numeric
     */
    public static function isNumeric(string $value): bool
    {
        return (bool) preg_match(self::NUMERIC_PATTERN, $value);
    }
    
    /**
     * Check if URL has http/https protocol
     * 
     * @param string $url URL to check
     * @return bool True if has protocol
     */
    public static function hasHttpProtocol(string $url): bool
    {
        return (bool) preg_match(self::URL_PROTOCOL_PATTERN, $url);
    }
    
    /**
     * Match file with specific extension
     * 
     * @param string $filename Filename to check
     * @param string $extension Extension (without dot)
     * @return bool True if matches
     */
    public static function hasExtension(string $filename, string $extension): bool
    {
        $pattern = '/\.' . preg_quote($extension, '/') . '$/';
        return (bool) preg_match($pattern, $filename);
    }
    
    /**
     * Match numeric filename with extension (e.g., "12345.dat")
     * 
     * @param string $filename Filename to check
     * @param string $extension Extension (without dot)
     * @return bool True if matches pattern
     */
    public static function isNumericFilename(string $filename, string $extension): bool
    {
        $pattern = '/^\d++\.' . preg_quote($extension, '/') . '$/';
        return (bool) preg_match($pattern, $filename);
    }
}
