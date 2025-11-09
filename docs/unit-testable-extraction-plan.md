# Unit Testable Methods - Extraction Plan

## Overview

Extract complex logic from `Bbs.php` into pure, testable utility classes. Focus on methods with:
- Complex business logic
- Security-critical operations
- Regex patterns
- Data transformations
- No side effects (or easily mockable)

## Priority 1: Security & Validation (CRITICAL)

### 1. TripCodeGenerator

**Current Location:** `Bbs.php:1145, 1486`, `setUndoCookie()`

**Current Code:**
```php
// Weak and scattered
$undokey = substr(preg_replace("/\W/", '', crypt($pcode, $this->config['ADMINPOST'])), -8);
$tripcode = substr(preg_replace("/\W/", '', crypt(substr($user, strpos($user, '#')), '00')), -7);
```

**Extract To:** `src/Utils/TripCodeGenerator.php`

```php
class TripCodeGenerator
{
    /**
     * Generate trip code from input string
     * 
     * @param string $input Raw input (e.g., password after #)
     * @param string $salt Salt for hashing
     * @return string Trip code (10 chars)
     */
    public static function generate(string $input, string $salt = ''): string
    {
        // Use modern hashing instead of crypt()
        $hash = hash_hmac('sha256', $input, $salt ?: 'default_salt');
        return substr($hash, 0, 10);
    }
    
    /**
     * Generate undo key from post code
     * 
     * @param string $postCode Post protection code
     * @param string $adminSalt Admin salt
     * @return string Undo key (8 chars)
     */
    public static function generateUndoKey(string $postCode, string $adminSalt): string
    {
        $hash = hash_hmac('sha256', $postCode, $adminSalt);
        return substr($hash, 0, 8);
    }
    
    /**
     * Parse username with trip code
     * 
     * @param string $username Username with optional #tripcode
     * @return array ['name' => string, 'trip_input' => string|null]
     */
    public static function parseUsername(string $username): array
    {
        $pos = strpos($username, '#');
        if ($pos === false) {
            return ['name' => $username, 'trip_input' => null];
        }
        
        return [
            'name' => substr($username, 0, $pos),
            'trip_input' => substr($username, $pos + 1)
        ];
    }
}
```

**Unit Tests:**
```php
test('generate creates consistent trip code', function () {
    $trip1 = TripCodeGenerator::generate('password', 'salt');
    $trip2 = TripCodeGenerator::generate('password', 'salt');
    expect($trip1)->toBe($trip2);
    expect(strlen($trip1))->toBe(10);
});

test('generate creates different codes for different inputs', function () {
    $trip1 = TripCodeGenerator::generate('password1', 'salt');
    $trip2 = TripCodeGenerator::generate('password2', 'salt');
    expect($trip1)->not->toBe($trip2);
});

test('generateUndoKey creates 8 character key', function () {
    $key = TripCodeGenerator::generateUndoKey('1234', 'admin_salt');
    expect(strlen($key))->toBe(8);
});

test('parseUsername splits name and trip input', function () {
    $result = TripCodeGenerator::parseUsername('User#password');
    expect($result['name'])->toBe('User');
    expect($result['trip_input'])->toBe('password');
});

test('parseUsername handles no trip code', function () {
    $result = TripCodeGenerator::parseUsername('User');
    expect($result['name'])->toBe('User');
    expect($result['trip_input'])->toBeNull();
});
```

**Impact:** Fixes weak crypto, enables testing, improves security

---

### 2. HtmlSanitizer

**Current Location:** `Bbs.php:594-604, 617, 735, 754`, `Webapp.php:237`

**Current Code:**
```php
// Scattered and insecure
preg_replace('/<[^>]*>/', '', $message['USER']);
preg_replace("/<a href=\"[^>]+>([^<]+)<\/a>/i", '$1', $formmsg);
```

**Extract To:** `src/Utils/HtmlSanitizer.php`

```php
class HtmlSanitizer
{
    /**
     * Strip all HTML tags safely
     * 
     * @param string $html Input with HTML
     * @return string Plain text
     */
    public static function stripTags(string $html): string
    {
        // Use built-in function, not regex
        return strip_tags($html);
    }
    
    /**
     * Remove links but keep text
     * 
     * @param string $html HTML with links
     * @return string HTML without links
     */
    public static function removeLinks(string $html): string
    {
        // Use DOMDocument for safe parsing
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $links = $dom->getElementsByTagName('a');
        foreach ($links as $link) {
            $text = $dom->createTextNode($link->textContent);
            $link->parentNode->replaceChild($text, $link);
        }
        
        return $dom->saveHTML();
    }
    
    /**
     * Remove specific link pattern
     * 
     * @param string $html HTML content
     * @param string $urlPattern URL pattern to remove
     * @return string Cleaned HTML
     */
    public static function removeLinksByPattern(string $html, string $urlPattern): string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $links = $dom->getElementsByTagName('a');
        $toRemove = [];
        
        foreach ($links as $link) {
            if (str_starts_with($link->getAttribute('href'), $urlPattern)) {
                $toRemove[] = $link;
            }
        }
        
        foreach ($toRemove as $link) {
            $link->parentNode->removeChild($link);
        }
        
        return $dom->saveHTML();
    }
}
```

**Unit Tests:**
```php
test('stripTags removes all HTML', function () {
    $input = '<b>Hello</b> <script>alert(1)</script>World';
    expect(HtmlSanitizer::stripTags($input))->toBe('Hello World');
});

test('stripTags handles malformed HTML', function () {
    $input = '<script<script>>alert(1)</script>';
    $result = HtmlSanitizer::stripTags($input);
    expect($result)->not->toContain('<script');
});

test('removeLinks keeps text content', function () {
    $input = 'Hello <a href="http://example.com">World</a>!';
    $result = HtmlSanitizer::removeLinks($input);
    expect($result)->toContain('World');
    expect($result)->not->toContain('<a');
});

test('removeLinksByPattern removes matching links only', function () {
    $input = '<a href="/follow?s=1">Follow</a> <a href="/other">Other</a>';
    $result = HtmlSanitizer::removeLinksByPattern($input, '/follow');
    expect($result)->not->toContain('Follow');
    expect($result)->toContain('Other');
});
```

**Impact:** Prevents XSS, fixes ReDoS, enables testing

---

### 3. PostValidator

**Current Location:** `Bbs.php:976-1085` (`chkmessage()`)

**Current Code:**
```php
// 111 lines of validation mixed with error handling
public function chkmessage($limithost = true) {
    // Host check
    // Admin check
    // Referer check
    // Length check
    // Flood check
    // Spam check
    // etc...
}
```

**Extract To:** `src/Services/PostValidator.php`

```php
class PostValidator
{
    private array $config;
    private array $errors = [];
    
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    /**
     * Validate post data
     * 
     * @param array $formData Form input
     * @param array $session Session data
     * @throws ValidationException
     */
    public function validate(array $formData, array $session): void
    {
        $this->errors = [];
        
        $this->validateRunMode();
        $this->validateHost($session['HOST'], $session['AGENT']);
        $this->validateAdminOnly($formData);
        $this->validateReferer();
        $this->validateMessageLength($formData['v']);
        $this->validateMessageLines($formData['v']);
        $this->validateFloodControl($session['HOST']);
        $this->validateSpam($formData);
        
        if (!empty($this->errors)) {
            throw new ValidationException(implode("\n", $this->errors));
        }
    }
    
    private function validateRunMode(): void
    {
        if ($this->config['RUNMODE'] == 1) {
            $this->errors[] = 'Posting is suspended';
        }
    }
    
    private function validateHost(string $host, string $agent): void
    {
        if (NetworkHelper::hostnameMatch(
            $this->config['HOSTNAME_POSTDENIED'],
            $this->config['HOSTAGENT_BANNED']
        )) {
            $this->errors[] = 'Posting from this host is denied';
        }
    }
    
    private function validateAdminOnly(array $formData): void
    {
        if ($this->config['BBSMODE_ADMINONLY'] == 1 
            || ($this->config['BBSMODE_ADMINONLY'] == 2 && !$formData['f'])) {
            if (!$this->isAdmin($formData['u'])) {
                $this->errors[] = 'Admin only mode';
            }
        }
    }
    
    private function validateMessageLength(string $message): void
    {
        if (strlen($message) > $this->config['MAXMSGSIZE']) {
            $this->errors[] = 'Message too long';
        }
    }
    
    private function validateMessageLines(string $message): void
    {
        foreach (explode("\r", $message) as $line) {
            if (strlen($line) > $this->config['MAXMSGCOL']) {
                $this->errors[] = 'Line too long';
            }
        }
        
        $lineCount = substr_count($message, "\r") + 1;
        if ($lineCount > $this->config['MAXMSGLINE']) {
            $this->errors[] = 'Too many lines';
        }
    }
    
    private function validateFloodControl(string $host): void
    {
        // Check last post time from host
        // Implementation depends on storage
    }
    
    private function isAdmin(string $username): bool
    {
        return crypt($username, $this->config['ADMINPOST']) === $this->config['ADMINPOST'];
    }
}
```

**Unit Tests:**
```php
test('validate passes for valid post', function () {
    $validator = new PostValidator(['RUNMODE' => 0, 'MAXMSGSIZE' => 1000]);
    $formData = ['v' => 'Hello', 'u' => 'User'];
    $session = ['HOST' => '127.0.0.1', 'AGENT' => 'Browser'];
    
    expect(fn() => $validator->validate($formData, $session))->not->toThrow(ValidationException::class);
});

test('validate fails when posting suspended', function () {
    $validator = new PostValidator(['RUNMODE' => 1]);
    $formData = ['v' => 'Hello'];
    $session = ['HOST' => '127.0.0.1', 'AGENT' => 'Browser'];
    
    expect(fn() => $validator->validate($formData, $session))
        ->toThrow(ValidationException::class, 'suspended');
});

test('validate fails for too long message', function () {
    $validator = new PostValidator(['RUNMODE' => 0, 'MAXMSGSIZE' => 10]);
    $formData = ['v' => str_repeat('a', 100)];
    $session = ['HOST' => '127.0.0.1', 'AGENT' => 'Browser'];
    
    expect(fn() => $validator->validate($formData, $session))
        ->toThrow(ValidationException::class, 'too long');
});
```

**Impact:** Testable validation, single responsibility, reusable

---

## Priority 2: Data Transformation (HIGH)

### 4. QuoteFormatter

**Current Location:** `Bbs.php:594-604`

**Current Code:**
```php
// 8 sequential regex operations
$formmsg = preg_replace("/&gt; &gt;[^\r]+\r/", '', $formmsg);
$formmsg = preg_replace("/<a href=\"...\"/i", '', $formmsg);
// ... 6 more operations
```

**Extract To:** `src/Utils/QuoteFormatter.php`

```php
class QuoteFormatter
{
    /**
     * Format message as quote
     * 
     * @param string $message Original message
     * @param bool $removeLinks Remove links from quote
     * @return string Formatted quote
     */
    public static function formatAsQuote(string $message, bool $removeLinks = true): string
    {
        if ($removeLinks) {
            $message = self::removeQuoteMarkers($message);
            $message = HtmlSanitizer::removeLinks($message);
        }
        
        // Add quote prefix to each line
        $lines = explode("\r", $message);
        $quoted = array_map(fn($line) => "> $line", $lines);
        
        return "> " . implode("\r> ", $lines) . "\r";
    }
    
    /**
     * Remove existing quote markers
     * 
     * @param string $message Message with quotes
     * @return string Message without quotes
     */
    public static function removeQuoteMarkers(string $message): string
    {
        // Remove lines starting with "> >"
        $lines = explode("\r", $message);
        $filtered = array_filter($lines, fn($line) => !str_starts_with($line, '> >'));
        return implode("\r", $filtered);
    }
    
    /**
     * Clean up empty quote lines
     * 
     * @param string $message Quoted message
     * @return string Cleaned message
     */
    public static function cleanEmptyQuotes(string $message): string
    {
        // Remove "> \r" patterns
        return preg_replace("/\r>\s+\r/", "\r", $message);
    }
}
```

**Unit Tests:**
```php
test('formatAsQuote adds prefix to lines', function () {
    $input = "Line 1\rLine 2";
    $result = QuoteFormatter::formatAsQuote($input, false);
    expect($result)->toContain('> Line 1');
    expect($result)->toContain('> Line 2');
});

test('removeQuoteMarkers removes nested quotes', function () {
    $input = "Normal\r> > Nested\rNormal2";
    $result = QuoteFormatter::removeQuoteMarkers($input);
    expect($result)->not->toContain('> >');
    expect($result)->toContain('Normal');
});

test('cleanEmptyQuotes removes blank quote lines', function () {
    $input = "> Line 1\r>  \r> Line 2";
    $result = QuoteFormatter::cleanEmptyQuotes($input);
    expect($result)->not->toMatch('/>\s+\r/');
});
```

**Impact:** Eliminates 8 regex operations, testable, maintainable

---

### 5. MessageFormatter

**Current Location:** `Bbs.php:1087-1200` (`getformmessage()`)

**Current Code:**
```php
// 113 lines of data transformation
public function getformmessage() {
    $message = [];
    $message['PCODE'] = substr($this->form['pc'], 8, 4);
    $message['USER'] = $this->form['u'] ?: $this->config['ANONY_NAME'];
    // ... lots of transformations
}
```

**Extract To:** `src/Services/MessageFormatter.php`

```php
class MessageFormatter
{
    private array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    /**
     * Format form data into message array
     * 
     * @param array $formData Raw form input
     * @param array $session Session data
     * @return array Formatted message
     */
    public function formatMessage(array $formData, array $session): array
    {
        $message = [
            'PCODE' => $this->formatProtectCode($formData['pc']),
            'USER' => $this->formatUsername($formData['u']),
            'MAIL' => $formData['i'] ?? '',
            'TITLE' => $formData['t'] ?: ' ',
            'MSG' => $formData['v'],
            'URL' => $formData['l'] ?? '',
            'PHOST' => $session['HOST'],
            'AGENT' => $session['AGENT'],
            'REFID' => $formData['f'] ?? '',
        ];
        
        return $message;
    }
    
    private function formatProtectCode(string $code): string
    {
        return substr($code, 8, 4);
    }
    
    private function formatUsername(string $username): string
    {
        if (empty($username)) {
            return $this->config['ANONY_NAME'];
        }
        
        if ($this->isAdmin($username)) {
            return "<span class=\"muh\">{$this->config['ADMINNAME']}</span>";
        }
        
        return $username;
    }
    
    private function isAdmin(string $username): bool
    {
        return $this->config['ADMINPOST'] 
            && crypt($username, $this->config['ADMINPOST']) === $this->config['ADMINPOST'];
    }
}
```

**Unit Tests:**
```php
test('formatMessage creates valid message array', function () {
    $formatter = new MessageFormatter(['ANONY_NAME' => 'Anonymous']);
    $formData = ['pc' => '12345678ABCD', 'u' => 'User', 'v' => 'Hello'];
    $session = ['HOST' => '127.0.0.1', 'AGENT' => 'Browser'];
    
    $result = $formatter->formatMessage($formData, $session);
    
    expect($result['PCODE'])->toBe('ABCD');
    expect($result['USER'])->toBe('User');
    expect($result['MSG'])->toBe('Hello');
});

test('formatMessage uses anonymous name for empty user', function () {
    $formatter = new MessageFormatter(['ANONY_NAME' => 'Anonymous']);
    $formData = ['pc' => '12345678ABCD', 'u' => '', 'v' => 'Hello'];
    $session = ['HOST' => '127.0.0.1', 'AGENT' => 'Browser'];
    
    $result = $formatter->formatMessage($formData, $session);
    expect($result['USER'])->toBe('Anonymous');
});
```

**Impact:** Testable transformations, clear data flow

---

## Implementation Plan

### Week 1: Security Critical
1. Create `TripCodeGenerator` + tests (Day 1-2)
2. Create `HtmlSanitizer` + tests (Day 2-3)
3. Create `PostValidator` + tests (Day 3-5)

### Week 2: Data Transformation
4. Create `QuoteFormatter` + tests (Day 1-2)
5. Create `MessageFormatter` + tests (Day 3-4)
6. Integration testing (Day 5)

### Week 3: Integration
7. Update `Bbs.php` to use new classes
8. Deprecate old methods
9. Full regression testing

## Success Metrics

- **Test Coverage:** 90%+ for extracted classes
- **Performance:** No regression (benchmark before/after)
- **Security:** Pass OWASP security scan
- **Code Reduction:** Remove 400+ lines from `Bbs.php`

## Testing Strategy

### Unit Tests
- Pure functions with no dependencies
- Mock config/session data
- Test edge cases and error conditions

### Integration Tests
- Test extracted classes working together
- Mock file I/O and external dependencies
- Verify backward compatibility

### Security Tests
- XSS injection attempts
- ReDoS attack patterns
- Trip code collision testing
- Flood control bypass attempts

## Related Documents

- [Bbs Refactoring Plan](bbs-refactoring-plan.md)
- [Regex Issues](regex-issues.md)
- [PSR Compliance Refactoring](psr-compliance-refactoring.md)
