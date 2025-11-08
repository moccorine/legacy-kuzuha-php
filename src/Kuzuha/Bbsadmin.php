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
Admin mode module

*/

if (!defined("INCLUDED_FROM_BBS")) {
    header("Location: ../bbs.php");
    exit();
}



/**
 * Admin mode module
 *
 *
 *
 * @package strangeworld.cnscript
 * @access  public
 */
class Bbsadmin extends Webapp
{
    public $bbs;

    /**
     * Constructor
     *
     */
    public function __construct()
    {
        parent::__construct();
        if (func_num_args() > 0) {
            $this->bbs = func_get_arg(0);
            $this->c = &$this->bbs->c;
            $this->f = &$this->bbs->f;
            $this->t = &$this->bbs->t;
        }
        $this->template->readTemplatesFromFile($this->config['TEMPLATE_ADMIN']);
    }


    /**
     * Main process
     */
    public function main()
    {

        if (!defined('BBS_ACTIVATED')) {

            # Start measuring execution time
            $this->setstarttime();

            # Form acquisition preprocessing
            $this->procForm();

            # Reflect user settings
            $this->refcustom();
            $this->setusersession();

            # gzip compressed transfer
            if ($this->config['GZIPU']) {
                ob_start("ob_gzhandler");
            }
        }

        # Log file viewer
        if (@$this->form['ad'] == 'l') {
            $this->prtlogview(true);
        }
        # Message deletion mode
        elseif (@$this->form['ad'] == 'k') {
            $this->prtkilllist();
        }
        # Message deletion process
        elseif (@$this->form['ad'] == 'x') {
            if (isset($this->form['x'])) {
                $this->killmessage($this->form['x']);
            }
            $this->prtkilllist();
        }
        # Encrypted password generation page
        elseif (@$this->form['ad'] == 'p') {
            $this->prtsetpass();
        }
        # Encrypted password generation & display
        elseif (@$this->form['ad'] == 'ps') {
            $this->prtpass(@$this->form['ps']);
        }
        # Display server PHP configuration information
        elseif (@$this->form['ad'] == 'phpinfo') {
            phpinfo();
        }
        # Admin menu page
        else {
            $this->prtadminmenu();
        }


        if (!defined('BBS_ACTIVATED') and $this->config['GZIPU']) {
            ob_end_flush();
        }
    }





    /**
     * Admin menu page
     *
     */
    public function prtadminmenu()
    {

        $this->template->addVar('adminmenu', 'V', trim((string) $this->form['v']));

        $this->sethttpheader();
        print $this->prthtmlhead($this->config['BBSTITLE'] . ' Administration menu');
        $this->template->displayParsedTemplate('adminmenu');
        print $this->prthtmlfoot();

    }





    /**
     * Message deletion mode main page display
     *
     */
    public function prtkilllist()
    {

        if (!file_exists($this->config['LOGFILENAME'])) {
            $this->prterror('Failed to load message');
        }
        $logdata = file($this->config['LOGFILENAME']);

        $this->template->addVar('killlist', 'V', trim((string) $this->form['v']));

        $messages = [];
        foreach ($logdata as $logline) {
            $message = $this->getmessage($logline);
            $message['MSG'] = preg_replace("/<a href=[^>]+>Reference: [^<]+<\/a>/i", "", (string) $message['MSG'], 1);
            $message['MSG'] = preg_replace("/<[^>]+>/", "", ltrim($message['MSG']));
            $msgsplit = explode("\r", (string) $message['MSG']);
            $message['MSGDIGEST'] = $msgsplit[0];
            $index = 1;
            while ($index < count($msgsplit) - 1 and strlen($message['MSGDIGEST'] . $msgsplit[$index]) < 50) {
                $message['MSGDIGEST'] .= $msgsplit[$index];
                $index++;
            }
            $message['WDATE'] = Func::getdatestr($message['NDATE']);
            $message['USER_NOTAG'] = preg_replace("/<[^>]*>/", '', (string) $message['USER']);
            $messages[] = $message;
        }

        $this->template->addRows('killmessage', $messages);

        $this->sethttpheader();
        print $this->prthtmlhead($this->config['BBSTITLE'] . ' Message deletion mode');
        $this->template->displayParsedTemplate('killlist');
        print $this->prthtmlfoot();
    }





    /**
     * Message deletion process
     *
     */
    public function killmessage($killids)
    {

        if (!$killids) {
            return;
        }
        if (!is_array($killids)) {
            $tmp = $killids;
            $killids = [];
            $killids[] = $tmp;
        }

        $fh = @fopen($this->config['LOGFILENAME'], "r+");
        if (!$fh) {
            $this->prterror('Failed to load message');
        }
        flock($fh, 2);
        fseek($fh, 0, 0);

        $logdata = [];
        while (($logline = Func::fgetline($fh)) !== false) {
            $logdata[] = $logline;
        }

        $killntimes = [];
        $killlogdata = [];
        $newlogdata = [];
        $i = 0;
        while ($i < count($logdata)) {
            $items = explode(',', $logdata[$i], 3);
            if (count($items) > 2 and array_search($items[1], $killids) !== false) {
                $killntimes[$items[1]] = $items[0];
                $killlogdata[] = $logdata[$i];
            } else {
                $newlogdata[] = $logdata[$i];
            }
            $i++;
        }
        {
            fseek($fh, 0, 0);
            ftruncate($fh, 0);
            fwrite($fh, implode('', $newlogdata));
        }
        flock($fh, 3);
        fclose($fh);

        # Image deletion
        foreach ($killlogdata as $eachlogdata) {
            if (preg_match("/<img [^>]*?src=\"([^\"]+)\"[^>]+>/i", $eachlogdata, $matches) and file_exists($matches[1])) {
                unlink($matches[1]);
            }
        }

        # Message log line deletion
        if ($this->config['OLDLOGFILEDIR']) {
            foreach (array_keys($killntimes) as $killid) {
                $oldlogfilename = '';
                if ($this->config['OLDLOGFMT']) {
                    $oldlogext = 'dat';
                } else {
                    $oldlogext = 'html';
                }
                if ($this->config['OLDLOGSAVESW']) {
                    $oldlogfilename = date("Ym", $killntimes[$killid]) . ".$oldlogext";
                } else {
                    $oldlogfilename = date("Ymd", $killntimes[$killid]) . ".$oldlogext";
                }
                $fh = @fopen($this->config['OLDLOGFILEDIR'] . $oldlogfilename, "r+");
                if ($fh) {
                    flock($fh, 2);
                    fseek($fh, 0, 0);

                    $newlogdata = [];
                    $hit = false;

                    if ($this->config['OLDLOGFMT']) {
                        $needle = $killntimes[$killid] . "," . $killid . ",";
                        while (($logline = Func::fgetline($fh)) !== false) {
                            if (!$hit and str_contains($logline, $needle) and str_starts_with($logline, $needle)) {
                                $hit = true;
                            } else {
                                $newlogdata[] = $logline;
                            }
                        }
                    } else {
                        $needle = "<div class=\"m\" id=\"m{$killid}\">";
                        $flgbuffer = false;
                        while (($htmlline = Func::fgetline($fh)) !== false) {

                            # Start of message
                            if (!$hit and str_contains($htmlline, $needle)) {
                                $hit = true;
                                $flgbuffer = true;
                            }
                            # End of message
                            elseif ($flgbuffer and str_contains($htmlline, "<hr")) {
                                $flgbuffer = false;
                            }
                            # Inside message
                            elseif ($flgbuffer) {
                            } else {
                                $newlogdata[] = $htmlline;
                            }
                        }
                    }

                    {
                        fseek($fh, 0, 0);
                        ftruncate($fh, 0);
                        fwrite($fh, implode('', $newlogdata));
                    }
                    flock($fh, 3);
                    fclose($fh);
                } else {
                    #$this->prterror ( 'Failed to load message log' );
                }
            }
        }

    }





    /**
     * Encrypted password generation screen display
     *
     */
    public function prtsetpass()
    {

        $this->template->addVar('setpass', 'V', trim((string) $this->form['v']));

        $this->sethttpheader();
        print $this->prthtmlhead($this->config['BBSTITLE'] . ' Password settings page');
        $this->template->displayParsedTemplate('setpass');
        print $this->prthtmlfoot();
    }





    /**
     * Encrypted password generation & display
     *
     */
    public function prtpass($inputpass)
    {

        if (!@$inputpass) {
            $this->prterror('No password has been set.');
        }

        $salt = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 2);
        $cryptpass = crypt($inputpass, $salt);
        $inputsize = strlen($cryptpass) + 10;

        $this->template->addVars('pass', [
            'CRYPTPASS' => $cryptpass,
            'INPUTSIZE' => $inputsize,
        ]);

        $this->sethttpheader();
        print $this->prthtmlhead($this->config['BBSTITLE'] . ' Password settings page');
        $this->template->displayParsedTemplate('pass');
        print $this->prthtmlfoot();
    }





    /**
     * Log file display
     *
     */
    public function prtlogview($htmlescape = false)
    {
        if ($htmlescape) {
            header("Content-type: text/html");
            $logdata = file($this->config['LOGFILENAME']);
            print "<html><head><title>{$this->config['LOGFILENAME']}</title></head><body><pre>\n";
            foreach ($logdata as $logline) {
                if (!preg_match("/^\w+$/", $logline)) {
                    #$value_euc = JcodeConvert($logline, 2, 1);
                    #$value_euc = htmlentities($value_euc, ENT_QUOTES, 'EUC-JP');
                    #$logline = JcodeConvert($value_euc, 1, 2);
                    $logline = htmlspecialchars($logline, ENT_QUOTES);
                }
                $logline = str_replace("&#44;", ",", $logline);
                print $logline;
            }
            print "\n</pre></body></html>";
        } else {
            header("Content-type: text/plain");
            readfile($this->config['LOGFILENAME']);
        }
    }






}
