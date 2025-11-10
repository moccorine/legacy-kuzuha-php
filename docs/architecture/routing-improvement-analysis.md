# Routing Improvement Analysis

## Current State

### Routes Defined in routes.php
```
GET  /           → Bbs::main() (with repositories)
POST /           → Bbs::main() (with repositories)
GET  /search     → Getlog::main()
POST /search     → Getlog::main()
GET  /tree       → Treeview::main()
POST /tree       → Treeview::main()
GET  /thread     → Bbs::prtsearchlist()
POST /thread     → Bbs::prtsearchlist()
GET  /follow     → Bbs::prtfollow() or Bbs::main() (if POST with data)
POST /follow     → Bbs::main() (if has 'v' field)
GET  /admin      → BbsAdmin::main()
POST /admin      → BbsAdmin::main()
```

### Problem: Routes Still Call main()

Most routes end up calling `main()` which does internal routing:

```php
// routes.php
$app->get('/', function() {
    $bbs = new Bbs();
    $bbs->main();  // ← Internal routing happens here
});

// Bbs.php main()
switch ($mode) {
    case 'p':
        $this->handlePostMode();
        break;
    case 'c':
        $this->saveUserPreferences();
        break;
    case 'u':
        $this->prtundo();
        break;
    default:
        $this->prtmain(...);
        break;
}
```

**Result**: Slim routing is bypassed, all logic goes through `main()`.

## Issues

### 1. Duplicate Routing Logic
- Slim defines routes in `routes.php`
- `Bbs::main()` does internal routing based on `$this->form['m']`
- Two routing layers doing the same thing

### 2. Routes Don't Match Actions
```php
// URL says /follow but code calls main()
$app->get('/follow', function() {
    $bbs->prtfollow();  // ✓ Direct call
});

$app->post('/follow', function() {
    $bbs->main();  // ✗ Goes through internal routing
});
```

### 3. Cannot Apply Route-Specific Middleware
```php
// Want to apply auth middleware only to /admin
// But /admin calls main() which handles multiple modes
$app->post('/admin', function() {
    $bbsadmin->main();  // Handles 'k', 'x', 'p', 'l' modes internally
});
```

### 4. Hard to Test
- Cannot test individual actions without going through `main()`
- Cannot mock routing behavior
- Integration tests required for everything

### 5. Poor Separation of Concerns
- Routing logic in business logic layer
- HTTP concerns mixed with domain logic
- Violates Single Responsibility Principle

## Root Cause Analysis

### Why Does This Happen?

**Legacy Architecture**: Originally a single-file CGI script where `main()` was the entry point.

**Migration Path**: When moving to Slim, routes were added but `main()` was kept as-is.

**Form Parameter Dependency**: `main()` routes based on `$this->form['m']` which comes from POST/GET data.

### Example: Follow-Up Post Flow

```
User submits follow-up form
  ↓
POST /follow?s=5
  ↓
routes.php: if (POST && has 'v') → $bbs->main()
  ↓
Bbs::main()
  ↓
Bbs::handlePostMode() (because $this->form['m'] = 'p')
  ↓
Checks if ($this->form['f']) to determine follow-up
  ↓
Calls $this->prtputcomplete() or $this->prtmain()
```

**Problem**: The route `/follow` doesn't directly handle follow-up posts. It goes through `main()` → `handlePostMode()` → checks `$this->form['f']`.

## Proposed Solutions

### Option 1: Extract Actions from main() (Recommended)

**Goal**: Make routes call specific actions directly, not `main()`.

#### Step 1: Create Action Methods

```php
// Bbs.php
public function showMainPage(): void
{
    $this->loadAndSanitizeInput();
    $this->applyUserPreferences();
    $this->initializeSession();
    $this->prtmain(false, $this->accessCounterRepo, $this->participantCounterRepo);
}

public function handlePost(): void
{
    $this->loadAndSanitizeInput();
    $this->applyUserPreferences();
    $this->initializeSession();
    $this->loadUserEnvironment();
    
    $validator = new BbsPostValidator($this->config, $this->form, $this->session);
    $posterr = $validator->validate();
    
    if ($posterr === BbsPostValidator::VALID) {
        $message = $validator->buildMessage();
        $posterr = $this->saveMessage($message);
    }
    
    // Handle result...
}

public function showUserSettings(): void
{
    $this->loadAndSanitizeInput();
    $this->applyUserPreferences();
    $this->initializeSession();
    $this->prtcustom();
}

public function saveUserSettings(): void
{
    $this->loadAndSanitizeInput();
    $this->saveUserPreferences();
}

public function handleUndo(): void
{
    $this->loadAndSanitizeInput();
    $this->applyUserPreferences();
    $this->initializeSession();
    $this->prtundo();
}
```

#### Step 2: Update Routes

```php
// Main page
$app->get('/', function (Request $request, Response $response) use ($container) {
    $bbs = createBbs($container);
    $bbs->showMainPage();
    // ...
});

// Post submission
$app->post('/', function (Request $request, Response $response) use ($container) {
    $bbs = createBbs($container);
    $bbs->handlePost();
    // ...
});

// User settings page
$app->get('/settings', function (Request $request, Response $response) {
    $bbs = new Bbs();
    $bbs->showUserSettings();
    // ...
});

// Save user settings
$app->post('/settings', function (Request $request, Response $response) {
    $bbs = new Bbs();
    $bbs->saveUserSettings();
    // ...
});

// Undo
$app->post('/undo', function (Request $request, Response $response) use ($container) {
    $bbs = createBbs($container);
    $bbs->handleUndo();
    // ...
});
```

#### Step 3: Deprecate main()

```php
// Bbs.php
/**
 * @deprecated Use specific action methods instead
 */
public function main()
{
    // Keep for backward compatibility
    // But log deprecation warning
    trigger_error('Bbs::main() is deprecated', E_USER_DEPRECATED);
    
    // Existing implementation...
}
```

### Option 2: Controller Layer (Future)

Create dedicated controllers:

```php
class BbsController
{
    public function index(Request $request, Response $response): Response
    {
        $bbs = new Bbs(...);
        $bbs->showMainPage();
        return $response;
    }
    
    public function store(Request $request, Response $response): Response
    {
        $bbs = new Bbs(...);
        $bbs->handlePost();
        return $response;
    }
}

// routes.php
$app->get('/', [BbsController::class, 'index']);
$app->post('/', [BbsController::class, 'store']);
```

**Pros**: Clean separation, testable, follows MVC pattern
**Cons**: Requires more refactoring, new layer to maintain

### Option 3: Keep main() but Simplify (Minimal)

Keep `main()` but make it a thin dispatcher:

```php
public function main()
{
    $this->loadAndSanitizeInput();
    $this->applyUserPreferences();
    $this->initializeSession();
    
    $action = $this->determineAction();
    $this->$action();
}

private function determineAction(): string
{
    if ($this->form['setup']) return 'showUserSettings';
    
    return match($this->form['m'] ?? '') {
        'p' => 'handlePost',
        'c' => 'saveUserSettings',
        'u' => 'handleUndo',
        default => 'showMainPage',
    };
}
```

**Pros**: Minimal changes, keeps existing structure
**Cons**: Still has internal routing, doesn't solve core issues

## Recommended Approach

**Phase 1** (Now): Option 1 - Extract Actions
- Low risk, incremental
- Improves testability immediately
- Routes become clearer
- Can be done method by method

**Phase 2** (Later): Option 2 - Controller Layer
- After Phase 1 is complete
- When ready for larger refactoring
- Provides clean MVC architecture

## Implementation Plan

### Phase 1: Extract Actions from main()

#### Week 1: Main Page Actions
- [ ] Extract `showMainPage()` from `main()`
- [ ] Update `/` GET route to call `showMainPage()`
- [ ] Test main page display

#### Week 2: Post Actions
- [ ] Extract `handlePost()` from `handlePostMode()`
- [ ] Update `/` POST route to call `handlePost()`
- [ ] Test post submission

#### Week 3: Settings Actions
- [ ] Extract `showUserSettings()` from `prtcustom()`
- [ ] Extract `saveUserSettings()` (already exists)
- [ ] Create `/settings` routes
- [ ] Update form action URLs

#### Week 4: Other Actions
- [ ] Extract `handleUndo()` from `prtundo()`
- [ ] Create `/undo` route
- [ ] Update undo form action

#### Week 5: Follow-up Actions
- [ ] Extract `showFollowUpForm()` from `prtfollow()`
- [ ] Extract `handleFollowUpPost()` from `handlePostMode()`
- [ ] Update `/follow` routes
- [ ] Test follow-up flow

#### Week 6: Cleanup
- [ ] Mark `main()` as deprecated
- [ ] Add deprecation warnings
- [ ] Update documentation
- [ ] Remove internal routing from `main()`

### Phase 2: Controller Layer (Future)

- [ ] Create `Controllers/` directory
- [ ] Create `BbsController`
- [ ] Create `AdminController`
- [ ] Create `LogController`
- [ ] Migrate routes to use controllers
- [ ] Remove `main()` methods

## Benefits

### After Phase 1
- ✅ Routes directly call actions
- ✅ No more internal routing
- ✅ Easier to test individual actions
- ✅ Can apply route-specific middleware
- ✅ Clear separation of concerns
- ✅ Backward compatible (main() still works)

### After Phase 2
- ✅ Clean MVC architecture
- ✅ Controllers handle HTTP concerns
- ✅ Models handle business logic
- ✅ Views handle presentation
- ✅ Fully testable
- ✅ Industry-standard structure

## Risks & Mitigation

### Risk 1: Breaking Existing Functionality
**Mitigation**: Keep `main()` working, add new methods alongside

### Risk 2: Form Parameter Dependencies
**Mitigation**: Extract form handling into separate methods

### Risk 3: Session/Cookie Dependencies
**Mitigation**: Ensure all actions call `loadAndSanitizeInput()`, `applyUserPreferences()`, `initializeSession()`

### Risk 4: Testing Overhead
**Mitigation**: Write tests for new action methods as they're created

## Conclusion

**Recommendation**: Start with Phase 1, Option 1.

Extract actions from `main()` incrementally, starting with the simplest (main page display). This provides immediate benefits with minimal risk.

Phase 2 (Controller layer) can be considered after Phase 1 is complete and stable.
