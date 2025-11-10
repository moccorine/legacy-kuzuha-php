<?php

namespace App\Utils;

use Detection\MobileDetect;

/**
 * User Agent detection helper using Mobile Detect
 */
class UserAgentHelper
{
    private static ?MobileDetect $detector = null;

    /**
     * Get Mobile Detect instance (create new instance each time for testing)
     */
    private static function getDetector(): MobileDetect
    {
        // Always create new instance to pick up $_SERVER changes
        return new MobileDetect();
    }

    /**
     * Check if browser supports download (modern browser check)
     *
     * @return bool True if browser is modern enough
     */
    public static function supportsDownload(): bool
    {
        if (!isset($_SERVER['HTTP_USER_AGENT']) || empty($_SERVER['HTTP_USER_AGENT'])) {
            return true; // Allow if no user agent
        }

        $detect = self::getDetector();

        // Modern browsers (Chrome, Firefox, Safari, Edge, Opera)
        if ($detect->is('Chrome') || $detect->is('Firefox') ||
            $detect->is('Safari') || $detect->is('Edge') || $detect->is('Opera')) {
            return true;
        }

        // Check for IE version
        $ieVersion = $detect->version('IE');
        if ($ieVersion !== false) {
            // IE 5+ on Windows is supported
            if ((float)$ieVersion >= 5.0 && !$detect->is('OS X')) {
                return true;
            }
            return false;
        }

        // Default: allow for unknown browsers
        return true;
    }

    /**
     * Get browser name
     *
     * @return string Browser name
     */
    public static function getBrowserName(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (stripos($ua, 'Edg/') !== false || stripos($ua, 'Edge/') !== false) {
            return 'Edge';
        }
        if (stripos($ua, 'Chrome/') !== false && stripos($ua, 'Edg') === false) {
            return 'Chrome';
        }
        if (stripos($ua, 'Firefox/') !== false) {
            return 'Firefox';
        }
        if (stripos($ua, 'Safari/') !== false && stripos($ua, 'Chrome') === false) {
            return 'Safari';
        }
        if (stripos($ua, 'Opera') !== false || stripos($ua, 'OPR/') !== false) {
            return 'Opera';
        }
        if (stripos($ua, 'MSIE') !== false || stripos($ua, 'Trident/') !== false) {
            return 'IE';
        }

        return 'Unknown';
    }

    /**
     * Get browser version
     *
     * @param string|null $browser Browser name (auto-detect if null)
     * @return string|false Browser version or false if not detected
     */
    public static function getBrowserVersion(?string $browser = null)
    {
        $detect = self::getDetector();

        if ($browser === null) {
            $browser = self::getBrowserName();
        }

        return $detect->version($browser);
    }

    /**
     * Check if OS is Mac
     *
     * @return bool True if Mac OS
     */
    public static function isMac(): bool
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return stripos($ua, 'Macintosh') !== false || stripos($ua, 'Mac OS X') !== false;
    }

    /**
     * Check if OS is Windows
     *
     * @return bool True if Windows
     */
    public static function isWindows(): bool
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return stripos($ua, 'Windows') !== false;
    }

    /**
     * Check if mobile device
     *
     * @return bool True if mobile
     */
    public static function isMobile(): bool
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return stripos($ua, 'Mobile') !== false || stripos($ua, 'iPhone') !== false;
    }

    /**
     * Check if tablet device
     *
     * @return bool True if tablet
     */
    public static function isTablet(): bool
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return stripos($ua, 'iPad') !== false || stripos($ua, 'Tablet') !== false;
    }

    /**
     * Get OS name
     *
     * @return string OS name
     */
    public static function getOSName(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Check iOS first (before Mac OS X check)
        if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false || stripos($ua, 'iPod') !== false) {
            return 'iOS';
        }
        if (stripos($ua, 'Android') !== false) {
            return 'Android';
        }
        if (stripos($ua, 'Windows') !== false) {
            return 'Windows';
        }
        if (stripos($ua, 'Macintosh') !== false || stripos($ua, 'Mac OS X') !== false) {
            return 'OS X';
        }
        if (stripos($ua, 'Linux') !== false) {
            return 'Linux';
        }

        return 'Unknown';
    }
}
