<?php

use App\Utils\StringHelper;

test('htmlEscape escapes HTML special characters', function () {
    $input = '<script>alert("XSS")</script>';
    $result = StringHelper::htmlEscape($input);
    
    expect($result)->toContain('&lt;script&gt;');
    expect($result)->not->toContain('<script>');
});

test('htmlEscape converts newlines to br tags', function () {
    $input = "Line 1\nLine 2";
    $result = StringHelper::htmlEscape($input);
    
    expect($result)->toContain('<br>');
});

test('fixNumberString converts full-width to half-width', function () {
    $input = '１２３４５';
    $result = StringHelper::fixNumberString($input);
    
    expect($result)->toBe('12345');
});

test('fixNumberString handles null input', function () {
    $result = StringHelper::fixNumberString(null);
    
    expect($result)->toBe('');
});

test('escapeUrl escapes URL correctly', function () {
    $input = 'https://example.com/path?query=value&foo=bar';
    $result = StringHelper::escapeUrl($input);
    
    expect($result)->toContain('https://');
    expect($result)->toContain('?');
    expect($result)->toContain('&');
});

test('checkValue removes null bytes', function () {
    $input = "test\0value";
    $result = StringHelper::checkValue($input);
    
    expect($result)->toBe('testvalue');
});

test('threeByteHexToBase64 converts correctly', function () {
    $hex = 'ffffff';
    $result = StringHelper::threeByteHexToBase64($hex);
    
    expect($result)->toBe('////');
});

test('base64ToThreeByteHex converts correctly', function () {
    $base64 = '////';
    $result = StringHelper::base64ToThreeByteHex($base64);
    
    expect($result)->toBe('ffffff');
});

test('removeNonAlphanumeric removes non-alphanumeric characters', function () {
    expect(StringHelper::removeNonAlphanumeric('abc123!@#'))->toBe('abc123');
    expect(StringHelper::removeNonAlphanumeric('Hello World!'))->toBe('HelloWorld');
    expect(StringHelper::removeNonAlphanumeric('test_-./123'))->toBe('test123');
});

test('removeNonAlphanumeric handles empty string', function () {
    expect(StringHelper::removeNonAlphanumeric(''))->toBe('');
});

test('removeNonAlphanumeric handles only special characters', function () {
    expect(StringHelper::removeNonAlphanumeric('!@#$%^&*()'))->toBe('');
});
