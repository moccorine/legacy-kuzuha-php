<?php

namespace Kuzuha;

use App\Config;
use App\Utils\DateHelper;
use App\Utils\NetworkHelper;
use App\Utils\StringHelper;
use App\Utils\FileHelper;
use App\Utils\TripHelper;

class Webapp
{
    public $c; /* Settings information */
    public $f; /* Form input */
    public $s = []; /* Session-specific information such as the user's host */
    public $t; /* HTML template object */

    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $this->c = \App\Config::getInstance()->all();
        $this->t = new \patTemplate();
        $this->t->readTemplatesFromFile($this->c['TEMPLATE']);
    }

    /**
     * Destructor
     */
    public function destroy()
    {
    }

    /*20210625 Neko/2chtrip http://www.mits-jp.com/2ch/ */

    public function procForm()
    {
        if (!$this->c['BBSMODE_IMAGE'] and $_SERVER['CONTENT_LENGTH'] > $this->c['MAXMSGSIZE'] * 5) {
            $this->prterror(\App\Translator::trans('error.post_too_large'));
        }
        if ($this->c['BBSHOST'] and $_SERVER['HTTP_HOST'] != $this->c['BBSHOST']) {
            $this->prterror(\App\Translator::trans('error.invalid_caller'));
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
                    $value[$valuekey] = \App\Utils\StringHelper::htmlEscape($value[$valuekey]);
                }
            } else {
                $value = \App\Utils\StringHelper::htmlEscape($value);
            }
            $this->f[$name] = $value;
        }
    }

    /**
     * Session-specific information settings
     */
    public function setusersession()
    {

        $this->s['U'] = $this->f['u'];
        $this->s['I'] = $this->f['i'];
        $this->s['C'] = $this->f['c'];
        $this->s['MSGDISP'] = ($this->f['d'] == -1) ? $this->c['MSGDISP'] : $this->f['d'];
        $this->s['TOPPOSTID'] = $this->f['p'];
        # Get settings information cookies
        if ($this->c['COOKIE'] and $_COOKIE['c']
            and preg_match("/u=([^&]*)&i=([^&]*)&c=([^&]*)/", (string) $_COOKIE['c'], $matches)) {
            if (!isset($this->f['u'])) {
                $this->s['U'] = urldecode($matches[1]);
            }
            if (!isset($this->f['i'])) {
                $this->s['I'] = urldecode($matches[2]);
            }
            if (!isset($this->f['c'])) {
                $this->s['C'] = $matches[3];
            }
        }
        # Get cookie for the UNDO button
        if ($this->c['COOKIE'] and $this->c['ALLOW_UNDO'] and $_COOKIE['undo']
            and preg_match("/p=([^&]*)&k=([^&]*)/", (string) $_COOKIE['undo'], $matches)) {
            $this->s['UNDO_P'] = $matches[1];
            $this->s['UNDO_K'] = $matches[2];
        }
        # Default query
        $this->s['QUERY'] = "c=".$this->s['C'];
        if ($this->s['MSGDISP']) {
            $this->s['QUERY'] .= "&amp;d=".$this->s['MSGDISP'];
        }
        if ($this->s['TOPPOSTID']) {
            $this->s['QUERY'] .= "&amp;p=".$this->s['TOPPOSTID'];
        }
        # Default URL
        $this->s['DEFURL'] = $this->c['CGIURL'] . '?' . $this->s['QUERY'];
        # Initialize template variables
        $tmp = array_merge($this->c, $this->s);
        foreach ($tmp as $key => $val) {
            if (is_array($val)) {
                unset($tmp[$key]);
            }
        }
        $this->t->addGlobalVars($tmp);
    }

    /**
     * Error indication
     *
     * @access  public
     * @param   String  $err_message  Error message
     */
    public function prterror($err_message)
    {
        $this->sethttpheader();
        print $this->prthtmlhead($this->c['BBSTITLE'] . ' Error');
        $this->t->addVar('error', 'ERR_MESSAGE', $err_message);
        if (isset($this->s['DEFURL'])) {
            $this->t->setAttribute('backnavi', 'visibility', 'visible');
        }
        $this->t->displayParsedTemplate('error');
        print $this->prthtmlfoot();
        $this->destroy();
        exit();
    }

    /**
     * Display HTML header section
     *
     * @access  public
     * @param   String  $title        HTML title
     * @param   String  $customhead   Custom header in the head tag
     * @param   String  $customstyle  Custom style sheets in the style tag
     * @return  String  HTML data
     */
    public function prthtmlhead($title = "", $customhead = "", $customstyle = "")
    {
        $this->t->clearTemplate('header');
        $this->t->addVars('header', [
            'TITLE' => $title,
            'CUSTOMHEAD' => $customhead,
            'CUSTOMSTYLE' => $customstyle,
        ]);
        $htmlstr = $this->t->getParsedTemplate('header');
        return $htmlstr;
    }

    /**
     * Display HTML footer section
     *
     * @access  public
     * @return  String  HTML data
     */
    public function prthtmlfoot()
    {
        if ($this->c['SHOW_PRCTIME'] and $this->s['START_TIME']) {
            $duration = \App\Utils\DateHelper::microtimeDiff($this->s['START_TIME'], microtime());
            $duration = sprintf("%0.6f", $duration);
            $this->t->setAttribute('duration', 'visibility', 'visible');
            $this->t->addVar('duration', 'DURATION', $duration);
        }
        $htmlstr = $this->t->getParsedTemplate('footer');
        return $htmlstr;
    }

    /**
     * Copyright notice
     */
    public function prtcopyright()
    {
        $copyright = $this->t->getParsedTemplate('copyright');
        return $copyright;
    }

    /**
     * Redirector output with META tags
     *
     * @access  public
     * @param   String  $redirecturl    URL to redirect
     */
    public function prtredirect($redirecturl)
    {
        $this->sethttpheader();
        print $this->prthtmlhead(
            $this->c['BBSTITLE'] . ' - URL redirection',
            "<meta http-equiv=\"refresh\" content=\"1;url={$redirecturl}\">\n"
        );
        $this->t->addVar('redirect', 'REDIRECTURL', $redirecturl);
        $this->t->displayParsedTemplate('redirect');
        print $this->prthtmlfoot();
    }

    /**
     * Display message contents definition
     */
    public function setmessage($message, $mode = 0, $tlog = '')
    {

        if (count($message) < 10) {
            return;
        }
        $message['WDATE'] = \App\Utils\DateHelper::getDateString($message['NDATE'], $this->c['DATEFORMAT']);
        #20181102 Gikoneko: Escape special characters
        $message['MSG'] = preg_replace("/{/i", "&#123;", (string) $message['MSG'], -1);
        $message['MSG'] = preg_replace("/}/i", "&#125;", $message['MSG'], -1);

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
                "/<a href=\"m=f&s=(\d+)[^>]+>([^<]+)<\/a>$/i",
                "<a href=\"{$this->c['CGIURL']}?m=f&amp;s=$1&amp;{$this->s['QUERY']}\">$2</a>",
                $message['MSG'],
                1
            );
            $message['MSG'] = preg_replace(
                "/<a href=\"mode=follow&search=(\d+)[^>]+>([^<]+)<\/a>$/i",
                "<a href=\"{$this->c['CGIURL']}?m=f&amp;s=$1&amp;{$this->s['QUERY']}\">$2</a>",
                $message['MSG'],
                1
            );
        } else {
            $message['MSG'] = preg_replace(
                "/<a href=\"m=f&s=(\d+)[^>]+>([^<]+)<\/a>$/i",
                "<a href=\"#a$1\">$2</a>",
                $message['MSG'],
                1
            );
            $message['MSG'] = preg_replace(
                "/<a href=\"mode=follow&search=(\d+)[^>]+>([^<]+)<\/a>$/i",
                "<a href=\"#a$1\">$2</a>",
                $message['MSG'],
                1
            );
        }
        if ($mode == 0 or ($mode == 1 and $this->c['OLDLOGBTN'])) {

            if (!$this->c['FOLLOWWIN']) {
                $newwin = " target=\"link\"";
            } else {
                $newwin = '';
            }
            $spacer = "&nbsp;&nbsp;&nbsp;";
            $lnk_class = "class=\"internal\"";
            # Follow-up post button
            $message['BTNFOLLOW'] = '';
            if ($this->c['BBSMODE_ADMINONLY'] != 1) {
                $message['BTNFOLLOW'] = "$spacer<a href=\"{$this->c['CGIURL']}"
                    ."?m=f&amp;s={$message['POSTID']}&amp;".$this->s['QUERY'];
                if ($this->f['w']) {
                    $message['BTNFOLLOW'] .= "&amp;w=".$this->f['w'];
                }
                if ($mode == 1) {
                    $message['BTNFOLLOW'] .= "&amp;ff=$tlog";
                }
                $message['BTNFOLLOW'] .= "\"$newwin $lnk_class title=\"Follow-up post (reply)\" >{$this->c['TXTFOLLOW']}</a>";
            }
            # Search by user button
            $message['BTNAUTHOR'] = '';
            if ($message['USER'] != $this->c['ANONY_NAME'] and $this->c['BBSMODE_ADMINONLY'] != 1) {
                $message['BTNAUTHOR'] = "$spacer<a href=\"{$this->c['CGIURL']}"
                    ."?m=s&amp;s=". urlencode(preg_replace("/<[^>]*>/", '', (string) $message['USER'])) ."&amp;".$this->s['QUERY'];
                if ($this->f['w']) {
                    $message['BTNAUTHOR'] .= "&amp;w=".$this->f['w'];
                }
                if ($mode == 1) {
                    $message['BTNAUTHOR'] .= "&amp;ff=$tlog";
                }
                $message['BTNAUTHOR'] .= "\" target=\"link\" $lnk_class title=\"Search by user\" >{$this->c['TXTAUTHOR']}</a>";
            }
            # Thread view button
            if (!$message['THREAD']) {
                $message['THREAD'] = $message['POSTID'];
            }
            $message['BTNTHREAD'] = '';
            if ($this->c['BBSMODE_ADMINONLY'] != 1) {
                $message['BTNTHREAD'] = "$spacer<a href=\"{$this->c['CGIURL']}?m=t&amp;s={$message['THREAD']}&amp;".$this->s['QUERY'];
                if ($mode == 1) {
                    $message['BTNTHREAD'] .= "&amp;ff=$tlog";
                }
                $message['BTNTHREAD'] .= "\" target=\"link\" $lnk_class title=\"Thread view\" >{$this->c['TXTTHREAD']}</a>";
            }
            # Tree view button
            $message['BTNTREE'] = '';
            if ($this->c['BBSMODE_ADMINONLY'] != 1) {
                $message['BTNTREE'] = "$spacer<a href=\"{$this->c['CGIURL']}?m=tree&amp;s={$message['THREAD']}&amp;".$this->s['QUERY'];
                if ($mode == 1) {
                    $message['BTNTREE'] .= "&amp;ff=$tlog";
                }
                $message['BTNTREE'] .= "\" target=\"link\" $lnk_class title=\"Tree view\" >{$this->c['TXTTREE']}</a>";
            }
            # UNDO button
            $message['BTNUNDO'] = '';
            if ($this->c['ALLOW_UNDO'] and isset($this->s['UNDO_P']) and $this->s['UNDO_P'] == $message['POSTID']) {
                $message['BTNUNDO'] = "$spacer<a href=\"{$this->c['CGIURL']}?m=u&amp;s={$message['POSTID']}&amp;".$this->s['QUERY'];
                $message['BTNUNDO'] .= "\" $lnk_class title=\"Delete post\" >{$this->c['TXTUNDO']}</a>";
            }
            # Button integration
            $message['BTN'] = $message['BTNFOLLOW']. $message['BTNAUTHOR']. $message['BTNTHREAD']. $message['BTNTREE']. $message['BTNUNDO'];
        }
        # Email address
        if ($message['MAIL']) {
            $message['USER'] = "<a href=\"mailto:{$message['MAIL']}\">{$message['USER']}</a>";
        }
        # Change quote color
        $message['MSG'] = preg_replace("/(^|\r)(\&gt;[^\r]*)/", "$1<span class=\"q\">$2</span>", (string) $message['MSG']);
        $message['MSG'] = str_replace("</span>\r<span class=\"q\">", "\r", $message['MSG']);
        # Environment variables
        $message['ENVADDR'] = '';
        $message['ENVUA'] = '';
        $message['ENVBR'] = '';
        if ($this->c['IPPRINT'] or $this->c['UAPRINT']) {
            if ($this->c['IPPRINT']) {
                $message['ENVADDR'] = $message['PHOST'];
            }
            if ($this->c['UAPRINT']) {
                $message['ENVUA'] = $message['AGENT'];
            }
            if ($this->c['IPPRINT'] and $this->c['UAPRINT']) {
                $message['ENVBR'] = '<br>';
            }
            if ($message['ENVADDR'] or $message['ENVUA']) {
                $this->t->clearTemplate('envlist');
                $this->t->setAttribute("envlist", "visibility", "visible");
                $this->t->addVars('envlist', [
                    'ENVADDR' => $message['ENVADDR'],
                    'ENVUA' => $message['ENVUA'],
                    'ENVBR' => $message['ENVBR'],
                ]);
            }
        }
        # Whether or not to display images on the image BBS
        if (!$this->c['SHOWIMG']) {
            $message['MSG'] = \App\Utils\StringHelper::convertImageTag($message['MSG']);
        }
        # Convert img tags even if there is no image file
        elseif (preg_match("/<a href=[^>]+><img [^>]*?src=\"([^\"]+)\"[^>]+><\/a>/i", (string) $message['MSG'], $matches)) {
            if (!file_exists($matches[1])) {
                $message['MSG'] = \App\Utils\StringHelper::convertImageTag($message['MSG']);
            }
        }
        # Message display content definition
        $this->t->clearTemplate('message');
        $this->t->addVars('message', $message);
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
    public function prtmessage($message, $mode = 0, $tlog = '')
    {
        $this->setmessage($message, $mode, $tlog);
        $prtmessage = $this->t->getParsedTemplate('message');
        return $prtmessage;
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
    public function loadmessage($logfilename = "")
    {
        if ($logfilename) {
            preg_match("/^([\w.]*)$/", $logfilename, $matches);
            $logfilename = $this->c['OLDLOGFILEDIR']."/".$matches[1];
        } else {
            $logfilename = $this->c['LOGFILENAME'];
        }
        if (!file_exists($logfilename)) {
            $this->prterror(\App\Translator::trans('error.failed_to_read'));
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
            $logsplit[$i] = strtr($logsplit[$i], "\0", ",");
            $logsplit[$i] = str_replace("&#44;", ",", $logsplit[$i]);
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

        $this->c['LINKOFF'] = 0;
        $this->c['HIDEFORM'] = 0;
        $this->c['RELTYPE'] = 0;
        if (!isset($this->c['SHOWIMG'])) {
            $this->c['SHOWIMG'] = 0;
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
        if ($this->f['c']) {
            $strflag = '';
            $formc = $this->f['c'];
            if (strlen((string) $formc) > 5) {
                $formclen = strlen((string) $formc);
                $strflag = substr((string) $formc, 0, 2);
                $currentpos = 2;
                foreach ($colors as $confname) {
                    $colorval = \App\Utils\StringHelper::base64ToThreeByteHex(substr((string) $formc, $currentpos, 4));
                    if (strlen($colorval) == 6 and strcasecmp((string) $this->c[$confname], $colorval) != 0) {
                        $flgcolorchanged = true;
                        $this->c[$confname] = $colorval;
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
                $flagbin = str_pad(base_convert((string) $strflag, 32, 2), count($flags), "0", STR_PAD_LEFT);
                $currentpos = 0;
                foreach ($flags as $confname) {
                    $this->c[$confname] = substr($flagbin, $currentpos, 1);
                    $currentpos++;
                }
            }
        }
        # Update settings information
        if ($this->f['m'] == 'p' or $this->f['m'] == 'c' or $this->f['m'] == 'g') {
            $this->f['a'] ? $this->c['AUTOLINK'] = 1 : $this->c['AUTOLINK'] = 0;
            $this->f['g'] ? $this->c['GZIPU'] = 1 : $this->c['GZIPU'] = 0;
            $this->f['loff'] ? $this->c['LINKOFF'] = 1 : $this->c['LINKOFF'] = 0;
            $this->f['hide'] ? $this->c['HIDEFORM'] = 1 : $this->c['HIDEFORM'] = 0;
            $this->f['sim'] ? $this->c['SHOWIMG'] = 1 : $this->c['SHOWIMG'] = 0;
            if ($this->f['m'] == 'c') {
                $this->f['fw'] ? $this->c['FOLLOWWIN'] = 1 : $this->c['FOLLOWWIN'] = 0;
                $this->f['rt'] ? $this->c['RELTYPE'] = 1 : $this->c['RELTYPE'] = 0;
                $this->f['cookie'] ? $this->c['COOKIE'] = 1 : $this->c['COOKIE'] = 0;
            }
        }
        # Special conditions
        if ($this->c['BBSMODE_ADMINONLY'] != 0) {
            ($this->f['m'] == 'f' or ($this->f['m'] == 'p' and $this->f['write'])) ? $this->c['HIDEFORM'] = 0 : $this->c['HIDEFORM'] = 1;
        }
        # Update the settings string
        {
            $flagbin = '';
            foreach ($flags as $confname) {
                $this->c[$confname] ? $flagbin .= '1' : $flagbin .= '0';
            }
            $flagvalue = str_pad(base_convert($flagbin, 2, 32), 2, "0", STR_PAD_LEFT);

            if ($flgcolorchanged) {
                $this->f['c'] = $flagvalue . substr((string) $this->f['c'], 2);
            } else {
                $this->f['c'] = $flagvalue;
            }
        }
    }

    /**
     * HTTP header settings
     */
    public function sethttpheader()
    {
        header('Content-Type: text/html; charset=UTF-8');
        header("X-XSS-Protection: 1; mode=block");
        // header('X-FRAME-OPTIONS:DENY');
        // Remove X-Frame-Options (not needed when using CSP)
        header_remove("X-Frame-Options");
        // Allow embedding from anywhere
        header("Content-Security-Policy: frame-ancestors *;");

    }

    /**
     * Start execution time measurement
     */
    public function setstarttime()
    {
        $this->s['START_TIME'] = microtime();
    }

}
