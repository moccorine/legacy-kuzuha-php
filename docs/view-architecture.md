# View Architecture

## Overview
The bulletin board uses Twig template engine for all HTML rendering with full internationalization (i18n) support.

## Directory Structure

```
resources/views/
├── layout/
│   ├── base.twig              # Base HTML structure (used by most pages)
│   └── base_header.twig       # Header-only template (for tree thread view)
├── components/
│   ├── form.twig              # Post form component
│   ├── message.twig           # Message display component
│   ├── stats.twig             # Page statistics and navigation
│   ├── tree_customstyle.twig  # Tree view CSS generator
│   └── error.twig             # Error display (unused, error.twig used instead)
├── main/
│   ├── upper.twig             # Main page header and form
│   └── lower.twig             # Main page footer and navigation
├── tree/
│   ├── upper.twig             # Tree view header and form
│   └── lower.twig             # Tree view footer
├── log/
│   ├── list.twig              # Log file list
│   ├── searchresult.twig      # Search results
│   ├── topiclist.twig         # Topic list (thread index)
│   ├── archivelist.twig       # ZIP archive list
│   ├── htmldownload.twig      # HTML download page
│   ├── oldlog_header.twig     # Old log header
│   └── oldlog_footer.twig     # Old log footer
├── admin/
│   ├── menu.twig              # Admin menu
│   ├── killlist.twig          # Post deletion list
│   ├── password.twig          # Password settings
│   └── logview.twig           # Log viewer
├── custom.twig                # User settings page
├── error.twig                 # Error page
├── follow.twig                # Follow-up post page
├── newpost.twig               # New post page
├── postcomplete.twig          # Post completion message
├── redirect.twig              # Redirect page
└── undocomplete.twig          # Deletion completion message
```

## Component Architecture

### Layout Templates

**base.twig**
- Full HTML document structure
- Used by: follow, newpost, custom, error, redirect, completion pages
- Provides: `{% block content %}` for page-specific content

**base_header.twig**
- Header-only template (no closing tags)
- Used by: tree thread view (prtthreadtree)
- Allows custom body content and footer

### Reusable Components

**form.twig**
- Post form with conditional display
- Modes: default, image upload, hidden
- Features:
  - Name, email, title, content fields
  - Kaomoji (emoticon) buttons
  - File upload (image mode)
  - Form configuration (hidden on follow page)
- Variables: `SHOW_FORMCONFIG`, `BBSMODE_IMAGE`, `HIDEFORM`

**message.twig**
- Individual message display
- Features:
  - User name with HTML support (`|raw` filter)
  - Post date and time
  - Message content with quote highlighting
  - Action buttons (follow, search, thread, tree)
  - Environment info (IP, User-Agent) if enabled
- Whitespace control: `{{- -}}` for `<pre>` tags

**stats.twig**
- Page statistics and navigation
- Features:
  - Page view counter with bulletproof level
  - Current participants count
  - Max posts saved
  - Navigation links (PR office, message logs)
  - Help text for symbols (■, ★, ◆, 木, Undo)
- Only shown on main page, hidden on follow page

**tree_customstyle.twig**
- Generates CSS for tree view colors
- Variables: `C_BRANCH`, `C_UPDATE`, `C_NEWMSG`

## Page Templates

### Main Page (/)
- **upper.twig**: Header, navigation, form, stats
- **lower.twig**: Footer with pagination, duration, copyright
- Flow: upper → messages → lower

### Tree View (?m=tree)
- **Listing mode**: upper.twig → messages → lower.twig
- **Thread mode**: base_header.twig → tree HTML → manual footer
- Custom CSS via tree_customstyle.twig

### Follow Page (?m=f)
- **follow.twig**: Extends base.twig
- Shows referenced message and reply form
- Form config and stats hidden (`SHOW_FORMCONFIG=false`)

### Log Pages (?m=g)
- **list.twig**: Log file selection
- **searchresult.twig**: Search results display
- **topiclist.twig**: Thread index with reply counts
- **archivelist.twig**: ZIP archive downloads

### Admin Pages (?m=ad)
- **menu.twig**: Admin dashboard
- **killlist.twig**: Post management
- **password.twig**: Password generation
- **logview.twig**: Access log viewer

## Rendering Flow

### PHP Side

```php
// 1. Prepare data
$data = array_merge($this->config, $this->session, [
    'TITLE' => 'Page Title',
    'CUSTOM_VAR' => $value,
    'TRANS_KEY' => Translator::trans('namespace.key'),
]);

// 2. Render template
echo $this->renderTwig('template.twig', $data);
```

### Twig Side

```twig
{# Extend base layout #}
{% extends 'layout/base.twig' %}

{% block content %}
  {# Use variables #}
  <h1>{{ TITLE }}</h1>
  
  {# Include component #}
  {% include 'components/form.twig' %}
  
  {# Conditional display #}
  {% if SHOW_STATS %}
    {{ STATS|raw }}
  {% endif %}
{% endblock %}
```

## Translation Integration

All templates use translation keys via `Translator::trans()`:

```php
'TRANS_LABEL' => Translator::trans('namespace.key')
```

**Translation Files:**
- `translations/messages.ja.json` - Japanese
- `translations/messages.en.json` - English

**Namespaces:**
- `main.*` - Main page
- `form.*` - Form labels
- `message.*` - Message display
- `stats.*` - Statistics
- `tree.*` - Tree view
- `log.*` - Log pages
- `admin.*` - Admin pages
- `error.*` - Error messages
- `follow.*` - Follow page
- `newpost.*` - New post page
- `custom.*` - User settings
- `redirect.*` - Redirect page

## Special Filters

**|raw**
- Prevents HTML escaping
- Used for: USER, TITLE, MSG, FORM, STATS, BBSLINK
- Required when content contains HTML tags

**{{- -}}**
- Whitespace control
- Used in: message.twig for `<pre>` tags
- Prevents extra spaces/newlines

## Data Flow

```
Controller (Bbs.php, Getlog.php, etc.)
    ↓
Prepare data array
    ↓
Call renderTwig()
    ↓
View::getInstance()->render()
    ↓
Twig renders template
    ↓
HTML output
```

## Component Data Requirements

### form.twig
```php
[
    'MODE' => '<input type="hidden"...>',
    'PCODE' => 'security_code',
    'DTITLE' => 'default title',
    'DMSG' => 'default message',
    'DLINK' => 'default link',
    'HIDEFORM' => 0,
    'SHOW_FORMCONFIG' => true,
    'BBSMODE_IMAGE' => 0,
    'KAOMOJI_BUTTONS' => '<input...>',
    'TRANS_*' => 'translated labels',
]
```

### message.twig
```php
[
    'POSTID' => 123,
    'USER' => 'Username',
    'WDATE' => '2025/11/08(Sat) 12:34:56',
    'TITLE' => 'Subject',
    'MSG' => 'Message content',
    'BTN' => '<a href...>buttons</a>',
    'SHOW_ENV' => true,
    'ENVADDR' => 'IP address',
    'ENVUA' => 'User agent',
    'TRANS_USER' => '投稿者',
    'TRANS_POST_DATE' => '投稿日時',
]
```

### stats.twig
```php
[
    'COUNTER' => '12345',
    'SHOW_COUNTER' => true,
    'COUNTLEVEL' => 5,
    'MBRCOUNT' => '10',
    'SHOW_MBRCOUNT' => true,
    'CNTLIMIT' => 300,
    'LOGSAVE' => 5000,
    'INFOPAGE' => '/',
    'DEFURL' => '/?c=58&d=40',
    'BBSLINK' => '<a href...>',
    'TXTFOLLOW' => '■',
    'SHOW_UNDO' => true,
    'TRANS_*' => 'translated labels',
]
```

## Best Practices

1. **Always escape by default**: Twig auto-escapes, use `|raw` only when needed
2. **Use whitespace control**: `{{- -}}` for `<pre>` tags to prevent formatting issues
3. **Translate all text**: Use `Translator::trans()` for all user-facing strings
4. **Component reuse**: Use components for repeated elements (form, message, stats)
5. **Consistent naming**: Use UPPERCASE for template variables
6. **Merge config/session**: Always include `$this->config` and `$this->session` in data array

## Migration from patTemplate

The system previously used patTemplate. All templates have been migrated to Twig:
- No patTemplate dependencies remain
- All `prthtmlhead()`, `prthtmlfoot()` calls removed
- Template variables passed directly to Twig
- Cleaner separation of logic and presentation
