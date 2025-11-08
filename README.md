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
