<?php

namespace App\Models\Repositories;

/**
 * SQLite implementation for Access Counter (Future implementation)
 *
 * Schema:
 * CREATE TABLE access_counter (
 *     id INTEGER PRIMARY KEY CHECK (id = 1),
 *     count INTEGER NOT NULL DEFAULT 0
 * );
 */
class AccessCounterSqliteRepository implements AccessCounterRepositoryInterface
{
    private \PDO $db;

    public function __construct(string $dbPath)
    {
        $this->db = new \PDO("sqlite:$dbPath");
        $this->initializeSchema();
    }

    private function initializeSchema(): void
    {
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS access_counter (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                count INTEGER NOT NULL DEFAULT 0
            )
        ');

        $this->db->exec('
            INSERT OR IGNORE INTO access_counter (id, count) VALUES (1, 0)
        ');
    }

    public function increment(): int
    {
        $this->db->exec('UPDATE access_counter SET count = count + 1 WHERE id = 1');
        return $this->getCurrent();
    }

    public function getCurrent(): int
    {
        $stmt = $this->db->query('SELECT count FROM access_counter WHERE id = 1');
        return (int) $stmt->fetchColumn();
    }

    public function getCountLevel(): int|false
    {
        // SQLite doesn't use multiple files, concept doesn't apply
        return false;
    }
}
