<?php

use App\Translator;

test('Translator translates English messages', function () {
    Translator::setLocale('en');
    
    $message = Translator::trans('error.post_too_large');
    
    expect($message)->toBe('The post contents are too large.');
});

test('Translator translates Japanese messages', function () {
    Translator::setLocale('ja');
    
    $message = Translator::trans('error.post_too_large');
    
    expect($message)->toBe('投稿内容が大きすぎます。');
});

test('Translator handles parameters', function () {
    Translator::setLocale('en');
    
    $message = Translator::trans('error.file_open_failed', ['filename' => 'test.txt']);
    
    expect($message)->toContain('test.txt');
});

test('Translator returns key for missing translation', function () {
    Translator::setLocale('en');
    
    $message = Translator::trans('non.existent.key');
    
    expect($message)->toBe('non.existent.key');
});
