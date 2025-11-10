<?php

namespace App\Utils;

/**
 * Kaomoji (Japanese emoticons) helper
 */
class KaomojiHelper
{
    /**
     * Generate kaomoji buttons HTML
     *
     * @return string HTML for kaomoji buttons
     */
    public static function generateButtons(): string
    {
        $kaomojis = [
            ['ヽ(´ー｀)ノ', '(´ー`)', '(;´Д`)', 'ヽ(´∇`)ノ', '(´∇`)σ', '(＾Д^)'],
            ['(;^Д^)', '(ﾉД^､)σ', '(ﾟ∇ﾟ)', '(;ﾟ∇ﾟ)', 'Σ(;ﾟ∇ﾟ)', '(;ﾟДﾟ)', 'Σ(;ﾟДﾟ)'],
            ['(｀∇´)', '(｀ー´)', '(｀～´)', '(;`-´)', 'ヽ(`Д´)ノ', '(`Д´)'],
            ['(;`Д´)', '(ﾟ血ﾟ#)', '(╬⊙Д⊙)', '(ρ_;)', '(TДT)', '(ﾉД`､)', '(´Д`)'],
            ['(´-｀)', '(´￢`)', 'ヽ(ﾟρﾟ)ノ', '(ﾟー｀)', '(´π｀)', '(ﾟДﾟ)', '(ﾟへﾟ)'],
            ['(ﾟーﾟ)', '(ﾟｰﾟ)', '(*\'ｰ\')', '(\'ｰ\')', '(´人｀)', 'ъ( ﾟｰ^)', '（⌒∇⌒ゞ）'],
            ['(^^;ﾜﾗ', 'ε≡三ヽ(´ー`)ﾉ', 'ε≡Ξヽ( ^Д^)ノ', 'ヽ(´Д`;)ノΞ≡3'],
            ['(・∀・)', '( ´ω`)', 'Σ(ﾟдﾟlll)', '(´～`)', '┐(ﾟ～ﾟ)┌'],
        ];

        $html = '';
        foreach ($kaomojis as $row) {
            foreach ($row as $kaomoji) {
                $escaped = htmlspecialchars($kaomoji, ENT_QUOTES, 'UTF-8');
                $html .= "<input type=\"button\" class=\"kaomoji\" onClick=\"insertThisInThere('{$escaped}','contents1')\" value=\"{$escaped}\" />\n\t\t";
            }
        }
        return $html;
    }

    /**
     * Get all kaomoji as array
     *
     * @return array Array of kaomoji strings
     */
    public static function getAll(): array
    {
        return [
            'ヽ(´ー｀)ノ', '(´ー`)', '(;´Д`)', 'ヽ(´∇`)ノ', '(´∇`)σ', '(＾Д^)',
            '(;^Д^)', '(ﾉД^､)σ', '(ﾟ∇ﾟ)', '(;ﾟ∇ﾟ)', 'Σ(;ﾟ∇ﾟ)', '(;ﾟДﾟ)', 'Σ(;ﾟДﾟ)',
            '(｀∇´)', '(｀ー´)', '(｀～´)', '(;`-´)', 'ヽ(`Д´)ノ', '(`Д´)',
            '(;`Д´)', '(ﾟ血ﾟ#)', '(╬⊙Д⊙)', '(ρ_;)', '(TДT)', '(ﾉД`､)', '(´Д`)',
            '(´-｀)', '(´￢`)', 'ヽ(ﾟρﾟ)ノ', '(ﾟー｀)', '(´π｀)', '(ﾟДﾟ)', '(ﾟへﾟ)',
            '(ﾟーﾟ)', '(ﾟｰﾟ)', '(*\'ｰ\')', '(\'ｰ\')', '(´人｀)', 'ъ( ﾟｰ^)', '（⌒∇⌒ゞ）',
            '(^^;ﾜﾗ', 'ε≡三ヽ(´ー`)ﾉ', 'ε≡Ξヽ( ^Д^)ノ', 'ヽ(´Д`;)ノΞ≡3',
            '(・∀・)', '( ´ω`)', 'Σ(ﾟдﾟlll)', '(´～`)', '┐(ﾟ～ﾟ)┌',
        ];
    }
}
