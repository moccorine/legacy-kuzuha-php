# Counter Specification

## Overview

The BBS has two types of counters:
1. **Total Access Counter** - Tracks total page views using pseudo-transaction files
2. **Participant Counter** - Tracks currently active users based on IP and timeout

## 1. Total Access Counter

### Purpose
Count total page views with high concurrency support using a pseudo-transaction mechanism.

### File Structure
```
storage/app/count/
├── count0.dat
├── count1.dat
├── count2.dat
├── count3.dat
└── count4.dat
```

Each file contains a single integer representing a count value.

### Algorithm (Pseudo-Transaction)

```
1. Read all counter files (e.g., 5 files)
   count0.dat: 925
   count1.dat: 924
   count2.dat: 923
   count3.dat: 922
   count4.dat: 921

2. Find minimum value: 921 (in count4.dat)

3. Find maximum value: 925

4. Write (max + 1) to the file with minimum value
   count4.dat: 926

5. Return: 926
```

### Concurrency Handling

Multiple simultaneous requests will:
- Read different minimum values (due to timing)
- Write to different files
- Avoid write conflicts

**Example with 3 concurrent requests:**
```
Initial state:
count0.dat: 100
count1.dat: 99
count2.dat: 98

Request A: reads min=98, writes 101 to count2.dat
Request B: reads min=99, writes 101 to count1.dat  
Request C: reads min=100, writes 101 to count0.dat

Final state:
count0.dat: 101
count1.dat: 101
count2.dat: 101
```

All three requests successfully increment the counter without conflicts.

### Configuration

```php
'COUNTLEVEL' => 5,  // Number of counter files (default: 5)
'COUNTFILE' => './storage/app/count/count',  // File prefix
```

### Implementation

```php
public function counter($countlevel = 0)
{
    // Read all counter files
    for ($i = 0; $i < $countlevel; $i++) {
        $filename = "{$this->config['COUNTFILE']}{$i}.dat";
        $count[$i] = file_get_contents($filename);
    }
    
    // Find min and max
    sort($count, SORT_NUMERIC);
    $mincount = $count[0];
    $maxcount = $count[$countlevel - 1] + 1;
    
    // Write max+1 to file with min value
    file_put_contents($fileWithMin, $maxcount);
    
    return $maxcount;
}
```

### Advantages
- No file locking required
- High concurrency support
- Simple implementation
- Fault tolerant (if one file fails, others continue)

### Disadvantages
- Uses multiple files
- Count may temporarily diverge across files
- Requires periodic synchronization (not implemented)

---

## 2. Participant Counter

### Purpose
Track currently active users (within timeout period) based on unique IP addresses.

### File Structure
```
storage/app/bbs.cnt
```

Single file with CSV format:
```
userkey,timestamp
3789011499,1762615624
2500871836,1762615696
```

### Data Format

- **userkey**: `hexdec(substr(md5($remoteAddr), 0, 8))` - First 8 hex chars of MD5(IP) as decimal
- **timestamp**: UNIX timestamp of last visit

### Algorithm

```
1. Calculate user key from IP address
   IP: 192.168.1.1
   MD5: c6f057b86584942e415435ffb1fa93d4
   Key: hexdec("c6f057b8") = 3337838520

2. Read existing entries from file

3. Filter entries:
   - If user exists: update timestamp
   - If entry not expired: keep
   - If entry expired: discard

4. If user not found: add new entry

5. Write filtered entries back to file (with flock)

6. Return count of active entries
```

### Expiration Logic

Entry is considered active if:
```
(entry_timestamp + CNTLIMIT) >= current_time
```

Example with `CNTLIMIT = 300` (5 minutes):
```
Current time: 1762615696
Entry timestamp: 1762615400
Expired: (1762615400 + 300) < 1762615696 → YES, discard
```

### Configuration

```php
'CNTFILENAME' => './storage/app/bbs.cnt',  // Counter file path
'CNTLIMIT' => 300,  // Timeout in seconds (default: 5 minutes)
```

### Implementation

```php
public static function count(string $cntFilename, int $cntLimit, int $currentTime)
{
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ukey = hexdec(substr(md5($remoteAddr), 0, 8));
    
    // Read and filter entries
    $cntData = file($cntFilename);
    $newCntData = [];
    $mbrCount = 0;
    
    foreach ($cntData as $line) {
        [$cuser, $ctime] = explode(',', trim($line));
        
        if ($cuser == $ukey) {
            // Update current user
            $newCntData[] = "$ukey,$currentTime\n";
            $mbrCount++;
        } elseif (($ctime + $cntLimit) >= $currentTime) {
            // Keep active user
            $newCntData[] = "$cuser,$ctime\n";
            $mbrCount++;
        }
        // Expired entries are dropped
    }
    
    // Add new user if not found
    if (!$userAdded) {
        $newCntData[] = "$ukey,$currentTime\n";
        $mbrCount++;
    }
    
    // Write with lock
    $fh = fopen($cntFilename, 'w');
    flock($fh, LOCK_EX);
    fwrite($fh, implode('', $newCntData));
    flock($fh, LOCK_UN);
    fclose($fh);
    
    return $mbrCount;
}
```

### Concurrency Handling

Uses `flock(LOCK_EX)` for exclusive file locking:
- Only one process can write at a time
- Other processes wait for lock release
- Prevents data corruption

### Advantages
- Accurate active user count
- Automatic cleanup of expired entries
- Simple single-file format

### Disadvantages
- File locking can cause bottleneck under high load
- All entries rewritten on each access
- No historical data

---

## Migration to Repository Pattern

### Current Implementation
- Direct file I/O in `Bbs.php` and `ParticipantCounter.php`
- Tightly coupled to CSV format

### Future Implementation

**Total Access Counter:**
```php
interface AccessCounterRepositoryInterface {
    public function increment(): int;
    public function getCurrent(): int;
}

// CSV: Multi-file pseudo-transaction
class AccessCounterCsvRepository implements AccessCounterRepositoryInterface

// SQLite: Simple auto-increment
class AccessCounterSqliteRepository implements AccessCounterRepositoryInterface
```

**Participant Counter:**
```php
interface ParticipantCounterRepositoryInterface {
    public function recordVisit(string $userKey, int $timestamp): int;
    public function getActiveCount(int $currentTime, int $timeoutSeconds): int;
    public function cleanup(int $currentTime, int $timeoutSeconds): void;
}

// CSV: Current implementation
class ParticipantCounterCsvRepository implements ParticipantCounterRepositoryInterface

// SQLite: Table with indexed queries
class ParticipantCounterSqliteRepository implements ParticipantCounterRepositoryInterface
```

### SQLite Schema

```sql
-- Total access counter
CREATE TABLE access_counter (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    count INTEGER NOT NULL DEFAULT 0
);

-- Participant counter
CREATE TABLE participants (
    user_key TEXT PRIMARY KEY,
    last_seen INTEGER NOT NULL
);

CREATE INDEX idx_participants_last_seen ON participants(last_seen);
```

---

## Testing Considerations

### Total Access Counter
- Test concurrent increments
- Test file creation/recovery
- Test with different COUNTLEVEL values

### Participant Counter
- Test user key generation
- Test expiration logic
- Test concurrent access with flock
- Test file corruption recovery
