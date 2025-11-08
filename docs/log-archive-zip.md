# Log Archive ZIP Feature

## Overview
The bulletin board automatically creates ZIP archives of old log files for easy download and storage.

## How It Works

### Automatic ZIP Creation
When a new log period begins (daily or monthly depending on `OLDLOGSAVESW`), the system:
1. Identifies the most recently updated HTML log file (excluding the current period)
2. Creates a ZIP archive of that file
3. Saves the ZIP to the configured directory
4. Optionally deletes the original HTML file (if `OLDLOGFMT` is enabled)

### Configuration

**Required Settings in `conf.php`:**

```php
'ZIPDIR' => 'storage/app/archives/',  // Directory for ZIP archives
'OLDLOGFMT' => 1,                     // 1: Delete HTML after ZIP, 0: Keep both
```

**PHP Requirements:**
- PHP's `zip` extension must be enabled
- Uses native `ZipArchive` class (no external libraries needed)

### File Naming Convention

**Daily Mode (`OLDLOGSAVESW = 0`):**
- HTML: `YYYYMMDD.html` → ZIP: `YYYYMMDD.zip`
- Example: `20251108.html` → `20251108.zip`

**Monthly Mode (`OLDLOGSAVESW = 1`):**
- HTML: `YYYYMM.html` → ZIP: `YYYYMM.zip`
- Example: `202511.html` → `202511.zip`

## Accessing ZIP Archives

### Via Web Interface

**Archive List Page:**
```
http://your-bbs.com/bbs.php?m=g&gm=z
```

This page displays:
- All ZIP archives in the `ZIPDIR` directory
- File names, dates, and sizes
- Direct download links

**From Log Search Page:**
```
http://your-bbs.com/bbs.php?m=g
```
Click "ZIP archives" link at the top

### Supported Archive Formats

The archive list displays files with these extensions:
- `.zip` - ZIP archives (created by the system)
- `.lzh` - LHA archives (manual upload)
- `.rar` - RAR archives (manual upload)
- `.gz` - Gzip archives (manual upload)
- `.tar.gz` - Tar+Gzip archives (manual upload)

## Directory Structure

```
storage/app/archives/
├── 202501.zip
├── 202502.zip
├── 202503.zip
└── 202511.zip
```

## Permissions

The web server must have write permissions to `ZIPDIR`:

```bash
chmod 777 storage/app/archives
```

## Troubleshooting

### ZIP Files Not Created

**Check PHP zip extension:**
```bash
php -m | grep zip
```

If not installed:
```bash
# Debian/Ubuntu
sudo apt-get install php-zip

# Alpine (Docker)
apk add php-zip
```

**Check directory permissions:**
```bash
ls -la storage/app/archives/
```

**Check error logs:**
```bash
tail -f storage/logs/error.log
```

### ZIP Files Empty or Corrupted

- Ensure source HTML files exist before ZIP creation
- Check disk space: `df -h`
- Verify `ZipArchive` class is available: `php -r "var_dump(class_exists('ZipArchive'));"`

## Manual Archive Management

### Adding Archives Manually

You can manually place archive files in `ZIPDIR`:

```bash
cp old-logs.zip storage/app/archives/
```

They will appear in the archive list automatically.

### Cleaning Old Archives

Archives are not automatically deleted. To clean up:

```bash
# Delete archives older than 1 year
find storage/app/archives/ -name "*.zip" -mtime +365 -delete
```

## Code Reference

**ZIP Creation:** `src/Kuzuha/Bbs.php` - `main()` method (around line 1420)
**Archive List:** `src/Kuzuha/Getlog.php` - `prtarchivelist()` method
**Template:** `resources/views/log/archivelist.twig`

## Migration Notes

**Previous Implementation:**
- Used third-party `PHPZip` library (`lib/phpzip.inc.php`)
- Required manual library inclusion

**Current Implementation (v2.0+):**
- Uses PHP's native `ZipArchive` class
- No external dependencies
- More reliable and maintainable

## Security Considerations

- Archive directory should be outside web root if possible
- Use `.htaccess` to prevent direct directory listing:

```apache
# storage/app/archives/.htaccess
Options -Indexes
```

- Consider implementing download authentication for sensitive boards
- Regularly backup archive files to external storage
