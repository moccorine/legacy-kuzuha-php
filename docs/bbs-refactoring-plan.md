# Bbs.php Refactoring Plan

## Current State

**File:** `src/Kuzuha/Bbs.php`
- **Lines:** 1,502
- **Methods:** 21
- **Issues:** God Object anti-pattern, giant methods, mixed responsibilities

### Method Complexity Analysis

| Method | Lines | Responsibility |
|--------|-------|----------------|
| `putmessage()` | 244 | Post creation (validation, save, notification) |
| `prtmain()` | 119 | Main view rendering |
| `main()` | 92 | Request routing |
| `setcustom()` | 70 | User preference handling |
| `prtfollow()` | 74 | Follow-up form rendering |
| `msgsearchlist()` | 66 | Search results display |
| `prtcustom()` | 50 | Custom settings form |
| `prtundo()` | 46 | Undo post handling |
| `prtnewpost()` | 35 | New post form rendering |
| `prtsearchlist()` | 29 | Search list display |
| `setuserenv()` | 25 | User environment setup |
| `searchmessage()` | 23 | Message search logic |

## Problems

### 1. God Object
`Bbs` class handles too many responsibilities:
- Request routing
- Post validation
- Post persistence
- View rendering
- Search functionality
- User preferences
- Cookie management
- Counter management

### 2. Giant Methods
- `putmessage()`: 244 lines doing validation, file I/O, notification
- `prtmain()`: 119 lines mixing data retrieval and HTML generation
- `main()`: 92 lines with complex routing logic

### 3. Mixed Responsibilities
- Business logic mixed with presentation
- Validation scattered across methods
- Direct file I/O operations
- HTML generation in controller

### 4. Testing Challenges
- Hard to unit test due to dependencies
- No dependency injection (except Repositories)
- Global state dependencies ($_POST, $_GET)
- Side effects (file writes, cookies, headers)

## Refactoring Strategy

### Phase 1: Post Processing Service (Priority: HIGH)

**Goal:** Extract `putmessage()` logic to dedicated service

**New Class:** `src/Services/PostService.php`

**Methods to extract:**
```php
class PostService
{
    public function createPost(array $formData, array $config): array
    public function validatePost(array $formData): void
    public function savePost(array $message): string
    private function generatePostId(): string
    private function notifyNewPost(array $message): void
}
```

**Benefits:**
- Reduce `Bbs.php` by ~250 lines
- Testable post creation logic
- Reusable across different entry points

**Steps:**
1. Create `PostService` class
2. Extract validation logic from `chkmessage()`
3. Extract post creation from `putmessage()`
4. Extract file writing logic
5. Update `Bbs::putmessage()` to use service
6. Write unit tests

### Phase 2: Validation Service (Priority: HIGH)

**Goal:** Centralize validation logic

**New Class:** `src/Services/PostValidator.php`

**Methods to extract:**
```php
class PostValidator
{
    public function validate(array $formData, array $config): void
    private function validateHost(string $host): void
    private function validateContent(string $content): void
    private function validateFloodControl(string $host): void
    private function validateSpam(array $formData): void
}
```

**Current locations:**
- `chkmessage()` - 111 lines of validation
- Scattered validation in `putmessage()`

**Benefits:**
- Single responsibility for validation
- Easy to add new validation rules
- Testable validation logic

### Phase 3: View Rendering Service (Priority: MEDIUM)

**Goal:** Separate presentation from controller

**New Class:** `src/Services/ViewRenderer.php`

**Methods to extract:**
```php
class ViewRenderer
{
    public function renderMainView(array $data): string
    public function renderFollowForm(array $data): string
    public function renderNewPostForm(array $data): string
    public function renderCustomForm(array $data): string
    public function renderSearchResults(array $data): string
}
```

**Current locations:**
- `prtmain()` - 119 lines
- `prtfollow()` - 74 lines
- `prtnewpost()` - 35 lines
- `prtcustom()` - 50 lines
- `msgsearchlist()` - 66 lines

**Benefits:**
- Clean separation of concerns
- Easier to switch template engines
- Testable rendering logic

### Phase 4: Search Service (Priority: MEDIUM)

**Goal:** Extract search functionality

**New Class:** `src/Services/SearchService.php`

**Methods to extract:**
```php
class SearchService
{
    public function searchMessages(string $field, string $value): array
    public function searchByThread(string $threadId): array
    public function searchByUser(string $username): array
    public function getSearchResults(array $criteria): array
}
```

**Current locations:**
- `searchmessage()` - 23 lines
- `msgsearchlist()` - 66 lines
- `prtsearchlist()` - 29 lines

**Benefits:**
- Dedicated search logic
- Easier to optimize queries
- Potential for search indexing

### Phase 5: User Preference Service (Priority: LOW)

**Goal:** Handle user settings separately

**New Class:** `src/Services/UserPreferenceService.php`

**Methods to extract:**
```php
class UserPreferenceService
{
    public function getUserPreferences(): array
    public function savePreferences(array $preferences): void
    public function getDefaultPreferences(): array
}
```

**Current locations:**
- `setcustom()` - 70 lines
- `prtcustom()` - 50 lines
- `setuserenv()` - 25 lines

**Benefits:**
- Centralized preference management
- Easier to add new preferences
- Potential for database storage

## Implementation Order

1. **PostService** (Week 1)
   - Highest impact (244 lines)
   - Core functionality
   - Enables better testing

2. **PostValidator** (Week 1)
   - Works with PostService
   - Improves security
   - Reusable validation

3. **ViewRenderer** (Week 2)
   - Large impact (344 lines total)
   - Clean architecture
   - Template flexibility

4. **SearchService** (Week 2)
   - Medium complexity
   - Performance optimization opportunity

5. **UserPreferenceService** (Week 3)
   - Lower priority
   - Nice to have
   - Future enhancement

## Success Metrics

- Reduce `Bbs.php` from 1,502 to ~600 lines
- Achieve 80%+ test coverage for extracted services
- No breaking changes to existing functionality
- Maintain backward compatibility
- Improve code maintainability score

## Testing Strategy

Each extracted service should have:
- Unit tests for all public methods
- Integration tests for service interactions
- Feature tests for end-to-end workflows

## Migration Notes

- Use feature branches for each phase
- Maintain backward compatibility
- Add deprecation notices for old methods
- Document API changes
- Update README with new architecture

## Related Documents

- [Counter Repository Plan](counter-repository-plan.md)
- [Cookie Management](../src/Services/CookieService.php)
- [PSR Compliance Refactoring](psr-compliance-refactoring.md)
