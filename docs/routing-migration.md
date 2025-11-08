# Routing Migration Plan

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

### Phase 1: Backward Compatibility Setup
- [ ] Add `.htaccess` rewrite rules to support both formats
- [ ] Keep `Bbs::main()` routing logic intact
- [ ] Test that both old and new URLs work

### Phase 2: Template Updates
- [ ] Update main page templates (`main/upper.twig`, `components/stats.twig`)
- [ ] Update search/log templates (`log/*.twig`)
- [ ] Update tree view templates (`tree/*.twig`)
- [ ] Update admin templates (if any)

### Phase 3: Code Cleanup
- [ ] Remove `m=` parameter handling from `Bbs::main()`
- [ ] Remove `m=` parameter handling from `Imagebbs::main()`
- [ ] Simplify routing logic in classes

### Phase 4: Documentation
- [ ] Update README.md with new URL structure
- [ ] Update any user-facing documentation

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

## .htaccess Rewrite Rules (Phase 1)

```apache
RewriteEngine On
RewriteBase /

# Redirect old query parameter format to new paths
RewriteCond %{QUERY_STRING} ^m=g(.*)$
RewriteRule ^bbs\.php$ /search?%1 [R=301,L]

RewriteCond %{QUERY_STRING} ^m=tree(.*)$
RewriteRule ^bbs\.php$ /tree?%1 [R=301,L]

RewriteCond %{QUERY_STRING} ^m=ad(.*)$
RewriteRule ^bbs\.php$ /admin?%1 [R=301,L]

RewriteCond %{QUERY_STRING} ^m=t(.*)$
RewriteRule ^bbs\.php$ /thread?%1 [R=301,L]

# Route all requests to index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

## Testing Checklist

### Phase 1 Testing
- [ ] Access `/` - should show main board
- [ ] Access `bbs.php` - should redirect to `/`
- [ ] Access `/search` - should show search page
- [ ] Access `bbs.php?m=g` - should redirect to `/search`
- [ ] Access `/tree` with parameters - should work
- [ ] Access `bbs.php?m=tree&ff=...&s=...` - should redirect with params

### Phase 2 Testing
- [ ] Click all navigation links on main page
- [ ] Click all links in search results
- [ ] Click thread/tree links in topic list
- [ ] Verify no broken links

### Phase 3 Testing
- [ ] Verify old URLs return 404 or redirect
- [ ] Verify all functionality works with new URLs only

## Notes

- `DEFURL` variable in templates contains base URL with query string
- Need to create new template variables for RESTful paths
- Some links use `CGIURL` which may need updating
- Thread view (`m=t`) is currently handled in `Bbs::main()` as follow mode
