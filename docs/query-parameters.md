# Query String Parameters

## Mode Parameters

### `m` - Mode
Specifies the display mode of the bulletin board.

- `m=p` - Post mode (default when posting)
- `m=f` - Follow-up post (reply) mode
- `m=t` - Thread view mode
- `m=tree` - Tree view mode
- `m=g` - Message log search mode
- `m=s` - Search mode
- `m=u` - Undo (delete post) mode
- `m=ad` - Admin mode

## Display Parameters

### `c` - Cookie/Session ID
Session identifier for maintaining user state.

Example: `?c=58`

### `d` - Display count (MSGDISP)
Number of posts to display per page.

- Positive number: Display that many posts
- `0`: Display unread posts only
- `-1`: Use default from config (MSGDISP or TREEDISP)
- Empty/not set: Use default from config

Example: `?d=40` (display 40 posts)

### `p` - Post ID / Top Post ID (TOPPOSTID)
- In main view: Latest post ID for unread tracking
- In follow-up mode: Parent post ID to reply to
- In thread/tree view: Thread root post ID

Example: `?p=123`

### `s` - Search/Specific Post ID
- In follow-up mode (`m=f`): Post ID to reply to
- In thread mode (`m=t`): Thread ID to display
- In tree mode (`m=tree`): Thread ID to display
- In search mode (`m=s`): Search query

Example: `?m=f&s=15` (reply to post #15)

### `b` - Beginning index
Starting index for pagination (used with next page navigation).

Example: `?b=40` (start from post 40)

## Form Parameters

### `u` - User name
Username for posting.

### `i` - Email (Mail)
Email address for posting.

### `t` - Title
Post title/subject.

### `v` - Message content (Value)
The actual message text.

### `l` - Link/URL
URL to attach to the post.

## Display Options

### `hide` - Hide form
When checked, hides the post form (log-reading mode).

Example: `?hide=checked`

### `loff` - Link off
When checked, hides the link navigation row.

Example: `?loff=checked`

### `sim` - Show images
When checked, displays images in image board mode.

Example: `?sim=checked`

### `a` - Auto-link
When checked, automatically converts URLs to links.

Example: `?a=checked`

## Admin Parameters

### `ad` - Admin action
Specifies admin action when in admin mode (`m=ad`).

- `ad=ps` - Password settings

### `pc` - Protect code (PCODE)
CSRF protection token generated for each form.

Example: `?pc=690f1f6cVvww`

## Search Parameters

### `ff` - From file
Specifies archive file for log search.

Example: `?ff=20251108.dat`

### `w` - Search word
Search keyword for message log search.

Example: `?w=keyword`

## Action Parameters

### `post` - Post action
Submit button for posting.

### `reload` - Reload action
Reload the current page.

### `readnew` - Read new
Display only unread posts.

### `setup` - User settings
Open user settings page.

### `write` - Admin write
Post as administrator.

## Examples

### View main page with 40 posts
```
/?c=58&d=40
```

### Reply to post #15
```
/?m=f&s=15&c=58
```

### View thread #12 in tree mode
```
/?m=tree&s=12&c=58
```

### Search message logs
```
/?m=g&c=58
```

### View all posts in tree mode (default count)
```
/?m=tree&c=58
```

### Admin mode
```
/?m=ad (POST with password)
```
