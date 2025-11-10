<?php

namespace App\Services;

use App\Utils\StringHelper;

/**
 * User Preference Service
 *
 * Handles encoding/decoding of user preferences from URL parameter 'c'.
 * Format: [2-char flags in Base32][32-char colors in Base64]
 */
class UserPreferenceService
{
    private const COLOR_KEYS = [
        'C_BACKGROUND',
        'C_TEXT',
        'C_A_COLOR',
        'C_A_VISITED',
        'C_SUBJ',
        'C_QMSG',
        'C_A_ACTIVE',
        'C_A_HOVER',
    ];

    private const FLAG_KEYS = [
        'GZIPU',
        'RELTYPE',
        'AUTOLINK',
        'FOLLOWWIN',
        'COOKIE',
        'LINKOFF',
        'HIDEFORM',
        'SHOWIMG',
    ];

    /**
     * Apply preferences from encoded string to config
     */
    public function applyPreferences(array &$config, string $encoded): bool
    {
        if (empty($encoded)) {
            return false;
        }

        $colorChanged = false;
        $encodedLen = strlen($encoded);

        // Decode colors if string is long enough
        if ($encodedLen > 5) {
            $flagStr = substr($encoded, 0, 2);
            $pos = 2;

            foreach (self::COLOR_KEYS as $key) {
                $colorVal = StringHelper::base64ToThreeByteHex(substr($encoded, $pos, 4));
                if (strlen($colorVal) === 6 && strcasecmp($config[$key], $colorVal) !== 0) {
                    $colorChanged = true;
                    $config[$key] = $colorVal;
                }
                $pos += 4;
                if ($pos > $encodedLen) {
                    break;
                }
            }
        } elseif ($encodedLen === 2) {
            $flagStr = $encoded;
        } else {
            return false;
        }

        // Decode flags
        if (isset($flagStr)) {
            $flagBin = str_pad(base_convert($flagStr, 32, 2), count(self::FLAG_KEYS), '0', STR_PAD_LEFT);
            foreach (self::FLAG_KEYS as $i => $key) {
                $config[$key] = (int)$flagBin[$i];
            }
        }

        return $colorChanged;
    }

    /**
     * Update config from form input
     */
    public function updateFromForm(array &$config, array $form): void
    {
        if (!isset($form['m']) || !in_array($form['m'], ['p', 'c', 'g'])) {
            return;
        }

        $config['AUTOLINK'] = !empty($form['a']) ? 1 : 0;
        $config['GZIPU'] = !empty($form['g']) ? 1 : 0;
        $config['LINKOFF'] = !empty($form['loff']) ? 1 : 0;
        $config['HIDEFORM'] = !empty($form['hide']) ? 1 : 0;
        $config['SHOWIMG'] = !empty($form['sim']) ? 1 : 0;

        if ($form['m'] === 'c') {
            $config['FOLLOWWIN'] = !empty($form['fw']) ? 1 : 0;
            $config['RELTYPE'] = !empty($form['rt']) ? 1 : 0;
            $config['COOKIE'] = !empty($form['cookie']) ? 1 : 0;
        }
    }

    /**
     * Apply special conditions
     */
    public function applySpecialConditions(array &$config, array $form): void
    {
        if ($config['BBSMODE_ADMINONLY'] != 0) {
            $config['HIDEFORM'] = ($form['m'] === 'f' || ($form['m'] === 'p' && !empty($form['write']))) ? 0 : 1;
        }
    }

    /**
     * Encode preferences to string
     */
    public function encodePreferences(array $config, string $originalEncoded = '', bool $colorChanged = false): string
    {
        $flagBin = '';
        foreach (self::FLAG_KEYS as $key) {
            $flagBin .= $config[$key] ? '1' : '0';
        }
        $flagValue = str_pad(base_convert($flagBin, 2, 32), 2, '0', STR_PAD_LEFT);

        if ($colorChanged) {
            return $flagValue . substr($originalEncoded, 2);
        }

        return $flagValue;
    }

    /**
     * Initialize default preferences
     */
    public function initializeDefaults(array &$config): void
    {
        $config['LINKOFF'] = 0;
        $config['HIDEFORM'] = 0;
        $config['RELTYPE'] = 0;
        if (!isset($config['SHOWIMG'])) {
            $config['SHOWIMG'] = 0;
        }
    }
}
