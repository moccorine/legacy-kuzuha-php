<?php

namespace Kuzuha;

use App\Config;
use App\Translator;
use App\Utils\DateHelper;
use App\Utils\NetworkHelper;
use App\Utils\StringHelper;
use App\Utils\SecurityHelper;
use App\Utils\FileHelper;

/**
 * Standard bulletin board class - Bbs
 *
 * A bulletin board display class for PC.
 * If you want to customize/extend the bulletin board function itself, inherit this class.
 *
 * @package strangeworld.cnscript
 * @access  public
 */
class Bbs extends Webapp
{
    /**
     * Constructor
     *
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Main process
     */
    public function main()
    {
        # Start execution time measurement
        $this->setstarttime();
        # Form acquisition preprocessing
        $this->procForm();
        # Reflect user settings
        $this->refcustom();
        $this->setusersession();
        # gzip compression transfer
        if ($this->c['GZIPU']) {
            ob_start("ob_gzhandler");
        }
        # Post operation
        if ($this->f['m'] == 'p' and trim((string) $this->f['v'])) {
            # Get environment variables
            $this->setuserenv();
            # Parameter check
            $posterr = $this->chkmessage();
            # Post operation
            if (!$posterr) {
                $posterr = $this->putmessage($this->getformmessage());
            }
            # Douple post error, etc.
            if ($posterr == 1) {
                $this->prtmain();
            }
            # Protect code redisplayed due to time lapse
            elseif ($posterr == 2) {
                if ($this->f['f']) {
                    $this->prtfollow(true);
                } elseif ($this->f['write']) {
                    $this->prtnewpost(true);
                } else {
                    $this->prtmain(true);
                }
            }
            # Entering admin mode
            elseif ($posterr == 3) {
                define('BBS_ACTIVATED', true);
                require_once(PHP_BBSADMIN);
                $bbsadmin = new Bbsadmin($this);
                $bbsadmin->main();
            }
            # Post completion page
            elseif ($this->f['f']) {
                $this->prtputcomplete();
            } else {
                $this->prtmain();
            }
        }
        # Display follow-up page
        elseif ($this->f['m'] == 'f') {
            $this->prtfollow();
        }
        # Post search
        elseif ($this->f['m'] == 't' or $this->f['m'] == 's') {
            $this->prtsearchlist();
        }
        # Display user settings page
        elseif ($this->f['setup']) {
            $this->prtcustom();
        }
        # User settings process
        elseif ($this->f['m'] == 'c') {
            $this->setcustom();
        }
        # New post
        elseif ($this->f['m'] == 'p' and $this->f['write']) {
            $this->prtnewpost();
        }
        # UNDO process
        elseif ($this->f['m'] == 'u') {
            $this->prtundo();
        }
        # Default: bulletin board display
        else {
            $this->prtmain();
        }

        if ($this->c['GZIPU']) {
            ob_end_flush();
        }
    }

    /**
     * Display bulletin board
     *
     * @access  public
     * @param   Boolean  $retry  Retry flag
     */
    public function prtmain($retry = false)
    {
        # Get display message
        [$logdatadisp, $bindex, $eindex, $lastindex] = $this->getdispmessage();
        # Form section settings
        $dtitle = "";
        $dmsg = "";
        $dlink = "";
        if ($retry) {
            $dtitle = $this->f['t'];
            $dmsg = $this->f['v'];
            $dlink = $this->f['l'];
        }
        $this->setform($dtitle, $dmsg, $dlink);
        # HTML header partial output
        $this->sethttpheader();
        print $this->prthtmlhead($this->c['BBSTITLE']);
        # Upper main section
        $this->t->displayParsedTemplate('main_upper');
        # Display message
        foreach ($logdatadisp as $msgdata) {
            print $this->prtmessage($this->getmessage($msgdata), 0, 0);
        }
        # Message information
        if ($this->s['MSGDISP'] < 0) {
            $msgmore = '';
        } elseif ($eindex > 0) {
            $msgmore = "Shown above are posts {$bindex} through {$eindex}, in order of newest to oldest. ";
        } else {
            $msgmore = 'There are no unread messages. ';
        }
        if ($eindex >= $lastindex) {
            $msgmore .= 'There are no posts below this point.';
        }
        $this->t->addVar('main_lower', 'MSGMORE', $msgmore);
        # Navigation buttons
        if ($eindex > 0) {
            if ($eindex >= $lastindex) {
                $this->t->setAttribute("nextpage", "visibility", "hidden");
            } else {
                $this->t->addVar('nextpage', 'EINDEX', $eindex);
            }
            if (!$this->c['SHOW_READNEWBTN']) {
                $this->t->setAttribute("readnew", "visibility", "hidden");
            }
        }
        # Post as administrator
        if ($this->c['BBSMODE_ADMINONLY'] == 0) {
            $this->t->setAttribute("adminlogin", "visibility", "hidden");
        }
        # Lower main section
        $this->t->displayParsedTemplate('main_lower');
        print $this->prthtmlfoot();
    }

    /**
     * Get display range messages and parameters
     *
     * @access  public
     * @return  Array   $logdatadisp  Log line array
     * @return  Integer $bindex       Beginning of index
     * @return  Integer $eindex       End of index
     * @return  Integer $lastindex    End of all logs index
     */
    public function getdispmessage()
    {

        $logdata = $this->loadmessage();
        # Unread pointer (latest POSTID)
        $items = @explode(',', (string) $logdata[0], 3);
        $toppostid = $items[1];
        # Number of posts displayed
        $msgdisp = \App\Utils\StringHelper::fixNumberString($this->f['d']);
        if ($msgdisp === false) {
            $msgdisp = $this->c['MSGDISP'];
        } elseif ($msgdisp < 0) {
            $msgdisp = $this->c['MSGDISP'];
        } elseif ($msgdisp > $this->c['LOGSAVE']) {
            $msgdisp = $this->c['LOGSAVE'];
        }
        if ($this->f['readzero']) {
            $msgdisp = 0;
        }
        # Beginning of index
        $bindex = $this->f['b'];
        if (!$bindex) {
            $bindex = 0;
        }
        # For the next and subsequent pages
        if ($bindex > 1) {
            # If there are new posts, shift the beginning of the index
            if ($toppostid > $this->f['p']) {
                $bindex += ($toppostid - $this->f['p']);
            }
            # Don't update unread pointer
            $toppostid = $this->f['p'];
        }
        # End of index
        $eindex = $bindex + $msgdisp;
        # Unread reload
        if ($this->f['readnew'] or ($msgdisp == '0' and $bindex == 0)) {
            $bindex = 0;
            $eindex = $toppostid - $this->f['p'];
        }
        # For the last page, truncate
        $lastindex = count($logdata);
        if ($eindex > $lastindex) {
            $eindex = $lastindex;
        }
        # Display posts -1
        if ($msgdisp < 0) {
            $bindex = 0;
            $eindex = 0;
        }
        # Display messages
        if ($bindex == 0 and $eindex == 0) {
            $logdatadisp = [];
        } else {
            $logdatadisp = array_splice($logdata, $bindex, ($eindex - $bindex));
            if ($this->c['RELTYPE'] and ($this->f['readnew'] or ($msgdisp == '0' and $bindex == 0))) {
                $logdatadisp = array_reverse($logdatadisp);
            }
        }
        $this->s['TOPPOSTID'] = $toppostid;
        $this->s['MSGDISP'] = $msgdisp;
        $this->t->addGlobalVars([
            'TOPPOSTID' => $this->s['TOPPOSTID'],
            'MSGDISP' => $this->s['MSGDISP']
        ]);
        return [$logdatadisp, $bindex + 1, $eindex, $lastindex];
    }

    /**
     * Form section settings
     *
     * @access  public
     * @param   String  $dtitle     Initial value of the form title
     * @param   String  $dmsg       Initial value for the form contents
     * @param   String  $dlink      Initial value for the form link
     */
    public function setform($dtitle, $dmsg, $dlink, $mode = '')
    {
        # Protect code generation
        $pcode = \App\Utils\SecurityHelper::generateProtectCode();
        if (!$mode) {
            $mode = '<input type="hidden" name="m" value="p" />';
        }
        $this->t->addVars('form', [
            'MODE' => $mode,
            'PCODE' => $pcode,
        ]);
        # Hide post form
        if ($this->c['HIDEFORM'] and $this->f['m'] != 'f' and !$this->f['write']) {
            $this->t->addVar('postform', 'mode', 'hide');
        } else {
            $this->t->addVars('postform', [
                'DTITLE' => $dtitle,
                'DMSG' => $dmsg,
                'DLINK' => $dlink,
            ]);
        }
        # Settings and links lines
        if ($this->f['m'] != 'f' and !isset($this->f['f']) and !$this->f['write']) {
            # Counter
            if ($this->c['SHOW_COUNTER']) {
                $counter = $this->counter();
                $counter = number_format($counter);
                $this->t->addVar("counter", 'COUNTER', $counter);
                $this->t->setAttribute("counter", "visibility", "visible");
            }
            if ($this->c['CNTFILENAME']) {
                $mbrcount = $this->mbrcount();
                $mbrcount = number_format($mbrcount);
                $this->t->addVar("mbrcount", 'MBRCOUNT', $mbrcount);
                $this->t->setAttribute("mbrcount", "visibility", "visible");
            }
            if (!$this->c['SHOW_COUNTER'] and !$this->c['CNTFILENAME']) {
                $this->t->setAttribute("counterrow", "visibility", "hidden");
            }
            if ($this->c['BBSMODE_ADMINONLY'] == 0) {
                if ($this->c['AUTOLINK']) {
                    $this->t->addVar('formconfig', 'CHK_A', ' checked="checked"');
                }
                if ($this->c['HIDEFORM']) {
                    $this->t->addVar('formconfig', 'CHK_HIDE', ' checked="checked"');
                }
            } else {
                $this->t->setAttribute("formconfig", "visibility", "hidden");
            }
            # Hide link line
            if ($this->c['LINKOFF']) {
                $this->t->addVar('extraform', 'CHK_LOFF', ' checked="checked"');
                $this->t->setAttribute("linkrow", "visibility", "hidden");
            }
            # Hide help line
            if ($this->c['BBSMODE_ADMINONLY'] != 1) {
                if (!$this->c['ALLOW_UNDO']) {
                    $this->t->setAttribute("helpundo", "visibility", "hidden");
                }
            } else {
                $this->t->setAttribute("helprow", "visibility", "hidden");
            }
            # Navigation buttons line
            if (!$this->c['SHOW_READNEWBTN']) {
                $this->t->setAttribute("readnewbtn", "visibility", "hidden");
            }
            if (!($this->c['HIDEFORM'] and $this->c['BBSMODE_ADMINONLY'] == 0)) {
                $this->t->setAttribute("newpostbtn", "visibility", "hidden");
            }
        } else {
            $this->t->setAttribute("extraform", "visibility", "hidden");
        }
    }

    /**
     * Display follow-up page
     *
     * @access  public
     * @param   Boolean $retry  Retry flag
     */
    public function prtfollow($retry = false)
    {

        if (!$this->f['s']) {
            $this->prterror(\App\Translator::trans('error.no_parameters'));
        }

        # Administrator authentication
        if ($this->c['BBSMODE_ADMINONLY'] == 1
            and crypt((string) $this->f['u'], (string) $this->c['ADMINPOST']) != $this->c['ADMINPOST']) {
            $this->prterror(\App\Translator::trans('error.incorrect_password'));
        }
        $filename = '';
        if ($this->f['ff']) {
            $filename = trim((string) $this->f['ff']);
        }
        $result = $this->searchmessage('POSTID', $this->f['s'], false, $filename);
        if (!$result) {
            $this->prterror(\App\Translator::trans('error.message_not_found'));
        }
        # Get message
        $message = $this->getmessage($result[0]);

        if (!$retry) {
            $formmsg = $message['MSG'];
            $formmsg = preg_replace("/&gt; &gt;[^\r]+\r/", "", (string) $formmsg);
            $formmsg = preg_replace("/<a href=\"m=f\S+\"[^>]*>[^<]+<\/a>/i", "", $formmsg);
            $formmsg = preg_replace("/<a href=\"[^>]+>([^<]+)<\/a>/i", "$1", $formmsg);
            $formmsg = preg_replace("/\r*<a href=[^>]+><img [^>]+><\/a>/i", "", $formmsg);
            $formmsg = preg_replace("/\r/", "\r> ", $formmsg);
            $formmsg = "> $formmsg\r";
            $formmsg = preg_replace("/\r>\s+\r/", "\r", $formmsg);
            $formmsg = preg_replace("/\r>\s+\r$/", "\r", (string) $formmsg);
        } else {
            $formmsg = $this->f['v'];
            $formmsg = preg_replace("/<a href=\"m=f\S+\"[^>]*>[^<]+<\/a>/i", "", (string) $formmsg);
        }
        $formmsg .= "\r";

        $this->setform("＞" . preg_replace("/<[^>]*>/", '', (string) $message['USER']) . $this->c['FSUBJ'], $formmsg, '');

        if (!$message['THREAD']) {
            $message['THREAD'] = $message['POSTID'];
        }
        $filename ? $mode = 1 : $mode = 0;
        $this->setmessage($message, $mode, $filename);

        if ($this->c['AUTOLINK']) {
            $this->t->addVar('follow', 'CHK_A', ' checked="checked"');
        }
        $this->t->addVar('follow', 'FOLLOWID', $message['POSTID']);
        $this->t->addVar('follow', 'SEARCHID', $this->f['s']);
        $this->t->addVar('follow', 'FF', $this->f['ff']);
        # Display
        $this->sethttpheader();
        print $this->prthtmlhead($this->c['BBSTITLE'] . ' Follow-up post');
        $this->t->displayParsedTemplate('follow');
        print $this->prthtmlfoot();

    }

    /**
     * Display new post page
     *
     * @access  public
     */
    public function prtnewpost($retry = false)
    {

        # Administrator authentication
        if ($this->c['BBSMODE_ADMINONLY'] != 0
            and crypt((string) $this->f['u'], (string) $this->c['ADMINPOST']) != $this->c['ADMINPOST']) {
            $this->prterror(\App\Translator::trans('error.incorrect_password'));
        }
        # Form section
        $dtitle = "";
        $dmsg = "";
        $dlink = "";
        if ($retry) {
            $dtitle = $this->f['t'];
            $dmsg = $this->f['v'];
            $dlink = $this->f['l'];
        }
        $this->setform($dtitle, $dmsg, $dlink);

        if ($this->c['AUTOLINK']) {
            $this->t->addVar('newpost', 'CHK_A', ' checked="checked"');
        }

        $this->sethttpheader();
        print $this->prthtmlhead("{$this->c['BBSTITLE']} New post");
        $this->t->displayParsedTemplate('newpost');
        print $this->prthtmlfoot();

    }

    /**
     * Post search
     *
     * @param   Integer $mode       0: Bulletin board / 1: Message log search (with buttons displayed) / 2: Message log search (without buttons displayed) / 3: For message log file output
     */
    public function prtsearchlist($mode = "")
    {

        if (!$this->f['s']) {
            $this->prterror(\App\Translator::trans('error.no_parameters'));
        }
        if (!$mode) {
            $mode = $this->f['m'];
        }
        $this->sethttpheader();
        print $this->prthtmlhead($this->c['BBSTITLE'] . ' Post search');
        $this->t->displayParsedTemplate('searchlist_upper');

        $result = $this->msgsearchlist($mode);
        foreach ($result as $message) {
            print $this->prtmessage($message, $mode, $this->f['ff']);
        }
        $success = count($result);

        $this->t->addVar('searchlist_lower', 'SUCCESS', $success);
        $this->t->displayParsedTemplate('searchlist_lower');
        print $this->prthtmlfoot();

    }

    /**
     * Post search process
     */
    public function msgsearchlist($mode)
    {

        $fh = null;
        if ($this->f['ff']) {
            if (preg_match("/^[\w.]+$/", (string) $this->f['ff'])) {
                $fh = @fopen($this->c['OLDLOGFILEDIR'] . $this->f['ff'], "rb");
            }
            if (!$fh) {
                $this->prterror(\App\Translator::trans('error.file_open_failed', ['filename' => $this->f['ff']]));
            }
            flock($fh, 1);
        }

        $result = [];

        if ($fh) {
            $linecount = 0;
            $threadstart = false;
            while (($logline = \App\Utils\FileHelper::getLine($fh)) !== false) {
                if ($threadstart) {
                    $linecount++;
                }
                if ($linecount > $this->c['LOGSAVE']) {
                    break;
                }
                $message = $this->getmessage($logline);
                # Search by user
                if ($mode == 's' and preg_replace("/<[^>]*>/", '', (string) $message['USER']) == $this->f['s']) {
                    $result[] = $message;
                }
                # Search by thread
                elseif ($mode == 't'
                    and ($message['THREAD'] == $this->f['s'] or $message['POSTID'] == $this->f['s'])) {
                    $result[] = $message;
                    if (!$threadstart) {
                        $threadstart = true;
                    }
                }
            }
            flock($fh, 3);
            fclose($fh);
        } else {
            $logdata = $this->loadmessage();
            foreach ($logdata as $logline) {
                $message = $this->getmessage($logline);
                # Search by user
                if ($mode == 's' and preg_replace("/<[^>]*>/", '', (string) $message['USER']) == $this->f['s']) {
                    $result[] = $message;
                }
                # Search by thread
                elseif ($mode == 't'
                    and ($message['THREAD'] == $this->f['s'] or $message['POSTID'] == $this->f['s'])) {
                    $result[] = $message;
                    if ($message['POSTID'] == $this->f['s']) {
                        break;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Post complete
     */
    public function prtputcomplete()
    {

        $this->sethttpheader();
        print $this->prthtmlhead($this->c['BBSTITLE'] . ' Post complete');
        $this->t->displayParsedTemplate('postcomplete');
        print $this->prthtmlfoot();

    }

    /**
     * Display user settings page
     */
    public function prtcustom($mode = '')
    {

        if ($this->c['GZIPU']) {
            $this->t->addVar('custom', 'CHK_G', ' checked="checked"');
        }
        if ($this->c['AUTOLINK']) {
            $this->t->addVar('custom', 'CHK_A', ' checked="checked"');
        }
        if ($this->c['LINKOFF']) {
            $this->t->addVar('custom', 'CHK_LOFF', ' checked="checked"');
        }
        if ($this->c['HIDEFORM']) {
            $this->t->addVar('custom', 'CHK_HIDE', ' checked="checked"');
        }
        if ($this->c['SHOWIMG']) {
            $this->t->addVar('custom', 'CHK_SI', ' checked="checked"');
        }
        if ($this->c['COOKIE']) {
            $this->t->addVar('custom', 'CHK_COOKIE', ' checked="checked"');
        }

        $this->c['FOLLOWWIN'] ? $this->t->addVar('custom', 'CHK_FW_1', ' checked="checked"')
            : $this->t->addVar('custom', 'CHK_FW_0', ' checked="checked"');
        $this->c['RELTYPE'] ? $this->t->addVar('custom', 'CHK_RT_1', ' checked="checked"')
            : $this->t->addVar('custom', 'CHK_RT_0', ' checked="checked"');

        $this->t->addVar('custom_hide', 'BBSMODE_ADMINONLY', $this->c['BBSMODE_ADMINONLY']);
        $this->t->addVar('custom_a', 'BBSMODE_ADMINONLY', $this->c['BBSMODE_ADMINONLY']);
        $this->t->addVar('custom', 'MODE', $mode);

        $this->sethttpheader();
        print $this->prthtmlhead($this->c['BBSTITLE'] . ' User settings');
        $this->t->displayParsedTemplate('custom');
        print $this->prthtmlfoot();
    }

    /**
     * User settings process
     */
    public function setcustom()
    {

        $redirecturl = $this->c['CGIURL'];

        # Cookie消去
        if ($this->f['cr']) {
            $this->f['c'] = '';
            setcookie('c');
            setcookie('undo');
            $this->s['UNDO_P'] = '';
            $this->s['UNDO_K'] = '';
        } else {
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

            $flgchgindex = -1;
            $cindex = 0;
            foreach ($colors as $confname) {
                if (strlen((string) $this->f[$confname]) == 6 and preg_match("/^[0-9a-fA-F]{6}$/", (string) $this->f[$confname])
                    and $this->f[$confname] != $this->c[$confname]) {
                    $this->c[$confname] = $this->f[$confname];
                    $flgchgindex = $cindex;
                }
                $cindex++;
            }

            $cbase64str = '';
            for ($i = 0; $i <= $flgchgindex; $i++) {
                $cbase64str .= \App\Utils\StringHelper::threeByteHexToBase64($this->c[$colors[$i]]);
            }
            $this->refcustom();

            $this->f['c'] = substr((string) $this->f['c'], 0, 2) . $cbase64str;

            $redirecturl .= "?c=".$this->f['c'];
            foreach (['w', 'd',] as $key) {
                if ($this->f[$key] != '') {
                    $redirecturl .= "&{$key}=".$this->f[$key];
                }
            }
            if ($this->f['nm']) {
                $redirecturl .= "&m=".$this->f['nm'];
            }
            if ($this->c['COOKIE']) {
                $this->setbbscookie();
            }
        }
        # Redirect
        if (preg_match("/^(https?):\/\//", (string) $this->c['CGIURL'])) {
            header("Location: {$redirecturl}");
        } else {
            $this->prtredirect(htmlentities((string) $redirecturl));
        }
    }

    /**
     * UNDO process
     */
    public function prtundo()
    {
        if (!$this->f['s']) {
            $this->prterror(\App\Translator::trans('error.no_parameters'));
        }
        if (isset($this->s['UNDO_P']) and $this->s['UNDO_P'] == $this->f['s']) {
            $loglines = $this->searchmessage('POSTID', $this->s['UNDO_P']);
            if (count($loglines) < 1) {
                $this->prterror(\App\Translator::trans('error.post_not_found'));
            }
            $message = $this->getmessage($loglines[0]);
            $undokey = substr((string) preg_replace("/\W/", "", crypt((string) $message['PROTECT'], (string) $this->c['ADMINPOST'])), -8);
            if ($undokey != $this->s['UNDO_K']) {
                $this->prterror(\App\Translator::trans('error.deletion_not_permitted'));
            }
            # Erase operation
            require_once(PHP_BBSADMIN);
            $bbsadmin = new Bbsadmin();
            $bbsadmin->killmessage($this->s['UNDO_P']);

            $this->s['UNDO_P'] = '';
            $this->s['UNDO_K'] = '';
            setcookie('undo');
        } else {
            $this->prterror(\App\Translator::trans('error.deletion_not_permitted'));
        }
        $this->sethttpheader();
        print $this->prthtmlhead($this->c['BBSTITLE'] . ' Deletion complete');
        $this->t->displayParsedTemplate('undocomplete');
        print $this->prthtmlfoot();
    }

    /**
     * Message search (exact match)
     *
     * @access  public
     * @param   String  $varname      Variable name
     * @param   String  $searchvalue  Search string
     * @param   Boolean $ismultiple   Multiple search flag
     * @return  Array   Log line array
     */
    public function searchmessage($varname, $searchvalue, $ismultiple = false, $filename = "")
    {
        $result = [];
        $logdata = $this->loadmessage($filename);
        foreach ($logdata as $logline) {
            $message = $this->getmessage($logline);
            if (isset($message[$varname]) and $message[$varname] == $searchvalue) {
                $result[] = $logline;
                if (!$ismultiple) {
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * Post check
     *
     * @access  public
     * @param   Boolean   $limithost  Whether or not to check for same host
     * @return  Integer   Error code
     */
    public function chkmessage($limithost = true)
    {
        $posterr = 0;
        if ($this->c['RUNMODE'] == 1) {
            $this->prterror(\App\Translator::trans('error.posting_suspended'));
        }
        /* Prohibit access by host name process */
        if (\App\Utils\NetworkHelper::hostnameMatch($this->c['HOSTNAME_POSTDENIED'], $this->c['HOSTAGENT_BANNED'])) {
            $this->prterror(\App\Translator::trans('error.posting_suspended'));
        }
        if ($this->c['BBSMODE_ADMINONLY'] == 1 or ($this->c['BBSMODE_ADMINONLY'] == 2 and !$this->f['f'])) {
            if (crypt((string) $this->f['u'], (string) $this->c['ADMINPOST']) != $this->c['ADMINPOST']) {
                $this->prterror(\App\Translator::trans('error.admin_only'));
            }
        }
        if ($_SERVER['HTTP_REFERER'] and $this->c['REFCHECKURL']
            and (!str_contains((string) $_SERVER['HTTP_REFERER'], (string) $this->c['REFCHECKURL'])
            or strpos((string) $_SERVER['HTTP_REFERER'], (string) $this->c['REFCHECKURL']) > 0)) {
            $this->prterror("Posts cannot be made from any URLs besides <br>{$this->c['REFCHECKURL']}.");
        }
        foreach (explode("\r", (string) $this->f['v']) as $line) {
            if (strlen($line) > $this->c['MAXMSGCOL']) {
                $this->prterror(\App\Translator::trans('error.too_many_characters'));
            }
        }
        if (substr_count((string) $this->f['v'], "\r") > $this->c['MAXMSGLINE'] - 1) {
            $this->prterror(\App\Translator::trans('error.too_many_linebreaks'));
        }
        if (strlen((string) $this->f['v']) > $this->c['MAXMSGSIZE']) {
            $this->prterror(\App\Translator::trans('error.file_size_too_large'));
        }
        if (strlen((string) $this->f['u']) > $this->c['MAXNAMELENGTH']) {
            $this->prterror('There are too many characters in the name field. (Up to {MAXNAMELENGTH} characters)');
        }
        if (strlen((string) $this->f['i']) > $this->c['MAXMAILLENGTH']) {
            $this->prterror('There are too many characters in the email field. (Up to {MAXMAILLENGTH} characters)');
        }
        if ($this->f['i']) { ## mod
            $this->prterror(\App\Translator::trans('error.spam_detected')); ## mod
        } ## mod
        if (strlen((string) $this->f['t']) > $this->c['MAXTITLELENGTH']) {
            $this->prterror('There are too many characters in the title field. (Up to {MAXTITLELENGTH} characters)');
        }
        {
            $timestamp = \App\Utils\SecurityHelper::verifyProtectCode($this->f['pc'], $limithost);

            if ((CURRENT_TIME - $timestamp) < $this->c['MINPOSTSEC']) {
                $this->prterror(\App\Translator::trans('error.post_interval_too_short'));
            }
            /*            if ((CURRENT_TIME - $timestamp ) > $this->c['MAXPOSTSEC'] ) {
                            $this->prterror ( 'The time between posts is too long. Please try again.');
                            $posterr = 2;
                            return $posterr;
                        } */
        }

        if (trim((string) $this->f['v']) == '') {
            $posterr = 2;
            return $posterr;
        }

        ## if ($this->c['NGWORD']) {
        ##     foreach ($this->c['NGWORD'] as $ngword) {
        ##         if (strpos($this->f['v'], $ngword) !== FALSE
        ##             or strpos($this->f['l'], $ngword) !== FALSE
        ##             or strpos($this->f['t'], $ngword) !== FALSE
        ##             or strpos($this->f['u'], $ngword) !== FALSE
        ##             or strpos($this->f['i'], $ngword) !== FALSE) {
        ##             $this->prterror ( 'The post contains prohibited words.' );
        ##         }
        ##     }
        ## }
        if ($this->c['NGWORD']) { ## mod
            foreach ($this->c['NGWORD'] as $ngword) {
                $ngword = strtolower((string) $ngword); // Convert prohibited word to lowercase
                if (
                    str_contains(strtolower((string) $this->f['v']), $ngword) ||
                    str_contains(strtolower((string) $this->f['l']), $ngword) ||
                    str_contains(strtolower((string) $this->f['t']), $ngword) ||
                    str_contains(strtolower((string) $this->f['u']), $ngword) ||
                    str_contains(strtolower((string) $this->f['i']), $ngword)
                ) {
                    $this->prterror(\App\Translator::trans('error.prohibited_words'));
                }
            }
        } ## mod end

        #20240204 猫 spam detection (https://php.o0o0.jp/article/php-spam)
        # Number of characters: char_num = mb_strlen( $this->f['v'], 'UTF8');
        # Number of bytes: byte_num = strlen( $this->f['v']);

        ## $char_num = mb_strlen( $this->f['v'], 'UTF8');
        ## $byte_num = strlen( $this->f['v']);

        # When single-byte characters makes up more than 90% of the total
        ## if ((($char_num * 3 - $byte_num) / 2 / $char_num * 100) > 90) {
        ##     # Treat as spam
        ##     $this->prterror('This bulletin board\'s post function is currently disabled.');
        ## }
        ## disabled by TL: not suitable for languages that use single-byte characters (i.e. English)


        return $posterr;
    }

    /**
     * Get message from form input
     *
     * @access  public
     * @return  Array  Message array
     */
    public function getformmessage()
    {

        $message = [];
        $message['PCODE'] = $this->f['pc'];
        $message['USER'] = $this->f['u'];
        $message['MAIL'] = $this->f['i'];
        $message['TITLE'] = $this->f['t'];
        $message['MSG'] = $this->f['v'];
        $message['URL'] = $this->f['l'];
        $message['PHOST'] = $this->s['HOST'];
        $message['AGENT'] = $this->s['AGENT'];
        # Reference ID
        if ($this->f['f']) {
            $message['REFID'] = $this->f['f'];
        } else {
            $message['REFID'] = '';
        }
        # Protect code
        $message['PCODE'] = substr((string) $message['PCODE'], 8, 4);
        # Title
        if (!$message['TITLE']) {
            $message['TITLE'] = ' ';
        }
        # User
        if (!$message['USER']) {
            $message['USER'] = $this->c['ANONY_NAME'];
        } else {
            # Admin check
            if ($this->c['ADMINPOST'] and crypt((string) $message['USER'], (string) $this->c['ADMINPOST']) == $this->c['ADMINPOST']) {
                $message['USER'] = "<span class=\"muh\">{$this->c['ADMINNAME']}</span>";
                # Enter admin mode
                if ($this->c['ADMINKEY'] and trim((string) $message['MSG']) == $this->c['ADMINKEY']) {
                    return 3;
                }
            } elseif ($this->c['ADMINPOST'] and $message['USER'] == $this->c['ADMINPOST']) {
                $message['USER'] = $this->c['ADMINNAME'] . '<span class="muh"> (hacker)</span>';
            } elseif (!(!str_contains((string) $message['USER'], (string) $this->c['ADMINNAME']))) {
                $message['USER'] = $this->c['ADMINNAME'] . '<span class="muh"> (fraudster)</span>';
            }
            # Fixed handle name check
            elseif ($this->c['HANDLENAMES'][trim((string) $message['USER'])]) {
                $message['USER'] .= '<span class="muh"> (fraudster)</span>';
            }
            # Trip function (simple deception prevention function)
            elseif (str_contains((string) $message['USER'], '#')) {
                #20210702 猫・管理パスばれ防止
                if ($this->c['ADMINPOST'] and crypt(substr((string) $message['USER'], 0, strpos((string) $message['USER'], '#')), (string) $this->c['ADMINPOST']) == $this->c['ADMINPOST']) {
                    $message['USER'] = "<span class=\"muh\"><a href=\"mailto:{$this->c['ADMINMAIL']}\">{$this->c['ADMINNAME']}</a></span>".substr((string) $message['USER'], strpos((string) $message['USER'], '#'));
                }
                #20210923 猫・固定ハンドル名 パスばれ防止
                # 固定ハンドル名変換
                elseif (isset($this->c['HANDLENAMES'])) {
                    $handlename = array_search(trim(substr((string) $message['USER'], 0, strpos((string) $message['USER'], '#'))), $this->c['HANDLENAMES']);
                    if ($handlename !== false) {
                        $message['USER'] = "<span class=\"muh\">{$handlename}</span>".substr((string) $message['USER'], strpos((string) $message['USER'], '#'));
                    }
                }
                $message['USER'] = substr((string) $message['USER'], 0, strpos((string) $message['USER'], '#')) . ' <span class="mut">◆' . substr((string) preg_replace("/\W/", '', crypt(substr((string) $message['USER'], strpos((string) $message['USER'], '#')), '00')), -7) .$this->tripuse($message['USER']). '</span>';
            } elseif (str_contains((string) $message['USER'], '◆')) {
                $message['USER'] .= ' (fraudster)';
            }
            # Fixed handle name conversion
            elseif (isset($this->c['HANDLENAMES'])) {
                $handlename = array_search(trim((string) $message['USER']), $this->c['HANDLENAMES']);
                if ($handlename !== false) {
                    $message['USER'] = "<span class=\"muh\">{$handlename}</span>";
                }
            }
        }
        $message['MSG'] = rtrim((string) $message['MSG']);

        # Auto-link URLs
        if ($this->c['AUTOLINK']) {
            $message['MSG'] = preg_replace(
                "/((https?|ftp|news):\/\/[-_.,!~*'()a-zA-Z0-9;\/?:\@&=+\$,%#]+)/",
                "<a href=\"$1\" target=\"link\">$1</a>",
                $message['MSG']
            );
        }
        # URL field
        $message['URL'] = trim((string) $message['URL']);
        if ($message['URL']) {
            $message['MSG'] .= "\r\r<a href=\"".\App\Utils\StringHelper::escapeUrl($message['URL'])."\" target=\"link\">{$message['URL']}</a>";
        }
        # Reference
        if ($message['REFID']) {
            $refdata = $this->searchmessage('POSTID', $message['REFID'], false, $this->f['ff']);
            if (!$refdata) {
                $this->prterror(\App\Translator::trans('error.reference_not_found'));
            }
            $refmessage = $this->getmessage($refdata[0]);
            $refmessage['WDATE'] = \App\Utils\DateHelper::getDateString($refmessage['NDATE'], $this->c['DATEFORMAT']);
            $message['MSG'] .= "\r\r<a href=\"m=f&s={$message['REFID']}&r=&\">Reference: {$refmessage['WDATE']}</a>";
            # Simple self-reply prevention function
            if ($this->c['IPREC'] and $this->c['SHOW_SELFFOLLOW']
                and $refmessage['PHOST'] != '' and $refmessage['PHOST'] == $message['PHOST']) {
                $message['USER'] .= '<span class="muh"> (self-reply)</span>';
            }
        }
        # Check
        if (strlen((string) $message['MSG']) > $this->c['MAXMSGSIZE']) {
            $this->prterror('The post contents are too large.');
        }
        return $message;
    }

    /**
     * Message registration process
     *
     * @access  public
     * @return  Integer  Error code
     */
    public function putmessage($message)
    {
        if (!is_array($message)) {
            return $message;
        }
        $fh = @fopen($this->c['LOGFILENAME'], "rb+");
        if (!$fh) {
            $this->prterror(\App\Translator::trans('error.failed_to_read'));
        }
        flock($fh, 2);
        fseek($fh, 0, 0);

        $logdata = [];
        while (($logline = \App\Utils\FileHelper::getLine($fh)) !== false) {
            $logdata[] = $logline;
        }
        $posterr = 0;
        if ($this->f['ff']) {
            $refdata = $this->searchmessage('THREAD', $message['REFID'], false, $this->f['ff']);
            if (isset($refdata[0])) {
                $refmessage = $this->getmessage($refdata[0]);
                if ($refmessage) {
                    $message['THREAD'] = $refmessage['thread'];
                } else {
                    $message['THREAD'] = '';
                }
            } else {
                $message['THREAD'] = '';
            }
        } else {
            for ($i = 0; $i < count($logdata); $i++) {
                $items = @explode(',', $logdata[$i]);
                if (count($items) > 8) {
                    $items[9] = rtrim($items[9]);
                    if ($i < $this->c['CHECKCOUNT'] and $message['MSG'] == $items[9]) {
                        $posterr = 1;
                        break;
                    }
                    if ($this->c['IPREC'] and CURRENT_TIME < ($items[0] + $this->c['SPTIME'])
                        and $this->s['HOST'] == $items[4]) {
                        $posterr = 2;
                        break;
                    }
                    if ($message['PCODE'] == $items[2]) {
                        $posterr = 2;
                        break;
                    }
                    if ($message['REFID'] and $items[1] == $message['REFID']) {
                        $message['THREAD'] = $items[3];
                        if (!$message['THREAD']) {
                            $message['THREAD'] = $items[1];
                        }
                    }
                }
            }
        }
        if ($posterr) {
            flock($fh, 3);
            fclose($fh);
            return $posterr;
        } else {
            $items = @explode(',', $logdata[0], 3);
            $message['POSTID'] = $items[1] + 1;
            if (!$message['REFID']) {
                $message['THREAD'] = $message['POSTID'];
            }
            $msgdata = implode(',', [
                CURRENT_TIME,
                $message['POSTID'],
                $message['PCODE'],
                $message['THREAD'],
                $message['PHOST'],
                str_replace(',', '&#44;', $message['AGENT']),
                str_replace(',', '&#44;', $message['USER']),
                str_replace(',', '&#44;', $message['MAIL']),
                str_replace(',', '&#44;', $message['TITLE']),
                str_replace(',', '&#44;', $message['MSG']),
                $message['REFID'],
            ]);
            $msgdata = str_replace("\n", "", $msgdata) . "\n";
            if (count($logdata) >= $this->c['LOGSAVE']) {
                $logdata = array_slice($logdata, 0, $this->c['LOGSAVE'] - 2);
            }
            {
                $logdata = $msgdata . implode('', $logdata);
                fseek($fh, 0, 0);
                ftruncate($fh, 0);
                fwrite($fh, $logdata);
            }
            flock($fh, 3);
            fclose($fh);
            # Cookie registration
            if ($this->c['COOKIE']) {
                $this->setbbscookie();
                if ($this->c['ALLOW_UNDO']) {
                    $this->setundocookie($message['POSTID'], $message['PCODE']);
                }
            }

            # Message log output
            if ($this->c['OLDLOGFILEDIR']) {
                $dir = $this->c['OLDLOGFILEDIR'];

                if ($this->c['OLDLOGFMT']) {
                    $oldlogext = 'dat';
                } else {
                    $oldlogext = 'html';
                }
                if ($this->c['OLDLOGSAVESW']) {
                    $oldlogfilename = $dir . date("Ym", CURRENT_TIME) . ".$oldlogext";
                    $oldlogtitle = $this->c['BBSTITLE'] . date(" Y.m", CURRENT_TIME);
                } else {
                    $oldlogfilename = $dir . date("Ymd", CURRENT_TIME) . ".$oldlogext";
                    $oldlogtitle = $this->c['BBSTITLE'] . date(" Y.m.d", CURRENT_TIME);
                }
                if (@filesize($oldlogfilename) > $this->c['MAXOLDLOGSIZE']) {
                    $this->prterror(\App\Translator::trans('error.log_size_limit'));
                }
                $fh = @fopen($oldlogfilename, "ab");
                if (!$fh) {
                    $this->prterror(\App\Translator::trans('error.log_output_failed'));
                }
                flock($fh, 2);
                $isnewdate = false;
                if (!@filesize($oldlogfilename)) {
                    $isnewdate = true;
                }
                if ($this->c['OLDLOGFMT']) {
                    fwrite($fh, $msgdata);
                } else {
                    # HTML header for HTML output
                    if ($isnewdate) {
                        $oldloghtmlhead = $this->prthtmlhead($oldlogtitle);
                        $oldloghtmlhead .= "<span class=\"pagetitle\">$oldlogtitle</span>\n\n<hr />\n";
                        fwrite($fh, $oldloghtmlhead);
                    }
                    $msghtml = $this->prtmessage($this->getmessage($msgdata), 3);
                    fwrite($fh, $msghtml);
                }
                flock($fh, 3);
                fclose($fh);
                if (@filesize($oldlogfilename) > $this->c['MAXOLDLOGSIZE']) {
                    @chmod($oldlogfilename, 0400);
                }
                # Delete old log files
                if (!$this->c['OLDLOGSAVESW'] and $isnewdate) {
                    $limitdate = CURRENT_TIME - $this->c['OLDLOGSAVEDAY'] * 60 * 60 * 24;
                    $limitdate = date("Ymd", $limitdate);
                    $dh = opendir($dir);
                    while ($entry = readdir($dh)) {
                        $matches = [];
                        if (is_file($dir . $entry)
                            and preg_match("/(\d+)\.$oldlogext$/", $entry, $matches)) {
                            $timestamp = $matches[1];
                            if (strlen($timestamp) == strlen($limitdate) and $timestamp < $limitdate) {
                                unlink($dir . $entry);
                            }
                        }
                    }
                    closedir($dh);
                }

                # Archive creation
                if ($this->c['ZIPDIR'] and @function_exists('gzcompress')) {
                    # In the case of dat, it also writes the message log in HTML format as a temporary file to be saved in the ZIP
                    if ($this->c['OLDLOGFMT']) {
                        if ($this->c['OLDLOGSAVESW']) {
                            $tmplogfilename = $this->c['ZIPDIR'] . date("Ym", CURRENT_TIME) . ".html";
                        } else {
                            $tmplogfilename = $this->c['ZIPDIR'] . date("Ymd", CURRENT_TIME) . ".html";
                        }

                        $fhtmp = @fopen($tmplogfilename, "ab");
                        if (!$fhtmp) {
                            return;
                        }
                        flock($fhtmp, 2);

                        if (!@filesize($tmplogfilename)) {
                            $oldloghtmlhead = $this->prthtmlhead($oldlogtitle);
                            $oldloghtmlhead .= "<span class=\"pagetitle\">$oldlogtitle</span>\n\n<hr />\n";
                            fwrite($fhtmp, $oldloghtmlhead);
                        }
                        $msghtml = $this->prtmessage($this->getmessage($msgdata), 3);
                        fwrite($fhtmp, $msghtml);
                        flock($fhtmp, 3);
                        fclose($fhtmp);
                    }
                    $tmpdir = $dir;
                    if ($this->c['OLDLOGFMT']) {
                        $tmpdir = $this->c['ZIPDIR'];
                    }
                    if ($this->c['OLDLOGSAVESW']) {
                        $currentfile = date("Ym", CURRENT_TIME) . ".html";
                    } else {
                        $currentfile = date("Ymd", CURRENT_TIME) . ".html";
                    }

                    $files = [];
                    $dh = opendir($tmpdir);
                    if (!$dh) {
                        return;
                    }
                    while ($entry = readdir($dh)) {
                        if ($entry != $currentfile and is_file($tmpdir . $entry) and preg_match("/^\d+\.html$/", $entry)) {
                            $files[] = $entry;
                        }
                    }
                    closedir($dh);

                    # File with the latest update time, other than the current log
                    $maxftime = 0;
                    foreach ($files as $filename) {
                        $fstat = stat($tmpdir . $filename);
                        if ($fstat[9] > $maxftime) {
                            $maxftime = $fstat[9];
                            $checkedfile = $tmpdir . $filename;
                        }
                    }
                    if (!$checkedfile) {
                        return;
                    }
                    $zipfilename = preg_replace("/\.\w+$/", ".zip", $checkedfile);

                    # Create a ZIP file
                    require_once(LIB_PHPZIP);
                    $zip = new PHPZip();
                    $zipfiles[] = $checkedfile;
                    $zip->Zip($zipfiles, $zipfilename);

                    # Delete temporary files
                    if ($this->c['OLDLOGFMT']) {
                        unlink($checkedfile);
                    }
                }
            }
        }
        return 0;
    }

    /**
     * Get environment variables
     */
    public function setuserenv()
    {

        if ($this->c['UAREC']) {
            $agent = $_SERVER['HTTP_USER_AGENT'];
            $agent = \App\Utils\StringHelper::htmlEscape($agent);
            $this->s['AGENT'] = $agent;
        }
        if (!$this->c['IPREC']) {
            return;
        }
        [$addr, $host, $proxyflg, $realaddr, $realhost] = \App\Utils\NetworkHelper::getUserEnv();

        $this->s['ADDR'] = $addr;
        $this->s['HOST'] = $host;
        $this->s['PROXYFLG'] = $proxyflg;
        $this->s['REALADDR'] = $realaddr;
        $this->s['REALHOST'] = $realhost;
    }

    /**
     * Bulletin board cookie registration
     */
    public function setbbscookie()
    {
        $cookiestr = "u=" . urlencode((string) $this->f['u']);
        $cookiestr .= "&i=" . urlencode((string) $this->f['i']);
        $cookiestr .= "&c=" . $this->f['c'];
        setcookie('c', $cookiestr, CURRENT_TIME + 7776000); // expires in 90 days
    }

    /**
     * Register cookie for post UNDO
     */
    public function setundocookie($undoid, $pcode)
    {
        $undokey = substr((string) preg_replace("/\W/", "", crypt((string) $pcode, (string) $this->c['ADMINPOST'])), -8);
        $cookiestr = "p=$undoid&k=$undokey";
        $this->s['UNDO_P'] = $undoid;
        $this->s['UNDO_K'] = $undokey;
        setcookie('undo', $cookiestr, CURRENT_TIME + 86400); // expires in 24 hours
    }

    /**
     * Bulletproof counter process
     *
     * @access  public
     * @param   Integer Bulletproof level
     * @return  String  Counter value
     */
    public function counter($countlevel = 0)
    {
        if (!$countlevel) {
            if (isset($this->c['COUNTLEVEL'])) {
                $countlevel = $this->c['COUNTLEVEL'];
            }
            if ($countlevel < 1) {
                $countlevel = 1;
            }
        }
        $count = [];
        for ($i = 0; $i < $countlevel; $i++) {
            $filename = "{$this->c['COUNTFILE']}{$i}.dat";
            if (is_writable($filename) and $fh = @fopen($filename, "r")) {
                $count[$i] = fgets($fh, 10);
                fclose($fh);
            } else {
                $count[$i] = 0;
            }
            $filenumber[$count[$i]] = $i;
        }
        sort($count, SORT_NUMERIC);
        $mincount = $count[0];
        $maxcount = $count[$countlevel - 1] + 1;
        if ($fh = @fopen("{$this->c['COUNTFILE']}{$filenumber[$mincount]}.dat", "w")) {
            fputs($fh, $maxcount);
            fclose($fh);
            return $maxcount;
        } else {
            return 'Counter error';
        }
    }

    /**
     * Participant count (currently viewing)
     *
     * @access  public
     * @param   $cntfilename  Record file name
     * @return  String  Number of participants
     */
    public function mbrcount($cntfilename = "")
    {
        if (!$cntfilename) {
            $cntfilename = $this->c['CNTFILENAME'];
        }
        if ($cntfilename) {
            $mbrcount = 0;
            $remoteaddr = '0.0.0.0';
            if ($_SERVER['REMOTE_ADDR']) {
                $remoteaddr = $_SERVER['REMOTE_ADDR'];
            }
            $ukey = hexdec(substr(md5((string) $remoteaddr), 0, 8));
            $newcntdata = [];
            if (is_writable($cntfilename)) {
                $cntdata = file($cntfilename);
                $cadd = 0;
                foreach ($cntdata as $cntvalue) {
                    if (strrpos($cntvalue, ',') !== false) {
                        [$cuser, $ctime, ] = @explode(',', trim($cntvalue));
                        if ($cuser == $ukey) {
                            $newcntdata[] = "$ukey,".CURRENT_TIME."\n";
                            $cadd = 1;
                            $mbrcount++;
                        } elseif (($ctime + $this->c['CNTLIMIT']) >= CURRENT_TIME) {
                            $newcntdata[] = "$cuser,$ctime\n";
                            $mbrcount++;
                        }
                    }
                }
                if (!$cadd) {
                    $newcntdata[] = "$ukey,".CURRENT_TIME."\n";
                    $mbrcount++;
                }
            } else {
                $newcntdata[] = "$ukey,".CURRENT_TIME."\n";
                $mbrcount++;
            }
            if ($fh = @fopen($cntfilename, "w")) {
                $cntdatastr = implode('', $newcntdata);
                flock($fh, 2);
                fwrite($fh, $cntdatastr);
                flock($fh, 3);
                fclose($fh);
            } else {
                return ('Participant file output error');
            }
            return $mbrcount;
        } else {
            return;
        }
    }
}
