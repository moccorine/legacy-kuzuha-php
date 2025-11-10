# Validation Logic Refactoring Analysis

## Current State

### BbsPostValidator Service (Already Implemented)
**Location**: `src/Services/BbsPostValidator.php`

**Responsibilities**:
- Post validation logic
- Message building
- Admin mode detection
- Returns validation result codes

**Usage**: 
```php
$validator = new BbsPostValidator($this->config, $this->form, $this->session);
$posterr = $validator->validate();
```

### Dead Code in Bbs.php

The following validation methods are **no longer called** and should be removed:

1. **`validatePost($limithost = true)`** (line 1047)
   - Main validation orchestrator
   - Calls all sub-validation methods
   - **Status**: Dead code, replaced by BbsPostValidator

2. **`validatePostingEnabled()`** (line 1068)
   - Checks if posting is suspended
   - **Status**: Dead code

3. **`validateAdminOnly()`** (line 1084)
   - Checks admin-only mode
   - **Status**: Dead code

4. **`validateReferer()`** (line 1107)
   - HTTP referer validation
   - **Status**: Still used in handlePostMode() - KEEP

5. **`validateMessageFormat()`** (line 1126)
   - Message format validation
   - **Status**: Dead code

6. **`validateFieldLengths()`** (line 1149)
   - Field length validation
   - **Status**: Dead code

7. **`validatePostInterval($limithost)`** (line 1175)
   - Post interval/rate limiting
   - **Status**: Dead code

8. **`validateProhibitedWords()`** (line 1188)
   - Prohibited word checking
   - **Status**: Dead code

### Methods to Keep

1. **`validateReferer()`** - Still called in `handlePostMode()` at line 1093
2. **`validateAndResolveThread(array $message)`** (line 1433) - Used for thread resolution

## Verification

```bash
# Check if validatePost is called
grep -n "->validatePost\|this->validatePost" src/Kuzuha/Bbs.php
# Result: Not called

# Check if sub-validation methods are called
grep -n "validatePostingEnabled\|validateAdminOnly\|validateMessageFormat\|validateFieldLengths\|validatePostInterval\|validateProhibitedWords" src/Kuzuha/Bbs.php
# Result: Only defined, never called (except within validatePost itself)

# Check validateReferer usage
grep -n "validateReferer" src/Kuzuha/Bbs.php
# Result: Called at line 1093 in handlePostMode()
```

## Recommended Actions

### Phase 1: Remove Dead Code (Safe)

Remove the following methods from `Bbs.php`:
- `validatePost()`
- `validatePostingEnabled()`
- `validateAdminOnly()`
- `validateMessageFormat()`
- `validateFieldLengths()`
- `validatePostInterval()`
- `validateProhibitedWords()`

**Lines to remove**: ~150 lines of dead code

**Risk**: None - these methods are not called anywhere

### Phase 2: Review BbsPostValidator Coverage

Ensure `BbsPostValidator` covers all validation logic:

1. ✅ Posting enabled check
2. ✅ Admin-only mode check
3. ✅ Message format validation
4. ✅ Field length validation
5. ✅ Post interval/rate limiting
6. ✅ Prohibited words check
7. ❓ Referer validation - Currently in Bbs.php

### Phase 3: Move validateReferer to BbsPostValidator (Optional)

**Current**:
```php
// In Bbs.php handlePostMode()
$this->validateReferer();
$validator = new BbsPostValidator(...);
```

**Proposed**:
```php
// Move referer check into BbsPostValidator
$validator = new BbsPostValidator(...);
$posterr = $validator->validate(); // Includes referer check
```

**Benefit**: All validation in one place

**Risk**: Low - referer validation is independent

## Implementation Plan

### Step 1: Verify Dead Code
```bash
# Run tests to ensure nothing breaks
./vendor/bin/pest

# Check for any dynamic calls (eval, variable functions)
grep -r "validatePost\|validateAdminOnly" src/ --include="*.php"
```

### Step 2: Remove Dead Methods
- Delete 8 validation methods (~150 lines)
- Keep `validateReferer()` and `validateAndResolveThread()`

### Step 3: Update Documentation
- Update method count in class documentation
- Add comment explaining validation is handled by BbsPostValidator

### Step 4: Optional - Move validateReferer
- Add referer validation to BbsPostValidator
- Remove validateReferer() from Bbs.php
- Update handlePostMode() to remove explicit call

## Code Metrics

### Before Cleanup
- Total validation methods: 9
- Dead code: ~150 lines
- Active validation methods: 2 (validateReferer, validateAndResolveThread)

### After Cleanup
- Total validation methods: 2
- Dead code: 0 lines
- All validation centralized in BbsPostValidator service

## Testing Strategy

1. **Unit Tests**: Verify BbsPostValidator covers all cases
2. **Integration Tests**: Test post submission flows
3. **Manual Testing**: 
   - Normal post
   - Follow-up post
   - Admin mode activation
   - Rate limiting
   - Prohibited words

## Conclusion

**Recommendation**: Proceed with Phase 1 (Remove Dead Code)

- **Impact**: Low risk, high benefit
- **Effort**: 30 minutes
- **Lines removed**: ~150
- **Complexity reduction**: Significant

The validation logic has already been successfully extracted to `BbsPostValidator`. The old methods in `Bbs.php` are dead code and should be removed.
