<?php

namespace Kuzuha;

use App\Config;
use App\Services\CookieService;
use App\Services\LogService;
use App\Services\UserPreferenceService;
use App\Translator;
use App\Utils\DateHelper;
use App\Utils\PerformanceTimer;
use App\Utils\RegexPatterns;
use App\Utils\StringHelper;
use App\Utils\TextEscape;
use App\View;
use App\Models\Repositories\BbsLogRepositoryInterface;

/**
 * Webapp - Base class for BBS application
 * 
 * Provides core functionality for all BBS pages:
 * - Input processing and sanitization
 * - Session and user preference management
 * - Log file reading and parsing (via LogService)
 * - Message display preparation
 * - Template rendering
 * 
 * Extended by: Bbs, Getlog, Bbsadmin, Treeview, Imagebbs
 */
class Webapp
{
    // ========================================
    // Public Properties (accessed by child classes and templates)
    // ========================================
    
    /**
     * @var array Application configuration
     */
    public $config;
    
    /**
     * @var array Sanitized form input data
     */
    public $form = [];
    
    /**
     * @var array Session data (user info, display settings, query strings)
     */
    public $session = [];
    
    // ========================================
    // Protected/Private Properties
    // ========================================
    
    /**
     * @var BbsLogRepositoryInterface|null BBS log repository
     */
    protected $bbsLogRepository;
    
    /**
     * @var UserPreferenceService User preference service
     */
    private $preferenceService;
    
    /**
     * @var LogService Log reading and parsing service
     */
    private $logService;

    /**
     * Constructor
     * 
     * Initializes configuration and service instances.
     */
    public function __construct()
    {
        $this->config = Config::getInstance()->all();
        $this->preferenceService = new UserPreferenceService();
        $this->logService = new LogService(
            $this->config['LOGFILENAME'],
            $this->config['OLDLOGFILEDIR']
        );
    }

    /**
     * Display error page and exit
     *
     * @param string $err_message Error message to display
     * @return void
     */
    public function prterror($err_message)
    {
        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'] . ' Error',
            'ERR_MESSAGE' => $err_message,
            'CUSTOMSTYLE' => '',
            'CUSTOMHEAD' => '',
            'TRANS_RETURN' => Translator::trans('error.return'),
            'TRANS_RETURN_TITLE' => Translator::trans('error.return_title'),
        ]);
        echo View::getInstance()->render('error.twig', $data);
        exit();
    }


    /**
     * Set BBS log repository
     * 
     * @param BbsLogRepositoryInterface $repo Repository instance
     * @return void
     */
    public function setBbsLogRepository($repo): void
    {
        $this->bbsLogRepository = $repo;
        $this->logService->setBbsLogRepository($repo);
    }

    /*20210625 Neko/2chtrip http://www.mits-jp.com/2ch/ */

    /**
     * Load and sanitize form input from HTTP request
     * 
     * Receives form data from POST/GET requests, sanitizes it, and stores in $this->form.
     * Provides three-layer security: size limit, host validation, and HTML escaping.
     *
     * @access  public
     * @return  void
     */
    public function loadAndSanitizeInput()
    {
        // Check POST data size to prevent DoS attacks
        if (!$this->config['BBSMODE_IMAGE'] && $_SERVER['CONTENT_LENGTH'] > $this->config['MAXMSGSIZE'] * 5) {
            $this->prterror(Translator::trans('error.post_too_large'));
        }
        // Validate request host to prevent CSRF attacks
        if ($this->config['BBSHOST'] && $_SERVER['HTTP_HOST'] != $this->config['BBSHOST']) {
            $this->prterror(Translator::trans('error.invalid_caller'));
        }
        
        // Get input data from POST or GET
        $input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        
        // HTML escape all input values to prevent XSS attacks
        $this->form = array_map(function ($value) {
            return is_array($value)
                ? array_map([StringHelper::class, 'htmlEscape'], $value)
                : StringHelper::htmlEscape($value);
        }, $input);
    }

    /**
     * Initialize session data
     * 
     * Sets up session variables from form input and cookies:
     * - User data (name, email, color)
     * - Display settings (message count, top post ID)
     * - UNDO data (if available)
     * - Query string for URL persistence
     * - Default URL
     * 
     * Priority: Form input > Cookie data > Config defaults
     * 
     * @return void
     */
    public function initializeSession(): void
    {
        $this->loadFormDataToSession();
        $this->loadCookieDataToSession();
        $this->buildSessionUrls();
    }

    /**
     * Load form data to session
     */
    private function loadFormDataToSession(): void
    {
        $this->session['U'] = $this->form['u'] ?? null;
        $this->session['I'] = $this->form['i'] ?? null;
        $this->session['C'] = $this->form['c'] ?? null;
        $this->session['MSGDISP'] = (isset($this->form['d']) && $this->form['d'] != -1) 
            ? $this->form['d'] 
            : $this->config['MSGDISP'];
        $this->session['TOPPOSTID'] = $this->form['p'] ?? null;
    }

    /**
     * Load cookie data to session (if not set by form)
     */
    private function loadCookieDataToSession(): void
    {
        if (!$this->config['COOKIE']) {
            return;
        }

        // Load user data from cookie
        $userData = CookieService::getUserCookieFromGlobal();
        if ($userData) {
            $this->session['U'] = $this->session['U'] ?? $userData['name'];
            $this->session['I'] = $this->session['I'] ?? $userData['email'];
            $this->session['C'] = $this->session['C'] ?? $userData['color'];
        }

        // Load UNDO data from cookie
        if ($this->config['ALLOW_UNDO']) {
            $undoData = CookieService::getUndoCookieFromGlobal();
            if ($undoData) {
                $this->session['UNDO_P'] = $undoData['post_id'];
                $this->session['UNDO_K'] = $undoData['key'];
            }
        }
    }

    /**
     * Build query string and default URL
     */
    private function buildSessionUrls(): void
    {
        // Build query string for URL persistence
        $queryParts = ['c=' . $this->session['C']];
        
        if (!empty($this->session['MSGDISP'])) {
            $queryParts[] = 'd=' . $this->session['MSGDISP'];
        }
        if (!empty($this->session['TOPPOSTID'])) {
            $queryParts[] = 'p=' . $this->session['TOPPOSTID'];
        }
        
        $this->session['QUERY'] = implode('&', $queryParts);
        $this->session['DEFURL'] = $this->config['CGIURL'] . '?' . $this->session['QUERY'];
    }

    /**
     * Prepare message data for display
     * 
     * Transforms raw message data into display-ready format by:
     * - Formatting date/time
     * - Escaping special characters for Twig
     * - Converting reference links
     * - Generating action buttons (follow, thread, tree, etc.)
     * - Adding environment information if enabled
     *
     * @access  public
     * @param   array   $message  Raw message data from log
     * @param   int     $mode     Display mode (0: BBS, 1: Search with buttons, 2: Search without buttons, 3: File output)
     * @param   string  $tlog     Log file name (for search results)
     * @return  array   Message data prepared for display
     */
    public function prepareMessageForDisplay($message, $mode = 0, $tlog = '')
    {
        $message['WDATE'] = DateHelper::getDateString($message['NDATE'], $this->config['DATEFORMAT']);
        $message['MSG'] = TextEscape::escapeTwigChars((string) $message['MSG']);

        // TODO: YouTube URL展開機能の実装を検討（クライアントサイドまたはサーバーサイド）

        $message['MSG'] = $this->processReferenceLinks($message['MSG'], $mode);
        $message['MSG'] = $this->processQuotes($message['MSG']);
        $message['MSG'] = $this->processImages($message['MSG']);

        if (!$message['THREAD']) {
            $message['THREAD'] = $message['POSTID'];
        }

        if ($mode == 0 or ($mode == 1 and $this->config['OLDLOGBTN'])) {
            $message = array_merge($message, $this->buildActionButtons($message, $mode, $tlog));
        }

        $message['HAS_MAIL'] = !empty($message['MAIL']);
        $message['SHOW_IP'] = $this->config['IPPRINT'];
        $message['SHOW_UA'] = $this->config['UAPRINT'];

        return $message;
    }

    /**
     * Process reference links in message
     * 
     * Converts follow links based on display mode:
     * - Mode 0 (BBS): Full URL with query parameters
     * - Mode 1+ (Search): Anchor links to same page
     * 
     * @param string $msg Message content
     * @param int $mode Display mode
     * @return string Processed message
     */
    private function processReferenceLinks($msg, $mode)
    {
        if (!$mode) {
            $msg = preg_replace(
                "/<a href=\"" . preg_quote(route('follow', ['s' => '']), '/') . "(\d+)[^>]+>([^<]+)<\/a>$/i",
                "<a href=\"" . route('follow', ['s' => '$1']) . "&{$this->session['QUERY']}\">$2</a>",
                $msg,
                1
            );
            $msg = preg_replace(
                "/<a href=\"mode=follow&search=(\d+)[^>]+>([^<]+)<\/a>$/i",
                "<a href=\"" . route('follow', ['s' => '$1']) . "&{$this->session['QUERY']}\">$2</a>",
                $msg,
                1
            );
        } else {
            $msg = preg_replace(
                "/<a href=\"" . preg_quote(route('follow', ['s' => '']), '/') . "(\d+)[^>]+>([^<]+)<\/a>$/i",
                '<a href="#a$1">$2</a>',
                $msg,
                1
            );
            $msg = preg_replace(
                "/<a href=\"mode=follow&search=(\d+)[^>]+>([^<]+)<\/a>$/i",
                '<a href="#a$1">$2</a>',
                $msg,
                1
            );
        }
        return $msg;
    }

    /**
     * Process quote markers in message
     * 
     * Wraps lines starting with '>' in <span class="q"> tags.
     * Optimizes consecutive quote lines to avoid redundant tags.
     * 
     * @param string $msg Message content
     * @return string Processed message with quote styling
     */
    private function processQuotes($msg)
    {
        $msg = preg_replace("/(^|\r)(\&gt;[^\r]*)/", '$1<span class="q">$2</span>', (string) $msg);
        return str_replace("</span>\r<span class=\"q\">", "\r", $msg);
    }

    /**
     * Process image tags in message
     * 
     * Converts image tags to text links if:
     * - SHOWIMG config is disabled
     * - Image file does not exist
     * 
     * @param string $msg Message content
     * @return string Processed message
     */
    private function processImages($msg)
    {
        if (!$this->config['SHOWIMG']) {
            return StringHelper::convertImageTag($msg);
        }
        if (preg_match("/<a href=[^>]+><img [^>]*?src=\"([^\"]+)\"[^>]+><\/a>/i", (string) $msg, $matches)) {
            if (!file_exists($matches[1])) {
                return StringHelper::convertImageTag($msg);
            }
        }
        return $msg;
    }

    /**
     * Build action button URLs for message display
     * 
     * Generates URLs for message action buttons (follow, author search, thread, tree, undo).
     * Button availability depends on:
     * - Display mode (BBS vs search results)
     * - Admin-only mode setting
     * - Message author (anonymous vs named)
     * - UNDO availability
     * 
     * @param array $message Message data with POSTID, USER, THREAD
     * @param int $mode Display mode (0: BBS, 1: Search with buttons, 2+: Search without buttons)
     * @param string $tlog Archive log filename (for search results)
     * @return array Button URLs and settings
     */
    private function buildActionButtons($message, $mode, $tlog)
    {
        $buttons = [
            'FOLLOW_URL' => null,
            'AUTHOR_URL' => null,
            'THREAD_URL' => null,
            'TREE_URL' => null,
            'UNDO_URL' => null,
            'OPEN_NEW_WINDOW' => !$this->config['FOLLOWWIN'],
        ];

        if ($this->config['BBSMODE_ADMINONLY'] == 1) {
            return $buttons;
        }

        parse_str($this->session['QUERY'], $queryParams);

        $buttons['FOLLOW_URL'] = $this->buildFollowUrl($message['POSTID'], $queryParams, $mode, $tlog);
        $buttons['AUTHOR_URL'] = $this->buildAuthorUrl($message['USER'], $mode, $tlog);
        $buttons['THREAD_URL'] = $this->buildThreadUrl($message['THREAD'], $queryParams, $mode, $tlog);
        $buttons['TREE_URL'] = $this->buildTreeUrl($message['THREAD'], $mode, $tlog);
        $buttons['UNDO_URL'] = $this->buildUndoUrl($message['POSTID']);

        return $buttons;
    }

    /**
     * Add common parameters to URL params
     */
    private function addCommonParams(array $params, int $mode, string $tlog): array
    {
        if (!empty($this->form['w'])) {
            $params['w'] = $this->form['w'];
        }
        if ($mode == 1) {
            $params['ff'] = $tlog;
        }
        return $params;
    }

    /**
     * Build follow button URL
     */
    private function buildFollowUrl(string $postId, array $queryParams, int $mode, string $tlog): string
    {
        $params = array_merge(['s' => $postId], $queryParams);
        $params = $this->addCommonParams($params, $mode, $tlog);
        return route('follow', $params);
    }

    /**
     * Build author search URL (null for anonymous)
     */
    private function buildAuthorUrl(string $user, int $mode, string $tlog): ?string
    {
        if ($user == $this->config['ANONY_NAME']) {
            return null;
        }

        $params = [
            'm' => 's',
            's' => RegexPatterns::stripHtmlTags($user)
        ];
        $params = $this->addCommonParams($params, $mode, $tlog);
        return $this->config['CGIURL'] . '?' . http_build_query($params) . '&' . $this->session['QUERY'];
    }

    /**
     * Build thread view URL
     */
    private function buildThreadUrl(string $threadId, array $queryParams, int $mode, string $tlog): string
    {
        $params = array_merge(['s' => $threadId], $queryParams);
        $params = $this->addCommonParams($params, $mode, $tlog);
        return route('thread', $params);
    }

    /**
     * Build tree view URL
     */
    private function buildTreeUrl(string $threadId, int $mode, string $tlog): string
    {
        $params = ['m' => 'tree', 's' => $threadId];
        $params = $this->addCommonParams($params, $mode, $tlog);
        return $this->config['CGIURL'] . '?' . http_build_query($params) . '&' . $this->session['QUERY'];
    }

    /**
     * Build undo URL (null if not available)
     */
    private function buildUndoUrl(string $postId): ?string
    {
        if (!$this->config['ALLOW_UNDO'] || 
            !isset($this->session['UNDO_P']) || 
            $this->session['UNDO_P'] != $postId) {
            return null;
        }

        return $this->config['CGIURL'] . '?m=u&s=' . $postId . '&' . $this->session['QUERY'];
    }

    /**
     * Single message output
     *
     * Outputs the HTML of a message based on the message array.
     * Supports the message log module.
     *
     * @access  public
     * @param   Array   $message    Message
     * @param   Integer $mode       0: Bulletin board / 1: Message log search (with buttons displayed) / 2: Message log search (without buttons displayed) / 3: For message log output file
     * @param   String  $tlog       Specified log file
     * @return  String  Message HTML data
     */
    public function renderMessage($message, $mode = 0, $tlog = '')
    {
        $message = $this->prepareMessageForDisplay($message, $mode, $tlog);
        
        return $this->renderTwig('components/message.twig', array_merge($message, [
            'TRANS_USER' => Translator::trans('message.user'),
            'TRANS_POST_DATE' => Translator::trans('message.post_date'),
            'TXTFOLLOW' => $this->config['TXTFOLLOW'],
            'TXTAUTHOR' => $this->config['TXTAUTHOR'],
            'TXTTHREAD' => $this->config['TXTTHREAD'],
            'TXTTREE' => $this->config['TXTTREE'],
            'TXTUNDO' => $this->config['TXTUNDO'],
        ]));
    }

    /**
     * Get log lines from file
     *
     * Delegates to LogService for log reading operations.
     *
     * @param string $logfilename Log file name (optional)
     * @return array Raw log lines
     */
    public function getLogLines(string $logfilename = ''): array
    {
        try {
            return $this->logService->getLogLines($logfilename);
        } catch (\RuntimeException $e) {
            $this->prterror(Translator::trans('error.failed_to_read'));
        }
    }

    /**
     * Parse log line to message array
     *
     * Delegates to LogService for CSV parsing.
     *
     * @param string $logline Raw log line
     * @return array|null Message array or null if invalid
     */
    public function parseLogLine(string $logline): ?array
    {
        return $this->logService->parseLogLine($logline);
    }

    /**
     * Apply user preferences to configuration
     * 
     * Processes user preferences from URL parameter 'c' and form input:
     * - Decodes preferences from Base32/Base64 encoded string
     * - Updates config with color and flag settings
     * - Applies form-based overrides
     * - Re-encodes preferences for URL persistence
     * 
     * @param string $colorString Optional Base64 color string to append
     * @return string Encoded preference string for URL parameter
     */
    public function applyUserPreferences(string $colorString = ''): string
    {
        $this->preferenceService->initializeDefaults($this->config);
        
        $colorChanged = false;
        if (!empty($this->form['c'])) {
            $colorChanged = $this->preferenceService->applyPreferences($this->config, $this->form['c']);
        }
        
        $this->preferenceService->updateFromForm($this->config, $this->form);
        $this->preferenceService->applySpecialConditions($this->config, $this->form);
        
        $encoded = $this->preferenceService->encodePreferences(
            $this->config,
            $this->form['c'] ?? '',
            $colorChanged || !empty($colorString)
        );
        
        if ($colorString) {
            $encoded = substr($encoded, 0, 2) . $colorString;
        }
        
        $this->form['c'] = $encoded;
        return $encoded;
    }

    /**
     * Render Twig template
     */
    public function renderTwig($template, $data = [])
    {
        return View::getInstance()->render($template, $data);
    }

}
