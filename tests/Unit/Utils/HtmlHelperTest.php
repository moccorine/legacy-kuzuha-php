<?php

use App\Utils\HtmlHelper;

test('removeReferenceLink removes reference link', function () {
    $html = 'Message text<a href="/follow?s=123">Reference: 2025/01/01</a>';
    $result = HtmlHelper::removeReferenceLink($html);
    
    expect($result)->toBe('Message text');
});

test('removeReferenceLink handles case insensitive', function () {
    $html = 'Text<a href="/follow">REFERENCE: date</a>';
    $result = HtmlHelper::removeReferenceLink($html);
    
    expect($result)->toBe('Text');
});

test('removeReferenceLink only removes first occurrence', function () {
    $html = '<a href="#">Reference: 1</a><a href="#">Reference: 2</a>';
    $result = HtmlHelper::removeReferenceLink($html);
    
    expect($result)->toBe('<a href="#">Reference: 2</a>');
});

test('removeReferenceLink handles no reference link', function () {
    $html = 'Just plain text';
    $result = HtmlHelper::removeReferenceLink($html);
    
    expect($result)->toBe('Just plain text');
});

test('hasReferenceLinkAtEnd detects reference link at end', function () {
    $html = "Message\r\r<a href=\"/follow?s=123\">Reference: date</a>";
    
    expect(HtmlHelper::hasReferenceLinkAtEnd($html))->toBeTrue();
});

test('hasReferenceLinkAtEnd returns false when no reference at end', function () {
    $html = "Message text";
    
    expect(HtmlHelper::hasReferenceLinkAtEnd($html))->toBeFalse();
});

test('hasReferenceLinkAtEnd returns false when reference not at end', function () {
    $html = "<a href=\"/follow\">Reference: date</a>\r\rMore text";
    
    expect(HtmlHelper::hasReferenceLinkAtEnd($html))->toBeFalse();
});

test('insertBeforeReferenceLink inserts content correctly', function () {
    $html = "Message\r\r<a href=\"/follow?s=123\">Reference: date</a>";
    $content = '<img src="image.jpg">';
    
    $result = HtmlHelper::insertBeforeReferenceLink($html, $content);
    
    expect($result)->toBe("Message\r\r<img src=\"image.jpg\">\r\r<a href=\"/follow?s=123\">Reference: date</a>");
});

test('insertBeforeReferenceLink handles no reference link', function () {
    $html = "Message text";
    $content = '<img src="image.jpg">';
    
    $result = HtmlHelper::insertBeforeReferenceLink($html, $content);
    
    expect($result)->toBe("Message text");
});
