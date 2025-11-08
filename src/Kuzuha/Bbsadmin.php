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
            $this->config = &$this->bbs->config;
            $this->form = &$this->bbs->form;
            $this->template = &$this->bbs->template;
        }
        // Template loading removed - using Twig now
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
            if ($this->config['GZIPU'] && ob_get_level() === 0) {
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
            // If ADMINPOST is empty, show password setup page
            if (empty($this->config['ADMINPOST'])) {
                $this->prtsetpass();
            } else {
                $this->prtadminmenu();
            }
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
        $this->sethttpheader();
        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'] . ' Administration menu',
            'V' => trim((string) $this->form['v']),
        ]);
        echo $this->renderTwig('admin/menu.twig', $data);
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
            $message['WDATE'] = DateHelper::getDateString($message['NDATE']);
            $message['USER_NOTAG'] = preg_replace("/<[^>]*>/", '', (string) $message['USER']);
            $messages[] = $message;
        }

        $this->sethttpheader();
        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'] . ' Message deletion mode',
            'V' => trim((string) $this->form['v']),
            'messages' => $messages,
        ]);
        echo $this->renderTwig('admin/killlist.twig', $data);
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
        $this->sethttpheader();
        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'] . ' ' . Translator::trans('admin.password_settings_page'),
            'V' => trim((string) $this->form['v']),
            'TRANS_PASSWORD_SETTINGS_PAGE' => Translator::trans('admin.password_settings_page'),
            'TRANS_PASSWORD_INSTRUCTION' => Translator::trans('admin.password_setup_instruction'),
            'TRANS_ADMIN_PASSWORD' => Translator::trans('admin.admin_password'),
            'TRANS_SET' => Translator::trans('admin.set'),
            'TRANS_RESET' => Translator::trans('admin.reset'),
        ]);
        echo $this->renderTwig('admin/setpass.twig', $data);
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

        $this->sethttpheader();
        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'] . ' ' . Translator::trans('admin.password_settings_page'),
            'CRYPTPASS' => $cryptpass,
            'INPUTSIZE' => $inputsize,
            'TRANS_PASSWORD_SETTINGS_PAGE' => Translator::trans('admin.password_settings_page'),
            'TRANS_PASSWORD_GENERATED' => Translator::trans('admin.password_generated'),
            'TRANS_ADMIN_PASSWORD' => Translator::trans('admin.admin_password'),
            'TRANS_BULLETIN_BOARD' => Translator::trans('admin.bulletin_board'),
        ]);
        echo $this->renderTwig('admin/pass.twig', $data);
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
