# Legacy Kuzuha PHP BBS

## Deploy

### 1. Set permissions

```bash
chmod 777 log count
chmod 666 bbs.log bbs.cnt
```

### 2. Start Docker container

```bash
docker-compose up -d
```

### 3. Initial password setup

Access `http://localhost:8080/bbs.php` and you'll see the password settings page.

Copy the encrypted password string displayed (e.g., `7PUPc9zzOI3DQ`).

### 4. Configure admin password

Edit `conf.php` and set the encrypted password:

```php
'ADMINPOST' => '7PUPc9zzOI3DQ',
```

### 5. Restart container

```bash
docker-compose restart
```

The bulletin board is now ready at `http://localhost:8080/bbs.php`

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

## Routes

### Main Routes

- `bbs.php` - Main bulletin board (default)
- `bbs.php?m=g` - Message log search
- `bbs.php?m=tree` - Tree view
- `bbs.php?m=ad` (POST) - Admin mode (requires ADMINPOST password and ADMINKEY)

### Admin Routes

- Initial setup (when ADMINPOST is empty) - Password settings page
- `bbs.php?m=ad&ad=ps` (POST) - Generate encrypted password

### Image Mode

When `BBSMODE_IMAGE` is enabled in `conf.php`, the bulletin board operates in image upload mode.
