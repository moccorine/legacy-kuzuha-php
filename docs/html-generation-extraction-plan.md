# HTML Generation Extraction Plan

## Current State

**Mixed HTML Generation:**
- Some parts use Twig templates (modern)
- Some parts use `echo`/`print` with inline HTML (legacy)
- Total: 61 echo/print statements across 6 files

**File Breakdown:**
- Treeview.php: 23 echo/print statements (most legacy HTML)
- Getlog.php: 18 echo/print statements
- Bbs.php: 10 echo/print statements (partially migrated to Twig)
- Webapp.php: 4 echo/print statements
- Bbsadmin.php: 4 echo/print statements (mostly migrated to Twig)
- Imagebbs.php: 2 echo/print statements

## Problems

1. **Mixed Paradigms**: Hard to maintain when HTML is in both PHP and Twig
2. **Testing Difficulty**: Can't unit test HTML generation in PHP
3. **Security Risk**: Inline HTML more prone to XSS
4. **Code Duplication**: Similar HTML patterns repeated
5. **Poor Separation**: Business logic mixed with presentation

## Strategy

### Phase 1: Treeview.php (Priority: HIGH)
**Impact:** 23 echo/print statements, most legacy HTML

**Current Issues:**
```php
// Inline HTML generation
print "<pre class=\"msgtree\"><a href=\"...\">{$text}</a>";
print "<span class=\"update\"> [...] </span>\r";
echo "<hr>\n";
echo "<footer>\n";
```

**Target:** Create `tree/view.twig` template

**Benefits:**
- Remove 23 echo/print statements
- Consistent with other views
- Testable rendering

### Phase 2: Getlog.php (Priority: MEDIUM)
**Impact:** 18 echo/print statements

**Current Issues:**
- Archive list generation
- Search results display
- Mixed with business logic

**Target:** Create templates:
- `log/archive_list.twig`
- `log/search_results.twig`

### Phase 3: Remaining Files (Priority: LOW)
**Impact:** 16 echo/print statements

**Files:**
- Bbs.php: 10 statements (some already use Twig)
- Webapp.php: 4 statements
- Bbsadmin.php: 4 statements (mostly migrated)
- Imagebbs.php: 2 statements

## Implementation Plan

### Step 1: Analyze Treeview.php HTML Generation

Identify all HTML output patterns:
1. Tree structure rendering
2. Message display
3. Navigation links
4. Footer generation

### Step 2: Create Twig Templates

Extract HTML to templates:
```twig
{# tree/view.twig #}
<pre class="msgtree">
    <a href="{{ thread_url }}" target="link">{{ thread_text }}</a>
    <span class="update">[{{ date_label }}: {{ date }}]</span>
    {{ tree_content|raw }}
</pre>

<hr>

{{ message_html|raw }}

<hr>

<span class="bbsmsg">
    <a href="{{ return_url }}">{{ return_label }}</a>
</span>

<footer>
    {% if show_duration %}
        <p>
            <span class="msgmore">{{ page_gen_label }}: {{ duration }} {{ seconds_label }}</span>
            <a href="#top" title="{{ top_label }}">▲</a>
        </p>
    {% else %}
        <p><a href="#top" title="{{ top_label }}">▲</a></p>
    {% endif %}
</footer>
```

### Step 3: Refactor PHP to Use Templates

Replace echo/print with renderTwig():
```php
// BEFORE
print "<pre class=\"msgtree\">...";
echo "<hr>\n";
echo "<footer>\n";

// AFTER
$data = [
    'thread_url' => route('thread', $params),
    'tree_content' => $tree,
    'message_html' => $messageHtml,
    'show_duration' => $this->config['MSGTIME'],
    'duration' => $duration,
];
echo $this->renderTwig('tree/view.twig', $data);
```

### Step 4: Extract Reusable Components

Identify common patterns:
- Navigation links
- Footer with "back to top"
- Message display
- Stats display

Create component templates:
```twig
{# components/footer.twig #}
<footer>
    {% if show_duration %}
        <p>
            <span class="msgmore">{{ page_gen_label }}: {{ duration }} {{ seconds_label }}</span>
            <a href="#top" title="{{ top_label }}">▲</a>
        </p>
    {% else %}
        <p><a href="#top" title="{{ top_label }}">▲</a></p>
    {% endif %}
</footer>

{# components/navigation.twig #}
<span class="bbsmsg">
    <a href="{{ return_url }}">{{ return_label }}</a>
</span>
```

## Success Metrics

- **Reduce echo/print:** From 61 to 0
- **Template Coverage:** 100% of HTML in Twig
- **Code Reduction:** Remove ~200 lines of inline HTML
- **Maintainability:** Single source of truth for HTML
- **Security:** All output escaped by Twig

## Testing Strategy

### Before Extraction
1. Take screenshots of all pages
2. Document current HTML output
3. Create integration tests

### During Extraction
1. Compare rendered HTML (before/after)
2. Visual regression testing
3. Functional testing

### After Extraction
1. Verify all pages render correctly
2. Check XSS protection
3. Performance testing

## Migration Order

### Week 1: Treeview.php
1. Create `tree/view.twig` template
2. Extract tree rendering logic
3. Replace 23 echo/print statements
4. Test tree view functionality

### Week 2: Getlog.php
1. Create `log/archive_list.twig`
2. Create `log/search_results.twig`
3. Replace 18 echo/print statements
4. Test log search functionality

### Week 3: Remaining Files
1. Extract remaining HTML from Bbs.php
2. Clean up Webapp.php
3. Finalize Bbsadmin.php
4. Handle Imagebbs.php

### Week 4: Cleanup & Components
1. Extract reusable components
2. Refactor duplicate patterns
3. Documentation
4. Final testing

## Related Documents

- [Bbs Refactoring Plan](bbs-refactoring-plan.md)
- [PSR Compliance Refactoring](psr-compliance-refactoring.md)
