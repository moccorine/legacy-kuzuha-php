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

        if (count($message) < 10) {
            return $message;
        }
        $message['WDATE'] = DateHelper::getDateString($message['NDATE'], $this->config['DATEFORMAT']);
        $message['MSG'] = TextEscape::escapeTwigChars((string) $message['MSG']);

        #20241016 Heyuri: Deprecated by ytthumb.js, embedding each video in browser slows stuff down a lot
        ##20200524 Gikoneko: youtube embedding
        #$message['MSG'] = preg_replace("/<a href=\"https:\/\/youtu.be\/([^\"]+?)\" target=\"link\">([^<]+?)<\/a>/",
        #"<iframe width=\"560\" height=\"315\" src=\"https://www.youtube.com/embed/$1\" frameborder=\"0\" allow=\"autoplay; encrypted-media\" allowfullscreen></iframe>\r<a href=\"https://youtu.be/$1\">$2</a>", $message['MSG']);
        ##20200524 Gikoneko: youtube embedding 2
        #$message['MSG'] = preg_replace("/<a href=\"https:\/\/www.youtube.com\/watch\?v=([^\"]+?)\" target=\"link\">([^<]+?)<\/a>/",
        #"<iframe width=\"560\" height=\"315\" src=\"https://www.youtube.com/embed/$1\" frameborder=\"0\" allow=\"autoplay; encrypted-media\" allowfullscreen></iframe>\r<a href=\"https://www.youtube.com/watch?v=$1\">$2</a>", $message['MSG']);
        ##20200524 Gikoneko: youtube embedding 3
        #$message['MSG'] = preg_replace("/<a href=\"https:\/\/m.youtube.com\/watch\?v=([^\"]+?)\" target=\"link\">([^<]+?)<\/a>/",
        #"<iframe width=\"560\" height=\"315\" src=\"https://www.youtube.com/embed/$1\" frameborder=\"0\" allow=\"autoplay; encrypted-media\" allowfullscreen></iframe>\r<a href=\"https://m.youtube.com/watch?v=$1\">$2</a>", $message['MSG']);

        # "Reference"
        if (!$mode) {
            $message['MSG'] = preg_replace(
                "/<a href=\"" . preg_quote(route('follow', ['s' => '']), '/') . "(\d+)[^>]+>([^<]+)<\/a>$/i",
                "<a href=\"" . route('follow', ['s' => '$1']) . "&amp;{$this->session['QUERY']}\">$2</a>",
                $message['MSG'],
                1
            );
            $message['MSG'] = preg_replace(
                "/<a href=\"mode=follow&search=(\d+)[^>]+>([^<]+)<\/a>$/i",
                "<a href=\"" . route('follow', ['s' => '$1']) . "&amp;{$this->session['QUERY']}\">$2</a>",
                $message['MSG'],
                1
            );
        } else {
            $message['MSG'] = preg_replace(
                "/<a href=\"" . preg_quote(route('follow', ['s' => '']), '/') . "(\d+)[^>]+>([^<]+)<\/a>$/i",
                '<a href="#a$1">$2</a>',
                $message['MSG'],
                1
            );
            $message['MSG'] = preg_replace(
                "/<a href=\"mode=follow&search=(\d+)[^>]+>([^<]+)<\/a>$/i",
                '<a href="#a$1">$2</a>',
                $message['MSG'],
                1
            );
        }
        if ($mode == 0 or ($mode == 1 and $this->config['OLDLOGBTN'])) {

            if (!$this->config['FOLLOWWIN']) {
                $newwin = ' target="link"';
            } else {
                $newwin = '';
            }
            $spacer = '&nbsp;&nbsp;&nbsp;';
            $lnk_class = 'class="internal"';
            # Follow-up post button
            $message['BTNFOLLOW'] = '';
            if ($this->config['BBSMODE_ADMINONLY'] != 1) {
                $followParams = ['s' => $message['POSTID']];
                parse_str($this->session['QUERY'], $queryParams);
                $followParams = array_merge($followParams, $queryParams);
                if ($this->form['w']) {
                    $followParams['w'] = $this->form['w'];
                }
                if ($mode == 1) {
                    $followParams['ff'] = $tlog;
                }
                $message['BTNFOLLOW'] = "$spacer<a href=\"" . route('follow', $followParams) . "\"$newwin $lnk_class title=\"Follow-up post (reply)\" >{$this->config['TXTFOLLOW']}</a>";
            }
            # Search by user button
            $message['BTNAUTHOR'] = '';
            if ($message['USER'] != $this->config['ANONY_NAME'] and $this->config['BBSMODE_ADMINONLY'] != 1) {
                $message['BTNAUTHOR'] = "$spacer<a href=\"{$this->config['CGIURL']}"
                    .'?m=s&amp;s='. urlencode(RegexPatterns::stripHtmlTags((string) $message['USER'])) .'&amp;'.$this->session['QUERY'];
                if ($this->form['w']) {
                    $message['BTNAUTHOR'] .= '&amp;w='.$this->form['w'];
                }
                if ($mode == 1) {
                    $message['BTNAUTHOR'] .= "&amp;ff=$tlog";
                }
                $message['BTNAUTHOR'] .= "\" target=\"link\" $lnk_class title=\"Search by user\" >{$this->config['TXTAUTHOR']}</a>";
            }
            # Thread view button
            if (!$message['THREAD']) {
                $message['THREAD'] = $message['POSTID'];
            }
            $message['BTNTHREAD'] = '';
            if ($this->config['BBSMODE_ADMINONLY'] != 1) {
                $threadParams = ['s' => $message['THREAD']];
                parse_str($this->session['QUERY'], $queryParams);
                $threadParams = array_merge($threadParams, $queryParams);
                if ($mode == 1) {
                    $threadParams['ff'] = $tlog;
                }
                $message['BTNTHREAD'] = "$spacer<a href=\"" . route('thread', $threadParams) . "\" target=\"link\" $lnk_class title=\"Thread view\" >{$this->config['TXTTHREAD']}</a>";
            }
            # Tree view button
            $message['BTNTREE'] = '';
            if ($this->config['BBSMODE_ADMINONLY'] != 1) {
                $message['BTNTREE'] = "$spacer<a href=\"{$this->config['CGIURL']}?m=tree&amp;s={$message['THREAD']}&amp;".$this->session['QUERY'];
                if ($mode == 1) {
                    $message['BTNTREE'] .= "&amp;ff=$tlog";
                }
                $message['BTNTREE'] .= "\" target=\"link\" $lnk_class title=\"Tree view\" >{$this->config['TXTTREE']}</a>";
            }
            # UNDO button
            $message['BTNUNDO'] = '';
            if ($this->config['ALLOW_UNDO'] and isset($this->session['UNDO_P']) and $this->session['UNDO_P'] == $message['POSTID']) {
                $message['BTNUNDO'] = "$spacer<a href=\"{$this->config['CGIURL']}?m=u&amp;s={$message['POSTID']}&amp;".$this->session['QUERY'];
                $message['BTNUNDO'] .= "\" $lnk_class title=\"Delete post\" >{$this->config['TXTUNDO']}</a>";
            }
            # Button integration
            $message['BTN'] = $message['BTNFOLLOW']. $message['BTNAUTHOR']. $message['BTNTHREAD']. $message['BTNTREE']. $message['BTNUNDO'];
        }
        # Email address
        if ($message['MAIL']) {
            $message['USER'] = "<a href=\"mailto:{$message['MAIL']}\">{$message['USER']}</a>";
        }
        # Change quote color
        $message['MSG'] = preg_replace("/(^|\r)(\&gt;[^\r]*)/", '$1<span class="q">$2</span>', (string) $message['MSG']);
        $message['MSG'] = str_replace("</span>\r<span class=\"q\">", "\r", $message['MSG']);
        # Environment variables
        $message['ENVADDR'] = '';
        $message['ENVUA'] = '';
        $message['ENVBR'] = '';
        if ($this->config['IPPRINT'] or $this->config['UAPRINT']) {
            if ($this->config['IPPRINT']) {
                $message['ENVADDR'] = $message['PHOST'];
            }
            if ($this->config['UAPRINT']) {
                $message['ENVUA'] = $message['AGENT'];
            }
            if ($this->config['IPPRINT'] and $this->config['UAPRINT']) {
                $message['ENVBR'] = '<br>';
            }
        }
        # Whether or not to display images on the image BBS
        if (!$this->config['SHOWIMG']) {
            $message['MSG'] = StringHelper::convertImageTag($message['MSG']);
        }
        # Convert img tags even if there is no image file
        elseif (preg_match("/<a href=[^>]+><img [^>]*?src=\"([^\"]+)\"[^>]+><\/a>/i", (string) $message['MSG'], $matches)) {
            if (!file_exists($matches[1])) {
                $message['MSG'] = StringHelper::convertImageTag($message['MSG']);
            }
        }
        # Message display content definition
        
        return $message;
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
        
        $showEnv = !empty($message['ENVADDR']) || !empty($message['ENVUA']);
        
        return $this->renderTwig('components/message.twig', array_merge($message, [
            'SHOW_ENV' => $showEnv,
            'TRANS_USER' => Translator::trans('message.user'),
            'TRANS_POST_DATE' => Translator::trans('message.post_date'),
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
