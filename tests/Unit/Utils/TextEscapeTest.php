<?php

use App\Utils\TextEscape;

test('escapeTwigChars escapes opening brace', function () {
    $input = 'Hello {world}';
    $result = TextEscape::escapeTwigChars($input);
    
    expect($result)->toBe('Hello &#123;world&#125;');
});

test('escapeTwigChars handles multiple braces', function () {
    $input = '{{test}} {another}';
    $result = TextEscape::escapeTwigChars($input);
    
    expect($result)->toBe('&#123;&#123;test&#125;&#125; &#123;another&#125;');
});

test('escapeTwigChars handles text without braces', function () {
    $input = 'Hello world';
    $result = TextEscape::escapeTwigChars($input);
    
    expect($result)->toBe('Hello world');
});

test('escapeTwigChars handles empty string', function () {
    $result = TextEscape::escapeTwigChars('');
    
    expect($result)->toBe('');
});

test('escapeTwigChars handles only braces', function () {
    $input = '{}';
    $result = TextEscape::escapeTwigChars($input);
    
    expect($result)->toBe('&#123;&#125;');
});

test('escapeHtml escapes special characters', function () {
    $input = '<script>alert("XSS")</script>';
    $result = TextEscape::escapeHtml($input);
    
    expect($result)->toBe('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;');
});

test('escapeHtml escapes quotes', function () {
    $input = "It's a \"test\"";
    $result = TextEscape::escapeHtml($input);
    
    expect($result)->toContain('&apos;'); // Single quote (HTML5)
    expect($result)->toContain('&quot;'); // Double quote
});

test('escapeHtml handles ampersands', function () {
    $input = 'A & B';
    $result = TextEscape::escapeHtml($input);
    
    expect($result)->toBe('A &amp; B');
});

test('escapeHtml handles empty string', function () {
    $result = TextEscape::escapeHtml('');
    
    expect($result)->toBe('');
});

test('escapeTwigChars is faster than preg_replace', function () {
    $input = str_repeat('Hello {world} ', 100);
    
    // Benchmark str_replace approach
    $start = microtime(true);
    for ($i = 0; $i < 1000; $i++) {
        TextEscape::escapeTwigChars($input);
    }
    $strReplaceTime = microtime(true) - $start;
    
    // Benchmark preg_replace approach (old method)
    $start = microtime(true);
    for ($i = 0; $i < 1000; $i++) {
        $temp = preg_replace('/{/i', '&#123;', $input, -1);
        $temp = preg_replace('/}/i', '&#125;', $temp, -1);
    }
    $pregReplaceTime = microtime(true) - $start;
    
    // str_replace should be significantly faster
    expect($strReplaceTime)->toBeLessThan($pregReplaceTime);
});
