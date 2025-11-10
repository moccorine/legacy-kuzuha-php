<?php

namespace App\Models\Repositories;

class BbsLogFileRepository implements BbsLogRepositoryInterface
{
    private ?\SplFileObject $lockedFile = null;

    public function __construct(
        private string $logFilePath
    ) {
    }

    public function append(array $message): void
    {
        $line = implode(',', $message) . "\n";

        $file = new \SplFileObject($this->logFilePath, 'ab');
        if ($file->fwrite($line) === false) {
            throw new \RuntimeException("Failed to write to log file: {$this->logFilePath}");
        }
    }

    public function getAll(): array
    {
        if (!file_exists($this->logFilePath)) {
            throw new \RuntimeException("Log file does not exist: {$this->logFilePath}");
        }

        $file = new \SplFileObject($this->logFilePath, 'rb');
        $lines = [];

        while (!$file->eof()) {
            $line = $file->fgets();
            if ($line !== false && $line !== '') {
                $lines[] = $line;
            }
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

        $file = new \SplFileObject($this->logFilePath, 'wb');
        foreach ($newLines as $line) {
            $file->fwrite($line);
        }

        return true;
    }

    public function count(): int
    {
        return count($this->getAll());
    }

    public function getNextPostId(): int
    {
        if (!file_exists($this->logFilePath)) {
            return 1;
        }

        $all = $this->getAll();
        if (empty($all)) {
            return 1;
        }

        $firstLine = $all[0];
        $parts = explode(',', $firstLine, 3);

        return isset($parts[1]) ? (int)$parts[1] + 1 : 1;
    }

    public function prepend(array $message, int $maxMessages): void
    {
        $this->lock();

        try {
            $all = $this->getAll();

            // Convert associative array to indexed array and clean newlines
            $values = array_values($message);
            $cleanMessage = array_map(fn ($v) => str_replace("\n", '', $v), $values);
            $line = implode(',', $cleanMessage) . "\n";

            if (count($all) >= $maxMessages) {
                $all = array_slice($all, 0, $maxMessages - 1);
            }

            array_unshift($all, $line);

            $file = new \SplFileObject($this->logFilePath, 'wb');
            foreach ($all as $logLine) {
                $file->fwrite($logLine);
            }
        } finally {
            $this->unlock();
        }
    }

    public function lock(): void
    {
        if ($this->lockedFile) {
            return;
        }

        $this->lockedFile = new \SplFileObject($this->logFilePath, 'rb+');

        if (!$this->lockedFile->flock(LOCK_EX)) {
            $this->lockedFile = null;
            throw new \RuntimeException("Failed to lock log file: {$this->logFilePath}");
        }
    }

    public function unlock(): void
    {
        if (!$this->lockedFile) {
            return;
        }

        $this->lockedFile->flock(LOCK_UN);
        $this->lockedFile = null;
    }

    /**
     * Delete messages by post IDs
     *
     * @param array $postIds Array of post IDs to delete
     * @return array Deleted message lines
     */
    public function deleteMessages(array $postIds): array
    {
        $this->lock();

        try {
            $allLines = $this->getAll();
            $deletedLines = [];
            $remainingLines = [];

            foreach ($allLines as $line) {
                $items = explode(',', $line, 3);
                if (count($items) > 2 && in_array($items[1], $postIds, true)) {
                    $deletedLines[] = $line;
                } else {
                    $remainingLines[] = $line;
                }
            }

            // Rewrite file with remaining messages
            $handle = fopen($this->logFile, 'w');
            if ($handle === false) {
                throw new \RuntimeException("Failed to open log file for writing: {$this->logFile}");
            }

            fwrite($handle, implode('', $remainingLines));
            fclose($handle);

            return $deletedLines;
        } finally {
            $this->unlock();
        }
    }

    /**
     * Delete message from archive file
     *
     * @param string $filepath Archive file path
     * @param string $postId Post ID to delete
     * @param int $timestamp Post timestamp
     * @param bool $isDatFormat True for DAT format, false for HTML format
     * @return bool True if deleted, false if not found or failed
     */
    public function deleteFromArchive(string $filepath, string $postId, int $timestamp, bool $isDatFormat): bool
    {
        $fh = @fopen($filepath, 'r+');
        if (!$fh) {
            return false;
        }

        flock($fh, LOCK_EX);
        fseek($fh, 0);

        $result = $isDatFormat
            ? $this->filterDatFormat($fh, $postId, $timestamp)
            : $this->filterHtmlFormat($fh, $postId);

        $deleted = $result['deleted'];
        $newlogdata = $result['lines'];

        fseek($fh, 0);
        ftruncate($fh, 0);
        fwrite($fh, implode('', $newlogdata));
        flock($fh, LOCK_UN);
        fclose($fh);

        return $deleted;
    }

    /**
     * Filter DAT format archive, removing target message
     *
     * @param resource $fh File handle
     * @param string $postId Post ID to remove
     * @param int $timestamp Post timestamp
     * @return array{deleted: bool, lines: array<int, string>}
     */
    private function filterDatFormat($fh, string $postId, int $timestamp): array
    {
        $newlogdata = [];
        $needle = $timestamp . ',' . $postId . ',';
        $deleted = false;

        while (($line = fgets($fh)) !== false) {
            if (!$deleted && str_starts_with($line, $needle)) {
                $deleted = true;
            } else {
                $newlogdata[] = $line;
            }
        }

        return ['deleted' => $deleted, 'lines' => $newlogdata];
    }

    /**
     * Filter HTML format archive, removing target message
     *
     * @param resource $fh File handle
     * @param string $postId Post ID to remove
     * @return array{deleted: bool, lines: array<int, string>}
     */
    private function filterHtmlFormat($fh, string $postId): array
    {
        $newlogdata = [];
        $needle = "<div class=\"m\" id=\"m{$postId}\">";
        $inTargetMessage = false;
        $deleted = false;

        while (($line = fgets($fh)) !== false) {
            if (!$inTargetMessage && str_contains($line, $needle)) {
                $inTargetMessage = true;
                $deleted = true;
            } elseif ($inTargetMessage && str_contains($line, '<hr')) {
                $inTargetMessage = false;
            } elseif (!$inTargetMessage) {
                $newlogdata[] = $line;
            }
        }

        return ['deleted' => $deleted, 'lines' => $newlogdata];
    }
}
