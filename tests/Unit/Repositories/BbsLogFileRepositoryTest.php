<?php

namespace Tests\Unit\Repositories;

use App\Models\Repositories\BbsLogFileRepository;
use PHPUnit\Framework\TestCase;

class BbsLogFileRepositoryTest extends TestCase
{
    private string $testLogFile;
    private BbsLogFileRepository $repository;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->testLogFile = sys_get_temp_dir() . '/test_bbs_' . uniqid() . '.log';
        $this->repository = new BbsLogFileRepository($this->testLogFile);
    }
    
    protected function tearDown(): void
    {
        if (file_exists($this->testLogFile)) {
            unlink($this->testLogFile);
        }
        parent::tearDown();
    }
    
    public function testAppendMessage(): void
    {
        $message = ['1', 'TestUser', 'test@example.com', 'Test Title', 'Test Message', '1234567890'];
        
        $this->repository->append($message);
        
        $this->assertFileExists($this->testLogFile);
        $content = file_get_contents($this->testLogFile);
        $this->assertStringContainsString('1,TestUser,test@example.com,Test Title,Test Message,1234567890', $content);
    }
    
    public function testGetAllThrowsExceptionWhenFileDoesNotExist(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Log file does not exist');
        
        $this->repository->getAll();
    }
    
    public function testGetAllReturnsAllMessages(): void
    {
        $message1 = ['1', 'User1', '', 'Title1', 'Message1', '1000'];
        $message2 = ['2', 'User2', '', 'Title2', 'Message2', '2000'];
        
        $this->repository->append($message1);
        $this->repository->append($message2);
        
        $result = $this->repository->getAll();
        
        $this->assertCount(2, $result);
        $this->assertStringContainsString('User1', $result[0]);
        $this->assertStringContainsString('User2', $result[1]);
    }
    
    public function testGetRange(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->repository->append([$i, "User$i", '', "Title$i", "Message$i", (string)($i * 1000)]);
        }
        
        $result = $this->repository->getRange(1, 2);
        
        $this->assertCount(2, $result);
        $this->assertStringContainsString('User2', $result[0]);
        $this->assertStringContainsString('User3', $result[1]);
    }
    
    public function testFindByIdReturnsMessageWhenFound(): void
    {
        $message1 = ['1', 'User1', '', 'Title1', 'Message1', '1000'];
        $message2 = ['2', 'User2', '', 'Title2', 'Message2', '2000'];
        
        $this->repository->append($message1);
        $this->repository->append($message2);
        
        $result = $this->repository->findById(2);
        
        $this->assertNotNull($result);
        $this->assertStringContainsString('User2', $result);
    }
    
    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $message = ['1', 'User1', '', 'Title1', 'Message1', '1000'];
        $this->repository->append($message);
        
        $result = $this->repository->findById(999);
        
        $this->assertNull($result);
    }
    
    public function testDeleteByIdRemovesMessage(): void
    {
        $message1 = ['1', 'User1', '', 'Title1', 'Message1', '1000'];
        $message2 = ['2', 'User2', '', 'Title2', 'Message2', '2000'];
        $message3 = ['3', 'User3', '', 'Title3', 'Message3', '3000'];
        
        $this->repository->append($message1);
        $this->repository->append($message2);
        $this->repository->append($message3);
        
        $result = $this->repository->deleteById(2);
        
        $this->assertTrue($result);
        $this->assertCount(2, $this->repository->getAll());
        $this->assertNull($this->repository->findById(2));
        $this->assertNotNull($this->repository->findById(1));
        $this->assertNotNull($this->repository->findById(3));
    }
    
    public function testDeleteByIdReturnsFalseWhenNotFound(): void
    {
        $message = ['1', 'User1', '', 'Title1', 'Message1', '1000'];
        $this->repository->append($message);
        
        $result = $this->repository->deleteById(999);
        
        $this->assertFalse($result);
        $this->assertCount(1, $this->repository->getAll());
    }
    
    public function testCount(): void
    {
        // Create empty file first
        file_put_contents($this->testLogFile, '');
        $this->assertEquals(0, $this->repository->count());
        
        $this->repository->append(['1', 'User1', '', 'Title1', 'Message1', '1000']);
        $this->assertEquals(1, $this->repository->count());
        
        $this->repository->append(['2', 'User2', '', 'Title2', 'Message2', '2000']);
        $this->assertEquals(2, $this->repository->count());
    }
    
    public function testAppendThrowsExceptionWhenFileCannotBeOpened(): void
    {
        $invalidRepo = new BbsLogFileRepository('/invalid/path/that/does/not/exist/test.log');
        
        $this->expectException(\RuntimeException::class);
        
        $invalidRepo->append(['1', 'User', '', 'Title', 'Message', '1000']);
    }
}
