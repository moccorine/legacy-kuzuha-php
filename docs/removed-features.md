# Removed Features

## ZIP Archive Generation for HTML Logs

**Removed in:** refactor/extract-html-generation branch (commit 2ff00bf)

**Reason:** 
- Feature was never used (ZIPDIR config is empty)
- Called undefined method `prthtmlhead()`
- Dead code that would cause fatal errors if enabled

**Original Functionality:**

When `ZIPDIR` was configured and `gzcompress()` was available, the system would:
1. Generate HTML versions of log files alongside dat format
2. Create ZIP archives of old HTML logs
3. Delete temporary HTML files after archiving

**Configuration Required:**
```php
'ZIPDIR' => '/path/to/zip/directory/',  // Must end with /
'OLDLOGFMT' => 1,  // Use dat format (1) or HTML format (0)
```

**Original Code (Bbs.php, lines ~1350-1375):**

```php
if ($this->config['ZIPDIR'] and @function_exists('gzcompress')) {
    # In the case of dat, it also writes the message log in HTML format 
    # as a temporary file to be saved in the ZIP
    if ($this->config['OLDLOGFMT']) {
        if ($this->config['OLDLOGSAVESW']) {
            $tmplogfilename = $this->config['ZIPDIR'] . date('Ym', CURRENT_TIME) . '.html';
        } else {
            $tmplogfilename = $this->config['ZIPDIR'] . date('Ymd', CURRENT_TIME) . '.html';
        }

        $fhtmp = @fopen($tmplogfilename, 'ab');
        if (!$fhtmp) {
            return;
        }
        flock($fhtmp, 2);

        if (!@filesize($tmplogfilename)) {
            // Generate HTML header (would need implementation)
            $oldloghtmlhead = $this->prthtmlhead($oldlogtitle);
            $oldloghtmlhead .= "<span class=\"pagetitle\">$oldlogtitle</span>\n\n<hr />\n";
            fwrite($fhtmp, $oldloghtmlhead);
        }
        $msghtml = $this->prtmessage($this->getmessage($msgdata), 3);
        fwrite($fhtmp, $msghtml);
        flock($fhtmp, 3);
        fclose($fhtmp);
    }
}
```

**To Re-implement:**

If you want to restore this feature:

1. **Implement HTML header/footer generation:**
   ```php
   // Create a method to generate HTML document structure
   private function generateLogHtmlHeader(string $title): string
   {
       return $this->renderTwig('log/header.twig', [
           'TITLE' => $title,
           'BBSTITLE' => $this->config['BBSTITLE'],
       ]);
   }
   
   private function generateLogHtmlFooter(): string
   {
       return $this->renderTwig('log/footer.twig', []);
   }
   ```

2. **Create Twig templates:**
   ```twig
   {# resources/views/log/header.twig #}
   <!DOCTYPE html>
   <html>
   <head>
       <meta charset="UTF-8">
       <title>{{ TITLE }}</title>
   </head>
   <body>
   <span class="pagetitle">{{ TITLE }}</span>
   <hr>
   
   {# resources/views/log/footer.twig #}
   </body>
   </html>
   ```

3. **Restore the ZIP generation code:**
   - Add back the code block shown above
   - Replace `prthtmlhead()` with `generateLogHtmlHeader()`
   - Add footer generation at the end of the file

4. **Configure ZIPDIR:**
   ```php
   'ZIPDIR' => '/path/to/storage/archives/',
   ```

5. **Ensure directory permissions:**
   ```bash
   mkdir -p /path/to/storage/archives
   chmod 777 /path/to/storage/archives
   ```

**Benefits of Re-implementation:**
- Compressed HTML archives for easy browsing
- Reduced storage space (ZIP compression)
- Separate archive directory for organization

**Considerations:**
- Requires additional disk I/O
- Needs proper error handling
- Should use Twig templates instead of inline HTML
- Consider using a background job for large archives

## Related Files

- `conf.php` - ZIPDIR and OLDLOGFMT configuration
- `Bbs.php` - putmessage() method (log archiving)
- Git commit: 2ff00bf
