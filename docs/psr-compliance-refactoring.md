# PSR Compliance Refactoring Plan

## Overview

Refactor method names from snake_case to camelCase to comply with PSR-1 and PSR-12 coding standards.

## Status: 🚧 IN PROGRESS

## Strategy

Refactor incrementally, one class at a time, to minimize risk:
1. Identify all public/protected methods in a class
2. Rename methods to camelCase
3. Update all call sites
4. Run tests
5. Commit changes

## Classes to Refactor

### Priority 1: Utility Classes (Low Risk)
- [ ] `App\Utils\ParticipantCounter`
- [ ] `App\Utils\NetworkHelper`
- [ ] `App\Utils\StringHelper`
- [ ] `App\Utils\DateHelper`

### Priority 2: Core Classes (Medium Risk)
- [ ] `App\Config`
- [ ] `App\Translator`

### Priority 3: Legacy Classes (High Risk)
- [ ] `Kuzuha\Webapp` (base class)
- [ ] `Kuzuha\Bbs`
- [ ] `Kuzuha\Imagebbs`
- [ ] `Kuzuha\Getlog`
- [ ] `Kuzuha\Treeview`
- [ ] `Kuzuha\Bbsadmin`

## Method Naming Examples

| Before (snake_case) | After (camelCase) |
|---------------------|-------------------|
| `mbrcount()` | `getMemberCount()` |
| `counter()` | `getCounter()` |
| `prthtmlhead()` | `printHtmlHead()` |
| `prthtmlfoot()` | `printHtmlFoot()` |
| `prtmain()` | `printMain()` |
| `prtfollow()` | `printFollow()` |
| `setcustom()` | `setCustom()` |
| `getcustom()` | `getCustom()` |

## Testing Checklist

After each refactoring:
- [ ] Run unit tests: `./vendor/bin/pest`
- [ ] Test main page: `http://localhost:8080/`
- [ ] Test search page: `http://localhost:8080/search`
- [ ] Test tree view: `http://localhost:8080/tree`
- [ ] Test thread view: `http://localhost:8080/thread`
- [ ] Check error logs

## Notes

- Keep backward compatibility where possible using method aliases
- Update documentation after each class refactoring
- Private methods can be refactored more aggressively
- Focus on public API first
