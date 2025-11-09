# Counter Repository Implementation Plan

## Overview

Implement Counter Repository as a **standalone module** that can be developed and tested independently, then integrated later.

## Implementation Order

### Step 1: Interfaces (契約定義)
```
src/Models/Repositories/
├── AccessCounterRepositoryInterface.php
└── ParticipantCounterRepositoryInterface.php
```

### Step 2: CSV Implementations (既存ロジック抽出)
```
src/Models/Repositories/
├── AccessCounterCsvRepository.php
└── ParticipantCounterCsvRepository.php
```

### Step 3: SQLite Implementations (新規実装)
```
src/Models/Repositories/
├── AccessCounterSqliteRepository.php
└── ParticipantCounterSqliteRepository.php
```

### Step 4: Factory (自動選択)
```
src/Models/RepositoryFactory.php
```

### Step 5: Tests (単体テスト)
```
tests/Unit/Repositories/
├── AccessCounterCsvRepositoryTest.php
├── AccessCounterSqliteRepositoryTest.php
├── ParticipantCounterCsvRepositoryTest.php
└── ParticipantCounterSqliteRepositoryTest.php
```

### Step 6: Integration (既存コードと結合)
- `Bbs.php` の `counter()` を Repository 経由に変更
- `Bbs.php` の `getParticipantCount()` を Repository 経由に変更

---

## Interface Design

### AccessCounterRepositoryInterface

```php
<?php

namespace App\Models\Repositories;

interface AccessCounterRepositoryInterface
{
    /**
     * Increment counter and return new value
     * 
     * @return int New counter value
     */
    public function increment(): int;
    
    /**
     * Get current counter value without incrementing
     * 
     * @return int Current counter value
     */
    public function getCurrent(): int;
}
```

### ParticipantCounterRepositoryInterface

```php
<?php

namespace App\Models\Repositories;

interface ParticipantCounterRepositoryInterface
{
    /**
     * Record a visit and return active participant count
     * 
     * @param string $userKey User identifier (IP-based hash)
     * @param int $timestamp Current timestamp
     * @param int $timeoutSeconds Timeout in seconds
     * @return int Number of active participants
     */
    public function recordVisit(string $userKey, int $timestamp, int $timeoutSeconds): int;
    
    /**
     * Get active participant count without recording
     * 
     * @param int $currentTime Current timestamp
     * @param int $timeoutSeconds Timeout in seconds
     * @return int Number of active participants
     */
    public function getActiveCount(int $currentTime, int $timeoutSeconds): int;
    
    /**
     * Clean up expired entries
     * 
     * @param int $currentTime Current timestamp
     * @param int $timeoutSeconds Timeout in seconds
     * @return void
     */
    public function cleanup(int $currentTime, int $timeoutSeconds): void;
}
```

---

## CSV Implementation (Extract from existing code)

### AccessCounterCsvRepository

**Constructor:**
```php
public function __construct(string $filePrefix, int $fileCount)
{
    $this->filePrefix = $filePrefix;  // './storage/app/count/count'
    $this->fileCount = $fileCount;    // 5
}
```

**Methods:**
- `increment()` - Extract from `Bbs::counter()`
- `getCurrent()` - Read max value from all files

### ParticipantCounterCsvRepository

**Constructor:**
```php
public function __construct(string $filename)
{
    $this->filename = $filename;  // './storage/app/bbs.cnt'
}
```

**Methods:**
- `recordVisit()` - Extract from `ParticipantCounter::count()`
- `getActiveCount()` - Read and filter without writing
- `cleanup()` - Remove expired entries

---

## SQLite Implementation (New)

### Database Schema

```sql
-- Access counter
CREATE TABLE IF NOT EXISTS access_counter (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    count INTEGER NOT NULL DEFAULT 0
);

INSERT OR IGNORE INTO access_counter (id, count) VALUES (1, 0);

-- Participant counter
CREATE TABLE IF NOT EXISTS participants (
    user_key TEXT PRIMARY KEY,
    last_seen INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_participants_last_seen 
ON participants(last_seen);
```

### AccessCounterSqliteRepository

**Constructor:**
```php
public function __construct(string $dbPath)
{
    $this->db = new \PDO("sqlite:$dbPath");
    $this->initializeSchema();
}
```

**Methods:**
```php
public function increment(): int
{
    $this->db->exec("UPDATE access_counter SET count = count + 1 WHERE id = 1");
    return $this->getCurrent();
}

public function getCurrent(): int
{
    $stmt = $this->db->query("SELECT count FROM access_counter WHERE id = 1");
    return (int) $stmt->fetchColumn();
}
```

### ParticipantCounterSqliteRepository

**Methods:**
```php
public function recordVisit(string $userKey, int $timestamp, int $timeoutSeconds): int
{
    // INSERT OR REPLACE
    $stmt = $this->db->prepare(
        "INSERT OR REPLACE INTO participants (user_key, last_seen) VALUES (?, ?)"
    );
    $stmt->execute([$userKey, $timestamp]);
    
    // Cleanup expired
    $this->cleanup($timestamp, $timeoutSeconds);
    
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

public function cleanup(int $currentTime, int $timeoutSeconds): void
{
    $expireTime = $currentTime - $timeoutSeconds;
    $stmt = $this->db->prepare(
        "DELETE FROM participants WHERE last_seen < ?"
    );
    $stmt->execute([$expireTime]);
}
```

---

## Factory Implementation

```php
<?php

namespace App\Models;

use App\Config;
use App\Models\Repositories\AccessCounterRepositoryInterface;
use App\Models\Repositories\ParticipantCounterRepositoryInterface;

class RepositoryFactory
{
    public static function createAccessCounterRepository(): AccessCounterRepositoryInterface
    {
        $config = Config::getInstance();
        $backend = $config->get('STORAGE_BACKEND') ?? 'csv';
        
        if ($backend === 'sqlite') {
            return new Repositories\AccessCounterSqliteRepository(
                $config->get('SQLITE_DATABASE')
            );
        }
        
        return new Repositories\AccessCounterCsvRepository(
            $config->get('COUNTFILE'),
            $config->get('COUNTLEVEL')
        );
    }
    
    public static function createParticipantCounterRepository(): ParticipantCounterRepositoryInterface
    {
        $config = Config::getInstance();
        $backend = $config->get('STORAGE_BACKEND') ?? 'csv';
        
        if ($backend === 'sqlite') {
            return new Repositories\ParticipantCounterSqliteRepository(
                $config->get('SQLITE_DATABASE')
            );
        }
        
        return new Repositories\ParticipantCounterCsvRepository(
            $config->get('CNTFILENAME')
        );
    }
}
```

---

## Configuration

Add to `conf.php`:
```php
// Storage backend: 'csv' or 'sqlite'
'STORAGE_BACKEND' => 'csv',

// SQLite database path (if using sqlite backend)
'SQLITE_DATABASE' => './storage/database.sqlite',
```

---

## Testing Strategy

### Unit Tests (Isolated)

**CSV Tests:**
- Use temporary files
- Test increment/decrement
- Test concurrent access simulation
- Test file corruption recovery

**SQLite Tests:**
- Use in-memory database (`:memory:`)
- Test CRUD operations
- Test expiration logic
- Test transaction handling

### Integration Tests (After Step 6)

- Test switching between CSV and SQLite
- Test data migration
- Test performance comparison

---

## Development Workflow

1. **Develop in isolation** - No changes to existing code
2. **Test thoroughly** - Unit tests for all implementations
3. **Integrate gradually** - Replace one method at a time
4. **Keep backward compatibility** - CSV remains default
5. **Document migration path** - How to switch to SQLite

---

## Benefits of Standalone Development

✅ **No risk to existing code** - Current system continues working
✅ **Easy to test** - Mock dependencies, test in isolation
✅ **Parallel development** - Can work on other features
✅ **Gradual rollout** - Integrate when ready
✅ **Easy rollback** - Just don't integrate if issues found

---

## Next Steps

1. Create interfaces (契約)
2. Implement CSV repositories (extract existing logic)
3. Write unit tests for CSV
4. Implement SQLite repositories
5. Write unit tests for SQLite
6. Create factory
7. Integration (when ready)
