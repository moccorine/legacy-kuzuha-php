<?php

use App\Models\Repositories\AccessCounterSqliteRepository;

beforeEach(function () {
    // Use in-memory database for testing
    $this->repository = new AccessCounterSqliteRepository(':memory:');
});

test('increment returns 1 on first call', function () {
    $count = $this->repository->increment();
    
    expect($count)->toBe(1);
});

test('increment increases counter', function () {
    $count1 = $this->repository->increment();
    $count2 = $this->repository->increment();
    $count3 = $this->repository->increment();
    
    expect($count1)->toBe(1);
    expect($count2)->toBe(2);
    expect($count3)->toBe(3);
});

test('getCurrent returns current value without incrementing', function () {
    $this->repository->increment();
    $this->repository->increment();
    
    $current = $this->repository->getCurrent();
    expect($current)->toBe(2);
    
    // Should not increment
    $stillCurrent = $this->repository->getCurrent();
    expect($stillCurrent)->toBe(2);
});

test('getCurrent returns 0 initially', function () {
    $current = $this->repository->getCurrent();
    
    expect($current)->toBe(0);
});

test('handles large numbers', function () {
    // Set to large number
    for ($i = 0; $i < 1000; $i++) {
        $this->repository->increment();
    }
    
    $current = $this->repository->getCurrent();
    expect($current)->toBe(1000);
});

test('getCountLevel returns false for SQLite', function () {
    $level = $this->repository->getCountLevel();
    expect($level)->toBeFalse();
});
