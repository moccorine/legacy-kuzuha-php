<?php

namespace Kuzuha;

use App\Config;
use App\Translator;
use App\Utils\DateHelper;
use App\Utils\FileHelper;
use App\Utils\NetworkHelper;
use App\Utils\SecurityHelper;
use App\Utils\StringHelper;

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

        # If ADMINPOST is empty and not setting password, show password setup page
        if (empty($this->config['ADMINPOST']) && $this->form['m'] != 'ad') {
            $bbsadmin = new Bbsadmin($this);
            $bbsadmin->prtsetpass();
            return;
        }

        # gzip compression transfer
        if ($this->config['GZIPU']) {
            ob_start('ob_gzhandler');
        }
        # Post operation
        if ($this->form['m'] == 'p' and trim((string) $this->form['v'])) {
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
                if ($this->form['f']) {
                    $this->prtfollow(true);
                } elseif ($this->form['write']) {
                    $this->prtnewpost(true);
                } else {
                    $this->prtmain(true);
                }
            }
            # Entering admin mode
            elseif ($posterr == 3) {
                define('BBS_ACTIVATED', true);
                $bbsadmin = new Bbsadmin($this);
                $bbsadmin->main();
            }
            # Post completion page
            elseif ($this->form['f']) {
                $this->prtputcomplete();
            } else {
                $this->prtmain();
            }
        }
        # Display follow-up page
        elseif ($this->form['m'] == 'f') {
            $this->prtfollow();
        }
        # Message log search
        elseif ($this->form['m'] == 'g') {
            $getlog = new \Kuzuha\Getlog();
            $getlog->main();
            return;
        }
        # Tree view
        elseif ($this->form['m'] == 'tree') {
            $treeview = new \Kuzuha\Treeview();
            $treeview->main();
            return;
        }
        # Admin mode
        elseif ($this->form['m'] == 'ad') {
            $bbsadmin = new Bbsadmin($this);
            $bbsadmin->main();
            return;
        }
        # Post search
        elseif ($this->form['m'] == 't' or $this->form['m'] == 's') {
            $this->prtsearchlist();
        }
        # Display user settings page
        elseif ($this->form['setup']) {
            $this->prtcustom();
        }
        # User settings process
        elseif ($this->form['m'] == 'c') {
            $this->setcustom();
        }
        # New post
        elseif ($this->form['m'] == 'p' and $this->form['write']) {
            $this->prtnewpost();
        }
        # UNDO process
        elseif ($this->form['m'] == 'u') {
            $this->prtundo();
        }
        # Default: bulletin board display
        else {
            $this->prtmain();
        }

        if ($this->config['GZIPU']) {
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
        $dtitle = '';
        $dmsg = '';
        $dlink = '';
        if ($retry) {
            $dtitle = $this->form['t'];
            $dmsg = $this->form['v'];
            $dlink = $this->form['l'];
        }
        
        # Get form HTML using Twig
        $formData = $this->getFormData($dtitle, $dmsg, $dlink);
        $formHtml = $this->renderTwig('components/form.twig', $formData);

        # HTML header partial output
        $this->sethttpheader();

        # Upper main section
        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'],
            'CUSTOMSTYLE' => '',
            'CUSTOMHEAD' => '',
            'FORM' => $formHtml,
            'TRANS_PR_OFFICE' => Translator::trans('main.pr_office'),
            'TRANS_PR_OFFICE_TITLE' => Translator::trans('main.pr_office_title'),
            'TRANS_EMAIL_ADMIN' => Translator::trans('main.email_admin'),
            'TRANS_CONTACT' => Translator::trans('main.contact'),
            'TRANS_MESSAGE_LOGS' => Translator::trans('main.message_logs'),
            'TRANS_MESSAGE_LOGS_TITLE' => Translator::trans('main.message_logs_title'),
            'TRANS_TREE_VIEW' => Translator::trans('main.tree_view'),
            'TRANS_TREE_VIEW_TITLE' => Translator::trans('main.tree_view_title'),
            'TRANS_BOTTOM' => Translator::trans('main.bottom'),
        ]);
        echo $this->renderTwig('main/upper.twig', $data);

        # Display message
        foreach ($logdatadisp as $msgdata) {
            print $this->prtmessage($this->getmessage($msgdata), 0, 0);
        }
        # Message information
        if ($this->session['MSGDISP'] < 0) {
            $msgmore = '';
        } elseif ($eindex > 0) {
            $msgmore = Translator::trans('main.shown_posts', ['%bindex%' => $bindex, '%eindex%' => $eindex]);
        } else {
            $msgmore = Translator::trans('main.no_unread_messages');
        }
        if ($eindex >= $lastindex) {
            $msgmore .= Translator::trans('main.no_posts_below');
        }

        # Navigation buttons
        $showNextPage = false;
        if ($eindex > 0) {
            if ($eindex < $lastindex) {
                $showNextPage = true;
            }
            $showReadNew = $this->config['SHOW_READNEWBTN'];
        } else {
            $showReadNew = false;
        }

        # Post as administrator
        $showAdminLogin = ($this->config['BBSMODE_ADMINONLY'] != 0);

        # Duration
        $duration = null;
        $transPageGenerationTime = '';
        if ($this->config['SHOW_PRCTIME'] && $this->session['START_TIME']) {
            $duration = DateHelper::microtimeDiff($this->session['START_TIME'], microtime());
            $duration = sprintf('%0.6f', $duration);
            $transPageGenerationTime = Translator::trans('main.page_generation_time', ['%duration%' => $duration]);
        }

        # Lower main section
        $data = array_merge($this->config, $this->session, [
            'MSGMORE' => $msgmore,
            'SHOW_NEXTPAGE' => $showNextPage,
            'EINDEX' => $eindex ?? '',
            'SHOW_READNEW' => $showReadNew,
            'SHOW_ADMINLOGIN' => $showAdminLogin,
            'DURATION' => $duration,
            'TRANS_PAGE_GENERATION_TIME' => $transPageGenerationTime,
            'TRANS_NEXT_PAGE' => Translator::trans('main.next_page'),
            'TRANS_RELOAD' => Translator::trans('main.reload'),
            'TRANS_UNREAD' => Translator::trans('main.unread'),
            'TRANS_TOP' => Translator::trans('main.top'),
            'TRANS_POST_AS_ADMIN' => Translator::trans('main.post_as_admin'),
        ]);
        echo $this->renderTwig('main/lower.twig', $data);
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
        $msgdisp = StringHelper::fixNumberString($this->form['d']);
        if ($msgdisp === false) {
            $msgdisp = $this->config['MSGDISP'];
        } elseif ($msgdisp < 0) {
            $msgdisp = $this->config['MSGDISP'];
        } elseif ($msgdisp > $this->config['LOGSAVE']) {
            $msgdisp = $this->config['LOGSAVE'];
        }
        if ($this->form['readzero']) {
            $msgdisp = 0;
        }
        # Beginning of index
        $bindex = $this->form['b'];
        if (!$bindex) {
            $bindex = 0;
        }
        # For the next and subsequent pages
        if ($bindex > 1) {
            # If there are new posts, shift the beginning of the index
            if ($toppostid > $this->form['p']) {
                $bindex += ($toppostid - $this->form['p']);
            }
            # Don't update unread pointer
            $toppostid = $this->form['p'];
        }
        # End of index
        $eindex = $bindex + $msgdisp;
        # Unread reload
        if ($this->form['readnew'] or ($msgdisp == '0' and $bindex == 0)) {
            $bindex = 0;
            $eindex = $toppostid - $this->form['p'];
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
            if ($this->config['RELTYPE'] and ($this->form['readnew'] or ($msgdisp == '0' and $bindex == 0))) {
                $logdatadisp = array_reverse($logdatadisp);
            }
        }
        $this->session['TOPPOSTID'] = $toppostid;
        $this->session['MSGDISP'] = $msgdisp;
        $this->template->addGlobalVars([
            'TOPPOSTID' => $this->session['TOPPOSTID'],
            'MSGDISP' => $this->session['MSGDISP'],
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
    /**
     * Prepare form data for Twig rendering
     */
    protected function getFormData($dtitle, $dmsg, $dlink, $mode = '')
    {
        # Protect code generation
        $pcode = SecurityHelper::generateProtectCode();
        if (!$mode) {
            $mode = '<input type="hidden" name="m" value="p" />';
        }
        
        # Hide post form
        $hideForm = ($this->config['HIDEFORM'] && $this->form['m'] != 'f' && !$this->form['write']);
        
        # Counter and member count
        $showCounter = false;
        $counter = '';
        if ($this->config['SHOW_COUNTER']) {
            $counter = number_format($this->counter());
            $showCounter = true;
        }
        
        $showMbrCount = false;
        $mbrcount = '';
        if ($this->config['CNTFILENAME']) {
            $mbrcount = number_format($this->mbrcount());
            $showMbrCount = true;
        }
        
        # Checkboxes
        $chkA = $this->config['AUTOLINK'] ? ' checked="checked"' : '';
        $chkHide = $this->config['HIDEFORM'] ? ' checked="checked"' : '';
        $chkLoff = $this->config['LINKOFF'] ? ' checked="checked"' : '';
        $chkSi = $this->config['SHOWIMG'] ? ' checked="checked"' : '';
        
        # Visibility flags
        $showFormConfig = ($this->config['BBSMODE_ADMINONLY'] == 0);
        $showLinkRow = !$this->config['LINKOFF'];
        $showHelp = ($this->config['BBSMODE_ADMINONLY'] != 1);
        $showUndo = $this->config['ALLOW_UNDO'];
        $showSiCheck = isset($this->config['SHOWIMG']);
        
        # Kaomoji buttons
        $kaomojiButtons = $this->generateKaomojiButtons();
        
        return array_merge($this->config, $this->session, [
            'MODE' => $mode,
            'PCODE' => $pcode,
            'HIDEFORM' => $hideForm ? 1 : 0,
            'DTITLE' => $dtitle,
            'DMSG' => $dmsg,
            'DLINK' => $dlink,
            'SHOW_COUNTER' => $showCounter,
            'COUNTER' => $counter,
            'SHOW_MBRCOUNT' => $showMbrCount,
            'MBRCOUNT' => $mbrcount,
            'CHK_A' => $chkA,
            'CHK_HIDE' => $chkHide,
            'CHK_LOFF' => $chkLoff,
            'CHK_SI' => $chkSi,
            'SHOW_FORMCONFIG' => $showFormConfig,
            'SHOW_LINKROW' => $showLinkRow,
            'SHOW_HELP' => $showHelp,
            'SHOW_UNDO' => $showUndo,
            'SHOW_SICHECK' => $showSiCheck,
            'KAOMOJI_BUTTONS' => $kaomojiButtons,
            'BBSMODE_IMAGE' => $this->config['BBSMODE_IMAGE'] ?? 0,
            // Translations
            'TRANS_NAME' => Translator::trans('form.name'),
            'TRANS_NAME_TITLE' => Translator::trans('form.name_title'),
            'TRANS_EMAIL' => Translator::trans('form.email'),
            'TRANS_EMAIL_TITLE' => Translator::trans('form.email_title'),
            'TRANS_TITLE' => Translator::trans('form.title'),
            'TRANS_TITLE_TITLE' => Translator::trans('form.title_title'),
            'TRANS_POST_RELOAD' => Translator::trans('form.post_reload'),
            'TRANS_POST_RELOAD_TITLE' => Translator::trans('form.post_reload_title'),
            'TRANS_POST_RELOAD_TITLE_R' => Translator::trans('form.post_reload_title_r'),
            'TRANS_CLEAR' => Translator::trans('form.clear'),
            'TRANS_CLEAR_TITLE' => Translator::trans('form.clear_title'),
            'TRANS_CONTENTS' => Translator::trans('form.contents'),
            'TRANS_CONTENTS_TITLE' => Translator::trans('form.contents_title'),
            'TRANS_CONTENTS_HELP' => Translator::trans('form.contents_help', [
                '%maxcol%' => $this->config['MAXMSGCOL'],
                '%maxline%' => $this->config['MAXMSGLINE']
            ]),
            'TRANS_CONTENTS_HELP_IMAGE' => Translator::trans('form.contents_help_image', [
                '%maxcol%' => $this->config['MAXMSGCOL'],
                '%maxline%' => $this->config['MAXMSGLINE'],
                '%imagetext%' => $this->config['IMAGETEXT']
            ]),
            'TRANS_URL' => Translator::trans('form.url'),
            'TRANS_URL_TITLE' => Translator::trans('form.url_title'),
            'TRANS_IMAGE_UPLOAD' => Translator::trans('form.image_upload'),
            'TRANS_IMAGE_UPLOAD_TITLE' => Translator::trans('form.image_upload_title'),
            'TRANS_IMAGE_UPLOAD_HELP' => Translator::trans('form.image_upload_help', [
                '%max_width%' => $this->config['MAX_IMAGEWIDTH'],
                '%max_height%' => $this->config['MAX_IMAGEHEIGHT'],
                '%max_size%' => $this->config['MAX_IMAGESIZE']
            ]),
            'TRANS_POSTS_DISPLAYED' => Translator::trans('form.posts_displayed'),
            'TRANS_POSTS_DISPLAYED_TITLE' => Translator::trans('form.posts_displayed_title'),
            'TRANS_AUTO_LINK' => Translator::trans('form.auto_link'),
            'TRANS_AUTO_LINK_TITLE' => Translator::trans('form.auto_link_title'),
            'TRANS_LOG_READING' => Translator::trans('form.log_reading'),
            'TRANS_LOG_READING_TITLE' => Translator::trans('form.log_reading_title'),
            'TRANS_HIDE_LINK' => Translator::trans('form.hide_link'),
            'TRANS_HIDE_LINK_TITLE' => Translator::trans('form.hide_link_title'),
            'TRANS_SHOW_IMAGES' => Translator::trans('form.show_images'),
            'TRANS_SHOW_IMAGES_TITLE' => Translator::trans('form.show_images_title'),
            'TRANS_USER_SETTINGS' => Translator::trans('form.user_settings'),
            'TRANS_USER_SETTINGS_TITLE' => Translator::trans('form.user_settings_title'),
            'TRANS_PAGE_VIEWS' => Translator::trans('form.page_views'),
            'TRANS_BULLETPROOF_LEVEL' => Translator::trans('form.bulletproof_level'),
            'TRANS_CURRENT_PARTICIPANTS' => Translator::trans('form.current_participants'),
            'TRANS_USERS' => Translator::trans('form.users'),
            'TRANS_WITHIN' => Translator::trans('form.within'),
            'TRANS_SECONDS' => Translator::trans('form.seconds'),
            'TRANS_MAX_POSTS' => Translator::trans('form.max_posts'),
            'TRANS_POSTS' => Translator::trans('form.posts'),
            'TRANS_TO_PR_OFFICE' => Translator::trans('form.to_pr_office'),
            'TRANS_PR_OFFICE' => Translator::trans('form.pr_office'),
            'TRANS_MESSAGE_LOGS_TITLE' => Translator::trans('form.message_logs_title'),
            'TRANS_MESSAGE_LOGS' => Translator::trans('form.message_logs'),
            'TRANS_FOLLOW_HELP' => Translator::trans('form.follow_help'),
            'TRANS_FOLLOW_POST' => Translator::trans('form.follow_post'),
            'TRANS_AUTHOR_HELP' => Translator::trans('form.author_help'),
            'TRANS_SEARCH_BY_USER' => Translator::trans('form.search_by_user'),
            'TRANS_THREAD_HELP' => Translator::trans('form.thread_help'),
            'TRANS_THREAD' => Translator::trans('form.thread'),
            'TRANS_TREE_HELP' => Translator::trans('form.tree_help'),
            'TRANS_TREE' => Translator::trans('form.tree'),
            'TRANS_UNDO_HELP' => Translator::trans('form.undo_help'),
            'TRANS_DELETE_PREVIOUS' => Translator::trans('form.delete_previous'),
            'TRANS_RELOAD' => Translator::trans('form.reload'),
            'TRANS_RELOAD_TITLE' => Translator::trans('form.reload_title'),
            'TRANS_UNREAD' => Translator::trans('form.unread'),
            'TRANS_BOTTOM_BTN' => Translator::trans('form.bottom_btn'),
            'TRANS_LATEST_30' => Translator::trans('form.latest_30'),
        ]);
    }

    /**
     * Generate kaomoji buttons HTML
     */
    private function generateKaomojiButtons()
    {
        $kaomojis = [
            ['ヽ(´ー｀)ノ', '(´ー`)', '(;´Д`)', 'ヽ(´∇`)ノ', '(´∇`)σ', '(＾Д^)'],
            ['(;^Д^)', '(ﾉД^､)σ', '(ﾟ∇ﾟ)', '(;ﾟ∇ﾟ)', 'Σ(;ﾟ∇ﾟ)', '(;ﾟДﾟ)', 'Σ(;ﾟДﾟ)'],
            ['(｀∇´)', '(｀ー´)', '(｀～´)', '(;`-´)', 'ヽ(`Д´)ノ', '(`Д´)'],
            ['(;`Д´)', '(ﾟ血ﾟ#)', '(╬⊙Д⊙)', '(ρ_;)', '(TДT)', '(ﾉД`､)', '(´Д`)'],
            ['(´-｀)', '(´￢`)', 'ヽ(ﾟρﾟ)ノ', '(ﾟー｀)', '(´π｀)', '(ﾟДﾟ)', '(ﾟへﾟ)'],
            ['(ﾟーﾟ)', '(ﾟｰﾟ)', '(*\'ｰ\')', '(\'ｰ\')', '(´人｀)', 'ъ( ﾟｰ^)', '（⌒∇⌒ゞ）'],
            ['(^^;ﾜﾗ', 'ε≡三ヽ(´ー`)ﾉ', 'ε≡Ξヽ( ^Д^)ノ', 'ヽ(´Д`;)ノΞ≡3'],
            ['(・∀・)', '( ´ω`)', 'Σ(ﾟдﾟlll)', '(´～`)', '┐(ﾟ～ﾟ)┌'],
        ];
        
        $html = '';
        foreach ($kaomojis as $row) {
            foreach ($row as $kaomoji) {
                $escaped = htmlspecialchars($kaomoji, ENT_QUOTES, 'UTF-8');
                $html .= "<input type=\"button\" class=\"kaomoji\" onClick=\"insertThisInThere('{$escaped}','contents1')\" value=\"{$escaped}\" />\n\t\t";
            }
            $html .= "<br />\n";
        }
        
        return $html;
    }

    public function setform($dtitle, $dmsg, $dlink, $mode = '')
    {
        # Protect code generation
        $pcode = SecurityHelper::generateProtectCode();
        if (!$mode) {
            $mode = '<input type="hidden" name="m" value="p" />';
        }
        
        # Generate kaomoji buttons HTML
        $kaomojiButtons = $this->generateKaomojiButtons();
        
        $this->template->addVars('form', [
            'MODE' => $mode,
            'PCODE' => $pcode,
        ]);
        # Hide post form
        if ($this->config['HIDEFORM'] and $this->form['m'] != 'f' and !$this->form['write']) {
            $this->template->addVar('postform', 'mode', 'hide');
        } else {
            $this->template->addVars('postform', [
                'DTITLE' => $dtitle,
                'DMSG' => $dmsg,
                'DLINK' => $dlink,
            ]);
        }
        # Settings and links lines
        if ($this->form['m'] != 'f' and !isset($this->form['f']) and !$this->form['write']) {
            # Counter
            if ($this->config['SHOW_COUNTER']) {
                $counter = $this->counter();
                $counter = number_format($counter);
                $this->template->addVar('counter', 'COUNTER', $counter);
                $this->template->setAttribute('counter', 'visibility', 'visible');
            }
            if ($this->config['CNTFILENAME']) {
                $mbrcount = $this->mbrcount();
                $mbrcount = number_format($mbrcount);
                $this->template->addVar('mbrcount', 'MBRCOUNT', $mbrcount);
                $this->template->setAttribute('mbrcount', 'visibility', 'visible');
            }
            if (!$this->config['SHOW_COUNTER'] and !$this->config['CNTFILENAME']) {
                $this->template->setAttribute('counterrow', 'visibility', 'hidden');
            }
            if ($this->config['BBSMODE_ADMINONLY'] == 0) {
                if ($this->config['AUTOLINK']) {
                    $this->template->addVar('formconfig', 'CHK_A', ' checked="checked"');
                }
                if ($this->config['HIDEFORM']) {
                    $this->template->addVar('formconfig', 'CHK_HIDE', ' checked="checked"');
                }
            } else {
                $this->template->setAttribute('formconfig', 'visibility', 'hidden');
            }
            # Hide link line
            if ($this->config['LINKOFF']) {
                $this->template->addVar('extraform', 'CHK_LOFF', ' checked="checked"');
                $this->template->setAttribute('linkrow', 'visibility', 'hidden');
            }
            # Hide help line
            if ($this->config['BBSMODE_ADMINONLY'] != 1) {
                if (!$this->config['ALLOW_UNDO']) {
                    $this->template->setAttribute('helpundo', 'visibility', 'hidden');
                }
            } else {
                $this->template->setAttribute('helprow', 'visibility', 'hidden');
            }
            # Navigation buttons line
            if (!$this->config['SHOW_READNEWBTN']) {
                $this->template->setAttribute('readnewbtn', 'visibility', 'hidden');
            }
            if (!($this->config['HIDEFORM'] and $this->config['BBSMODE_ADMINONLY'] == 0)) {
                $this->template->setAttribute('newpostbtn', 'visibility', 'hidden');
            }
        } else {
            $this->template->setAttribute('extraform', 'visibility', 'hidden');
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

        if (!$this->form['s']) {
            $this->prterror(Translator::trans('error.no_parameters'));
        }

        # Administrator authentication
        if ($this->config['BBSMODE_ADMINONLY'] == 1
            and crypt((string) $this->form['u'], (string) $this->config['ADMINPOST']) != $this->config['ADMINPOST']) {
            $this->prterror(Translator::trans('error.incorrect_password'));
        }
        $filename = '';
        if ($this->form['ff']) {
            $filename = trim((string) $this->form['ff']);
        }
        $result = $this->searchmessage('POSTID', $this->form['s'], false, $filename);
        if (!$result) {
            $this->prterror(Translator::trans('error.message_not_found'));
        }
        # Get message
        $message = $this->getmessage($result[0]);

        if (!$retry) {
            $formmsg = $message['MSG'];
            $formmsg = preg_replace("/&gt; &gt;[^\r]+\r/", '', (string) $formmsg);
            $formmsg = preg_replace("/<a href=\"m=f\S+\"[^>]*>[^<]+<\/a>/i", '', $formmsg);
            $formmsg = preg_replace("/<a href=\"[^>]+>([^<]+)<\/a>/i", '$1', $formmsg);
            $formmsg = preg_replace("/\r*<a href=[^>]+><img [^>]+><\/a>/i", '', $formmsg);
            $formmsg = preg_replace("/\r/", "\r> ", $formmsg);
            $formmsg = "> $formmsg\r";
            $formmsg = preg_replace("/\r>\s+\r/", "\r", $formmsg);
            $formmsg = preg_replace("/\r>\s+\r$/", "\r", (string) $formmsg);
        } else {
            $formmsg = $this->form['v'];
            $formmsg = preg_replace("/<a href=\"m=f\S+\"[^>]*>[^<]+<\/a>/i", '', (string) $formmsg);
        }
        $formmsg .= "\r";

        $this->setform('＞' . preg_replace('/<[^>]*>/', '', (string) $message['USER']) . $this->config['FSUBJ'], $formmsg, '');

        if (!$message['THREAD']) {
            $message['THREAD'] = $message['POSTID'];
        }
        $filename ? $mode = 1 : $mode = 0;

        // Get message HTML using Twig
        $messageHtml = $this->prtmessage($message, $mode, $filename);

        // Get form HTML using Twig
        $formData = $this->getFormData('＞' . preg_replace('/<[^>]*>/', '', (string) $message['USER']) . $this->config['FSUBJ'], $formmsg, '');
        $formHtml = $this->renderTwig('components/form.twig', $formData);
        
        // Add follow-specific hidden inputs before </form>
        $hiddenInputs = '<input type="hidden" name="f" value="' . htmlspecialchars($this->form['s']) . '" />';
        $hiddenInputs .= '<input type="hidden" name="ff" value="' . htmlspecialchars($this->form['ff']) . '" />';
        $hiddenInputs .= '<input type="hidden" name="s" value="' . htmlspecialchars($this->form['s']) . '" />';
        $formHtml = str_replace('</form>', $hiddenInputs . '</form>', $formHtml);

        $this->sethttpheader();
        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'] . ' ' . Translator::trans('follow.followup_post'),
            'MESSAGE' => $messageHtml,
            'FORM' => $formHtml,
            'TRANS_FOLLOWUP_POST' => Translator::trans('follow.followup_post'),
            'TRANS_RETURN' => Translator::trans('follow.return'),
        ]);
        echo $this->renderTwig('follow.twig', $data);
    }

    /**
     * Display new post page
     *
     * @access  public
     */
    public function prtnewpost($retry = false)
    {
        # Administrator authentication
        if ($this->config['BBSMODE_ADMINONLY'] != 0
            and crypt((string) $this->form['u'], (string) $this->config['ADMINPOST']) != $this->config['ADMINPOST']) {
            $this->prterror(Translator::trans('error.incorrect_password'));
        }
        # Form section
        $dtitle = '';
        $dmsg = '';
        $dlink = '';
        if ($retry) {
            $dtitle = $this->form['t'];
            $dmsg = $this->form['v'];
            $dlink = $this->form['l'];
        }
        
        // Get form HTML using Twig
        $formData = $this->getFormData($dtitle, $dmsg, $dlink);
        $formHtml = $this->renderTwig('components/form.twig', $formData);

        $this->sethttpheader();
        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'] . ' ' . Translator::trans('newpost.new_post'),
            'FORM' => $formHtml,
            'TRANS_NEW_POST' => Translator::trans('newpost.new_post'),
            'TRANS_RETURN' => Translator::trans('newpost.return'),
        ]);
        echo $this->renderTwig('newpost.twig', $data);
    }

    /**
     * Post search
     *
     * @param   Integer $mode       0: Bulletin board / 1: Message log search (with buttons displayed) / 2: Message log search (without buttons displayed) / 3: For message log file output
     */
    public function prtsearchlist($mode = '')
    {
        if (!$this->form['s']) {
            $this->prterror(Translator::trans('error.no_parameters'));
        }
        if (!$mode) {
            $mode = $this->form['m'];
        }

        $result = $this->msgsearchlist($mode);
        $messages = '';
        foreach ($result as $message) {
            $messages .= $this->prtmessage($message, $mode, $this->form['ff']);
        }
        $success = count($result);

        $this->sethttpheader();
        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'] . ' ' . Translator::trans('search.post_search'),
            'MESSAGES' => $messages,
            'SUCCESS' => $success,
            'TRANS_POSTS_FOUND' => Translator::trans('search.posts_found'),
            'TRANS_RETURN' => Translator::trans('search.return'),
        ]);
        echo $this->renderTwig('searchlist.twig', $data);
    }

    /**
     * Post search process
     */
    public function msgsearchlist($mode)
    {

        $fh = null;
        if ($this->form['ff']) {
            if (preg_match("/^[\w.]+$/", (string) $this->form['ff'])) {
                $fh = @fopen($this->config['OLDLOGFILEDIR'] . $this->form['ff'], 'rb');
            }
            if (!$fh) {
                $this->prterror(Translator::trans('error.file_open_failed', ['filename' => $this->form['ff']]));
            }
            flock($fh, 1);
        }

        $result = [];

        if ($fh) {
            $linecount = 0;
            $threadstart = false;
            while (($logline = FileHelper::getLine($fh)) !== false) {
                if ($threadstart) {
                    $linecount++;
                }
                if ($linecount > $this->config['LOGSAVE']) {
                    break;
                }
                $message = $this->getmessage($logline);
                # Search by user
                if ($mode == 's' and preg_replace('/<[^>]*>/', '', (string) $message['USER']) == $this->form['s']) {
                    $result[] = $message;
                }
                # Search by thread
                elseif ($mode == 't'
                    and ($message['THREAD'] == $this->form['s'] or $message['POSTID'] == $this->form['s'])) {
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
                if ($mode == 's' and preg_replace('/<[^>]*>/', '', (string) $message['USER']) == $this->form['s']) {
                    $result[] = $message;
                }
                # Search by thread
                elseif ($mode == 't'
                    and ($message['THREAD'] == $this->form['s'] or $message['POSTID'] == $this->form['s'])) {
                    $result[] = $message;
                    if ($message['POSTID'] == $this->form['s']) {
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
        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'] . ' ' . Translator::trans('complete.post_complete'),
            'TRANS_POST_COMPLETE' => Translator::trans('complete.post_complete'),
            'TRANS_RETURN_TO_BBS' => Translator::trans('complete.return_to_bbs'),
            'TRANS_RETURN_INSTRUCTION' => Translator::trans('complete.return_instruction'),
        ]);
        echo $this->renderTwig('postcomplete.twig', $data);
    }

    /**
     * Display user settings page
     */
    public function prtcustom($mode = '')
    {
        $this->sethttpheader();
        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'] . ' ' . Translator::trans('custom.user_settings'),
            'MODE' => $mode,
            'CHK_G' => $this->config['GZIPU'] ? ' checked="checked"' : '',
            'CHK_A' => $this->config['AUTOLINK'] ? ' checked="checked"' : '',
            'CHK_LOFF' => $this->config['LINKOFF'] ? ' checked="checked"' : '',
            'CHK_HIDE' => $this->config['HIDEFORM'] ? ' checked="checked"' : '',
            'CHK_SI' => $this->config['SHOWIMG'] ? ' checked="checked"' : '',
            'CHK_COOKIE' => $this->config['COOKIE'] ? ' checked="checked"' : '',
            'CHK_FW_0' => !$this->config['FOLLOWWIN'] ? ' checked="checked"' : '',
            'CHK_FW_1' => $this->config['FOLLOWWIN'] ? ' checked="checked"' : '',
            'CHK_RT_0' => !$this->config['RELTYPE'] ? ' checked="checked"' : '',
            'CHK_RT_1' => $this->config['RELTYPE'] ? ' checked="checked"' : '',
            'TRANS_USER_SETTINGS' => Translator::trans('custom.user_settings'),
            'TRANS_DISPLAY_COLORS' => Translator::trans('custom.display_colors'),
            'TRANS_TEXT_COLOR' => Translator::trans('custom.text_color'),
            'TRANS_BG_COLOR' => Translator::trans('custom.bg_color'),
            'TRANS_LINK_COLOR' => Translator::trans('custom.link_color'),
            'TRANS_VISITED_COLOR' => Translator::trans('custom.visited_color'),
            'TRANS_ACTIVE_COLOR' => Translator::trans('custom.active_color'),
            'TRANS_HOVER_COLOR' => Translator::trans('custom.hover_color'),
            'TRANS_TITLE_COLOR' => Translator::trans('custom.title_color'),
            'TRANS_QUOTE_COLOR' => Translator::trans('custom.quote_color'),
            'TRANS_ADDITIONAL_FEATURES' => Translator::trans('custom.additional_features'),
            'TRANS_POSTS_DISPLAYED' => Translator::trans('custom.posts_displayed'),
            'TRANS_GZIP' => Translator::trans('custom.gzip'),
            'TRANS_HIDE_FORM' => Translator::trans('custom.hide_form'),
            'TRANS_AUTOLINK' => Translator::trans('custom.autolink'),
            'TRANS_COOKIE' => Translator::trans('custom.cookie'),
            'TRANS_HIDE_LINKS' => Translator::trans('custom.hide_links'),
            'TRANS_FOLLOWUP_DISPLAY' => Translator::trans('custom.followup_display'),
            'TRANS_NEW_WINDOW' => Translator::trans('custom.new_window'),
            'TRANS_SAME_PAGE' => Translator::trans('custom.same_page'),
            'TRANS_BOOKMARK_INFO' => Translator::trans('custom.bookmark_info'),
            'TRANS_REGISTER' => Translator::trans('custom.register'),
            'TRANS_REGISTER_TITLE' => Translator::trans('custom.register_title'),
            'TRANS_UNDO' => Translator::trans('custom.undo'),
            'TRANS_UNDO_TITLE' => Translator::trans('custom.undo_title'),
            'TRANS_RESTORE' => Translator::trans('custom.restore'),
            'TRANS_RESTORE_TITLE' => Translator::trans('custom.restore_title'),
            'TRANS_RETURN' => Translator::trans('custom.return'),
        ]);
        echo $this->renderTwig('custom.twig', $data);
    }

    /**
     * User settings process
     */
    public function setcustom()
    {

        $redirecturl = $this->config['CGIURL'];

        # Cookie消去
        if ($this->form['cr']) {
            $this->form['c'] = '';
            setcookie('c');
            setcookie('undo');
            $this->session['UNDO_P'] = '';
            $this->session['UNDO_K'] = '';
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
                if (strlen((string) $this->form[$confname]) == 6 and preg_match('/^[0-9a-fA-F]{6}$/', (string) $this->form[$confname])
                    and $this->form[$confname] != $this->config[$confname]) {
                    $this->config[$confname] = $this->form[$confname];
                    $flgchgindex = $cindex;
                }
                $cindex++;
            }

            $cbase64str = '';
            for ($i = 0; $i <= $flgchgindex; $i++) {
                $cbase64str .= StringHelper::threeByteHexToBase64($this->config[$colors[$i]]);
            }
            $this->refcustom();

            $this->form['c'] = substr((string) $this->form['c'], 0, 2) . $cbase64str;

            $redirecturl .= '?c='.$this->form['c'];
            foreach (['w', 'd',] as $key) {
                if ($this->form[$key] != '') {
                    $redirecturl .= "&{$key}=".$this->form[$key];
                }
            }
            if ($this->form['nm']) {
                $redirecturl .= '&m='.$this->form['nm'];
            }
            if ($this->config['COOKIE']) {
                $this->setbbscookie();
            }
        }
        # Redirect
        if (preg_match("/^(https?):\/\//", (string) $this->config['CGIURL'])) {
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
        if (!$this->form['s']) {
            $this->prterror(Translator::trans('error.no_parameters'));
        }
        if (isset($this->session['UNDO_P']) and $this->session['UNDO_P'] == $this->form['s']) {
            $loglines = $this->searchmessage('POSTID', $this->session['UNDO_P']);
            if (count($loglines) < 1) {
                $this->prterror(Translator::trans('error.post_not_found'));
            }
            $message = $this->getmessage($loglines[0]);
            $undokey = substr((string) preg_replace("/\W/", '', crypt((string) $message['PROTECT'], (string) $this->config['ADMINPOST'])), -8);
            if ($undokey != $this->session['UNDO_K']) {
                $this->prterror(Translator::trans('error.deletion_not_permitted'));
            }
            # Erase operation
            $bbsadmin = new Bbsadmin();
            $bbsadmin->killmessage($this->session['UNDO_P']);

            $this->session['UNDO_P'] = '';
            $this->session['UNDO_K'] = '';
            setcookie('undo');
        } else {
            $this->prterror(Translator::trans('error.deletion_not_permitted'));
        }
        $this->sethttpheader();
        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'] . ' ' . Translator::trans('complete.deletion_complete'),
            'TRANS_DELETION_COMPLETE' => Translator::trans('complete.deletion_complete'),
            'TRANS_RETURN_TO_BBS' => Translator::trans('complete.return_to_bbs'),
            'TRANS_RETURN_INSTRUCTION' => Translator::trans('complete.return_instruction'),
        ]);
        echo $this->renderTwig('undocomplete.twig', $data);
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
    public function searchmessage($varname, $searchvalue, $ismultiple = false, $filename = '')
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
        if ($this->config['RUNMODE'] == 1) {
            $this->prterror(Translator::trans('error.posting_suspended'));
        }
        /* Prohibit access by host name process */
        if (NetworkHelper::hostnameMatch($this->config['HOSTNAME_POSTDENIED'], $this->config['HOSTAGENT_BANNED'])) {
            $this->prterror(Translator::trans('error.posting_suspended'));
        }
        if ($this->config['BBSMODE_ADMINONLY'] == 1 or ($this->config['BBSMODE_ADMINONLY'] == 2 and !$this->form['f'])) {
            if (crypt((string) $this->form['u'], (string) $this->config['ADMINPOST']) != $this->config['ADMINPOST']) {
                $this->prterror(Translator::trans('error.admin_only'));
            }
        }
        if ($_SERVER['HTTP_REFERER'] and $this->config['REFCHECKURL']
            and (!str_contains((string) $_SERVER['HTTP_REFERER'], (string) $this->config['REFCHECKURL'])
            or strpos((string) $_SERVER['HTTP_REFERER'], (string) $this->config['REFCHECKURL']) > 0)) {
            $this->prterror("Posts cannot be made from any URLs besides <br>{$this->config['REFCHECKURL']}.");
        }
        foreach (explode("\r", (string) $this->form['v']) as $line) {
            if (strlen($line) > $this->config['MAXMSGCOL']) {
                $this->prterror(Translator::trans('error.too_many_characters'));
            }
        }
        if (substr_count((string) $this->form['v'], "\r") > $this->config['MAXMSGLINE'] - 1) {
            $this->prterror(Translator::trans('error.too_many_linebreaks'));
        }
        if (strlen((string) $this->form['v']) > $this->config['MAXMSGSIZE']) {
            $this->prterror(Translator::trans('error.file_size_too_large'));
        }
        if (strlen((string) $this->form['u']) > $this->config['MAXNAMELENGTH']) {
            $this->prterror('There are too many characters in the name field. (Up to {MAXNAMELENGTH} characters)');
        }
        if (strlen((string) $this->form['i']) > $this->config['MAXMAILLENGTH']) {
            $this->prterror('There are too many characters in the email field. (Up to {MAXMAILLENGTH} characters)');
        }
        if ($this->form['i']) { ## mod
            $this->prterror(Translator::trans('error.spam_detected')); ## mod
        } ## mod
        if (strlen((string) $this->form['t']) > $this->config['MAXTITLELENGTH']) {
            $this->prterror('There are too many characters in the title field. (Up to {MAXTITLELENGTH} characters)');
        }
        {
            $timestamp = SecurityHelper::verifyProtectCode($this->form['pc'], $limithost);

            if ((CURRENT_TIME - $timestamp) < $this->config['MINPOSTSEC']) {
                $this->prterror(Translator::trans('error.post_interval_too_short'));
            }
            /*            if ((CURRENT_TIME - $timestamp ) > $this->config['MAXPOSTSEC'] ) {
                            $this->prterror ( 'The time between posts is too long. Please try again.');
                            $posterr = 2;
                            return $posterr;
                        } */
        }

        if (trim((string) $this->form['v']) == '') {
            $posterr = 2;
            return $posterr;
        }

        ## if ($this->config['NGWORD']) {
        ##     foreach ($this->config['NGWORD'] as $ngword) {
        ##         if (strpos($this->form['v'], $ngword) !== FALSE
        ##             or strpos($this->form['l'], $ngword) !== FALSE
        ##             or strpos($this->form['t'], $ngword) !== FALSE
        ##             or strpos($this->form['u'], $ngword) !== FALSE
        ##             or strpos($this->form['i'], $ngword) !== FALSE) {
        ##             $this->prterror ( 'The post contains prohibited words.' );
        ##         }
        ##     }
        ## }
        if ($this->config['NGWORD']) { ## mod
            foreach ($this->config['NGWORD'] as $ngword) {
                $ngword = strtolower((string) $ngword); // Convert prohibited word to lowercase
                if (
                    str_contains(strtolower((string) $this->form['v']), $ngword) ||
                    str_contains(strtolower((string) $this->form['l']), $ngword) ||
                    str_contains(strtolower((string) $this->form['t']), $ngword) ||
                    str_contains(strtolower((string) $this->form['u']), $ngword) ||
                    str_contains(strtolower((string) $this->form['i']), $ngword)
                ) {
                    $this->prterror(Translator::trans('error.prohibited_words'));
                }
            }
        } ## mod end

        #20240204 猫 spam detection (https://php.o0o0.jp/article/php-spam)
        # Number of characters: char_num = mb_strlen( $this->form['v'], 'UTF8');
        # Number of bytes: byte_num = strlen( $this->form['v']);

        ## $char_num = mb_strlen( $this->form['v'], 'UTF8');
        ## $byte_num = strlen( $this->form['v']);

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
        $message['PCODE'] = $this->form['pc'];
        $message['USER'] = $this->form['u'];
        $message['MAIL'] = $this->form['i'];
        $message['TITLE'] = $this->form['t'];
        $message['MSG'] = $this->form['v'];
        $message['URL'] = $this->form['l'];
        $message['PHOST'] = $this->session['HOST'];
        $message['AGENT'] = $this->session['AGENT'];
        # Reference ID
        if ($this->form['f']) {
            $message['REFID'] = $this->form['f'];
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
            $message['USER'] = $this->config['ANONY_NAME'];
        } else {
            # Admin check
            if ($this->config['ADMINPOST'] and crypt((string) $message['USER'], (string) $this->config['ADMINPOST']) == $this->config['ADMINPOST']) {
                $message['USER'] = "<span class=\"muh\">{$this->config['ADMINNAME']}</span>";
                # Enter admin mode
                if ($this->config['ADMINKEY'] and trim((string) $message['MSG']) == $this->config['ADMINKEY']) {
                    return 3;
                }
            } elseif ($this->config['ADMINPOST'] and $message['USER'] == $this->config['ADMINPOST']) {
                $message['USER'] = $this->config['ADMINNAME'] . '<span class="muh"> (hacker)</span>';
            } elseif (!(!str_contains((string) $message['USER'], (string) $this->config['ADMINNAME']))) {
                $message['USER'] = $this->config['ADMINNAME'] . '<span class="muh"> (fraudster)</span>';
            }
            # Fixed handle name check
            elseif ($this->config['HANDLENAMES'][trim((string) $message['USER'])]) {
                $message['USER'] .= '<span class="muh"> (fraudster)</span>';
            }
            # Trip function (simple deception prevention function)
            elseif (str_contains((string) $message['USER'], '#')) {
                #20210702 猫・管理パスばれ防止
                if ($this->config['ADMINPOST'] and crypt(substr((string) $message['USER'], 0, strpos((string) $message['USER'], '#')), (string) $this->config['ADMINPOST']) == $this->config['ADMINPOST']) {
                    $message['USER'] = "<span class=\"muh\"><a href=\"mailto:{$this->config['ADMINMAIL']}\">{$this->config['ADMINNAME']}</a></span>".substr((string) $message['USER'], strpos((string) $message['USER'], '#'));
                }
                #20210923 猫・固定ハンドル名 パスばれ防止
                # 固定ハンドル名変換
                elseif (isset($this->config['HANDLENAMES'])) {
                    $handlename = array_search(trim(substr((string) $message['USER'], 0, strpos((string) $message['USER'], '#'))), $this->config['HANDLENAMES']);
                    if ($handlename !== false) {
                        $message['USER'] = "<span class=\"muh\">{$handlename}</span>".substr((string) $message['USER'], strpos((string) $message['USER'], '#'));
                    }
                }
                $message['USER'] = substr((string) $message['USER'], 0, strpos((string) $message['USER'], '#')) . ' <span class="mut">◆' . substr((string) preg_replace("/\W/", '', crypt(substr((string) $message['USER'], strpos((string) $message['USER'], '#')), '00')), -7) .$this->tripuse($message['USER']). '</span>';
            } elseif (str_contains((string) $message['USER'], '◆')) {
                $message['USER'] .= ' (fraudster)';
            }
            # Fixed handle name conversion
            elseif (isset($this->config['HANDLENAMES'])) {
                $handlename = array_search(trim((string) $message['USER']), $this->config['HANDLENAMES']);
                if ($handlename !== false) {
                    $message['USER'] = "<span class=\"muh\">{$handlename}</span>";
                }
            }
        }
        $message['MSG'] = rtrim((string) $message['MSG']);

        # Auto-link URLs
        if ($this->config['AUTOLINK']) {
            $message['MSG'] = preg_replace(
                "/((https?|ftp|news):\/\/[-_.,!~*'()a-zA-Z0-9;\/?:\@&=+\$,%#]+)/",
                '<a href="$1" target="link">$1</a>',
                $message['MSG']
            );
        }
        # URL field
        $message['URL'] = trim((string) $message['URL']);
        if ($message['URL']) {
            $message['MSG'] .= "\r\r<a href=\"".StringHelper::escapeUrl($message['URL'])."\" target=\"link\">{$message['URL']}</a>";
        }
        # Reference
        if ($message['REFID']) {
            $refdata = $this->searchmessage('POSTID', $message['REFID'], false, $this->form['ff']);
            if (!$refdata) {
                $this->prterror(Translator::trans('error.reference_not_found'));
            }
            $refmessage = $this->getmessage($refdata[0]);
            $refmessage['WDATE'] = DateHelper::getDateString($refmessage['NDATE'], $this->config['DATEFORMAT']);
            $message['MSG'] .= "\r\r<a href=\"m=f&s={$message['REFID']}&r=&\">Reference: {$refmessage['WDATE']}</a>";
            # Simple self-reply prevention function
            if ($this->config['IPREC'] and $this->config['SHOW_SELFFOLLOW']
                and $refmessage['PHOST'] != '' and $refmessage['PHOST'] == $message['PHOST']) {
                $message['USER'] .= '<span class="muh"> (self-reply)</span>';
            }
        }
        # Check
        if (strlen((string) $message['MSG']) > $this->config['MAXMSGSIZE']) {
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
        $fh = @fopen($this->config['LOGFILENAME'], 'rb+');
        if (!$fh) {
            $this->prterror(Translator::trans('error.failed_to_read'));
        }
        flock($fh, 2);
        fseek($fh, 0, 0);

        $logdata = [];
        while (($logline = FileHelper::getLine($fh)) !== false) {
            $logdata[] = $logline;
        }
        $posterr = 0;
        if ($this->form['ff']) {
            $refdata = $this->searchmessage('THREAD', $message['REFID'], false, $this->form['ff']);
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
                    if ($i < $this->config['CHECKCOUNT'] and $message['MSG'] == $items[9]) {
                        $posterr = 1;
                        break;
                    }
                    if ($this->config['IPREC'] and CURRENT_TIME < ($items[0] + $this->config['SPTIME'])
                        and $this->session['HOST'] == $items[4]) {
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
            $msgdata = str_replace("\n", '', $msgdata) . "\n";
            if (count($logdata) >= $this->config['LOGSAVE']) {
                $logdata = array_slice($logdata, 0, $this->config['LOGSAVE'] - 2);
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
            if ($this->config['COOKIE']) {
                $this->setbbscookie();
                if ($this->config['ALLOW_UNDO']) {
                    $this->setundocookie($message['POSTID'], $message['PCODE']);
                }
            }

            # Message log output
            if ($this->config['OLDLOGFILEDIR']) {
                $dir = $this->config['OLDLOGFILEDIR'];

                if ($this->config['OLDLOGFMT']) {
                    $oldlogext = 'dat';
                } else {
                    $oldlogext = 'html';
                }
                if ($this->config['OLDLOGSAVESW']) {
                    $oldlogfilename = $dir . date('Ym', CURRENT_TIME) . ".$oldlogext";
                    $oldlogtitle = $this->config['BBSTITLE'] . date(' Y.m', CURRENT_TIME);
                } else {
                    $oldlogfilename = $dir . date('Ymd', CURRENT_TIME) . ".$oldlogext";
                    $oldlogtitle = $this->config['BBSTITLE'] . date(' Y.m.d', CURRENT_TIME);
                }
                if (@filesize($oldlogfilename) > $this->config['MAXOLDLOGSIZE']) {
                    $this->prterror(Translator::trans('error.log_size_limit'));
                }
                $fh = @fopen($oldlogfilename, 'ab');
                if (!$fh) {
                    $this->prterror(Translator::trans('error.log_output_failed'));
                }
                flock($fh, 2);
                $isnewdate = false;
                if (!@filesize($oldlogfilename)) {
                    $isnewdate = true;
                }
                if ($this->config['OLDLOGFMT']) {
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
                if (@filesize($oldlogfilename) > $this->config['MAXOLDLOGSIZE']) {
                    @chmod($oldlogfilename, 0400);
                }
                # Delete old log files
                if (!$this->config['OLDLOGSAVESW'] and $isnewdate) {
                    $limitdate = CURRENT_TIME - $this->config['OLDLOGSAVEDAY'] * 60 * 60 * 24;
                    $limitdate = date('Ymd', $limitdate);
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
                if ($this->config['ZIPDIR'] and @function_exists('gzcompress')) {
                    # In the case of dat, it also writes the message log in HTML format as a temporary file to be saved in the ZIP
                    if ($this->config['OLDLOGFMT']) {
                        if ($this->config['OLDLOGSAVESW']) {
                            $tmplogfilename = $this->config['ZIPDIR'] . date('Ym', CURRENT_TIME) . '.html';
                        } else {
                            $tmplogfilename = $this->config['ZIPDIR'] . date('Ymd', CURRENT_TIME) . '.html';
                        }

                        $fhtmp = @fopen($tmplogfilename, 'ab');
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
                    if ($this->config['OLDLOGFMT']) {
                        $tmpdir = $this->config['ZIPDIR'];
                    }
                    if ($this->config['OLDLOGSAVESW']) {
                        $currentfile = date('Ym', CURRENT_TIME) . '.html';
                    } else {
                        $currentfile = date('Ymd', CURRENT_TIME) . '.html';
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
                    $zipfilename = preg_replace("/\.\w+$/", '.zip', $checkedfile);

                    # Create a ZIP file
                    require_once(LIB_PHPZIP);
                    $zip = new PHPZip();
                    $zipfiles[] = $checkedfile;
                    $zip->Zip($zipfiles, $zipfilename);

                    # Delete temporary files
                    if ($this->config['OLDLOGFMT']) {
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

        if ($this->config['UAREC']) {
            $agent = $_SERVER['HTTP_USER_AGENT'];
            $agent = StringHelper::htmlEscape($agent);
            $this->session['AGENT'] = $agent;
        }
        if (!$this->config['IPREC']) {
            return;
        }
        [$addr, $host, $proxyflg, $realaddr, $realhost] = NetworkHelper::getUserEnv();

        $this->session['ADDR'] = $addr;
        $this->session['HOST'] = $host;
        $this->session['PROXYFLG'] = $proxyflg;
        $this->session['REALADDR'] = $realaddr;
        $this->session['REALHOST'] = $realhost;
    }

    /**
     * Bulletin board cookie registration
     */
    public function setbbscookie()
    {
        $cookiestr = 'u=' . urlencode((string) $this->form['u']);
        $cookiestr .= '&i=' . urlencode((string) $this->form['i']);
        $cookiestr .= '&c=' . $this->form['c'];
        setcookie('c', $cookiestr, CURRENT_TIME + 7776000); // expires in 90 days
    }

    /**
     * Register cookie for post UNDO
     */
    public function setundocookie($undoid, $pcode)
    {
        $undokey = substr((string) preg_replace("/\W/", '', crypt((string) $pcode, (string) $this->config['ADMINPOST'])), -8);
        $cookiestr = "p=$undoid&k=$undokey";
        $this->session['UNDO_P'] = $undoid;
        $this->session['UNDO_K'] = $undokey;
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
            if (isset($this->config['COUNTLEVEL'])) {
                $countlevel = $this->config['COUNTLEVEL'];
            }
            if ($countlevel < 1) {
                $countlevel = 1;
            }
        }
        $count = [];
        for ($i = 0; $i < $countlevel; $i++) {
            $filename = "{$this->config['COUNTFILE']}{$i}.dat";
            if (is_writable($filename) and $fh = @fopen($filename, 'r')) {
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
        if ($fh = @fopen("{$this->config['COUNTFILE']}{$filenumber[$mincount]}.dat", 'w')) {
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
    public function mbrcount($cntfilename = '')
    {
        if (!$cntfilename) {
            $cntfilename = $this->config['CNTFILENAME'];
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
                        } elseif (($ctime + $this->config['CNTLIMIT']) >= CURRENT_TIME) {
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
            if ($fh = @fopen($cntfilename, 'w')) {
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
