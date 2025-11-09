<?php

use App\Models\Repositories\AccessCounterCsvRepository;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/counter_test_' . uniqid();
    mkdir($this->tempDir);
    $this->filePrefix = $this->tempDir . '/count';
    $this->fileCount = 5;
    
    // Initialize counter files
    for ($i = 0; $i < $this->fileCount; $i++) {
        file_put_contents("{$this->filePrefix}{$i}.dat", '0');
    }
    
    $this->repository = new AccessCounterCsvRepository($this->filePrefix, $this->fileCount);
});

afterEach(function () {
    // Cleanup
    for ($i = 0; $i < $this->fileCount; $i++) {
        @unlink("{$this->filePrefix}{$i}.dat");
    }
    @rmdir($this->tempDir);
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

test('increment distributes across multiple files', function () {
    // Increment 10 times
    for ($i = 0; $i < 10; $i++) {
        $this->repository->increment();
    }
    
    // Check that multiple files have been updated
    $fileCounts = [];
    for ($i = 0; $i < $this->fileCount; $i++) {
        $fileCounts[] = (int) file_get_contents("{$this->filePrefix}{$i}.dat");
    }
    
    // At least 2 files should have non-zero values
    $nonZeroFiles = array_filter($fileCounts, fn($c) => $c > 0);
    expect(count($nonZeroFiles))->toBeGreaterThanOrEqual(2);
});

test('handles concurrent increments simulation', function () {
    // Simulate concurrent access by incrementing rapidly
    $results = [];
    for ($i = 0; $i < 20; $i++) {
        $results[] = $this->repository->increment();
    }
    
    // All results should be unique and sequential
    expect($results)->toBe(range(1, 20));
});

test('getCurrent returns max value from all files', function () {
    // Manually set different values
    file_put_contents("{$this->filePrefix}0.dat", '5');
    file_put_contents("{$this->filePrefix}1.dat", '10');
    file_put_contents("{$this->filePrefix}2.dat", '3');
    file_put_contents("{$this->filePrefix}3.dat", '8');
    file_put_contents("{$this->filePrefix}4.dat", '7');
    
    $current = $this->repository->getCurrent();
    expect($current)->toBe(10);
});

test('getCountLevel returns number of files', function () {
    $level = $this->repository->getCountLevel();
    expect($level)->toBe(5);
});
