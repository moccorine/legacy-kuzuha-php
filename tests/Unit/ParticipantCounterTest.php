<?php

use App\Utils\ParticipantCounter;

beforeEach(function () {
    $this->testFile = sys_get_temp_dir() . '/test_participant_' . uniqid() . '.cnt';
});

afterEach(function () {
    if (file_exists($this->testFile)) {
        unlink($this->testFile);
    }
});

test('counts new participant', function () {
    $count = ParticipantCounter::count($this->testFile, 300, time());
    
    expect($count)->toBe(1);
    expect(file_exists($this->testFile))->toBeTrue();
});

test('updates existing participant timestamp', function () {
    $time1 = time();
    $count1 = ParticipantCounter::count($this->testFile, 300, $time1);
    
    $time2 = $time1 + 100;
    $count2 = ParticipantCounter::count($this->testFile, 300, $time2);
    
    expect($count1)->toBe(1);
    expect($count2)->toBe(1);
    
    $content = file_get_contents($this->testFile);
    expect($content)->toContain((string)$time2);
});

test('removes expired participants', function () {
    // Add first participant
    $time1 = 1000;
    file_put_contents($this->testFile, "12345678,$time1\n");
    
    // Add second participant after expiry time
    $time2 = $time1 + 400; // 400 seconds later
    $count = ParticipantCounter::count($this->testFile, 300, $time2);
    
    // First participant should be expired, only new one remains
    expect($count)->toBe(1);
});

test('keeps active participants', function () {
    // Add first participant
    $time1 = 1000;
    file_put_contents($this->testFile, "12345678,$time1\n");
    
    // Add second participant within time limit
    $time2 = $time1 + 100; // 100 seconds later
    $count = ParticipantCounter::count($this->testFile, 300, $time2);
    
    // Both participants should be active
    expect($count)->toBe(2);
});

test('returns zero for empty filename', function () {
    $count = ParticipantCounter::count('', 300, time());
    
    expect($count)->toBe(0);
});

test('returns error message when file not writable', function () {
    $readOnlyFile = sys_get_temp_dir() . '/readonly_' . uniqid() . '.cnt';
    touch($readOnlyFile);
    chmod($readOnlyFile, 0444);
    
    $count = ParticipantCounter::count($readOnlyFile, 300, time());
    
    expect($count)->toBe('Participant file output error');
    
    chmod($readOnlyFile, 0644);
    unlink($readOnlyFile);
});
