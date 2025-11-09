<?php

use App\Utils\HtmlParser;

test('parseMessage extracts user name', function () {
    $html = '<span class="mun">TestUser</span>';
    $result = HtmlParser::parseMessage($html);
    
    expect($result['USER'])->toBe('TestUser');
});

test('parseMessage extracts title', function () {
    $html = '<span class="ms">Test Title</span>';
    $result = HtmlParser::parseMessage($html);
    
    expect($result['TITLE'])->toBe('Test Title');
});

test('parseMessage extracts message content', function () {
    $html = '<blockquote><pre>Test message content</pre></blockquote>';
    $result = HtmlParser::parseMessage($html);
    
    expect($result['MSG'])->toBe('Test message content');
});

test('parseMessage extracts date parts', function () {
    $html = '<span class="md">投稿日：2025/01/15(水) 14時30分45秒</span>';
    $result = HtmlParser::parseMessage($html);
    
    expect($result)->toHaveKey('date_parts');
    expect($result['date_parts']['year'])->toBe('2025');
    expect($result['date_parts']['month'])->toBe('01');
    expect($result['date_parts']['day'])->toBe('15');
    expect($result['date_parts']['hour'])->toBe('14');
    expect($result['date_parts']['minute'])->toBe('30');
    expect($result['date_parts']['second'])->toBe('45');
});

test('parseMessage handles missing elements', function () {
    $html = '<div>No special elements</div>';
    $result = HtmlParser::parseMessage($html);
    
    expect($result['USER'])->toBe('');
    expect($result['TITLE'])->toBe('');
    expect($result['MSG'])->toBe('');
});

test('parseMessage handles complete message', function () {
    $html = '
        <span class="mun">User123</span>
        <span class="ms">Subject</span>
        <blockquote><pre>Message body</pre></blockquote>
        <span class="md">投稿日：2025/01/01(月) 12時00分00秒</span>
    ';
    $result = HtmlParser::parseMessage($html);
    
    expect($result['USER'])->toBe('User123');
    expect($result['TITLE'])->toBe('Subject');
    expect($result['MSG'])->toBe('Message body');
    expect($result['date_parts']['year'])->toBe('2025');
});

test('extractText returns text from selector', function () {
    $html = '<div class="test">Hello World</div>';
    $result = HtmlParser::extractText($html, '.test');
    
    expect($result)->toBe('Hello World');
});

test('extractText returns null when element not found', function () {
    $html = '<div>No match</div>';
    $result = HtmlParser::extractText($html, '.nonexistent');
    
    expect($result)->toBeNull();
});

test('hasElement returns true when element exists', function () {
    $html = '<div class="exists">Content</div>';
    
    expect(HtmlParser::hasElement($html, '.exists'))->toBeTrue();
});

test('hasElement returns false when element does not exist', function () {
    $html = '<div>Content</div>';
    
    expect(HtmlParser::hasElement($html, '.nonexistent'))->toBeFalse();
});
