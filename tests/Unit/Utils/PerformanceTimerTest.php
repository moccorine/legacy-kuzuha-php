<?php

use App\Utils\PerformanceTimer;

beforeEach(function () {
    PerformanceTimer::reset();
});

test('start initializes the timer', function () {
    PerformanceTimer::start();
    
    expect(PerformanceTimer::isRunning())->toBeTrue();
});

test('elapsed returns null when not started', function () {
    expect(PerformanceTimer::elapsed())->toBeNull();
});

test('elapsed returns time after start', function () {
    PerformanceTimer::start();
    usleep(10000); // Sleep 10ms
    
    $elapsed = PerformanceTimer::elapsed();
    
    expect($elapsed)->toBeGreaterThan(0.009);
    expect($elapsed)->toBeLessThan(0.1);
});

test('elapsedFormatted returns null when not started', function () {
    expect(PerformanceTimer::elapsedFormatted())->toBeNull();
});

test('elapsedFormatted returns formatted time', function () {
    PerformanceTimer::start();
    usleep(10000); // Sleep 10ms
    
    $formatted = PerformanceTimer::elapsedFormatted(6);
    
    expect($formatted)->toMatch('/^\d+\.\d{6}$/');
});

test('elapsedFormatted respects precision parameter', function () {
    PerformanceTimer::start();
    usleep(10000);
    
    $formatted3 = PerformanceTimer::elapsedFormatted(3);
    $formatted2 = PerformanceTimer::elapsedFormatted(2);
    
    expect($formatted3)->toMatch('/^\d+\.\d{3}$/');
    expect($formatted2)->toMatch('/^\d+\.\d{2}$/');
});

test('reset stops the timer', function () {
    PerformanceTimer::start();
    expect(PerformanceTimer::isRunning())->toBeTrue();
    
    PerformanceTimer::reset();
    
    expect(PerformanceTimer::isRunning())->toBeFalse();
    expect(PerformanceTimer::elapsed())->toBeNull();
});

test('isRunning returns false initially', function () {
    expect(PerformanceTimer::isRunning())->toBeFalse();
});

test('multiple elapsed calls return increasing values', function () {
    PerformanceTimer::start();
    
    $elapsed1 = PerformanceTimer::elapsed();
    usleep(5000);
    $elapsed2 = PerformanceTimer::elapsed();
    
    expect($elapsed2)->toBeGreaterThan($elapsed1);
});
