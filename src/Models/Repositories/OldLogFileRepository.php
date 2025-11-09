<?php

namespace App\Models\Repositories;

class OldLogFileRepository implements OldLogRepositoryInterface
{
    private string $logDir;
    private string $extension;
    private bool $monthlyMode;
    private int $maxSize;
    private bool $wasNewFile = false;

    public function __construct(
        string $logDir,
        string $extension,
        bool $monthlyMode,
        int $maxSize
    ) {
        $this->logDir = rtrim($logDir, '/') . '/';
        $this->extension = $extension;
        $this->monthlyMode = $monthlyMode;
        $this->maxSize = $maxSize;
    }

    public function append(string $message): void
    {
        $filename = $this->getCurrentFilename();
        
        if ($this->getSize() > $this->maxSize) {
            throw new \RuntimeException('Log size limit exceeded');
        }

        $this->wasNewFile = !file_exists($filename);

        $file = new \SplFileObject($filename, 'ab');
        $file->flock(LOCK_EX);
        $file->fwrite($message);
        $file->flock(LOCK_UN);

        if ($this->getSize() > $this->maxSize) {
            $this->setReadOnly();
        }
    }

    public function getCurrentFilename(): string
    {
        $timestamp = defined('CURRENT_TIME') ? CURRENT_TIME : time();
        $dateFormat = $this->monthlyMode ? 'Ym' : 'Ymd';
        return $this->logDir . date($dateFormat, $timestamp) . '.' . $this->extension;
    }

    public function getSize(): int
    {
        $filename = $this->getCurrentFilename();
        return file_exists($filename) ? filesize($filename) : 0;
    }

    public function setReadOnly(): void
    {
        $filename = $this->getCurrentFilename();
        if (file_exists($filename)) {
            chmod($filename, 0400);
        }
    }

    public function isNewFile(): bool
    {
        return $this->wasNewFile;
    }

    public function getAll(string $filename): array
    {
        $filepath = $this->logDir . $filename;
        
        if (!file_exists($filepath)) {
            throw new \RuntimeException("Archive file does not exist: {$filename}");
        }

        $file = new \SplFileObject($filepath, 'rb');
        $file->flock(LOCK_SH);
        
        $lines = [];
        while (!$file->eof()) {
            $line = $file->fgets();
            if ($line !== false && trim($line) !== '') {
                $lines[] = $line;
            }
        }
        
        $file->flock(LOCK_UN);
        
        return $lines;
    }
}
