<?php

namespace Kuzuha;

use App\Config;
use App\Services\CookieService;
use App\Translator;
use App\Utils\DateHelper;
use App\Utils\PerformanceTimer;
use App\Utils\RegexPatterns;
use App\Utils\StringHelper;
use App\Utils\TextEscape;
use App\View;

class Webapp
{
    public $config; /* Settings information */
    public $form; /* Form input */
    public $session = []; /* Session-specific information such as the user's host */

    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $this->config = Config::getInstance()->all();
    }

    /*20210625 Neko/2chtrip http://www.mits-jp.com/2ch/ */

    public function procForm()
    {
        if (!$this->config['BBSMODE_IMAGE'] and $_SERVER['CONTENT_LENGTH'] > $this->config['MAXMSGSIZE'] * 5) {
            $this->prterror(Translator::trans('error.post_too_large'));
        }
        if ($this->config['BBSHOST'] and $_SERVER['HTTP_HOST'] != $this->config['BBSHOST']) {
            $this->prterror(Translator::trans('error.invalid_caller'));
        }
        # Limited to POST or GET only
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->f = $_POST;
        } else {
            $this->f = $_GET;
        }
        # String replacement
        foreach ($this->f as $name => $value) {
            if (is_array($value)) {
                foreach (array_keys($value) as $valuekey) {
                    $value[$valuekey] = StringHelper::htmlEscape($value[$valuekey]);
                }
            } else {
                $value = StringHelper::htmlEscape($value);
            }
            $this->form[$name] = $value;
        }
    }

    /**
     * Session-specific information settings
     */
    public function setusersession()
    {

        $this->session['U'] = $this->form['u'];
        $this->session['I'] = $this->form['i'];
        $this->session['C'] = $this->form['c'];
        $this->session['MSGDISP'] = (isset($this->form['d']) && $this->form['d'] != -1) ? $this->form['d'] : $this->config['MSGDISP'];
        $this->session['TOPPOSTID'] = $this->form['p'];
        # Get settings information cookies
        if ($this->config['COOKIE']) {
            $userData = CookieService::getUserCookieFromGlobal();
            if ($userData) {
                if (!isset($this->form['u'])) {
                    $this->session['U'] = $userData['name'];
                }
                if (!isset($this->form['i'])) {
                    $this->session['I'] = $userData['email'];
                }
                if (!isset($this->form['c'])) {
                    $this->session['C'] = $userData['color'];
                }
            }
        }
        # Get cookie for the UNDO button
        if ($this->config['COOKIE'] && $this->config['ALLOW_UNDO']) {
            $undoData = CookieService::getUndoCookieFromGlobal();
            if ($undoData) {
                $this->session['UNDO_P'] = $undoData['post_id'];
                $this->session['UNDO_K'] = $undoData['key'];
            }
        }
        # Default query
        $this->session['QUERY'] = 'c='.$this->session['C'];
        if ($this->session['MSGDISP']) {
            $this->session['QUERY'] .= '&amp;d='.$this->session['MSGDISP'];
        }
        if ($this->session['TOPPOSTID']) {
            $this->session['QUERY'] .= '&amp;p='.$this->session['TOPPOSTID'];
        }
        # Default URL
        $this->session['DEFURL'] = $this->config['CGIURL'] . '?' . $this->session['QUERY'];
        # Initialize template variables
        $tmp = array_merge($this->config, $this->session);
        foreach ($tmp as $key => $val) {
            if (is_array($val)) {
                unset($tmp[$key]);
            }
        }
    }

    /**
     * Error indication
     *
     * @access  public
     * @param   String  $err_message  Error message
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

    private function processReferenceLinks($msg, $mode)
    {
        if (!$mode) {
            $msg = preg_replace(
                "/<a href=\"" . preg_quote(route('follow', ['s' => '']), '/') . "(\d+)[^>]+>([^<]+)<\/a>$/i",
                "<a href=\"" . route('follow', ['s' => '$1']) . "&amp;{$this->session['QUERY']}\">$2</a>",
                $msg,
                1
            );
            $msg = preg_replace(
                "/<a href=\"mode=follow&search=(\d+)[^>]+>([^<]+)<\/a>$/i",
                "<a href=\"" . route('follow', ['s' => '$1']) . "&amp;{$this->session['QUERY']}\">$2</a>",
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

    private function processQuotes($msg)
    {
        $msg = preg_replace("/(^|\r)(\&gt;[^\r]*)/", '$1<span class="q">$2</span>', (string) $msg);
        return str_replace("</span>\r<span class=\"q\">", "\r", $msg);
    }

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

        $followParams = array_merge(['s' => $message['POSTID']], $queryParams);
        if ($this->form['w']) {
            $followParams['w'] = $this->form['w'];
        }
        if ($mode == 1) {
            $followParams['ff'] = $tlog;
        }
        $buttons['FOLLOW_URL'] = route('follow', $followParams);

        if ($message['USER'] != $this->config['ANONY_NAME']) {
            $authorParams = [
                'm' => 's',
                's' => RegexPatterns::stripHtmlTags((string) $message['USER'])
            ];
            if ($this->form['w']) {
                $authorParams['w'] = $this->form['w'];
            }
            if ($mode == 1) {
                $authorParams['ff'] = $tlog;
            }
            $buttons['AUTHOR_URL'] = $this->config['CGIURL'] . '?' . http_build_query($authorParams) . '&' . $this->session['QUERY'];
        }

        $threadParams = array_merge(['s' => $message['THREAD']], $queryParams);
        if ($mode == 1) {
            $threadParams['ff'] = $tlog;
        }
        $buttons['THREAD_URL'] = route('thread', $threadParams);

        $treeParams = [
            'm' => 'tree',
            's' => $message['THREAD']
        ];
        if ($mode == 1) {
            $treeParams['ff'] = $tlog;
        }
        $buttons['TREE_URL'] = $this->config['CGIURL'] . '?' . http_build_query($treeParams) . '&' . $this->session['QUERY'];

        if ($this->config['ALLOW_UNDO'] && isset($this->session['UNDO_P']) && $this->session['UNDO_P'] == $message['POSTID']) {
            $buttons['UNDO_URL'] = $this->config['CGIURL'] . '?m=u&s=' . $message['POSTID'] . '&' . $this->session['QUERY'];
        }

        return $buttons;
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
     * Log reading
     *
     * Reads the log file, returns it as a line array.
     *
     * @access  public
     * @param   String  $logfilename  Log file name (optional)
     * @return  Array   Log line array
     */
    public function loadmessage($logfilename = '')
    {
        if ($logfilename) {
            preg_match("/^([\w.]*)$/", $logfilename, $matches);
            $logfilename = $this->config['OLDLOGFILEDIR'].'/'.$matches[1];
        } else {
            $logfilename = $this->config['LOGFILENAME'];
        }
        if (!file_exists($logfilename)) {
            $this->prterror(Translator::trans('error.failed_to_read'));
        }
        $logdata = file($logfilename);
        return $logdata;
    }

    /**
     * Get single message
     *
     * Converts a log line to a message array and returns it.
     *
     * @access  public
     * @param   String  $logline  Log line
     * @return  Array   Message array
     */
    public function getmessage($logline)
    {

        $logsplit = @explode(',', rtrim($logline));
        if (count($logsplit) < 10) {
            return;
        }
        $i = 5;
        while ($i <= 9) {
            $logsplit[$i] = strtr($logsplit[$i], "\0", ',');
            $logsplit[$i] = str_replace('&#44;', ',', $logsplit[$i]);
            $i++;
        }
        $message = [];
        $messagekey = ['NDATE', 'POSTID', 'PROTECT', 'THREAD', 'PHOST', 'AGENT', 'USER', 'MAIL', 'TITLE', 'MSG', 'REFID', 'RESERVED1', 'RESERVED2', 'RESERVED3', ];
        $logsplitcount = count($logsplit);
        $i = 0;
        while ($i < $logsplitcount) {
            if ($i > 12) {
                break;
            }
            $message[$messagekey[$i]] = $logsplit[$i];
            $i++;
        }
        return $message;
    }

    /**
     * Reflect user settings
     */
    public function refcustom()
    {

        $this->config['LINKOFF'] = 0;
        $this->config['HIDEFORM'] = 0;
        $this->config['RELTYPE'] = 0;
        if (!isset($this->config['SHOWIMG'])) {
            $this->config['SHOWIMG'] = 0;
        }
        $flgcolorchanged = false;

        $colors = [
            'C_BACKGROUND',
            'C_TEXT',
            'C_A_COLOR',
            'C_A_VISITED',
            'C_SUBJ',
            'C_QMSG',
            'C_A_ACTIVE',
            'C_A_HOVER',
        ];
        $flags = [
            'GZIPU',
            'RELTYPE',
            'AUTOLINK',
            'FOLLOWWIN',
            'COOKIE',
            'LINKOFF',
            'HIDEFORM',
            'SHOWIMG',
        ];
        # Update from settings string
        if (isset($this->form['c']) && $this->form['c']) {
            $strflag = '';
            $formc = $this->form['c'];
            if (strlen((string) $formc) > 5) {
                $formclen = strlen((string) $formc);
                $strflag = substr((string) $formc, 0, 2);
                $currentpos = 2;
                foreach ($colors as $confname) {
                    $colorval = StringHelper::base64ToThreeByteHex(substr((string) $formc, $currentpos, 4));
                    if (strlen($colorval) == 6 and strcasecmp((string) $this->config[$confname], $colorval) != 0) {
                        $flgcolorchanged = true;
                        $this->config[$confname] = $colorval;
                    }
                    $currentpos += 4;
                    if ($currentpos > $formclen) {
                        break;
                    }
                }
            } elseif (strlen((string) $formc) == 2) {
                $strflag = $formc;
            }
            if ($strflag) {
                $flagbin = str_pad(base_convert((string) $strflag, 32, 2), count($flags), '0', STR_PAD_LEFT);
                $currentpos = 0;
                foreach ($flags as $confname) {
                    $this->config[$confname] = substr($flagbin, $currentpos, 1);
                    $currentpos++;
                }
            }
        }
        # Update settings information
        if (isset($this->form['m']) && ($this->form['m'] == 'p' or $this->form['m'] == 'c' or $this->form['m'] == 'g')) {
            $this->config['AUTOLINK'] = !empty($this->form['a']) ? 1 : 0;
            $this->config['GZIPU'] = !empty($this->form['g']) ? 1 : 0;
            $this->config['LINKOFF'] = !empty($this->form['loff']) ? 1 : 0;
            $this->config['HIDEFORM'] = !empty($this->form['hide']) ? 1 : 0;
            $this->form['sim'] ? $this->config['SHOWIMG'] = 1 : $this->config['SHOWIMG'] = 0;
            if ($this->form['m'] == 'c') {
                $this->form['fw'] ? $this->config['FOLLOWWIN'] = 1 : $this->config['FOLLOWWIN'] = 0;
                $this->form['rt'] ? $this->config['RELTYPE'] = 1 : $this->config['RELTYPE'] = 0;
                $this->form['cookie'] ? $this->config['COOKIE'] = 1 : $this->config['COOKIE'] = 0;
            }
        }
        # Special conditions
        if ($this->config['BBSMODE_ADMINONLY'] != 0) {
            ($this->form['m'] == 'f' or ($this->form['m'] == 'p' and $this->form['write'])) ? $this->config['HIDEFORM'] = 0 : $this->config['HIDEFORM'] = 1;
        }
        # Update the settings string
        {
            $flagbin = '';
            foreach ($flags as $confname) {
                $this->config[$confname] ? $flagbin .= '1' : $flagbin .= '0';
            }
            $flagvalue = str_pad(base_convert($flagbin, 2, 32), 2, '0', STR_PAD_LEFT);

            if ($flgcolorchanged) {
                $this->form['c'] = $flagvalue . substr((string) $this->form['c'], 2);
            } else {
                $this->form['c'] = $flagvalue;
            }
        }
    }

    /**
     * Render Twig template
     */
    public function renderTwig($template, $data = [])
    {
        return View::getInstance()->render($template, $data);
    }

}
