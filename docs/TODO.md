# TODO

## Routing Refactoring

### Move Bbs::main() logic to Slim routes
- `Bbs::main()` currently handles routing logic internally using `$this->form['m']` parameter
- Should be split into individual Slim routes for better RESTful design:
  - `m=p` (post) → Already handled by POST `/`
  - `m=c` (save settings) → POST `/settings`
  - `setup` (settings page) → GET `/settings`
  - `m=u` (undo) → POST `/undo`
  - `m=p&write` (new post page) → GET `/new`
  - Default → `prtmain()` direct call

**Benefits:**
- More RESTful architecture
- Clearer routing structure
- Easier to test
- Better separation of concerns

**Considerations:**
- Initialization logic (`procForm()`, `refcustom()`, `setusersession()`) needs to be called in each route or moved to middleware
- Admin password check logic should be centralized
- GZIP handling should be middleware
