<?php

namespace App\Models\Repositories;

class BbsLogFileRepository implements BbsLogRepositoryInterface
{
    public function __construct(
        private string $logFilePath
    ) {}
    
    public function append(array $message): void
    {
        $line = implode(',', $message) . "\n";
        
        $fh = @fopen($this->logFilePath, 'ab');
        if (!$fh) {
            throw new \RuntimeException("Failed to open log file: {$this->logFilePath}");
        }
        
        if (fwrite($fh, $line) === false) {
            fclose($fh);
            throw new \RuntimeException("Failed to write to log file: {$this->logFilePath}");
        }
        
        fclose($fh);
    }
    
    public function getAll(): array
    {
        if (!file_exists($this->logFilePath)) {
            return [];
        }
        
        $lines = @file($this->logFilePath);
        if ($lines === false) {
            throw new \RuntimeException("Failed to read log file: {$this->logFilePath}");
        }
        
        return $lines;
    }
    
    public function getRange(int $start, int $limit): array
    {
        $all = $this->getAll();
        return array_slice($all, $start, $limit);
    }
    
    public function findById(int $postId): ?string
    {
        $all = $this->getAll();
        
        foreach ($all as $line) {
            $parts = explode(',', rtrim($line));
            if (isset($parts[0]) && (int)$parts[0] === $postId) {
                return $line;
            }
        }
        
        return null;
    }
    
    public function deleteById(int $postId): bool
    {
        $all = $this->getAll();
        $found = false;
        $newLines = [];
        
        foreach ($all as $line) {
            $parts = explode(',', rtrim($line));
            if (isset($parts[0]) && (int)$parts[0] === $postId) {
                $found = true;
                continue;
            }
            $newLines[] = $line;
        }
        
        if (!$found) {
            return false;
        }
        
        $fh = @fopen($this->logFilePath, 'wb');
        if (!$fh) {
            throw new \RuntimeException("Failed to open log file for writing: {$this->logFilePath}");
        }
        
        foreach ($newLines as $line) {
            fwrite($fh, $line);
        }
        
        fclose($fh);
        return true;
    }
    
    public function count(): int
    {
        return count($this->getAll());
    }
}
