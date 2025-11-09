<?php

namespace App\Models\Repositories;

/**
 * SQLite implementation for Participant Counter (Future implementation)
 * 
 * Schema:
 * CREATE TABLE participants (
 *     user_key TEXT PRIMARY KEY,
 *     last_seen INTEGER NOT NULL
 * );
 * CREATE INDEX idx_participants_last_seen ON participants(last_seen);
 */
class ParticipantCounterSqliteRepository implements ParticipantCounterRepositoryInterface
{
    private \PDO $db;

    public function __construct(string $dbPath)
    {
        $this->db = new \PDO("sqlite:$dbPath");
        $this->initializeSchema();
    }

    private function initializeSchema(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS participants (
                user_key TEXT PRIMARY KEY,
                last_seen INTEGER NOT NULL
            )
        ");
        
        $this->db->exec("
            CREATE INDEX IF NOT EXISTS idx_participants_last_seen 
            ON participants(last_seen)
        ");
    }

    public function recordVisit(string $userKey, int $timestamp, int $timeoutSeconds): int
    {
        // Upsert participant
        $stmt = $this->db->prepare(
            "INSERT OR REPLACE INTO participants (user_key, last_seen) VALUES (?, ?)"
        );
        $stmt->execute([$userKey, $timestamp]);
        
        // Cleanup expired entries
        $expireTime = $timestamp - $timeoutSeconds;
        $stmt = $this->db->prepare("DELETE FROM participants WHERE last_seen < ?");
        $stmt->execute([$expireTime]);
        
        return $this->getActiveCount($timestamp, $timeoutSeconds);
    }

    public function getActiveCount(int $currentTime, int $timeoutSeconds): int
    {
        $expireTime = $currentTime - $timeoutSeconds;
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM participants WHERE last_seen >= ?"
        );
        $stmt->execute([$expireTime]);
        return (int) $stmt->fetchColumn();
    }
}
