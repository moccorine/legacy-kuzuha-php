<?php

namespace App\Utils;

class NetworkHelper
{
    /**
     * Get user environment information
     *
     * @return array [addr, host, proxyflg, realaddr, realhost]
     */
    public static function getUserEnv(): array
    {
        $addr = $_SERVER['REMOTE_ADDR'];
        $host = $_SERVER['REMOTE_HOST'];
        $agent = $_SERVER['HTTP_USER_AGENT'];
        if ($addr == $host or !$host) {
            $host = gethostbyaddr($addr);
        }

        $proxyflg = 0;

        if ($_SERVER['HTTP_CACHE_CONTROL']) {
            $proxyflg = 1;
        }
        if ($_SERVER['HTTP_CACHE_INFO']) {
            $proxyflg += 2;
        }
        if ($_SERVER['HTTP_CLIENT_IP']) {
            $proxyflg += 4;
        }
        if ($_SERVER['HTTP_FORWARDED']) {
            $proxyflg += 8;
        }
        if ($_SERVER['HTTP_FROM']) {
            $proxyflg += 16;
        }
        if ($_SERVER['HTTP_PROXY_AUTHORIZATION']) {
            $proxyflg += 32;
        }
        if ($_SERVER['HTTP_PROXY_CONNECTION']) {
            $proxyflg += 64;
        }
        if ($_SERVER['HTTP_SP_HOST']) {
            $proxyflg += 128;
        }
        if ($_SERVER['HTTP_VIA']) {
            $proxyflg += 256;
        }
        if ($_SERVER['HTTP_X_FORWARDED_FOR']) {
            $proxyflg += 512;
        }
        if ($_SERVER['HTTP_X_LOCKING']) {
            $proxyflg += 1024;
        }
        if (preg_match('/cache|delegate|gateway|httpd|proxy|squid|www|via/i', (string) $agent)) {
            $proxyflg += 2048;
        }
        if (preg_match('/cache|^dns|dummy|^ns|firewall|gate|keep|mail|^news|pop|proxy|smtp|w3|^web|www/i', (string) $host)) {
            $proxyflg += 4096;
        }
        if ($host == $addr) {
            $proxyflg += 8192;
        }

        $realaddr = '';
        $realhost = '';
        if ($proxyflg > 0) {
            $matches = [];
            if (preg_match("/^(\d+)\.(\d+)\.(\d+)\.(\d+)/", (string) $_SERVER['HTTP_X_FORWARDED_FOR'], $matches)) {
                $realaddr = "{$matches[1]}.{$matches[2]}.{$matches[3]}.{$matches[4]}";
            } elseif (preg_match("/(\d+)\.(\d+)\.(\d+)\.(\d+)/", (string) $_SERVER['HTTP_FORWARDED'], $matches)) {
                $realaddr = "{$matches[1]}.{$matches[2]}.{$matches[3]}.{$matches[4]}";
            } elseif (preg_match("/(\d+)\.(\d+)\.(\d+)\.(\d+)/", (string) $_SERVER['HTTP_VIA'], $matches)) {
                $realaddr = "{$matches[1]}.{$matches[2]}.{$matches[3]}.{$matches[4]}";
            } elseif (preg_match("/(\d+)\.(\d+)\.(\d+)\.(\d+)/", (string) $_SERVER['HTTP_CLIENT_IP'], $matches)) {
                $realaddr = "{$matches[1]}.{$matches[2]}.{$matches[3]}.{$matches[4]}";
            } elseif (preg_match("/(\d+)\.(\d+)\.(\d+)\.(\d+)/", (string) $_SERVER['HTTP_SP_HOST'], $matches)) {
                $realaddr = "{$matches[1]}.{$matches[2]}.{$matches[3]}.{$matches[4]}";
            } elseif (preg_match("/.*\sfor\s(.+)/", (string) $_SERVER['HTTP_FORWARDED'], $matches)) {
                $realhost = $matches[1];
            } elseif (preg_match("/\-\@(.+)/", (string) $_SERVER['HTTP_FROM'], $matches)) {
                $realhost = $matches[1];
            }
            if (!$realaddr and $realhost) {
                $realaddr = gethostbyname($realhost);
            }
            if ($realaddr and !$realhost) {
                $realaddr = gethostbyname($realhost);
            }
        }
        return [$addr, $host, $proxyflg, $realaddr, $realhost];
    }

    /**
     * Check if IP is in CIDR range
     *
     * @param string $cidraddr CIDR address
     * @param string $checkaddr IP to check
     * @return bool True if in range
     */
    public static function checkIpRange(string $cidraddr, string $checkaddr): bool
    {
        [$netaddr, $cidrmask] = explode('/', $cidraddr);
        $netaddr_long = ip2long($netaddr);
        $cidrmask = 2 ** (32 - $cidrmask) - 1;
        $bits1 = str_pad(decbin($netaddr_long), 32, '0', STR_PAD_LEFT);
        $bits2 = str_pad(decbin($cidrmask), 32, '0', STR_PAD_LEFT);
        $final = '';
        for ($i = 0; $i < 32; $i++) {
            if ($bits2[$i] == '1') {
                $final .= 'x';
            } else {
                $final .= $bits1[$i];
            }
        }
        $checkaddr_long = ip2long($checkaddr);
        $bits3 = str_pad(decbin($checkaddr_long), 32, '0', STR_PAD_LEFT);
        for ($i = 0; $i < 32; $i++) {
            if ($final[$i] != 'x' and $final[$i] != $bits3[$i]) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if hostname matches patterns
     *
     * @param array $hostlist Host patterns
     * @param array $hostagent Agent patterns
     * @return bool True if matched
     */
    public static function hostnameMatch(array $hostlist, array $hostagent): bool
    {
        if (!$hostlist or !$hostagent) {
            return false;
        }
        $hit = false;
        [$addr, $host, $proxyflg, $realaddr, $realhost] = self::getUserEnv();
        $agent = $_SERVER['HTTP_USER_AGENT'];
        foreach ($hostlist as $hostpattern) {
            foreach ($hostagent as $hostagentpattern) {
                if ((preg_match("/$hostpattern/", (string) $host) or preg_match("/$hostpattern/", (string) $realhost)) or preg_match("/$hostagentpattern/", (string) $agent)) {
                    $hit = true;
                    break;
                }
            }
        }
        return $hit;
    }
}
