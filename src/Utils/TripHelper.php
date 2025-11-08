<?php

namespace App\Utils;

class TripHelper
{
    /**
     * Generate tripcode from key
     *
     * @param string $key Key string (may contain # separator)
     * @return string Generated tripcode
     */
    public static function generate(string $key): string
    {
        $key = mb_convert_encoding($key, 'SJIS', 'UTF-8');

        # Trip
        $trip = '';
        if (preg_match("/([^\#]*)\#(.+)/", $key, $match)) {
            if (strlen($match[2]) >= 12) {
                # New conversion method
                $mark = substr($match[2], 0, 1);
                if ($mark == '#' || $mark == '$') {
                    if (preg_match('|^#([[:xdigit:]]{16})([./0-9A-Za-z]{0,2})$|', $match[2], $str)) {
                        $trip = substr(crypt(pack('H*', $str[1]), "$str[2].."), -10);
                    } else {
                        # For future expansion
                        $trip = '???';
                    }
                } else {
                    $trip = substr(base64_encode(sha1($match[2], true)), 0, 12);
                    $trip = str_replace('+', '.', $trip);
                }
            } else {
                $salt = substr($match[2].'H.', 1, 2);
                $salt = preg_replace("/[^\.-z]/", '.', $salt);
                $salt = strtr($salt, ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
                $trip = substr(crypt($match[2], $salt), -10);
            }
            $trip = '◆'.$trip;
        } else {
            $trip = str_replace('◆', '◇', $key);
        }
        return $trip;
    }
}
