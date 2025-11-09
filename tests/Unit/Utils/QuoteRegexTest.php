<?php

use App\Utils\QuoteRegex;

describe('removeNestedQuotes', function () {
    test('removes nested quote markers', function () {
        $input = "Line 1\r&gt; &gt; Nested quote\rLine 2";
        $result = QuoteRegex::removeNestedQuotes($input);
        
        expect($result)->not->toContain('&gt; &gt;');
        expect($result)->toContain('Line 1');
        expect($result)->toContain('Line 2');
    });
    
    test('preserves single quotes', function () {
        $input = "&gt; Single quote\rNormal line";
        $result = QuoteRegex::removeNestedQuotes($input);
        
        expect($result)->toContain('&gt; Single');
    });
    
    test('handles text without nested quotes', function () {
        $input = "Normal text\rAnother line";
        $result = QuoteRegex::removeNestedQuotes($input);
        
        expect($result)->toBe($input);
    });
});

describe('addQuotePrefix', function () {
    test('adds prefix to single line', function () {
        $input = "Hello";
        $result = QuoteRegex::addQuotePrefix($input);
        
        expect($result)->toBe("> Hello\r");
    });
    
    test('adds prefix to multiple lines', function () {
        $input = "Line 1\rLine 2\rLine 3";
        $result = QuoteRegex::addQuotePrefix($input);
        
        expect($result)->toBe("> Line 1\r> Line 2\r> Line 3\r");
    });
    
    test('handles empty string', function () {
        $result = QuoteRegex::addQuotePrefix('');
        
        expect($result)->toBe("> \r");
    });
});

describe('cleanEmptyQuoteLines', function () {
    test('removes empty quote lines', function () {
        $input = "> Line 1\r>  \r> Line 2\r";
        $result = QuoteRegex::cleanEmptyQuoteLines($input);
        
        // Pattern /\r>\s++\r/ matches "\r>  \r" (2 spaces)
        expect($result)->toBe("> Line 1\r> Line 2\r");
    });
    
    test('preserves non-empty quote lines', function () {
        $input = "> Line 1\r> Line 2";
        $result = QuoteRegex::cleanEmptyQuoteLines($input);
        
        expect($result)->toBe($input);
    });
});

describe('formatAsQuote', function () {
    test('formats simple message as quote', function () {
        $input = "Hello\rWorld";
        $result = QuoteRegex::formatAsQuote($input, removeLinks: false);
        
        expect($result)->toStartWith('> ');
        expect($result)->toContain('> Hello');
        expect($result)->toContain('> World');
    });
    
    test('removes nested quotes', function () {
        $input = "Line 1\r&gt; &gt; Nested\rLine 2";
        $result = QuoteRegex::formatAsQuote($input, removeLinks: false);
        
        expect($result)->not->toContain('&gt; &gt;');
    });
    
    test('removes links when requested', function () {
        $input = '<a href="http://example.com">Link</a> Text';
        $result = QuoteRegex::formatAsQuote($input, removeLinks: true);
        
        expect($result)->not->toContain('<a');
        expect($result)->toContain('Link');
        expect($result)->toContain('Text');
    });
    
    test('preserves links when not requested', function () {
        $input = '<a href="http://example.com">Link</a>';
        $result = QuoteRegex::formatAsQuote($input, removeLinks: false);
        
        expect($result)->toContain('<a href');
    });
    
    test('removes follow links with base URL', function () {
        $input = '<a href="/follow?s=123">Follow</a> Text';
        $result = QuoteRegex::formatAsQuote($input, removeLinks: true, followLinkBase: '/follow?s=');
        
        expect($result)->not->toContain('Follow');
        expect($result)->toContain('Text');
    });
    
    test('removes image links', function () {
        $input = '<a href="image.jpg"><img src="thumb.jpg"></a> Text';
        $result = QuoteRegex::formatAsQuote($input, removeLinks: true);
        
        expect($result)->not->toContain('<img');
        expect($result)->toContain('Text');
    });
    
    test('cleans empty quote lines', function () {
        $input = "Line 1\r\rLine 2";
        $result = QuoteRegex::formatAsQuote($input, removeLinks: false);
        
        // Empty line becomes "> \r" which is then cleaned
        expect($result)->toContain('> Line 1');
        expect($result)->toContain('> Line 2');
    });
    
    test('handles complex message', function () {
        $input = "Hello\r&gt; &gt; Old quote\r<a href=\"#\">Link</a>\rWorld";
        $result = QuoteRegex::formatAsQuote($input, removeLinks: true);
        
        expect($result)->toStartWith('> ');
        expect($result)->not->toContain('&gt; &gt;');
        expect($result)->not->toContain('<a');
        expect($result)->toContain('Hello');
        expect($result)->toContain('Link');
        expect($result)->toContain('World');
    });
});

describe('performance', function () {
    test('formatAsQuote is faster than 8 separate operations', function () {
        $input = str_repeat("Line with <a href=\"#\">link</a>\r", 50);
        
        // Benchmark new method
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            QuoteRegex::formatAsQuote($input, removeLinks: true);
        }
        $newTime = microtime(true) - $start;
        
        // Benchmark old method (8 operations)
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $temp = preg_replace("/&gt; &gt;[^\r]+\r/", '', $input);
            $temp = preg_replace("/<a href=\"[^>]+>([^<]+)<\/a>/i", '$1', $temp);
            $temp = preg_replace("/\r*<a href=[^>]+><img [^>]+><\/a>/i", '', $temp);
            $temp = preg_replace("/\r/", "\r> ", $temp);
            $temp = "> $temp\r";
            $temp = preg_replace("/\r>\s+\r/", "\r", $temp);
            $temp = preg_replace("/\r>\s+\r$/", "\r", $temp);
        }
        $oldTime = microtime(true) - $start;
        
        expect($newTime)->toBeLessThan($oldTime);
    });
});
