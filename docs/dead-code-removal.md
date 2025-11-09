# Dead Code Removal

## Conclusion

**Status:** DEAD CODE - Methods are called but never defined, yet app works fine

**Reason:** The code paths calling these undefined methods are never executed because:
1. `CGIURL` is `/` (relative path)
2. `ValidationRegex::hasHttpProtocol('/')` returns `false`  
3. So `header("Location: ...")` is executed instead of `prtredirect()`

**Action:** SAFE TO REMOVE

## Undefined Method Calls

### prthtmlhead() and prthtmlfoot()

**Status:** UNDEFINED - Methods are called but never defined

**Locations:**
- `Webapp.php:145` - `print $this->prthtmlhead(...)`
- `Webapp.php:156` - `print $this->prthtmlfoot()`
- `Getlog.php:417` - `print $this->prthtmlhead(...)`
- `Getlog.php:381, 430, 912` - `print $this->prthtmlfoot()`
- `Bbs.php:1334, 1381` - `$oldloghtmlhead = $this->prthtmlhead(...)`

**Issue:** These methods are never defined in:
- Webapp class
- View class
- Any parent class or trait

**Impact:** Code will fail if these paths are executed

**Action Required:**
1. Check if these code paths are actually executed
2. If yes: Implement methods or replace with Twig templates
3. If no: Remove dead code

## Investigation Steps

### Step 1: Check if prtredirect() is used
```bash
grep -rn "prtredirect" src/
```

### Step 2: Check if Getlog methods are used
```bash
grep -rn "prtarchivelist\|prtoldlog" src/
```

### Step 3: Check if Bbs oldlog generation is used
```bash
grep -rn "oldloghtmlhead" src/
```

## Recommendation

**Priority: HIGH**

These undefined methods should either be:
1. **Removed** if the code paths are never executed (dead code)
2. **Implemented** if they are needed
3. **Replaced** with Twig template rendering

Most likely these are remnants from old code that was partially migrated to Twig.
