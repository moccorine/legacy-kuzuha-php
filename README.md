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
