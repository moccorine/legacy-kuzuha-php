<?php

use App\Utils\DateHelper;

test('getDateString formats date correctly', function () {
    $timestamp = strtotime('2025-11-08 14:30:00');
    $result = DateHelper::getDateString($timestamp, 'Y/m/d H:i:s');
    
    expect($result)->toBe('2025/11/08 14:30:00');
});

test('getDateString includes day of week', function () {
    $timestamp = strtotime('2025-11-08 14:30:00'); // Saturday
    $result = DateHelper::getDateString($timestamp, 'Y/m/d(-) H:i:s');
    
    expect($result)->toContain('Sat');
});

test('microtimeDiff calculates difference correctly', function () {
    $start = '0.12345678 1699000000';
    $end = '0.98765432 1699000001';
    
    $diff = DateHelper::microtimeDiff($start, $end);
    
    expect($diff)->toBeGreaterThan(1.8);
    expect($diff)->toBeLessThan(1.9);
});
