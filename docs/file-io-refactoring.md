# File I/O Refactoring Plan

## Overview

Extract file I/O operations from controller classes to a Model layer using the **Repository Pattern**. The Model layer will support both:
1. **Legacy CSV format** (current implementation)
2. **SQLite/DBMS** (future implementation)

This allows gradual migration from CSV to database without breaking existing functionality.

## Status: 🚧 IN PROGRESS

## Architecture

```
src/
├── Models/
│   ├── Entities/
│   │   ├── Message.php          # Message entity (data object)
│   │   ├── Counter.php          # Counter entity
│   │   └── Archive.php          # Archive entity
│   │
│   ├── Repositories/
│   │   ├── MessageRepositoryInterface.php
│   │   ├── MessageCsvRepository.php      # CSV implementation
│   │   ├── MessageSqliteRepository.php   # SQLite implementation
│   │   │
│   │   ├── CounterRepositoryInterface.php
│   │   ├── CounterCsvRepository.php
│   │   ├── CounterSqliteRepository.php
│   │   │
│   │   └── ArchiveRepositoryInterface.php
│   │       ├── ArchiveCsvRepository.php
│   │       └── ArchiveSqliteRepository.php
│   │
│   └── RepositoryFactory.php    # Factory to create repositories based on config
```

## Repository Pattern Benefits

1. **Abstraction**: Controllers don't know about storage implementation
2. **Swappable**: Easy to switch between CSV and SQLite
3. **Testable**: Mock repositories for unit tests
4. **Gradual Migration**: Support both formats during transition
5. **Future-proof**: Easy to add MySQL, PostgreSQL, etc.

## Migration Strategy

### Phase 1: Message Repository
- [ ] Create `Message` entity class
- [ ] Create `MessageRepositoryInterface`
- [ ] Implement `MessageCsvRepository` (extract from Webapp)
- [ ] Implement `MessageSqliteRepository` (basic structure)
- [ ] Create `RepositoryFactory`
- [ ] Add configuration option for storage backend
- [ ] Add unit tests for both implementations

### Phase 2: Counter Repository
- [ ] Create `Counter` entity class
- [ ] Create `CounterRepositoryInterface`
- [ ] Implement `CounterCsvRepository`
- [ ] Implement `CounterSqliteRepository`
- [ ] Integrate with `ParticipantCounter`
- [ ] Add unit tests

### Phase 3: Archive Repository
- [ ] Create `Archive` entity class
- [ ] Create `ArchiveRepositoryInterface`
- [ ] Implement `ArchiveCsvRepository` (extract from Getlog)
- [ ] Implement `ArchiveSqliteRepository`
- [ ] Add unit tests

### Phase 4: Integration
- [ ] Update controllers to use repositories
- [ ] Add migration script (CSV → SQLite)
- [ ] Update documentation
- [ ] Performance testing

## Configuration

Add to `conf.php`:
```php
'STORAGE_BACKEND' => 'csv', // or 'sqlite'
'SQLITE_DATABASE' => 'storage/database.sqlite',
```

## CSV Format (Current)

Messages stored in log files with pipe-delimited format:
```
postid|thread|date|user|email|url|host|title|message|...
```

## SQLite Schema (Future)

```sql
CREATE TABLE messages (
    id INTEGER PRIMARY KEY,
    thread_id INTEGER,
    post_date INTEGER,
    user TEXT,
    email TEXT,
    url TEXT,
    host TEXT,
    title TEXT,
    message TEXT,
    ...
);

CREATE TABLE counters (
    id INTEGER PRIMARY KEY,
    user_key TEXT UNIQUE,
    last_seen INTEGER
);

CREATE TABLE archives (
    id INTEGER PRIMARY KEY,
    filename TEXT,
    created_at INTEGER,
    ...
);
```

## Example Usage

```php
// In controller
$messageRepo = RepositoryFactory::createMessageRepository();
$message = $messageRepo->findById($postId);
$messageRepo->save($message);

// Repository automatically uses CSV or SQLite based on config
```

## Testing Strategy

- Unit tests for each repository implementation
- Mock file system for CSV tests
- In-memory SQLite for database tests
- Integration tests for repository factory
- Test data migration between formats

## Notes

- Start with CSV implementation (extract existing logic)
- SQLite implementation can be minimal initially
- Keep backward compatibility
- Document CSV file format specification
- Consider adding caching layer later
