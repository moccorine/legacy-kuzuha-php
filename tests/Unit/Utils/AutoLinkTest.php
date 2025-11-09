<?php

use App\Utils\AutoLink;

test('convert → converts http URL to link', function () {
    $text = 'Check this http://example.com/page';
    $result = AutoLink::convert($text);
    
    expect($result)->toBe('Check this <a href="http://example.com/page" target="link">http://example.com/page</a>');
});

test('convert → converts https URL to link', function () {
    $text = 'Visit https://example.com/secure';
    $result = AutoLink::convert($text);
    
    expect($result)->toBe('Visit <a href="https://example.com/secure" target="link">https://example.com/secure</a>');
});

test('convert → handles multiple URLs', function () {
    $text = 'First http://example.com and second https://test.org';
    $result = AutoLink::convert($text);
    
    expect($result)->toContain('<a href="http://example.com" target="link">http://example.com</a>');
    expect($result)->toContain('<a href="https://test.org" target="link">https://test.org</a>');
});

test('convert → handles URLs with query parameters', function () {
    $text = 'Search https://example.com/search?q=test&page=1';
    $result = AutoLink::convert($text);
    
    expect($result)->toContain('href="https://example.com/search?q=test&page=1"');
});

test('convert → handles URLs with fragments', function () {
    $text = 'Link https://example.com/page#section';
    $result = AutoLink::convert($text);
    
    expect($result)->toContain('href="https://example.com/page#section"');
});

test('convert → does not convert ftp URLs', function () {
    $text = 'FTP site ftp://example.com/file';
    $result = AutoLink::convert($text);
    
    expect($result)->toBe('FTP site ftp://example.com/file');
});

test('convert → does not convert news URLs', function () {
    $text = 'News link news://example.com/article';
    $result = AutoLink::convert($text);
    
    expect($result)->toBe('News link news://example.com/article');
});

test('convert → handles text without URLs', function () {
    $text = 'Just plain text without any links';
    $result = AutoLink::convert($text);
    
    expect($result)->toBe('Just plain text without any links');
});

test('convert → handles empty string', function () {
    $result = AutoLink::convert('');
    
    expect($result)->toBe('');
});

test('convert → handles URLs with special characters', function () {
    $text = 'URL https://example.com/path-with_special.chars~!*()';
    $result = AutoLink::convert($text);
    
    expect($result)->toContain('href="https://example.com/path-with_special.chars~!*()"');
});
