# Legacy Kuzuha PHP BBS

## Deploy

### 1. Clone and setup

```bash
git clone <repository-url>
cd legacy-kuzuha-php
cp .env.example .env
```

### 2. Configure environment

Edit `.env` and set your configuration:
```bash
APP_NAME="Your BBS Name"
APP_URL=http://your-domain.com
APP_LOCALE=ja  # or en

ADMIN_NAME=Administrator
ADMIN_EMAIL=your@email.com
# Leave ADMIN_PASSWORD empty for initial setup
ADMIN_PASSWORD=
ADMIN_KEY=your-secret-key
```

### 3. Set permissions

```bash
chmod -R 777 storage/app storage/logs storage/cache
```

### 4. Start Docker container

```bash
docker-compose up -d
```

### 5. Install dependencies

```bash
docker-compose exec web composer install
```

### 6. Initial password setup

Access `http://localhost:8080/` and you'll see the password settings page.

Enter your desired admin password and click "Set".

Copy the encrypted password string displayed (e.g., `7PUPc9zzOI3DQ`).

### 7. Configure admin password

Edit `.env` and set the encrypted password:

```bash
ADMIN_PASSWORD=7PUPc9zzOI3DQ
```

### 8. Restart container

```bash
docker-compose restart
```

The bulletin board is now ready at `http://localhost:8080/`

## URL Structure

The bulletin board uses RESTful routing:

- `/` - Main bulletin board
- `/search` - Message log search and archives
- `/tree` - Tree view of message threads
- `/thread` - Thread view
- `/follow` - Follow-up post page (reply to a specific post)
- `/admin` - Admin mode (requires authentication)

**Legacy URLs** are automatically redirected to the new paths with 301 status:
- `/?m=g` → `/search`
- `/?m=tree` → `/tree`
- `/?m=t` → `/thread`
- `/?m=f` → `/follow`
- `/?m=ad` → `/admin`

**Subdirectory Installation**: The application automatically handles subdirectory installations (e.g., `/path/to/bbs/`) using the `route()` helper function. All URLs are generated relative to the `CGIURL` configuration.

## Log Mode Configuration

The bulletin board supports two log archive modes:

### Daily Log Mode (OLDLOGSAVESW = 0)
- Archives are saved daily with filename format: `YYYYMMDD.dat` (e.g., `20251108.dat`)
- Time range search uses hours and minutes (HH:MM)
- Suitable for high-traffic boards

### Monthly Log Mode (OLDLOGSAVESW = 1) - Default
- Archives are saved monthly with filename format: `YYYYMM.dat` (e.g., `202511.dat`)
- Time range search uses days and hours (DD HH)
- Suitable for low to medium-traffic boards

**Configuration:**
Edit `conf.php` and set:
```php
'OLDLOGSAVESW' => 0,  // Daily mode
// or
'OLDLOGSAVESW' => 1,  // Monthly mode (default)
```

**Important:** Once you start using the board, do not change this setting. The log mode cannot be switched after archives are created, as the file naming conventions are incompatible. Choose the appropriate mode before initial deployment.

## Image Mode

When `BBSMODE_IMAGE` is enabled in `conf.php`, the bulletin board operates in image upload mode.
