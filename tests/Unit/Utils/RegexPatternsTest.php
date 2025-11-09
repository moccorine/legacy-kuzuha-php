<?php

use App\Utils\RegexPatterns;

describe('stripHtmlTags', function () {
    test('removes simple HTML tags', function () {
        $input = '<b>Hello</b> <i>World</i>';
        expect(RegexPatterns::stripHtmlTags($input))->toBe('Hello World');
    });
    
    test('removes tags with attributes', function () {
        $input = '<span class="test">Content</span>';
        expect(RegexPatterns::stripHtmlTags($input))->toBe('Content');
    });
    
    test('handles malformed HTML safely', function () {
        $input = '<script<script>>alert(1)</script>';
        $result = RegexPatterns::stripHtmlTags($input);
        
        expect($result)->toBe('alert(1)');
        expect($result)->not->toContain('<');
        expect($result)->not->toContain('>');
    });
    
    test('handles nested tags', function () {
        $input = '<a href="test"><b>Link</b></a>';
        expect(RegexPatterns::stripHtmlTags($input))->toBe('Link');
    });
    
    test('handles empty string', function () {
        expect(RegexPatterns::stripHtmlTags(''))->toBe('');
    });
    
    test('handles string without tags', function () {
        $input = 'Plain text';
        expect(RegexPatterns::stripHtmlTags($input))->toBe('Plain text');
    });
    
    test('handles XSS attempts', function () {
        $input = '<img src=x onerror=alert(1)>';
        $result = RegexPatterns::stripHtmlTags($input);
        
        expect($result)->toBe('');
        expect($result)->not->toContain('alert');
    });
    
    test('preserves text between tags', function () {
        $input = 'Before <b>bold</b> after';
        expect(RegexPatterns::stripHtmlTags($input))->toBe('Before bold after');
    });
    
    test('is faster than preg_replace', function () {
        $input = str_repeat('<b>Test</b> ', 100);
        
        // Benchmark strip_tags
        $start = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            RegexPatterns::stripHtmlTags($input);
        }
        $stripTagsTime = microtime(true) - $start;
        
        // Benchmark preg_replace
        $start = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            preg_replace('/<[^>]*>/', '', $input);
        }
        $pregReplaceTime = microtime(true) - $start;
        
        expect($stripTagsTime)->toBeLessThan($pregReplaceTime);
    });
});

describe('removeAnchorTags', function () {
    test('removes anchor tag but keeps text', function () {
        $input = '<a href="http://example.com">Link Text</a>';
        expect(RegexPatterns::removeAnchorTags($input))->toBe('Link Text');
    });
    
    test('removes multiple anchor tags', function () {
        $input = '<a href="#">First</a> and <a href="#">Second</a>';
        expect(RegexPatterns::removeAnchorTags($input))->toBe('First and Second');
    });
    
    test('handles anchor with attributes', function () {
        $input = '<a href="#" class="link" target="_blank">Text</a>';
        expect(RegexPatterns::removeAnchorTags($input))->toBe('Text');
    });
    
    test('preserves non-anchor content', function () {
        $input = 'Before <a href="#">link</a> after';
        expect(RegexPatterns::removeAnchorTags($input))->toBe('Before link after');
    });
    
    test('handles empty anchor', function () {
        $input = '<a href="#"></a>';
        // Empty anchor has no text content, so it remains unchanged
        expect(RegexPatterns::removeAnchorTags($input))->toBe('<a href="#"></a>');
    });
});

describe('removeImageLinks', function () {
    test('removes image link completely', function () {
        $input = '<a href="image.jpg"><img src="thumb.jpg"></a>';
        expect(RegexPatterns::removeImageLinks($input))->toBe('');
    });
    
    test('removes multiple image links', function () {
        $input = '<a href="#"><img src="1.jpg"></a> text <a href="#"><img src="2.jpg"></a>';
        $result = RegexPatterns::removeImageLinks($input);
        
        expect($result)->not->toContain('<img');
        expect($result)->toContain('text');
    });
    
    test('preserves text-only links', function () {
        $input = '<a href="#">Text Link</a>';
        expect(RegexPatterns::removeImageLinks($input))->toBe('<a href="#">Text Link</a>');
    });
    
    test('handles image with attributes', function () {
        $input = '<a href="#"><img src="test.jpg" alt="Test" class="thumb"></a>';
        expect(RegexPatterns::removeImageLinks($input))->toBe('');
    });
});
