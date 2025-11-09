<?php

use App\Models\Repositories\ParticipantCounterCsvRepository;

beforeEach(function () {
    $this->tempFile = sys_get_temp_dir() . '/participant_test_' . uniqid() . '.cnt';
    touch($this->tempFile);
    $this->repository = new ParticipantCounterCsvRepository($this->tempFile);
});

afterEach(function () {
    @unlink($this->tempFile);
});

test('recordVisit returns 1 for first visitor', function () {
    $count = $this->repository->recordVisit('user1', 1000, 300);
    
    expect($count)->toBe(1);
});

test('recordVisit counts multiple unique visitors', function () {
    $count1 = $this->repository->recordVisit('user1', 1000, 300);
    $count2 = $this->repository->recordVisit('user2', 1010, 300);
    $count3 = $this->repository->recordVisit('user3', 1020, 300);
    
    expect($count1)->toBe(1);
    expect($count2)->toBe(2);
    expect($count3)->toBe(3);
});

test('recordVisit updates existing user timestamp', function () {
    $this->repository->recordVisit('user1', 1000, 300);
    $count = $this->repository->recordVisit('user1', 1100, 300);
    
    // Still only 1 user
    expect($count)->toBe(1);
    
    // Verify timestamp was updated
    $content = file_get_contents($this->tempFile);
    expect($content)->toContain('user1,1100');
});

test('recordVisit removes expired entries', function () {
    $this->repository->recordVisit('user1', 1000, 300);
    $this->repository->recordVisit('user2', 1250, 300);
    
    // user1 should expire (1000 + 300 = 1300 < 1500)
    // user2 should still be active (1250 + 300 = 1550 >= 1500)
    $count = $this->repository->recordVisit('user3', 1500, 300);
    
    expect($count)->toBe(2); // user2 and user3 only
});

test('getActiveCount returns count without recording', function () {
    $this->repository->recordVisit('user1', 1000, 300);
    $this->repository->recordVisit('user2', 1100, 300);
    
    $count = $this->repository->getActiveCount(1200, 300);
    
    expect($count)->toBe(2);
    
    // File should not have changed
    $content = file_get_contents($this->tempFile);
    expect($content)->not->toContain('1200');
});

test('getActiveCount filters expired entries', function () {
    $this->repository->recordVisit('user1', 1000, 300);
    $this->repository->recordVisit('user2', 1250, 300);
    $this->repository->recordVisit('user3', 1400, 300);
    
    // At time 1500:
    // user1: 1000 + 300 = 1300 < 1500 → expired
    // user2: 1250 + 300 = 1550 >= 1500 → active
    // user3: 1400 + 300 = 1700 >= 1500 → active
    $count = $this->repository->getActiveCount(1500, 300);
    
    expect($count)->toBe(2);
});

test('handles empty file', function () {
    $count = $this->repository->getActiveCount(1000, 300);
    
    expect($count)->toBe(0);
});

test('handles timeout edge case', function () {
    $this->repository->recordVisit('user1', 1000, 300);
    
    // Exactly at timeout boundary (1000 + 300 = 1300)
    $countAt1300 = $this->repository->getActiveCount(1300, 300);
    expect($countAt1300)->toBe(1);
    
    // Just after timeout (1000 + 300 < 1301)
    $countAt1301 = $this->repository->getActiveCount(1301, 300);
    expect($countAt1301)->toBe(0);
});

test('file format is correct', function () {
    $this->repository->recordVisit('12345678', 1000, 300);
    $this->repository->recordVisit('87654321', 1100, 300);
    
    $content = file_get_contents($this->tempFile);
    $lines = explode("\n", trim($content));
    
    expect($lines)->toHaveCount(2);
    expect($lines[0])->toBe('12345678,1000');
    expect($lines[1])->toBe('87654321,1100');
});
