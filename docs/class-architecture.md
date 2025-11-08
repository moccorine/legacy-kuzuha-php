# Class Architecture

## Class Hierarchy

```
Webapp (Base class)
├── Bbs (Standard bulletin board)
│   ├── Imagebbs (Image upload mode)
│   └── Treeview (Tree view display)
├── Getlog (Log search and archives)
└── Bbsadmin (Admin functions)
```

## Webapp (Base Class)

**Location:** `src/Kuzuha/Webapp.php`

**Responsibility:** Common functionality shared by all bulletin board modes

### Core Functions

**Session & Form Management:**
- `__construct()` - Initialize config
- `procForm()` - Process form input, sanitize data
- `setusersession()` - Set user session data (host, IP, user agent)
- `refcustom()` - Load user custom settings from cookie

**Message Handling:**
- `loadmessage($logfilename)` - Load messages from log file
- `getmessage($logline)` - Parse single message from log line
- `setmessage($message, $mode, $tlog)` - Prepare message for display (add buttons, format)
- `prtmessage($message, $mode, $tlog)` - Render message using Twig

**Output:**
- `prterror($err_message)` - Display error page
- `prtredirect($redirecturl)` - Display redirect page
- `renderTwig($template, $data)` - Render Twig template

**Utilities:**
- `sethttpheader()` - Set HTTP headers
- `setstarttime()` - Record page generation start time

### Properties
```php
public $config;   // Configuration array
public $form;     // Form input data
public $session;  // Session data (user info, URLs, etc.)
```

## Bbs (Standard Bulletin Board)

**Location:** `src/Kuzuha/Bbs.php`

**Responsibility:** Main bulletin board functionality (posting, viewing, searching)

### Page Display

**Main Pages:**
- `main()` - Route dispatcher (main, tree, admin, follow, newpost, etc.)
- `prtmain($retry)` - Display main page with message list
- `prtfollow($retry)` - Display follow-up (reply) page
- `prtnewpost($retry)` - Display new post page
- `prtputcomplete()` - Display post completion message
- `prtundo()` - Display deletion completion message

**User Settings:**
- `prtcustom($mode)` - Display user settings page
- `setcustom()` - Save user custom settings to cookie

**Search:**
- `prtsearchlist($mode)` - Display search results page
- `msgsearchlist($mode)` - Execute message search

### Message Operations

**Reading:**
- `getdispmessage()` - Get messages to display (with pagination)
- `searchmessage($varname, $searchvalue, $ismultiple, $filename)` - Search for specific message

**Writing:**
- `chkmessage($limithost)` - Validate message before posting
- `getformmessage()` - Prepare message data from form
- `putmessage($message)` - Write message to log file

### Component Generation

**Form & Stats:**
- `getFormData($dtitle, $dmsg, $dlink, $mode)` - Prepare form component data
- `getStatsData()` - Prepare stats component data
- `generateKaomojiButtons()` - Generate emoticon buttons HTML

### Utilities

**User Tracking:**
- `setuserenv()` - Get user environment (IP, host, user agent)
- `setbbscookie()` - Set BBS cookie
- `setundocookie($undoid, $pcode)` - Set undo cookie for post deletion

**Counters:**
- `counter($countlevel)` - Page view counter
- `mbrcount($cntfilename)` - Current participants counter

## Imagebbs (Image Upload Mode)

**Location:** `src/Kuzuha/Imagebbs.php`

**Responsibility:** Extends Bbs with image upload functionality

### Key Differences
- Overrides form generation to include file upload field
- Handles image file uploads
- Validates image files (size, type)
- Stores images in configured directory

**Inherits:** All Bbs functionality

## Treeview (Tree View Display)

**Location:** `src/Kuzuha/Treeview.php`

**Responsibility:** Display messages in tree/thread format

### Main Functions

**Display:**
- `main()` - Route dispatcher (tree list, thread view, user settings)
- `prttreeview($retry)` - Display tree view list page
- `prtthreadtree()` - Display single thread as tree

**Tree Generation:**
- `prttexttree(&$msgcurrent, &$thread)` - Generate text-based tree structure
- `gentree(&$treemsgs, $parentid)` - Recursive tree builder

**Overrides:**
- `getdispmessage()` - Modified for tree view pagination

**Inherits:** All Bbs functionality (posting, searching, etc.)

## Getlog (Log Search & Archives)

**Location:** `src/Kuzuha/Getlog.php`

**Responsibility:** Old log browsing, searching, and archive management

### Main Functions

**Display:**
- `main()` - Route dispatcher (log list, search, topic list, archives)
- `prtloglist()` - Display log file list
- `prtsearchresult()` - Display search results
- `prttopiclist()` - Display topic (thread) list
- `prtarchivelist()` - Display ZIP archive list

**Search:**
- `msgsearch($message, $conditions)` - Check if message matches search criteria
- `oldlogsearch($conditions)` - Search through old log files

**Export:**
- `prthtmldownload()` - Generate HTML download of log file

**Inherits:** Webapp base functionality

## Bbsadmin (Admin Functions)

**Location:** `src/Kuzuha/Bbsadmin.php`

**Responsibility:** Administrative operations (post deletion, password management)

### Main Functions

**Display:**
- `main()` - Route dispatcher (menu, kill list, password, log view)
- `prtadminmenu()` - Display admin menu
- `prtkilllist()` - Display post deletion list
- `prtpassword()` - Display password settings
- `prtlogview()` - Display access log

**Operations:**
- `killmessage()` - Delete posts
- `generatePassword()` - Generate encrypted password

**Inherits:** Webapp base functionality

## Responsibility Summary

### Webapp (Base)
- **What:** Common utilities
- **Why:** Avoid code duplication across all modes
- **Examples:** Form processing, message parsing, rendering, error handling

### Bbs (Main)
- **What:** Core bulletin board operations
- **Why:** Standard posting and viewing functionality
- **Examples:** Post messages, view list, reply, search, user settings

### Imagebbs (Extension)
- **What:** Image upload support
- **Why:** Optional image board mode
- **Examples:** File upload, image validation, thumbnail generation

### Treeview (Extension)
- **What:** Tree/thread display
- **Why:** Alternative view format for threaded discussions
- **Examples:** Tree structure, thread navigation, recursive display

### Getlog (Separate)
- **What:** Archive management
- **Why:** Historical data access separate from active board
- **Examples:** Browse old logs, search archives, download HTML/ZIP

### Bbsadmin (Separate)
- **What:** Administrative tasks
- **Why:** Privileged operations requiring authentication
- **Examples:** Delete posts, manage passwords, view logs

## Data Flow

### Posting a Message
```
User submits form
    ↓
Bbs::main() routes to prtmain()
    ↓
Bbs::chkmessage() validates input
    ↓
Bbs::getformmessage() prepares data
    ↓
Bbs::putmessage() writes to log
    ↓
Bbs::prtputcomplete() shows confirmation
```

### Viewing Messages
```
User accesses main page
    ↓
Bbs::main() routes to prtmain()
    ↓
Bbs::getdispmessage() loads messages
    ↓
Webapp::getmessage() parses each line
    ↓
Webapp::setmessage() adds buttons/formatting
    ↓
Webapp::prtmessage() renders with Twig
```

### Searching Archives
```
User submits search form
    ↓
Getlog::main() routes to prtsearchresult()
    ↓
Getlog::oldlogsearch() scans log files
    ↓
Getlog::msgsearch() filters messages
    ↓
Webapp::prtmessage() renders results
```

## Refactoring Considerations

### Current Issues
1. **Webapp is too generic** - Contains both utilities and business logic
2. **Bbs is too large** - 1500+ lines, multiple responsibilities
3. **Inheritance depth** - Imagebbs/Treeview inherit everything from Bbs

### Potential Improvements

**Option 1: Extract Services**
```
MessageService - Message CRUD operations
FormService - Form generation and validation
SessionService - User session management
RenderService - Template rendering
```

**Option 2: Composition over Inheritance**
```
Bbs uses MessageRepository
Bbs uses FormBuilder
Bbs uses ViewRenderer
```

**Option 3: Split by Feature**
```
PostController - Posting operations
ViewController - Display operations
SearchController - Search operations
AdminController - Admin operations
```

### Recommended Approach
Keep current structure for now (legacy compatibility), but:
1. Move utility methods to separate helper classes
2. Extract form/stats generation to dedicated classes
3. Create MessageRepository for data access
4. Gradually reduce Webapp/Bbs size through extraction

## Testing Strategy

**Unit Tests:**
- Webapp: Message parsing, form processing
- Bbs: Message validation, search logic
- Getlog: Search criteria matching

**Integration Tests:**
- Full posting flow
- Search across multiple logs
- Admin operations

**Current Status:** No automated tests (legacy codebase)
