# Protect Code (PCODE) Specification

## Overview

Protect Code (PCODE) is a security mechanism used in the bulletin board system to prevent:
- **CSRF attacks** (Cross-Site Request Forgery)
- **Double posting** (submitting the same form twice)
- **Replay attacks** (reusing old form submissions)
- **Post interval violations** (posting too quickly)

## Structure

PCODE is a **12-character hexadecimal string** composed of two parts:

```
[8-char timestamp][4-char cryptcode]
```

### Example
```
675e8a3c4f2a
├─────┬─────┘└─┬─┘
│     │        └─ Cryptcode (4 chars)
│     └────────── Timestamp in hex (8 chars)
└──────────────── Total: 12 characters
```

## Generation Process

### 1. Timestamp Component (8 characters)

```php
$timestamp = CURRENT_TIME; // Unix timestamp
$timestamphex = dechex($timestamp); // Convert to hexadecimal (8 chars)
```

### 2. User Key (Optional - when limithost=true)

```php
$ukey = hexdec(substr(md5($remoteaddr), 0, 8));
// MD5 hash of IP address, first 8 chars converted to decimal
```

### 3. Base Code

```php
$basecode = dechex($timestamp + $ukey);
// Timestamp + User Key, converted to hex
```

### 4. Crypt Code (4 characters)

```php
$salt = substr($adminPost, -4) . $basecode;
$cryptcode = crypt($basecode . substr($adminPost, -4), $salt);
$cryptcode = substr(preg_replace("/\W/", '', $cryptcode), -4);
// Last 4 alphanumeric characters of crypt result
```

### 5. Final PCODE

```php
$pcode = dechex($timestamp) . $cryptcode;
// 8-char timestamp + 4-char cryptcode = 12 chars total
```

## Verification Process

When a form is submitted, the PCODE is verified:

### 1. Length Check
```php
if (strlen($pcode) != 12) {
    return null; // Invalid
}
```

### 2. Extract Components
```php
$timestamphex = substr($pcode, 0, 8);  // First 8 chars
$cryptcode = substr($pcode, 8, 4);     // Last 4 chars
```

### 3. Reconstruct and Verify
```php
$timestamp = hexdec($timestamphex);
$basecode = dechex($timestamp + $ukey);
$verifycode = crypt($basecode . substr($adminPost, -4), $salt);
$verifycode = substr(preg_replace("/\W/", '', $verifycode), -4);

if ($cryptcode != $verifycode) {
    return null; // Invalid
}
return $timestamp; // Valid - return original timestamp
```

## Security Features

### 1. CSRF Protection
- PCODE is generated server-side and embedded in forms
- Cannot be forged without knowing `ADMINPOST` secret
- Each form submission requires a valid PCODE

### 2. Double Post Prevention
- PCODE is stored with each post in the log file
- System checks if the same PCODE was already used
- Prevents accidental double-clicks or form resubmissions

```php
if ($message['PCODE'] == $items[2]) {
    $posterr = 2; // Retry error - show form again
    break;
}
```

### 3. Post Interval Enforcement
- Timestamp is extracted from PCODE
- System calculates time elapsed since form was generated
- Enforces minimum posting interval (`MINPOSTSEC`)

```php
if ((CURRENT_TIME - $timestamp) < $this->config['MINPOSTSEC']) {
    $this->prterror(Translator::trans('error.post_interval_too_short'));
}
```

### 4. IP Address Binding (Optional)
- When `limithost=true`, PCODE includes IP address hash
- Form can only be submitted from the same IP that generated it
- Prevents form stealing and cross-network attacks

## Usage in Forms

### Generation (Server-side)
```php
$pcode = SecurityHelper::generateProtectCode();
```

### Embedding in HTML
```twig
<input type="hidden" name="pc" value="{{ PCODE }}" />
```

### Verification (Server-side)
```php
$timestamp = SecurityHelper::verifyProtectCode($this->form['pc'], $limithost);
if (!$timestamp) {
    // Invalid PCODE
}
```

## Storage in Log File

PCODE is stored as the 3rd field in each log entry:

```
timestamp,postid,pcode,thread,host,agent,user,mail,title,message,refid
```

Example:
```
1699876860,12345,4f2a,12340,192.168.1.1,Mozilla/5.0,...
                 ^^^^
                 Only the 4-char cryptcode is stored
```

Note: Only the **4-character cryptcode portion** is stored (extracted via `substr($pcode, 8, 4)`), not the full 12-character PCODE.

## Configuration

### MINPOSTSEC
Minimum seconds between posts (default: 5)
```php
'MINPOSTSEC' => 5,
```

### IPREC
Enable IP address recording and checking
```php
'IPREC' => 1, // 1=enabled, 0=disabled
```

### SPTIME
Spam prevention time window (seconds)
```php
'SPTIME' => 60, // Block same IP for 60 seconds
```

## Security Considerations

### Strengths
1. **Cryptographically secure**: Uses `crypt()` with secret salt
2. **Time-limited**: Old PCODEs become invalid after max posting interval
3. **IP-bound**: Optional IP address binding prevents form theft
4. **Unique per form**: Each form generation creates a new PCODE

### Limitations
1. **Not session-based**: PCODE doesn't require active session
2. **Predictable timestamp**: Timestamp is visible in hex format
3. **Weak crypt**: Uses DES crypt (legacy), not modern hashing
4. **No expiration**: Only enforces minimum interval, not maximum age

### Recommendations
1. Keep `ADMINPOST` secret secure (used as salt)
2. Enable `IPREC` for IP-based protection
3. Set appropriate `MINPOSTSEC` to prevent spam
4. Consider upgrading to modern HMAC-based tokens in future

## Related Files

- `src/Utils/SecurityHelper.php` - PCODE generation and verification
- `src/Kuzuha/Bbs.php` - PCODE usage in post processing
- `resources/views/components/form.twig` - PCODE embedding in forms
- `conf.php` - Security configuration settings

## See Also

- [Cookie Management](./cookie-management.md)
- [Post Validation](./bbs-refactoring-plan.md)
- [Security Best Practices](./security-best-practices.md)
