# Twig Migration Status

## Overview
Migration from patTemplate to Twig template engine with internationalization (i18n) support.

## Completed Migrations

### Pages (95%)
- ✅ Main page (upper/lower) - Fully migrated with i18n
- ✅ Tree view (upper/lower) - Partially migrated (prttreeview uses Twig, prtthreadtree uses patTemplate header)
- ✅ Search list - Fully migrated
- ✅ Follow-up post page - Fully migrated with i18n
- ✅ New post page - Fully migrated
- ✅ User settings (custom) - Fully migrated
- ✅ Post completion page - Fully migrated
- ✅ Deletion completion page - Fully migrated
- ✅ Log search results (oldlog header/footer) - Fully migrated
- ✅ Topic list - Fully migrated with HTML entity decoding
- ✅ Archive list - Fully migrated
- ✅ HTML download page - Fully migrated
- ✅ Admin pages (menu, killlist, password, log view) - Fully migrated
- ✅ Error page - Fully migrated with i18n
- ✅ Redirect page - Fully migrated with i18n

### Components (100%)
- ✅ Message display (`components/message.twig`) - With whitespace control for `<pre>` tags
- ✅ Form component (`components/form.twig`) - Fully migrated with conditional display
- ✅ Stats component (`components/stats.twig`) - Page statistics and navigation links
- ✅ Tree custom style (`components/tree_customstyle.twig`) - CSS generation for tree view
- ✅ Error display (`error.twig`) - Standalone error page
- ✅ Layout base (`layout/base.twig`) - Base HTML structure
- ✅ Footer - Integrated into main/lower.twig and tree/lower.twig

### Translations (200+ keys)
- ✅ Japanese (ja) - Complete coverage
- ✅ English (en) - Complete coverage
- Namespaces: 
  - `admin.*` - Admin interface
  - `log.*` - Log search and archives
  - `search.*` - Search functionality
  - `follow.*` - Follow-up posts
  - `newpost.*` - New post page
  - `custom.*` - User settings
  - `tree.*` - Tree view
  - `main.*` - Main page
  - `message.*` - Message display
  - `error.*` - Error messages
  - `redirect.*` - Redirect page
  - `stats.*` - Page statistics
  - `form.*` - Form labels and help text

## Key Improvements

### Form Component Migration
**Status:** ✅ Complete  
**Features:**
- Conditional display based on SHOW_FORMCONFIG (hidden on follow page)
- Image mode and default mode support
- Kaomoji buttons generation
- Counter and member count display
- All form labels translated
- Whitespace control for textarea content

### Stats Component Separation
**Status:** ✅ Complete  
**Features:**
- Separated from form component
- Page view counter with bulletproof level
- Current participants count
- Navigation links (PR office, message logs)
- Help text for symbols (■, ★, ◆, 木, Undo)
- Only displayed on main page, hidden on follow page

### Message Component
**Status:** ✅ Complete  
**Features:**
- Whitespace control with `{{- -}}` for `<pre>` tags
- `|raw` filter for USER and TITLE to preserve HTML
- `|raw` filter for MSG content
- SHOW_ENV conditional for environment display
- Proper handling of quoted text (>)

### Tree View
**Status:** ⚠️ Partially Complete  
**Completed:**
- prttreeview() uses Twig (tree/upper.twig, tree/lower.twig)
- Custom CSS generation (tree_customstyle.twig)
- Form component integration
- Full Japanese translation

**Remaining:**
- prtthreadtree() still uses patTemplate header (prthtmlhead)
- Footer manually generated instead of using tree/lower.twig
- Could be migrated to Twig for consistency

## Deprecated/Unused Components
These patTemplate methods are no longer called in main flow:
- `prthtmlhead()` - Replaced by `base.twig` (still used in prtthreadtree)
- `prthtmlfoot()` - Replaced by footer in page templates
- `prtcopyright()` - Integrated into footer
- Form template in `template/template.html` - Replaced by `components/form.twig`
- Stats sections (counterrow, linkrow, helprow) - Replaced by `components/stats.twig`

## Benefits Achieved
1. **Separation of concerns** - Logic in PHP, presentation in Twig
2. **Internationalization** - Easy to add new languages via JSON files
3. **Maintainability** - Cleaner template syntax, easier to modify
4. **Security** - Automatic HTML escaping by default, explicit `|raw` where needed
5. **Consistency** - Unified layout system with `base.twig`
6. **Component reusability** - Form, message, stats components used across pages
7. **Whitespace control** - Proper handling of `<pre>` tags without extra spaces

## Issues Fixed During Migration
1. ✅ Tree view not displaying when MSGDISP < 0 (removed incorrect break condition)
2. ✅ MSGDISP defaulting to -1 when d parameter missing (explicit empty string check)
3. ✅ Double-escaped ampersands in URLs (added `|raw` filter to DEFURL, QUERY)
4. ✅ Whitespace in `<pre>` tags (using `{{- -}}` in message.twig)
5. ✅ Footer order (duration before copyright in lower.twig)
6. ✅ HTML tags in USER/TITLE fields (added `|raw` filter)
7. ✅ Textarea whitespace (using `{{- DMSG|raw -}}` in form.twig)
8. ✅ Reference link translation (message.reference: "参考")
9. ✅ Topic list HTML entities (&gt; decoded to >)
10. ✅ Topic list whitespace (single-line format in pre tag)
11. ✅ Form config visibility (hidden on follow page with SHOW_FORMCONFIG)
12. ✅ Stats component separation (moved from form to separate component)

## Testing Checklist
- ✅ Main page display with stats and form
- ✅ Tree view display (both prttreeview and prtthreadtree)
- ✅ Message display with proper whitespace
- ✅ Error pages in Japanese
- ✅ Admin pages functionality
- ✅ Search functionality
- ✅ Follow-up posts (without form config and stats)
- ✅ User settings page
- ✅ Reference links with Japanese translation
- ✅ Topic list with decoded HTML entities
- ✅ Textarea content without extra whitespace
- ✅ Form component conditional display

## Remaining Work (Optional)

### prtthreadtree() Full Migration
**Status:** Optional improvement  
**Current:** Uses patTemplate header, manual footer generation  
**Proposed:** Create dedicated tree thread template or reuse tree/upper.twig  
**Effort:** 1-2 hours  
**Priority:** Low (current implementation works correctly)

## Migration Statistics
- **Total templates migrated:** 15+ pages, 5 components
- **Translation keys added:** 200+
- **patTemplate usage:** ~5% (only prtthreadtree header)
- **Twig coverage:** ~95%
- **Lines of template code:** ~2000+ lines migrated

## Documentation
- ✅ `query-parameters.md` - Complete URL parameter documentation
- ✅ `twig-migration-status.md` - This file
- ✅ Translation files with clear namespace organization
- ✅ Inline comments in Twig templates
