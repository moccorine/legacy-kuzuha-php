<?php

use App\Config;

test('Config is singleton', function () {
    $instance1 = Config::getInstance();
    $instance2 = Config::getInstance();
    
    expect($instance1)->toBe($instance2);
});

test('Config get returns value', function () {
    $config = Config::getInstance();
    
    $value = $config->get('BBSTITLE');
    
    expect($value)->toBeString();
});

test('Config get returns default for missing key', function () {
    $config = Config::getInstance();
    
    $value = $config->get('NON_EXISTENT_KEY', 'default');
    
    expect($value)->toBe('default');
});

test('Config has checks key existence', function () {
    $config = Config::getInstance();
    
    expect($config->has('BBSTITLE'))->toBeTrue();
    expect($config->has('NON_EXISTENT_KEY'))->toBeFalse();
});

test('Config set updates value', function () {
    $config = Config::getInstance();
    
    $config->set('TEST_KEY', 'test_value');
    
    expect($config->get('TEST_KEY'))->toBe('test_value');
});

test('Config all returns array', function () {
    $config = Config::getInstance();
    
    $all = $config->all();
    
    expect($all)->toBeArray();
    expect($all)->toHaveKey('BBSTITLE');
});
