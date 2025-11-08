<?php

namespace App\Utils;

class DateHelper
{
    /**
     * Get date string
     *
     * @param int $timestamp Unix timestamp
     * @param string $format Date format
     * @return string Formatted date string
     */
    public static function getDateString(int $timestamp, string $format = ''): string
    {
        if (!$format) {
            $format = "Y/m/d(-) H:i:s";
        }
        $datestr = date($format, $timestamp);
        if (str_contains((string) $format, '-')) {
            static $wdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            $datestr = str_replace('-', $wdays[date("w", $timestamp)], $datestr);
        }
        return $datestr;
    }

    /**
     * Calculate microtime difference
     *
     * @param string $a Start microtime
     * @param string $b End microtime
     * @return float Time difference
     */
    public static function microtimeDiff(string $a, string $b): float
    {
        [$a_dec, $a_sec] = explode(" ", $a);
        [$b_dec, $b_sec] = explode(" ", $b);
        return $b_sec - $a_sec + $b_dec - $a_dec;
    }
}
