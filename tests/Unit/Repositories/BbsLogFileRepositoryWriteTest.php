<?php

namespace Tests\Unit\Repositories;

use App\Models\Repositories\BbsLogFileRepository;
use PHPUnit\Framework\TestCase;

class BbsLogFileRepositoryWriteTest extends TestCase
{
    private string $testLogFile;
    private BbsLogFileRepository $repository;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->testLogFile = sys_get_temp_dir() . '/test_bbs_write_' . uniqid() . '.log';
        $this->repository = new BbsLogFileRepository($this->testLogFile);
    }
    
    protected function tearDown(): void
    {
        if (file_exists($this->testLogFile)) {
            unlink($this->testLogFile);
        }
        parent::tearDown();
    }
    
    public function testGetNextPostIdReturnsOneForEmptyLog(): void
    {
        $nextId = $this->repository->getNextPostId();
        
        $this->assertEquals(1, $nextId);
    }
    
    public function testGetNextPostIdReturnsIncrementedId(): void
    {
        // Add messages with post IDs
        $this->repository->append(['1000', '5', 'user', 'title', 'message']);
        $this->repository->append(['1001', '6', 'user2', 'title2', 'message2']);
        
        $nextId = $this->repository->getNextPostId();
        
        $this->assertEquals(6, $nextId); // First line has post ID 5, so next is 6
    }
    
    public function testPrependAddsMessageAtBeginning(): void
    {
        // Add initial messages
        $this->repository->append(['1000', '1', 'user1', 'title1', 'msg1']);
        $this->repository->append(['1001', '2', 'user2', 'title2', 'msg2']);
        
        // Prepend new message
        $this->repository->prepend(['1002', '3', 'user3', 'title3', 'msg3'], 10);
        
        $all = $this->repository->getAll();
        
        $this->assertCount(3, $all);
        $this->assertStringContainsString('user3', $all[0]); // New message is first
        $this->assertStringContainsString('user1', $all[1]);
        $this->assertStringContainsString('user2', $all[2]);
    }
    
    public function testPrependRespectsMaxMessages(): void
    {
        // Add 5 messages
        for ($i = 1; $i <= 5; $i++) {
            $this->repository->append([1000 + $i, $i, "user$i", "title$i", "msg$i"]);
        }
        
        // Prepend with max 3 messages
        $this->repository->prepend([1006, 6, 'user6', 'title6', 'msg6'], 3);
        
        $all = $this->repository->getAll();
        
        $this->assertCount(3, $all);
        $this->assertStringContainsString('user6', $all[0]);
        $this->assertStringContainsString('user1', $all[1]);
        $this->assertStringContainsString('user2', $all[2]);
    }
    
    public function testLockAndUnlock(): void
    {
        // Create file first
        file_put_contents($this->testLogFile, "test\n");
        
        // Lock should succeed
        $this->repository->lock();
        
        // Unlock should succeed
        $this->repository->unlock();
        
        // Should be able to lock again
        $this->repository->lock();
        $this->repository->unlock();
        
        $this->assertTrue(true); // If we get here, lock/unlock worked
    }
    
    public function testPrependWithLocking(): void
    {
        // Add initial message
        $this->repository->append(['1000', '1', 'user1', 'title1', 'msg1']);
        
        // Prepend should handle locking internally
        $this->repository->prepend(['1001', '2', 'user2', 'title2', 'msg2'], 10);
        
        $all = $this->repository->getAll();
        
        $this->assertCount(2, $all);
        $this->assertStringContainsString('user2', $all[0]);
    }
    
    public function testConcurrentPrepend(): void
    {
        // Simulate concurrent writes
        $this->repository->append(['1000', '1', 'user1', 'title1', 'msg1']);
        
        // First prepend
        $this->repository->prepend(['1001', '2', 'user2', 'title2', 'msg2'], 10);
        
        // Second prepend
        $this->repository->prepend(['1002', '3', 'user3', 'title3', 'msg3'], 10);
        
        $all = $this->repository->getAll();
        
        $this->assertCount(3, $all);
        $this->assertStringContainsString('user3', $all[0]); // Last prepend is first
        $this->assertStringContainsString('user2', $all[1]);
        $this->assertStringContainsString('user1', $all[2]);
    }
}
