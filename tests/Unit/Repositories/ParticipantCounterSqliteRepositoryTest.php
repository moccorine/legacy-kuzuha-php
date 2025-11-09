<?php

use App\Models\Repositories\ParticipantCounterSqliteRepository;

beforeEach(function () {
    // Use in-memory database for testing
    $this->repository = new ParticipantCounterSqliteRepository(':memory:');
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
});

test('recordVisit removes expired entries', function () {
    $this->repository->recordVisit('user1', 1000, 300);
    $this->repository->recordVisit('user2', 1100, 300);
    
    // user1 should expire (1000 + 300 < 1500)
    $count = $this->repository->recordVisit('user3', 1500, 300);
    
    expect($count)->toBe(2); // user2 and user3 only
});

test('getActiveCount returns count without recording', function () {
    $this->repository->recordVisit('user1', 1000, 300);
    $this->repository->recordVisit('user2', 1100, 300);
    
    $count = $this->repository->getActiveCount(1200, 300);
    
    expect($count)->toBe(2);
});

test('getActiveCount filters expired entries', function () {
    $this->repository->recordVisit('user1', 1000, 300);
    $this->repository->recordVisit('user2', 1100, 300);
    $this->repository->recordVisit('user3', 1200, 300);
    
    // At time 1500, only user2 and user3 are active
    $count = $this->repository->getActiveCount(1500, 300);
    
    expect($count)->toBe(2);
});

test('handles empty database', function () {
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

test('handles concurrent updates', function () {
    // Same user multiple times
    for ($i = 0; $i < 10; $i++) {
        $this->repository->recordVisit('user1', 1000 + $i, 300);
    }
    
    // Should still be 1 user
    $count = $this->repository->getActiveCount(1100, 300);
    expect($count)->toBe(1);
});
