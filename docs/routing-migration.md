# Routing Migration Plan

## Status: ✅ COMPLETED

All phases of the RESTful routing migration have been successfully completed.

## Overview

Migrate from legacy query parameter routing (`?m=g`, `?m=tree`) to modern RESTful paths using Slim Framework routing.

## Current State

### Legacy Format (Query Parameters)
- `bbs.php` - Main board
- `bbs.php?m=g` - Message log search
- `bbs.php?m=tree` - Tree view
- `bbs.php?m=ad` - Admin mode (POST)

### Modern Format (RESTful Paths) - Already Defined
- `/` - Main board
- `/search` - Message log search
- `/tree` - Tree view
- `/admin` - Admin mode

## Migration Strategy

### Phase 1: Backward Compatibility Setup ✅ COMPLETED
- [x] Add Slim middleware to redirect `m=` parameter to RESTful paths
- [x] Add `/thread` route for follow mode (`m=t`)
- [x] Keep `Bbs::main()` routing logic intact
- [x] Test that both old and new URLs work
- **Implementation**: Middleware in `routes.php` handles 301 redirects, preserving other query parameters

### Phase 2: Template Updates ✅ COMPLETED
- [x] Update main page templates (`main/upper.twig`, `components/stats.twig`)
- [x] Update search/log templates (`log/*.twig`)
- [x] Update tree view templates (`tree/*.twig`)
- **Files updated**: 8 template files, 16 insertions, 20 deletions

### Phase 3: Code Cleanup ✅ COMPLETED
- [x] Remove `m=g`, `m=tree`, `m=ad` parameter handling from `Bbs::main()`
- [x] Keep `m=t` handling (now accessed via `/thread` route)
- [x] Simplify routing logic in classes
- **Result**: Removed 19 lines of routing code from `Bbs.php`

### Phase 4: Documentation ✅ COMPLETED
- [x] Update README.md with new URL structure
- [x] Remove legacy route documentation
- [x] Add 301 redirect notice

## URL Mapping

| Legacy URL | New URL | Status | Notes |
|------------|---------|--------|-------|
| `bbs.php` | `/` | ✅ Done | Already routed in `routes.php` |
| `bbs.php?m=g` | `/search` | ✅ Done | Already routed in `routes.php` |
| `bbs.php?m=tree` | `/tree` | ✅ Done | Already routed in `routes.php` |
| `bbs.php?m=ad` | `/admin` | ✅ Done | Already routed in `routes.php` |
| `bbs.php?m=t` | `/thread` | ⏳ TODO | Thread view (follow mode) |

## Template Files to Update

### Main Page (Priority: High)
- `resources/views/main/upper.twig` - Navigation links
- `resources/views/components/stats.twig` - Stats section links

### Search/Log Pages (Priority: High)
- `resources/views/log/list.twig` - Archive links, file links
- `resources/views/log/archivelist.twig` - Return links
- `resources/views/log/topiclist.twig` - Thread/tree links, return links
- `resources/views/log/htmldownload.twig` - Return link
- `resources/views/log/searchresult.twig` - Return link

### Tree View (Priority: Medium)
- `resources/views/tree/upper.twig` - Navigation links

### Admin (Priority: Low)
- Check for any admin template links

## Implementation Details

### Phase 1: Slim Middleware Redirect

Instead of `.htaccess` rewrite rules, we use Slim middleware for cleaner implementation:

```php
// Middleware in routes.php
$app->add(function (Request $request, $handler) {
    $queryParams = $request->getQueryParams();
    
    if (isset($queryParams['m'])) {
        $m = $queryParams['m'];
        $pathMap = [
            'g' => '/search',
            'tree' => '/tree',
            't' => '/thread',
            'ad' => '/admin',
        ];
        
        if (isset($pathMap[$m])) {
            unset($queryParams['m']);
            $newQuery = http_build_query($queryParams);
            $newPath = $pathMap[$m] . ($newQuery ? '?' . $newQuery : '');
            
            return $handler->handle($request)
                ->withStatus(301)
                ->withHeader('Location', $newPath);
        }
    }
    
    return $handler->handle($request);
});
```

**Benefits:**
- Preserves other query parameters (e.g., `?c=58&d=40&m=tree` → `/tree?c=58&d=40`)
- No `.htaccess` complexity
- Easier to debug and maintain
- Works regardless of web server (Apache/Nginx)

## Testing Checklist

### Phase 1 Testing
- [x] Access `/` - should show main board
- [x] Access `/search` - should show search page
- [x] Access `/?m=g` - should redirect to `/search` (301)
- [x] Access `/?c=58&d=40&m=tree` - should redirect to `/tree?c=58&d=40` (301)
- [x] Access `/tree` with parameters - should work
- [x] Verify query parameters preserved after redirect

### Phase 2 Testing
- [x] Click all navigation links on main page
- [x] Click all links in search results
- [x] Click thread/tree links in topic list
- [x] Verify no broken links

### Phase 3 Testing
- [x] Verify old URLs redirect with 301 status
- [x] Verify all functionality works with new URLs only
- [x] Verify main page, search, tree, thread routes work correctly

## Notes

- `DEFURL` variable in templates contains base URL with query string
- Need to create new template variables for RESTful paths
- Some links use `CGIURL` which may need updating
- Thread view (`m=t`) is currently handled in `Bbs::main()` as follow mode
