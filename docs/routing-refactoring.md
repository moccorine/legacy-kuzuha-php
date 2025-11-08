# Routing Refactoring Proposal

## Current Architecture

### Problem
All requests go through a single route (`/`) and Bbs::main() does internal routing based on `m=` parameter:

```php
// routes.php
$app->get('/', function() {
    $bbs = new Bbs();
    $bbs->main();  // Internal routing happens here
});

// Bbs.php main()
if ($this->form['m'] == 'f') {
    $this->prtfollow();
} elseif ($this->form['m'] == 'g') {
    $getlog = new Getlog();
    $getlog->main();
} elseif ($this->form['m'] == 'tree') {
    $treeview = new Treeview();
    $treeview->main();
}
// ... etc
```

### Issues
1. **Not RESTful** - All URLs are `/?m=something`
2. **Slim underutilized** - Framework routing not used
3. **Hard to test** - Single entry point for all operations
4. **No middleware** - Can't apply route-specific middleware
5. **Poor separation** - Routing logic mixed with business logic

## Proposed Architecture

### Option 1: Query Parameter Routes (Minimal Change)

Keep `/?m=` URLs but use Slim routing:

```php
// Main page
$app->get('/', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    $m = $params['m'] ?? '';
    
    if (empty($m)) {
        // Main page
        $bbs = new Bbs();
        return $bbs->prtmain();
    }
    
    return $response->withStatus(404);
});

// Follow page
$app->get('/[?m=f]', function (Request $request, Response $response) {
    $bbs = new Bbs();
    return $bbs->prtfollow();
});

// Tree view
$app->get('/[?m=tree]', function (Request $request, Response $response) {
    $treeview = new Treeview();
    return $treeview->main();
});

// Log search
$app->get('/[?m=g]', function (Request $request, Response $response) {
    $getlog = new Getlog();
    return $getlog->main();
});

// Admin
$app->map(['GET', 'POST'], '/[?m=ad]', function (Request $request, Response $response) {
    $bbsadmin = new Bbsadmin();
    return $bbsadmin->main();
});
```

**Pros:**
- URLs stay the same (backward compatible)
- Minimal code changes
- Clear route definitions

**Cons:**
- Still uses query parameters
- Not truly RESTful

### Option 2: Path-Based Routes (Modern)

Use proper URL paths:

```php
// Main page
$app->get('/', [BbsController::class, 'index']);

// Follow page
$app->get('/follow/{id}', [BbsController::class, 'follow']);

// Tree view
$app->get('/tree', [TreeController::class, 'index']);
$app->get('/tree/{id}', [TreeController::class, 'show']);

// Log search
$app->get('/logs', [LogController::class, 'index']);
$app->get('/logs/{filename}', [LogController::class, 'show']);
$app->get('/logs/search', [LogController::class, 'search']);

// Admin
$app->group('/admin', function ($app) {
    $app->get('', [AdminController::class, 'menu']);
    $app->get('/posts', [AdminController::class, 'killlist']);
    $app->post('/posts/{id}/delete', [AdminController::class, 'delete']);
})->add(AdminAuthMiddleware::class);

// API endpoints
$app->post('/api/posts', [BbsController::class, 'store']);
$app->delete('/api/posts/{id}', [BbsController::class, 'destroy']);
```

**Pros:**
- RESTful URLs
- Middleware support
- Better for SEO
- Modern architecture

**Cons:**
- Breaking change (all URLs change)
- Need URL rewriting for backward compatibility
- More refactoring required

### Option 3: Hybrid Approach (Recommended)

Use Slim routing but keep query parameters for backward compatibility:

```php
// Main bulletin board
$app->map(['GET', 'POST'], '/', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    $m = $params['m'] ?? '';
    
    $bbs = new Bbs();
    
    // Route based on m parameter
    switch ($m) {
        case 'f':
            return $bbs->prtfollow();
        case 't':
        case 's':
            return $bbs->prtsearchlist();
        case 'c':
            return $bbs->prtcustom();
        default:
            return $bbs->prtmain();
    }
});

// Tree view
$app->map(['GET', 'POST'], '/', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    if (($params['m'] ?? '') === 'tree') {
        $treeview = new Treeview();
        return $treeview->main();
    }
    return $response->withStatus(404);
});

// Log search
$app->map(['GET', 'POST'], '/', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    if (($params['m'] ?? '') === 'g') {
        $getlog = new Getlog();
        return $getlog->main();
    }
    return $response->withStatus(404);
});

// Admin
$app->map(['GET', 'POST'], '/', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    if (($params['m'] ?? '') === 'ad') {
        $bbsadmin = new Bbsadmin();
        return $bbsadmin->main();
    }
    return $response->withStatus(404);
})->add(AdminAuthMiddleware::class);
```

**Pros:**
- Backward compatible URLs
- Proper Slim routing
- Can add middleware per route
- Easier to test individual routes

**Cons:**
- Multiple route handlers check same parameter
- Some duplication

## Implementation Steps

### Phase 1: Extract Routing Logic (Recommended First Step)

1. Move routing logic from `Bbs::main()` to `routes.php`
2. Keep all URLs the same (`/?m=something`)
3. Each route calls appropriate method directly
4. Remove routing logic from `Bbs::main()`

**Benefits:**
- No URL changes (backward compatible)
- Clear separation of routing and business logic
- Can add middleware (auth, logging, rate limiting)
- Easier to understand request flow

### Phase 2: Create Controllers (Optional)

```php
class BbsController
{
    public function index(Request $request, Response $response) {
        $bbs = new Bbs();
        return $bbs->prtmain();
    }
    
    public function follow(Request $request, Response $response) {
        $bbs = new Bbs();
        return $bbs->prtfollow();
    }
    
    public function store(Request $request, Response $response) {
        $bbs = new Bbs();
        return $bbs->putmessage($bbs->getformmessage());
    }
}
```

### Phase 3: Modernize URLs (Future)

Add URL rewriting for backward compatibility:

```php
// New URLs
$app->get('/posts', [BbsController::class, 'index']);
$app->get('/posts/{id}/follow', [BbsController::class, 'follow']);

// Legacy URL support
$app->get('/', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    $m = $params['m'] ?? '';
    
    // Redirect to new URLs
    if ($m === 'f' && isset($params['s'])) {
        return $response->withRedirect("/posts/{$params['s']}/follow", 301);
    }
    // ... etc
});
```

## Comparison

| Aspect | Current | Phase 1 | Phase 2 | Phase 3 |
|--------|---------|---------|---------|---------|
| URL Format | `/?m=f&s=1` | `/?m=f&s=1` | `/?m=f&s=1` | `/posts/1/follow` |
| Routing | Bbs::main() | routes.php | routes.php | routes.php |
| Controllers | No | No | Yes | Yes |
| Middleware | No | Yes | Yes | Yes |
| Backward Compat | N/A | 100% | 100% | Via redirect |
| Effort | - | Low | Medium | High |
| Breaking Changes | - | None | None | URLs change |

## Recommendation

**Start with Phase 1:**
1. Move routing from `Bbs::main()` to `routes.php`
2. Keep all URLs unchanged
3. Enable middleware support
4. Improve testability

**Example Implementation:**

```php
// routes.php
$app->map(['GET', 'POST'], '/', function (Request $request, Response $response) {
    $_GET = $request->getQueryParams();
    $_POST = $request->getParsedBody() ?? [];
    
    $m = $_GET['m'] ?? '';
    $config = Config::getInstance();
    
    // Initialize appropriate class
    if ($config->get('BBSMODE_IMAGE') == 1) {
        $bbs = new \Kuzuha\Imagebbs();
    } else {
        $bbs = new \Kuzuha\Bbs();
    }
    
    // Route to appropriate method
    ob_start();
    
    switch ($m) {
        case 'f':
            $bbs->prtfollow();
            break;
        case 'g':
            $getlog = new \Kuzuha\Getlog();
            $getlog->main();
            break;
        case 'tree':
            $treeview = new \Kuzuha\Treeview();
            $treeview->main();
            break;
        case 'ad':
            $bbsadmin = new \Kuzuha\Bbsadmin($bbs);
            $bbsadmin->main();
            break;
        case 't':
        case 's':
            $bbs->prtsearchlist();
            break;
        case 'c':
            $bbs->prtcustom();
            break;
        case 'p':
            // Handle in Bbs::main() for now (complex logic)
            $bbs->main();
            break;
        default:
            $bbs->prtmain();
    }
    
    $output = ob_get_clean();
    $response->getBody()->write($output);
    return $response;
});
```

This approach:
- ✅ No URL changes
- ✅ Clear routing logic
- ✅ Can add middleware later
- ✅ Minimal refactoring
- ✅ Maintains all functionality

## Next Steps

1. Implement Phase 1 routing refactoring
2. Add middleware for admin authentication
3. Add logging middleware
4. Consider Phase 2 (controllers) for new features
5. Keep Phase 3 (URL modernization) as long-term goal
