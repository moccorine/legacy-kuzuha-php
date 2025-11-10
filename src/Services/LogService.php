<?php

namespace App\Services;

use App\Models\Repositories\BbsLogRepositoryInterface;

/**
 * Log Service
 *
 * Handles log file reading and CSV parsing operations.
 */
class LogService
{
    private ?BbsLogRepositoryInterface $bbsLogRepository = null;
    private string $logFilename;
    private string $oldLogDir;

    public function __construct(string $logFilename, string $oldLogDir)
    {
        $this->logFilename = $logFilename;
        $this->oldLogDir = $oldLogDir;
    }

    /**
     * Set BBS log repository for main log access
     */
    public function setBbsLogRepository(BbsLogRepositoryInterface $repository): void
    {
        $this->bbsLogRepository = $repository;
    }

    /**
     * Get log lines from file
     *
     * Reads the log file and returns raw lines as array.
     * - Without parameter: Returns main log (via repository or file)
     * - With parameter: Returns archive log (direct file read)
     *
     * @param string $filename Log file name (optional)
     * @return array Raw log lines
     * @throws \RuntimeException If file cannot be read
     */
    public function getLogLines(string $filename = ''): array
    {
        if ($filename) {
            return $this->readArchiveLog($filename);
        }

        if ($this->bbsLogRepository) {
            return $this->bbsLogRepository->getAll();
        }

        return $this->readMainLog();
    }

    /**
     * Parse log line to message array
     *
     * Converts a CSV log line to associative array with message fields.
     * Returns null if line format is invalid.
     *
     * @param string $logline Raw log line
     * @return array|null Message array or null if invalid
     */
    public function parseLogLine(string $logline): ?array
    {
        $logsplit = @explode(',', rtrim($logline));
        if (count($logsplit) < 10) {
            return null;
        }

        // Restore commas in message fields (positions 5-9)
        for ($i = 5; $i <= 9; $i++) {
            $logsplit[$i] = strtr($logsplit[$i], "\0", ',');
            $logsplit[$i] = str_replace('&#44;', ',', $logsplit[$i]);
        }

        $messageKey = [
            'NDATE', 'POSTID', 'PROTECT', 'THREAD', 'PHOST', 'AGENT',
            'USER', 'MAIL', 'TITLE', 'MSG', 'REFID',
            'RESERVED1', 'RESERVED2', 'RESERVED3',
        ];

        $message = [];
        foreach ($logsplit as $i => $value) {
            if ($i > 12) {
                break;
            }
            $message[$messageKey[$i]] = $value;
        }

        return $message;
    }

    /**
     * Read archive log file
     */
    private function readArchiveLog(string $filename): array
    {
        // Sanitize filename
        preg_match("/^([\w.]*)$/", $filename, $matches);
        $filepath = $this->oldLogDir . '/' . $matches[1];

        if (!file_exists($filepath)) {
            throw new \RuntimeException("Archive log file not found: {$filename}");
        }

        return file($filepath);
    }

    /**
     * Read main log file
     */
    private function readMainLog(): array
    {
        if (!file_exists($this->logFilename)) {
            throw new \RuntimeException("Main log file not found: {$this->logFilename}");
        }

        return file($this->logFilename);
    }
}
