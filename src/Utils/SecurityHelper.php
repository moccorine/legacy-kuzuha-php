<?php

namespace App\Utils;

use App\Config;

class SecurityHelper
{
    /**
     * Generate protect code
     *
     * @param bool $limithost Whether to check for same host
     * @return string Protect code
     */
    public static function generateProtectCode(bool $limithost = true): string
    {
        $timestamp = CURRENT_TIME;
        $ukey = 0;
        if ($limithost) {
            $remoteaddr = '0.0.0.0';
            if ($_SERVER['REMOTE_ADDR']) {
                $remoteaddr = $_SERVER['REMOTE_ADDR'];
            }
            $ukey = hexdec(substr(md5((string) $remoteaddr), 0, 8));
        }

        $basecode =  dechex($timestamp + $ukey);
        $adminPost = Config::getInstance()->get('ADMINPOST');
        $cryptcode = crypt($basecode . substr((string) $adminPost, -4), substr((string) $adminPost, -4) . $basecode);
        $cryptcode = substr((string) preg_replace("/\W/", '', $cryptcode), -4);
        $pcode = dechex($timestamp) . $cryptcode;
        return $pcode;
    }

    /**
     * Verify protect code
     *
     * @param string $pcode Protect code
     * @param bool $limithost Whether to check for same host
     * @return int|null Timestamp or null if invalid
     */
    public static function verifyProtectCode(string $pcode, bool $limithost = true): ?int
    {
        if (strlen($pcode) != 12) {
            return null;
        }
        $timestamphex = substr($pcode, 0, 8);
        $cryptcode = substr($pcode, 8, 4);

        $ukey = 0;
        if ($limithost) {
            $remoteaddr = '0.0.0.0';
            if ($_SERVER['REMOTE_ADDR']) {
                $remoteaddr = $_SERVER['REMOTE_ADDR'];
            }
            $ukey = hexdec(substr(md5((string) $remoteaddr), 0, 8));
        }

        $timestamp = hexdec($timestamphex);
        $basecode = dechex($timestamp + $ukey);
        $adminPost = Config::getInstance()->get('ADMINPOST');
        $verifycode = crypt($basecode . substr((string) $adminPost, -4), substr((string) $adminPost, -4) . $basecode);
        $verifycode = substr((string) preg_replace("/\W/", '', $verifycode), -4);
        if ($cryptcode != $verifycode) {
            return null;
        }
        return $timestamp;
    }

    /**
     * Encrypt admin password using crypt()
     * 
     * TODO: Replace with password_hash() for better security
     * Current implementation uses legacy crypt() for backward compatibility
     * 
     * @param string $password Plain text password
     * @return string Encrypted password
     */
    public static function encryptAdminPassword(string $password): string
    {
        $salt = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 2);
        return crypt($password, $salt);
    }

    /**
     * Verify admin password
     * 
     * @param string $password Plain text password
     * @param string $hash Encrypted password hash
     * @return bool True if password matches
     */
    public static function verifyAdminPassword(string $password, string $hash): bool
    {
        return crypt($password, $hash) === $hash;
    }
}
