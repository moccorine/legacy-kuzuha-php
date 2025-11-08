# Twig Migration Status

## Overview
Migration from patTemplate to Twig template engine with internationalization (i18n) support.

## Completed Migrations

### Pages (100%)
- ✅ Main page (upper/lower)
- ✅ Tree view (upper/lower)
- ✅ Search list
- ✅ Follow-up post page
- ✅ New post page
- ✅ User settings (custom)
- ✅ Post completion page
- ✅ Deletion completion page
- ✅ Log search results (oldlog header/footer)
- ✅ Topic list
- ✅ Archive list
- ✅ HTML download page
- ✅ Admin pages (menu, killlist, password, log view)
- ✅ Error page
- ✅ Redirect page

### Components (80%)
- ✅ Message display (`components/message.twig`)
- ✅ Tree custom style (`components/tree_customstyle.twig`)
- ✅ Error display (`components/error.twig`)
- ✅ Layout base (`layout/base.twig`)
- ✅ Footer (integrated into page templates)
- ❌ **Form component** (remaining)

### Translations
- ✅ Japanese (ja) - 120+ keys
- ✅ English (en) - 120+ keys
- Namespaces: admin, log, search, follow, newpost, custom, tree, main, message, error, redirect

## Remaining Work

### Form Component Migration
**Status:** Not started  
**Complexity:** High  
**Files affected:**
- `src/Kuzuha/Bbs.php` (3 locations)
- `src/Kuzuha/Treeview.php` (1 location)
- `template/template.html` (form template)

**Challenges:**
1. Large template with multiple modes (default, image, hide)
2. Complex conditional logic (BBSMODE_IMAGE, HIDEFORM, etc.)
3. Kaomoji buttons generation (顔文字ボタン)
4. Form state management (DTITLE, DMSG, DLINK)
5. Counter and member count display
6. Help text and navigation buttons

**Current Implementation:**
- Uses output buffering to capture patTemplate output
- Form HTML is passed to Twig templates as `{{ FORM|raw }}`
- Works but not fully migrated to Twig

**Migration Plan:**
1. Create `components/form.twig` (draft exists)
2. Migrate `setform()` method to prepare data for Twig
3. Generate kaomoji buttons in PHP or Twig
4. Handle all conditional display logic
5. Add translation keys for all form labels
6. Test all form modes (main, follow, newpost, tree)
7. Remove patTemplate form template

**Estimated effort:** 4-6 hours

## Deprecated/Unused Components
These patTemplate methods are no longer called:
- `prthtmlhead()` - Replaced by `base.twig`
- `prthtmlfoot()` - Replaced by footer in page templates
- `prtcopyright()` - Integrated into footer

## Benefits Achieved
1. **Separation of concerns** - Logic in PHP, presentation in Twig
2. **Internationalization** - Easy to add new languages
3. **Maintainability** - Cleaner template syntax
4. **Security** - Automatic HTML escaping by default
5. **Consistency** - Unified layout system with `base.twig`

## Known Issues Fixed
1. ✅ Tree view not displaying when MSGDISP < 0
2. ✅ MSGDISP defaulting to -1 when d parameter missing
3. ✅ Double-escaped ampersands in URLs (DEFURL, QUERY)
4. ✅ Whitespace issues in `<pre>` tags (using `{{- -}}`)
5. ✅ Footer order (duration before copyright)

## Testing Checklist
- ✅ Main page display
- ✅ Tree view display
- ✅ Message display with buttons
- ✅ Error pages
- ✅ Admin pages
- ✅ Search functionality
- ✅ Follow-up posts
- ✅ User settings
- ✅ Log archives
- ⏳ Form submission (still using patTemplate)

## Next Steps
1. Complete form component migration
2. Remove patTemplate dependency entirely
3. Add more translation keys as needed
4. Performance testing
5. Update README with new template system info
