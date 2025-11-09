# Regular Expression Issues and Improvements

## Overview

The codebase contains 87+ regex patterns across Kuzuha classes. Several patterns have performance, security, or maintainability issues.

## Critical Issues

### 1. Catastrophic Backtracking Risk

**Location:** `Bbs.php:595-597, 604`

```php
// PROBLEM: Nested quantifiers with overlapping character classes
preg_replace("/<a href=\"[^>]+>([^<]+)<\/a>/i", '$1', $formmsg);
preg_replace("/\r*<a href=[^>]+><img [^>]+><\/a>/i", '', $formmsg);
```

**Issue:** `[^>]+` followed by `>` can cause exponential backtracking on malformed HTML.

**Attack Vector:**
```php
$input = '<a href="' . str_repeat('x', 10000) . '...'; // No closing >
// Regex engine tries all combinations, causing CPU spike
```

**Fix:**
```php
// Use possessive quantifiers or atomic groups
preg_replace("/<a href=\"[^>]++>([^<]++)<\/a>/i", '$1', $formmsg);
// Or use non-greedy with explicit boundaries
preg_replace("/<a href=\"[^\"]*\"[^>]*?>([^<]+?)<\/a>/i", '$1', $formmsg);
```

### 2. HTML Tag Stripping (Insecure)

**Location:** `Bbs.php:617, 735, 754`, `Webapp.php:237`

```php
// PROBLEM: Naive HTML tag removal
preg_replace('/<[^>]*>/', '', $message['USER']);
```

**Issue:** 
- Doesn't handle malformed HTML: `<script<script>>alert(1)</script>`
- Doesn't handle HTML entities: `&lt;script&gt;`
- Can be bypassed with null bytes or special characters

**Fix:**
```php
// Use strip_tags() or proper HTML parser
strip_tags($message['USER']);
// Or use HTML Purifier for complex cases
```

### 3. Trip Code Generation (Weak)

**Location:** `Bbs.php:1145, 1486`

```php
// PROBLEM: Weak cryptographic pattern
preg_replace("/\W/", '', crypt($input, '00'));
```

**Issues:**
- Uses deprecated `crypt()` with weak salt
- `\W` removes important characters
- Predictable output (only 7 characters)

**Fix:**
```php
// Use modern hashing
$tripcode = substr(hash('sha256', $input . $salt), 0, 10);
// Or use password_hash() for proper key derivation
```

### 4. Multiple Replacements on Same String

**Location:** `Bbs.php:594-601`

```php
// PROBLEM: 8 sequential regex operations
$formmsg = preg_replace("/&gt; &gt;[^\r]+\r/", '', $formmsg);
$formmsg = preg_replace("/<a href=\"...\"/i", '', $formmsg);
$formmsg = preg_replace("/<a href=\"[^>]+>([^<]+)<\/a>/i", '$1', $formmsg);
$formmsg = preg_replace("/\r*<a href=[^>]+><img [^>]+><\/a>/i", '', $formmsg);
$formmsg = preg_replace("/\r/", "\r> ", $formmsg);
$formmsg = preg_replace("/\r>\s+\r/", "\r", $formmsg);
$formmsg = preg_replace("/\r>\s+\r$/", "\r", $formmsg);
```

**Issue:** 
- Performance: 8 passes over the string
- Hard to understand intent
- Potential for conflicts between replacements

**Fix:**
```php
// Combine into fewer operations or use a dedicated parser
class QuoteFormatter {
    public function format(string $message): string {
        // Parse once, transform, output
    }
}
```

## Medium Priority Issues

### 5. Unescaped Regex Delimiters

**Location:** `Bbs.php:595, 604`

```php
// PROBLEM: Dynamic route in regex without proper escaping
preg_replace("/<a href=\"" . preg_quote(route('follow', ['s' => '']), '/') . "[^\"]*\"[^>]*>[^<]+<\/a>/i", '', $formmsg);
```

**Issue:** 
- Nested `preg_quote()` call in every iteration
- Complex and hard to read
- Potential for escaping issues

**Fix:**
```php
// Pre-compile pattern
private static $followLinkPattern;

private function getFollowLinkPattern(): string {
    if (!self::$followLinkPattern) {
        $route = preg_quote(route('follow', ['s' => '']), '/');
        self::$followLinkPattern = "/<a href=\"{$route}[^\"]*\"[^>]*>[^<]+<\/a>/i";
    }
    return self::$followLinkPattern;
}

// Use
$formmsg = preg_replace($this->getFollowLinkPattern(), '', $formmsg);
```

### 6. Inconsistent Delimiters

**Location:** Throughout codebase

```php
// Mix of delimiters
preg_match("/pattern/", $str);      // Forward slash
preg_match('#pattern#', $str);      // Hash (not used)
preg_match('~pattern~', $str);      // Tilde (not used)
```

**Issue:** Inconsistency makes code harder to read

**Fix:** Standardize on `/` delimiter throughout codebase

### 7. Missing Anchors

**Location:** `Bbs.php:712`

```php
// PROBLEM: No start/end anchors
preg_match("/^[\w.]+$/", $this->form['ff']);
```

**Issue:** Actually this one is correct! But many others are missing anchors.

**Example of missing anchors:** `Bbs.php:867`
```php
// PROBLEM: No anchors, matches anywhere in string
preg_match('/^[0-9a-fA-F]{6}$/', $color); // This is correct

// But elsewhere:
preg_match('/\d+/', $str); // Should be /^\d+$/ if validating entire string
```

## Low Priority Issues

### 8. Redundant Escaping

**Location:** `Webapp.php:168-169`

```php
// PROBLEM: Using regex for simple character replacement
$message['MSG'] = preg_replace('/{/i', '&#123;', $message['MSG'], -1);
$message['MSG'] = preg_replace('/}/i', '&#125;', $message['MSG'], -1);
```

**Issue:** 
- Regex overkill for literal character replacement
- `/i` flag unnecessary for `{` and `}`

**Fix:**
```php
// Use str_replace() - much faster
$message['MSG'] = str_replace(['{', '}'], ['&#123;', '&#125;'], $message['MSG']);
```

### 9. Quote Highlighting Pattern

**Location:** `Webapp.php:283`

```php
// PROBLEM: Greedy match in quote pattern
preg_replace("/(^|\r)(\&gt;[^\r]*)/", '$1<span class="q">$2</span>', $message['MSG']);
```

**Issue:** 
- `[^\r]*` is greedy, could match large blocks
- Doesn't handle nested quotes well

**Fix:**
```php
// Use non-greedy or split by lines
preg_replace("/(^|\r)(\&gt;[^\r]*?(?=\r|$))/", '$1<span class="q">$2</span>', $message['MSG']);
```

## Refactoring Recommendations

### Phase 1: Extract to Dedicated Classes

```php
// src/Utils/HtmlSanitizer.php
class HtmlSanitizer {
    public static function stripTags(string $html): string;
    public static function removeLinks(string $html): string;
    public static function sanitizeUserInput(string $input): string;
}

// src/Utils/QuoteFormatter.php
class QuoteFormatter {
    public static function formatQuote(string $message): string;
    public static function removeQuotes(string $message): string;
}

// src/Utils/TripCodeGenerator.php
class TripCodeGenerator {
    public static function generate(string $input, string $salt): string;
    public static function verify(string $input, string $tripcode, string $salt): bool;
}
```

### Phase 2: Pre-compile Patterns

```php
class RegexPatterns {
    public const HTML_TAG = '/<[^>]++>/';
    public const LINK_TAG = '/<a\s+href="[^"]*"[^>]*>.*?<\/a>/is';
    public const IMAGE_TAG = '/<img\s+[^>]*>/i';
    public const HEX_COLOR = '/^[0-9a-fA-F]{6}$/';
    public const FILENAME = '/^[\w.]+$/';
    
    // Compiled patterns with modifiers
    public static function getFollowLinkPattern(string $baseRoute): string {
        static $cache = [];
        if (!isset($cache[$baseRoute])) {
            $escaped = preg_quote($baseRoute, '/');
            $cache[$baseRoute] = "/<a href=\"{$escaped}[^\"]*\"[^>]*>[^<]+<\/a>/i";
        }
        return $cache[$baseRoute];
    }
}
```

### Phase 3: Add Unit Tests

```php
// tests/Unit/Utils/HtmlSanitizerTest.php
test('stripTags removes all HTML tags', function () {
    $input = '<script>alert(1)</script>Hello<b>World</b>';
    expect(HtmlSanitizer::stripTags($input))->toBe('HelloWorld');
});

test('stripTags handles malformed HTML', function () {
    $input = '<script<script>>alert(1)</script>';
    expect(HtmlSanitizer::stripTags($input))->not->toContain('<script');
});

test('stripTags handles catastrophic backtracking', function () {
    $input = '<a href="' . str_repeat('x', 10000);
    $start = microtime(true);
    HtmlSanitizer::stripTags($input);
    $duration = microtime(true) - $start;
    expect($duration)->toBeLessThan(0.1); // Should complete in <100ms
});
```

## Security Considerations

### XSS Prevention
- Never use regex alone for HTML sanitization
- Use `htmlspecialchars()` or HTML Purifier
- Validate input format before processing

### ReDoS Prevention
- Avoid nested quantifiers: `(a+)+`, `(a*)*`, `(a+)*`
- Use possessive quantifiers: `a++`, `a*+`
- Set `pcre.backtrack_limit` in php.ini
- Add timeouts for user-provided patterns

### Input Validation
- Use anchors `^` and `$` for full string validation
- Whitelist approach: define what IS allowed
- Validate length before regex processing

## Performance Optimization

### Benchmarks Needed

Test current patterns against:
- Normal input (100 chars)
- Large input (10,000 chars)
- Malicious input (nested structures)
- Edge cases (empty, null, special chars)

### Optimization Strategies

1. **Replace regex with string functions where possible**
   - `str_replace()` is 10x faster than `preg_replace()` for literals
   - `strpos()` is faster than `preg_match()` for simple searches

2. **Cache compiled patterns**
   - Store in static properties
   - Use pattern constants

3. **Reduce passes over data**
   - Combine multiple replacements
   - Use callback functions for complex logic

## Implementation Priority

1. **Critical (Week 1):** Fix catastrophic backtracking patterns
2. **High (Week 2):** Replace HTML tag stripping with `strip_tags()`
3. **Medium (Week 3):** Extract to utility classes
4. **Low (Week 4):** Optimize performance, add tests

## Related Documents

- [Bbs Refactoring Plan](bbs-refactoring-plan.md)
- [PSR Compliance Refactoring](psr-compliance-refactoring.md)
