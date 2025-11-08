<?php

namespace Kuzuha;

use App\Config;
use App\Translator;
use App\Utils\DateHelper;
use App\Utils\NetworkHelper;
use App\Utils\StringHelper;
use App\Utils\SecurityHelper;
use App\Utils\FileHelper;
use App\Utils\TripHelper;


/*

KuzuhaScriptPHP ver0.0.7alpha (13:04 2003/02/18)
Message log viewer module

* Todo

*/

if (!defined("INCLUDED_FROM_BBS")) {
    header("Location: ../bbs.php?m=g");
    exit();
}


/*
 * Module-specific settings
 *
 * They will be added to/overwritten by $CONF.
 */
$GLOBALS['CONF_GETLOG'] = [

    # Whether or not multiple logs can be searched
    'MULTIPLESEARCH' => 1,

    # Search term highlight color
    'C_QUERY' => 'FF8000',

    # Maximum number of keywords that can be searched
    'MAXKEYWORDS' => 10,

];


/**
 * Message log viewer module
 *
 *
 *
 * @package strangeworld.cnscript
 * @access  public
 */
class Getlog extends Webapp
{
    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $config = Config::getInstance();
        foreach ($GLOBALS['CONF_GETLOG'] as $key => $value) {
            $config->set($key, $value);
        }
        parent::__construct();
        $this->template->readTemplatesFromFile($this->config['TEMPLATE_LOG']);
    }


    /**
     * Main process
     */
    public function main()
    {

        # Start measuring execution time
        $this->setstarttime();

        # Form acquisition preprocessing
        $this->procForm();

        # Reflect personal settings
        $this->refcustom();
        $this->setusersession();

        # gzip compressed transfer
        if ($this->config['GZIPU']) {
            ob_start("ob_gzhandler");
        }

        # Search process
        if (@$this->form['f']) {
            $this->prtsearchresult();
        }
        # Download
        elseif (@$this->form['dl']) {
            $result = $this->prthtmldownload($this->form['dl']);
            if ($result) {
                $this->prtloglist();
            }
        }
        # Topic list
        elseif (@$this->form['l']) {
            $result = $this->prttopiclist($this->form['l']);
            if ($result) {
                $this->prtloglist();
            }
        }
        # ZIP archives
        elseif (@$this->form['gm'] == 'z' and @$this->config['ZIPDIR']) {
            $this->prtarchivelist();
        }
        # Search page
        else {
            $this->prtloglist();
        }

        if ($this->config['GZIPU']) {
            ob_end_flush();
        }
    }





    /**
     * Display search page
     *
     */
    public function prtloglist()
    {

        $dir = $this->config['OLDLOGFILEDIR'];

        if ($this->config['OLDLOGFMT']) {
            $oldlogext = 'dat';
        } else {
            $oldlogext = 'html';
        }

        $files = [];

        $dh = opendir($dir);
        if (!$dh) {
            $this->prterror('This directory could not be opened.');
        }
        while ($entry = readdir($dh)) {
            if (is_file($dir . $entry) and preg_match("/^\d+\.$oldlogext$/", $entry)) {
                $files[] = $entry;
            }
        }
        closedir($dh);

        # Sort by natural file name order
        natsort($files);

        # Check for files with the latest update time as standard
        $maxftime = 0;
        foreach ($files as $filename) {
            $fstat = stat($dir . $filename);
            if ($fstat[9] > $maxftime) {
                $maxftime = $fstat[9];
                $checkedfile = $filename;
            }
        }

        if ($this->config['ZIPDIR'] and function_exists("gzcompress")) {
            $this->template->setAttribute("ziplink", "visibility", "visible");
        }

        if (!$this->config['OLDLOGFMT']) {
            $this->template->setAttribute("topiclink", "visibility", "hidden");
        }
        if (!$this->dlchk()) {
            $this->template->setAttribute("dllink", "visibility", "hidden");
        }

        foreach ($files as $filename) {
            $fstat = stat($dir . $filename);
            $fsize = $fstat[7];
            $ftime = date("Y/m/d H:i:s", $fstat[9]);
            $ftitle = '';
            $matches = [];
            if (preg_match("/^(\d\d\d\d)(\d\d)(\d\d)\.$oldlogext/", $filename, $matches)) {
                $ftitle = "{$matches[1]}/{$matches[2]}/{$matches[3]}";
            } elseif (preg_match("/^(\d\d\d\d)(\d\d)\.$oldlogext/", $filename, $matches)) {
                $ftitle = "{$matches[1]}/{$matches[2]}";
            } else {
                $ftitle = $filename;
            }

            $checked = '';
            if ($filename == $checkedfile) {
                $checked = ' checked="checked"';
            }
            $checkbox = '';
            if (@$this->config['MULTIPLESEARCH']) {
                $checkbox = "<input type=\"checkbox\" name=\"f[]\" value=\"$filename\"$checked />";
            } else {
                $checkbox = "<input type=\"radio\" name=\"f\" value=\"$filename\"$checked />";
            }

            $this->template->clearTemplate('topiclink');
            $this->template->clearTemplate('dllink');
            $this->template->addVar('topiclink', 'FILENAME', $filename);
            $this->template->addVar('dllink', 'FILENAME', $filename);
            $this->template->addVars('filelist', [
                'FCHECK' => $checkbox,
                'FILENAME' => $filename,
                'FTITLE' => $ftitle,
                'FTIME' => $ftime,
                'FSIZE' => $fsize,
            ]);
            $this->template->parseTemplate('filelist', 'a');
        }

        $this->template->addVar('dateform', 'OLDLOGSAVESW', $this->config['OLDLOGSAVESW']);
        if ($this->config['BBSMODE_IMAGE'] == 1) {
            if ($this->config['SHOWIMG']) {
                $this->template->addVar('sicheck', 'CHK_SI', ' checked="checked"');
            }
            $this->template->setAttribute('sicheck', 'visibility', 'visible');
        }
        if (!$this->config['OLDLOGFMT'] or !$this->config['OLDLOGBTN']) {
            $this->template->setAttribute("check_bt", "visibility", "hidden");
        }
        if ($this->config['GZIPU']) {
            $this->template->addVar('loglist', 'CHK_G', ' checked="checked"');
        }

        # Output
        $this->sethttpheader();
        print $this->prthtmlhead($this->config['BBSTITLE'] . ' Message log search');
        $this->template->displayParsedTemplate('loglist');
        print $this->prthtmlfoot();

    }







    /**
     * Get search conditions
     */
    public function getconditions($filename)
    {
        $conditions = [];

        $conditions['showall'] = true;
        if (@$this->form['q']) {
            $conditions['showall'] = false;
        }

        foreach (['q', 't', 'b', 'ci',] as $formvalue) {
            $conditions[$formvalue] = @$this->form[$formvalue];
        }
        foreach (['sd', 'sh', 'si', 'ed', 'eh', 'ei',] as $formvalue) {
            if ($conditions['showall'] and @$this->form[$formvalue]) {
                $conditions['showall'] = false;
            }
            $conditions[$formvalue] = str_pad((string) @$this->form[$formvalue], 2, "0", STR_PAD_LEFT);
        }

        if ($conditions['q']) {
            $conditions['q'] = trim((string) $conditions['q']);
            $conditions['keywords'] = preg_split("/\s+/", $conditions['q']);
            if (count($conditions['keywords']) > $this->config['MAXKEYWORDS']) {
                $this->prterror('There are too many search keywords.');
            }
        }

        $conditions['savesw'] = $this->config['OLDLOGSAVESW'];

        return $conditions;
    }







    /**
     * Display message log search results
     *
     */
    public function prtsearchresult()
    {

        $formf = [];
        if (is_array($this->form['f'])) {
            $formf = $this->form['f'];
        } else {
            $formf[] = $this->form['f'];
        }
        if (!@$this->config['MULTIPLESEARCH'] and count($formf) > 1) {
            array_splice($formf, 1);
        }
        $files = [];
        foreach ($formf as $filename) {
            if (preg_match("/^\d+\./", (string) $filename) and is_file($this->config['OLDLOGFILEDIR'] . $filename)) {
                $files[] = $filename;
            }
        }

        $this->sethttpheader();
        $customstyle = "  .sq { color: #{$this->config['C_QUERY']}; }\n";
        print $this->prthtmlhead($this->config['BBSTITLE'] . ' Message log search results', '', $customstyle);
        $this->template->displayParsedTemplate('searchresult');

        foreach ($files as $filename) {
            $conditions = $this->getconditions($filename);
            $resultcode = $this->prtoldlog($filename, $conditions, false);
        }

        print $this->prthtmlfoot();

    }







    /**
     * Download message log HTML files
     *
     */
    public function prthtmldownload($filename)
    {

        if ($this->config['OLDLOGFMT']) {
            $oldlogext = 'dat';
        } else {
            $oldlogext = 'html';
        }

        # Illegal file name
        if (!preg_match("/^\d+\.$oldlogext$/", (string) $filename)) {
            return 1;
        } elseif (!is_file($this->config['OLDLOGFILEDIR'] . $filename)) {
            return 1;
        }

        $dlfilename = str_replace(".dat", ".html", $filename);

        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=".$dlfilename);

        if ($this->config['OLDLOGFMT']) {
            $this->sethttpheader();
            print $this->prthtmlhead($this->config['BBSTITLE'] . ' Message log');
            $this->template->displayParsedTemplate('htmldownload');
        }

        $conditions = $this->getconditions($filename);
        $resultcode = $this->prtoldlog($filename, $conditions, true);

        if ($this->config['OLDLOGFMT']) {
            print $this->prthtmlfoot();
        }

    }







    /**
     * Search all files
     *
     */
    public function prtoldlog($filename, $conditions = "", $isdownload = false)
    {

        $dir = $this->config['OLDLOGFILEDIR'];

        if ($this->config['OLDLOGFMT']) {
            $oldlogext = 'dat';
        } else {
            $oldlogext = 'html';
        }

        # Illegal file name
        if (!preg_match("/^\d+\.$oldlogext$/", (string) $filename)) {
            return 1;
        } elseif (!is_file($dir . $filename)) {
            return 1;
        }

        $this->template->clearTemplate('oldlog_upper');
        $this->template->clearTemplate('oldlog_lower');
        $this->template->addVar('oldlog_upper', 'FILENAME', $filename);

        $fh = @fopen($dir . $filename, "rb");
        if (!$fh) {
            $this->template->addVar('oldlog_upper', 'success', 'false');
            $this->template->displayParsedTemplate('oldlog_upper');
            return 2;
        }
        flock($fh, 1);

        $timerangestr = '';
        if (!(!$this->config['OLDLOGFMT'] and !$conditions)) {
            if (!@$conditions['showall']) {
                if (@$conditions['savesw']) {
                    if ($conditions['sd'] > 1 or $conditions['sh'] > 0 or $conditions['ed'] < 31 or $conditions['eh'] < 24) {
                        $timerangestr .= "Day {$conditions['sd']} Hour {$conditions['sd']} - Day {$conditions['ed']} Hour {$conditions['ed']}　";
                    }
                } else {
                    if ($conditions['sh'] > 0 or $conditions['si'] > 0 or $conditions['eh'] < 24 or $conditions['ei'] > 0) {
                        $timerangestr .= "Hour {$conditions['sh']} Minute {$conditions['si']} - Hour {$conditions['eh']} Minute {$conditions['ei']}　";
                    }
                }
            }
            $this->template->addVar('oldlog_upper', 'TIMERANGE', $timerangestr);
            $this->template->displayParsedTemplate('oldlog_upper');
        }


        $msgmode = 2;
        if (@$this->form['bt']) {
            $msgmode = 1;
        }
        $resultcount = 0;

        # dat search
        if ($this->config['OLDLOGFMT']) {
            if (!@$conditions['showall']) {
                $result = 0;
                while (($logline = FileHelper::getLine($fh)) !== false) {
                    $message = $this->getmessage($logline);
                    $result = $this->msgsearch($message, $conditions);
                    # Search hit
                    if ($result == 1) {
                        $prtmessage = $this->prtmessage($message, $msgmode, $filename);
                        # Highlight search keywords
                        if ($conditions['q']) {
                            $needle = "\Q{$conditions['q']}\E";
                            $quoteq = preg_quote((string) $conditions['q'], "/");
                            if ($conditions['ci']) {
                                #$prtmessage = preg_replace("/($quoteq)/i", "<span class=\"sq\">$1</span>", $prtmessage);
                                #while (preg_match("/(<[^<>]*)<span class=\"sq\">$quoteq<\/span>/i", $prtmessage)) {
                                #  $prtmessage = preg_replace("/(<[^<>]*)<span class=\"sq\">$quoteq<\/span>/i", "$1", $prtmessage, 1);
                                #}
                                $prtmessage = preg_replace("/((?:\G|>)[^<]*?)($quoteq)/i", "$1<span class=\"sq\"><mark>$2</mark></span>", $prtmessage);
                            } else {
                                #$prtmessage = str_replace($conditions['q'], "<span class=\"sq\">{$conditions['q']}</span>", $prtmessage);
                                #while (preg_match("/(<[^<>]*)<span class=\"sq\">$quoteq<\/span>/", $prtmessage)) {
                                #  $prtmessage = preg_replace("/(<[^<>]*)<span class=\"sq\">$quoteq<\/span>/", "$1", $prtmessage, 1);
                                #}
                                $prtmessage = preg_replace("/((?:\G|>)[^<]*?)($quoteq)/", "$1<span class=\"sq\"><mark>$2</mark></span>", $prtmessage);
                            }
                        }
                        print $prtmessage;
                        $resultcount++;
                    }
                    # End of search
                    elseif ($result == 2) {
                        break;
                    }
                }
            }
            # Show all
            else {
                while (($logline = FileHelper::getLine($fh)) !== false) {
                    $messagestr = $this->prtmessage($this->getmessage($logline), $msgmode, $filename);
                    print $messagestr;
                }
            }
        }
        # HTML search
        else {
            if (!$conditions['showall']) {
                # Buffers file reads for each message
                $buffer = "";
                $flgbuffer = false;
                $result = 0;
                while (($htmlline = FileHelper::getLine($fh)) !== false) {
                    # Start message
                    if (!$flgbuffer and preg_match("/<div [^>]*id=\"m\d+\"[^>]*>/", $htmlline)) {
                        $buffer = $htmlline;
                        $flgbuffer = true;
                    }
                    # End message
                    elseif ($flgbuffer and str_contains($htmlline, "<!--  -->")) {
                        $buffer .= $htmlline;
                        {
                            $result = $this->msgsearchhtml($buffer, $conditions);
                            if ($result == 1) {
                                # Search keyword highlighting
                                if ($conditions['q']) {
                                    $needle = "\Q{$conditions['q']}\E";
                                    $quoteq = preg_quote((string) $conditions['q'], "/");
                                    if ($conditions['ci']) {
                                        #$buffer = preg_replace("/($quoteq)/i", "<span class=\"sq\">$1</span>", $buffer);
                                        #while (preg_match("/(<[^<>]*)<span class=\"sq\">$quoteq<\/span>/i", $buffer)) {
                                        #  $buffer = preg_replace("/(<[^<>]*)<span class=\"sq\">$quoteq<\/span>/i", "$1", $buffer, 1);
                                        #}
                                        $buffer = preg_replace("/((?:\G|>)[^<]*?)($quoteq)/i", "$1<span class=\"sq\"><mark>$2</mark></span>", $buffer);
                                    } else {
                                        #$buffer = str_replace($conditions['q'], "<span class=\"sq\">{$conditions['q']}</span>", $buffer);
                                        #while (preg_match("/(<[^<>]*)<span class=\"sq\">$quoteq<\/span>/", $buffer)) {
                                        #  $buffer = preg_replace("/(<[^<>]*)<span class=\"sq\">$quoteq<\/span>/", "$1", $buffer, 1);
                                        #}
                                        $buffer = preg_replace("/((?:\G|>)[^<]*?)($quoteq)/", "$1<span class=\"sq\"><mark>$2</mark></span>", $buffer);
                                    }
                                }
                                print $buffer;
                                $resultcount++;
                            } elseif ($result == 2) {
                                break;
                            }
                        }
                        $buffer = "";
                        $flgbuffer = false;
                    }
                    # Middle of message
                    elseif ($flgbuffer) {
                        $buffer .= $htmlline;
                    }
                    # Other than message
                    else {
                    }
                }
            } else {
                while (($htmlline = FileHelper::getLine($fh)) !== false) {
                    print $htmlline;
                }
            }
        }
        flock($fh, 3);
        fclose($fh);

        if (!(!$this->config['OLDLOGFMT'] and !$conditions)) {
            $resultmsg = '';
            if (!$conditions['showall']) {
                #$resultmsg = "{$filename}：&nbsp;{$timerangestr}&nbsp;";
                if (@$conditions['q'] != '') {
                    $value = $conditions['q'];
                    #$value_euc = JcodeConvert($value, 2, 1);
                    #$value_euc = htmlentities($value_euc, ENT_QUOTES, 'EUC-JP');
                    #$value = JcodeConvert($value_euc, 1, 2);
                    $value = htmlentities((string) $value, ENT_QUOTES);
                    $resultmsg .= 'For "' . $value . '" there were ';
                }
                if ($resultcount > 0) {
                    $resultmsg .= $resultcount . ' results found.';
                } else {
                    $resultmsg .= 'no results found.';
                }
                #print $resultmsg;
                $this->template->addVar('oldlog_lower', 'RESULTMSG', $resultmsg);
                $this->template->displayParsedTemplate('oldlog_lower');
            }
        }

    }












    /**
     * Single message search (HTML format)
     */
    public function msgsearchhtml($buffer, $conditions)
    {
        $message = [];

        $message['USER'] = '';
        $message['TITLE'] = '';
        $message['MSG'] = '';
        $message['NDATESTR'] = '';

        if (preg_match("/<span class=\"mun\">([^<]+)<\/span>/", (string) $buffer, $matches)) {
            $message['USER'] = $matches[1];
        }
        if (preg_match("/<span class=\"ms\">([^<]+)<\/span>/", (string) $buffer, $matches)) {
            $message['TITLE'] = $matches[1];
        }
        if (preg_match("/<blockquote>[\r\n\s]*<pre>(.+?)<\/pre>/ms", (string) $buffer, $matches)) {
            $message['MSG'] = $matches[1];
        }
        if (preg_match("/<span class=\"md\">[^<]*投稿日：(\d+)\/(\d+)\/(\d+)[^\d]+(\d+)時(\d+)分(\d+)秒/", (string) $buffer, $matches)) {
            if (@$conditions['savesw']) {
                $message['NDATESTR'] = $matches[3] . $matches[4];
            } else {
                $message['NDATESTR'] = $matches[4] . $matches[5];
            }
        }

        return $this->msgsearch($message, $conditions);
    }



    /**
     * Single message search (dat format)
     * Return values - 0: No hit, 1: Hit, 2: Signal end of search
     */
    public function msgsearch($message, $conditions)
    {

        if (!$message) {
            return 0;
        }

        # Monthly
        if (@$conditions['savesw']) {
            $starttime = $conditions['sd'].$conditions['sh'];
            $endtime = $conditions['ed'].$conditions['eh'];
            if (!@$message['NDATESTR']) {
                $message['NDATESTR'] = date("dH", $message['NDATE']);
            }
        }
        # Daily
        else {
            $starttime = $conditions['sh'].$conditions['si'];
            $endtime = $conditions['eh'].$conditions['ei'];
            if (!@$message['NDATESTR']) {
                $message['NDATESTR'] = date("Hi", $message['NDATE']);
            }
        }
        if ($message['NDATESTR'] < $starttime or $message['NDATESTR'] > $endtime) {
            return 2;
        }

        $hit = false;

        # Keyword search
        if (@$conditions['keywords']) {

            $haystack = '';
            if ($conditions['t'] == 'u') {
                $haystack = $message['USER'];
            } elseif ($conditions['t'] == 't') {
                $haystack = $message['TITLE'];
            } else {
                $haystack = "{$message['USER']}<>{$message['TITLE']}<>{$message['MSG']}";
            }

            # OR search
            if ($conditions['b'] == 'o') {
                $hit = false;
                foreach ($conditions['keywords'] as $needle) {
                    if ($conditions['ci']) {
                        $result = stristr((string) $haystack, (string) $needle);
                    } else {
                        $result = strpos((string) $haystack, (string) $needle);
                    }
                    if ($result !== false) {
                        $hit = true;
                        break;
                    }
                }
            }
            # AND search
            else {
                $hit = true;
                foreach ($conditions['keywords'] as $needle) {
                    if ($conditions['ci']) {
                        $result = stristr((string) $haystack, (string) $needle);
                    } else {
                        $result = strpos((string) $haystack, (string) $needle);
                    }
                    if ($result === false) {
                        $hit = false;
                        break;
                    }
                }
            }
        } else {
            $hit = true;
        }

        if ($hit) {
            return 1;
        } else {
            return 0;
        }

    }




    /**
     * Display topic list
     */
    public function prttopiclist($filename)
    {

        # Illegal file name
        if (!preg_match("/^\d+\.dat$/", (string) $filename)) {
            return 1;
        } elseif (!is_file($this->config['OLDLOGFILEDIR'] . $filename)) {
            return 1;
        }

        $fh = @fopen($this->config['OLDLOGFILEDIR'] . $filename, "rb");
        if (!$fh) {
            $this->prterror($filename . ' was unable to be opened.');
        }
        flock($fh, 1);

        $tid = [];
        $tcount = [];
        $ttitle = [];
        $ttime = [];
        $tindex = 0;
        while (($logline = FileHelper::getLine($fh)) !== false) {
            $message = $this->getmessage($logline);
            if (!$message['THREAD'] or $message['THREAD'] == $message['POSTID'] or !@$ttitle[$message['THREAD']]) {
                $tid[$tindex] = $message['POSTID'];
                $tcount[$message['POSTID']] = 0;

                $msg = ltrim((string) $message['MSG']);
                $msg = preg_replace("/<a href=[^>]+>Reference: [^<]+<\/a>/i", "", $msg, 1);
                $msg = preg_replace("/<[^>]+>/", "", (string) $msg);
                $msgsplit = explode("\r", (string) $msg);
                $msgdigest = $msgsplit[0];
                $index = 1;
                while ($index < count($msgsplit) - 1 and strlen($msgdigest . $msgsplit[$index]) < 50) {
                    $msgdigest .= $msgsplit[$index];
                    $index++;
                }
                $ttitle[$message['POSTID']] = $msgdigest;

                if (str_contains($ttitle[$message['POSTID']], "\r")) {
                    $ttitle[$message['POSTID']] = substr(
                        $ttitle[$message['POSTID']],
                        0,
                        strpos($ttitle[$message['POSTID']], "\r")
                    );
                }

                $ttime[$message['POSTID']] = $message['NDATE'];
                $tindex++;
            } else {
                $tcount[$message['THREAD']]++;
                $ttime[$message['THREAD']] = $message['NDATE'];
            }
        }
        flock($fh, 3);
        fclose($fh);

        $this->template->addVar('topiclist', 'FILENAME', $filename);

        $tidcount = count($tid);
        $i = 0;
        while ($i < $tidcount) {
            if ($tid[$i]) {
                $tc = sprintf("%02d", $tcount[$tid[$i]]);
                $tt = date("m/d H:i:s", $ttime[$tid[$i]]);
                $this->template->addVars('topic', [
                    'TID' => $tid[$i],
                    'TC' => $tc,
                    'TT' => $tt,
                    'TTITLE' => $ttitle[$tid[$i]],
                    'FILENAME' => $filename,
                ]);
                $this->template->parseTemplate('topic', 'a');
            }
            $i++;
        }

        $this->sethttpheader();
        print $this->prthtmlhead($this->config['BBSTITLE'] . ' Topic list ' . $filename);
        $this->template->displayParsedTemplate('topiclist');
        print $this->prthtmlfoot();

    }





    /**
     * Display ZIP archive list page
     *
     */
    public function prtarchivelist()
    {

        $dir = $this->config['ZIPDIR'];

        $dh = opendir($dir);
        if (!$dh) {
            $this->prterror('This directory could not be opened.');
        }
        $files = [];
        while ($entry = readdir($dh)) {
            if (is_file($dir . $entry) and preg_match("/\.(zip|lzh|rar|gz|tar\.gz)$/i", $entry)) {
                $files[] = $entry;
            }
        }
        closedir($dh);

        # Sort by natural file name order
        natsort($files);

        foreach ($files as $filename) {
            $fstat = stat($dir . $filename);
            $fsize = $fstat[7];
            $ftime = date("Y/m/d H:i:s", $fstat[9]);

            $this->template->setAttribute('archive', 'visibility', 'visible');
            $this->template->addVars('archive', [
                'DIR' => $dir,
                'FILENAME' => $filename,
                'FTIME' => $ftime,
                'FSIZE' => $fsize,
            ]);
            $this->template->parseTemplate('archive', 'a');
        }

        $this->sethttpheader();
        print $this->prthtmlhead($this->config['BBSTITLE'] . ' Message log archive');
        $this->template->displayParsedTemplate('archivelist');
        print $this->prthtmlfoot();

    }




    /**
     * Check download function availability
     */
    public function dlchk()
    {

        if (!@$_SERVER['HTTP_USER_AGENT']) {
            return true;
        }
        if (preg_match("/^Mozilla\/(\S+)\s(.+)/", (string) @$_SERVER['HTTP_USER_AGENT'], $matches)) {
            $ver = $matches[1];
            $uos = $matches[2];
            $isie = 0;
            if (preg_match("/MSIE (\S)/", $uos, $matches)) {
                $isie = 1;
                $iever = $matches[1];
            }
            $ismac = 0;
            if (preg_match("/Mac/", $uos, $matches)) {
                $ismac = 1;
            }
            if ((@$ver >= 4 and !@$isie) or (@$ver >= 4 and @$isie and @$iever >= 5 and !@$ismac)) {
                return true;
            } else {
                return false;
            }
        }
        return true;
    }










}
