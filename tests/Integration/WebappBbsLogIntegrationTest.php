<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Kuzuha\Webapp;
use App\Models\Repositories\BbsLogFileRepository;

class WebappBbsLogIntegrationTest extends TestCase
{
    private string $testLogFile;
    private BbsLogFileRepository $repository;
    private Webapp $webapp;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->testLogFile = sys_get_temp_dir() . '/test_webapp_bbs_' . uniqid() . '.log';
        $this->repository = new BbsLogFileRepository($this->testLogFile);
        $this->webapp = new Webapp();
    }
    
    protected function tearDown(): void
    {
        if (file_exists($this->testLogFile)) {
            unlink($this->testLogFile);
        }
        parent::tearDown();
    }
    
    public function testLoadmessageUsesRepositoryWhenSet(): void
    {
        // Prepare test data
        $message1 = ['1', 'User1', '', 'Title1', 'Message1', '1000'];
        $message2 = ['2', 'User2', '', 'Title2', 'Message2', '2000'];
        
        $this->repository->append($message1);
        $this->repository->append($message2);
        
        // Set repository to webapp
        $this->webapp->setBbsLogRepository($this->repository);
        
        // Mock config to use test file
        $this->webapp->config['LOGFILENAME'] = $this->testLogFile;
        
        // Load messages
        $messages = $this->webapp->loadmessage();
        
        // Verify
        $this->assertCount(2, $messages);
        $this->assertStringContainsString('User1', $messages[0]);
        $this->assertStringContainsString('User2', $messages[1]);
    }
    
    public function testLoadmessageFallsBackToFileWhenRepositoryNotSet(): void
    {
        // Create test file directly
        file_put_contents($this->testLogFile, "1,User1,,Title1,Message1,1000\n");
        file_put_contents($this->testLogFile, "2,User2,,Title2,Message2,2000\n", FILE_APPEND);
        
        // Don't set repository
        $this->webapp->config['LOGFILENAME'] = $this->testLogFile;
        
        // Load messages
        $messages = $this->webapp->loadmessage();
        
        // Verify
        $this->assertCount(2, $messages);
        $this->assertStringContainsString('User1', $messages[0]);
        $this->assertStringContainsString('User2', $messages[1]);
    }
}
