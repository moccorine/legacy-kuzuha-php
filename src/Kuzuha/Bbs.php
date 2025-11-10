<?php

namespace Kuzuha;

use App\Config;
use App\Models\Repositories\AccessCounterRepositoryInterface;
use App\Models\Repositories\BbsLogRepositoryInterface;
use App\Models\Repositories\OldLogRepositoryInterface;
use App\Models\Repositories\ParticipantCounterRepositoryInterface;
use App\Services\BbsPostValidator;
use App\Translator;
use App\Utils\AutoLink;
use App\Utils\DateHelper;
use App\Utils\KaomojiHelper;
use App\Utils\NetworkHelper;
use App\Utils\PerformanceTimer;
use App\Utils\QuoteRegex;
use App\Utils\RegexPatterns;
use App\Utils\SecurityHelper;
use App\Utils\StringHelper;
use App\Utils\ValidationRegex;

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
    // Post error codes
    public const POST_SUCCESS = 0;
    public const POST_ERROR_DUPLICATE = 1;
    public const POST_ERROR_RATE_LIMIT = 2;

    private ?AccessCounterRepositoryInterface $accessCounterRepo = null;
    private ?ParticipantCounterRepositoryInterface $participantCounterRepo = null;
    protected $bbsLogRepository = null;
    protected $oldLogRepository = null;
    private array $pendingCookies = [];

    public function __construct(
        ?AccessCounterRepositoryInterface $accessCounterRepo = null,
        ?ParticipantCounterRepositoryInterface $participantCounterRepo = null,
        ?BbsLogRepositoryInterface $bbsLogRepository = null,
        ?OldLogRepositoryInterface $oldLogRepository = null
    ) {
        parent::__construct();
        $this->accessCounterRepo = $accessCounterRepo;
        $this->participantCounterRepo = $participantCounterRepo;
        $this->bbsLogRepository = $bbsLogRepository;
        $this->oldLogRepository = $oldLogRepository;

        if ($bbsLogRepository) {
            $this->setBbsLogRepository($bbsLogRepository);
        }
    }

    /**
     * Main process
     */
    public function main()
    {
        // Start execution time measurement
        PerformanceTimer::start();
        // Form acquisition preprocessing
        $this->loadAndSanitizeInput();
        // Reflect user settings
        $this->applyUserPreferences();

        $this->initializeSession();

        // If ADMINPOST is empty and not setting password, show password setup page
        if (empty($this->config['ADMINPOST']) && $this->form['m'] != 'ad') {
            $bbsadmin = new Bbsadmin($this);
            $bbsadmin->prtsetpass();
            return;
        }

        // gzip compression transfer
        if ($this->config['GZIPU']) {
            ob_start('ob_gzhandler');
        }

        // Route to appropriate handler based on mode
        // Priority: setup button > mode parameter
        if ($this->form['setup']) {
            // Display user settings page
            $this->prtcustom();
            return;
        }

        $mode = $this->form['m'] ?? '';

        switch ($mode) {
            case 'p':
                $this->handlePostMode();
                break;

            case 'c':
                // User settings process
                $this->saveUserPreferences();
                break;

            case 'u':
                // UNDO process
                $this->prtundo();
                break;

            default:
                // Default: bulletin board display
                $this->prtmain(false, $this->accessCounterRepo, $this->participantCounterRepo);
                break;
        }

        if ($this->config['GZIPU']) {
            ob_end_flush();
        }
    }

    /**
     * Handle post mode operations
     */
    private function handlePostMode()
    {
        // New post page display
        if ($this->form['write'] && !trim((string) $this->form['v'])) {
            $this->prtnewpost();
            return;
        }

        // Post submission
        if (!trim((string) $this->form['v'])) {
            $this->prtmain(false, $this->accessCounterRepo, $this->participantCounterRepo);
            return;
        }

        // Get environment variables
        $this->setuserenv();

        // Create validator and validate post
        $validator = new BbsPostValidator($this->config, $this->form, $this->session);
        $posterr = $validator->validate();

        // Post operation
        if ($posterr === BbsPostValidator::VALID) {
            $message = $validator->buildMessage();

            // Resolve thread ID from archive if replying from archive file (ff parameter)
            if ($this->form['ff'] && $message['REFID']) {
                $message['THREAD'] = $this->resolveThreadFromArchive($message['REFID'], $this->form['ff']);
            } else {
                // Use null as placeholder to resolve from current log
                $message['THREAD'] = null;
            }

            $posterr = $this->saveMessage($message);
        }

        // Handle post result
        switch ($posterr) {
            case self::POST_ERROR_DUPLICATE:
                // Double post error, etc.
                $this->prtmain(false, $this->accessCounterRepo, $this->participantCounterRepo);
                break;

            case self::POST_ERROR_RATE_LIMIT:
                // Protect code redisplayed due to time lapse
                if ($this->form['f']) {
                    $this->prtfollow(true);
                } elseif ($this->form['write']) {
                    $this->prtnewpost(true);
                } else {
                    $this->prtmain(true, $this->accessCounterRepo, $this->participantCounterRepo);
                }
                break;

            case BbsPostValidator::ADMIN_MODE:
                // Admin mode activation (special code from validator)
                define('BBS_ACTIVATED', true);
                $bbsadmin = new Bbsadmin($this);
                $bbsadmin->main();
                break;

            default:
                // Post completion
                if ($this->form['f']) {
                    $this->prtputcomplete();
                } else {
                    $this->prtmain(false, $this->accessCounterRepo, $this->participantCounterRepo);
                }
                break;
        }
    }

    /**
     * Display bulletin board
     *
     * @access  public
     * @param   Boolean  $retry  Retry flag
     * @param   AccessCounterRepositoryInterface|null  $accessCounterRepo
     * @param   ParticipantCounterRepositoryInterface|null  $participantCounterRepo
     */
    public function prtmain(
        $retry = false,
        ?AccessCounterRepositoryInterface $accessCounterRepo = null,
        ?ParticipantCounterRepositoryInterface $participantCounterRepo = null
    ) {
        // Override injected repositories if provided
        if ($accessCounterRepo !== null) {
            $this->accessCounterRepo = $accessCounterRepo;
        }
        if ($participantCounterRepo !== null) {
            $this->participantCounterRepo = $participantCounterRepo;
        }

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

        # Get stats HTML using Twig
        $statsData = $this->getStatsData();
        $statsHtml = $this->renderTwig('components/stats.twig', $statsData);

        # HTML header partial output

        # Upper main section
        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'],
            'CUSTOMSTYLE' => '',
            'CUSTOMHEAD' => '',
            'FORM' => $formHtml,
            'STATS' => $statsHtml,
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
            print $this->renderMessage($this->parseLogLine($msgdata), 0, 0);
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

        // Duration
        $duration = null;
        if ($this->config['SHOW_PRCTIME'] && PerformanceTimer::isRunning()) {
            $duration = PerformanceTimer::elapsedFormatted(6);
        }

        # Lower main section
        $data = array_merge($this->config, $this->session, [
            'MSGMORE' => $msgmore,
            'SHOW_NEXTPAGE' => $showNextPage,
            'EINDEX' => $eindex ?? '',
            'SHOW_READNEW' => $showReadNew,
            'SHOW_ADMINLOGIN' => $showAdminLogin,
            'DURATION' => $duration,
            'TRANS_PAGE_GENERATION_TIME' => Translator::trans('main.page_generation_time'),
            'TRANS_SECONDS' => Translator::trans('main.seconds'),
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

        $logdata = $this->getLogLines();
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
     * Prepare stats component data for Twig rendering
     */
    protected function getStatsData()
    {
        $counter = '';
        $showCounter = false;
        if ($this->config['SHOW_COUNTER'] && $this->accessCounterRepo !== null) {
            $counter = number_format($this->accessCounterRepo->increment());
            $showCounter = true;
        }

        $mbrcount = '';
        $showMbrCount = false;
        if ($this->config['CNTFILENAME'] && $this->participantCounterRepo !== null) {
            $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $userKey = (string) hexdec(substr(md5($remoteAddr), 0, 8));
            $mbrcount = number_format(
                $this->participantCounterRepo->recordVisit($userKey, CURRENT_TIME, $this->config['CNTLIMIT'])
            );
            $showMbrCount = true;
        }

        return [
            'COUNTER' => $counter,
            'SHOW_COUNTER' => $showCounter,
            'COUNTLEVEL' => $this->config['COUNTLEVEL'] ?? '',
            'MBRCOUNT' => $mbrcount,
            'SHOW_MBRCOUNT' => $showMbrCount,
            'CNTLIMIT' => $this->config['CNTLIMIT'] ?? '',
            'LOGSAVE' => $this->config['LOGSAVE'] ?? '',
            'COUNTDATE' => $this->config['COUNTDATE'] ?? '',
            'INFOPAGE' => $this->config['INFOPAGE'] ?? '',
            'DEFURL' => $this->session['DEFURL'] ?? '',
            'BBSLINK' => $this->session['BBSLINK'] ?? '',
            'TXTFOLLOW' => $this->config['TXTFOLLOW'] ?? '',
            'TXTAUTHOR' => $this->config['TXTAUTHOR'] ?? '',
            'TXTTHREAD' => $this->config['TXTTHREAD'] ?? '',
            'TXTTREE' => $this->config['TXTTREE'] ?? '',
            'TXTUNDO' => $this->config['TXTUNDO'] ?? '',
            'SHOW_UNDO' => $this->config['ALLOW_UNDO'] && $this->config['BBSMODE_ADMINONLY'] != 1,
            'TRANS_PAGEVIEW' => Translator::trans('stats.pageview'),
            'TRANS_BULLETPROOF_LEVEL' => Translator::trans('stats.bulletproof_level'),
            'TRANS_CURRENT_PARTICIPANTS' => Translator::trans('stats.current_participants'),
            'TRANS_USERS' => Translator::trans('stats.users'),
            'TRANS_SECONDS_WITHIN' => Translator::trans('stats.seconds_within'),
            'TRANS_MAX_POSTS_SAVED' => Translator::trans('stats.max_posts_saved'),
            'TRANS_POSTS' => Translator::trans('stats.posts'),
            'TRANS_TO_PR_OFFICE' => Translator::trans('stats.to_pr_office'),
            'TRANS_PR_OFFICE' => Translator::trans('stats.pr_office'),
            'TRANS_MESSAGE_LOGS' => Translator::trans('stats.message_logs'),
            'TRANS_MESSAGE_LOGS_TITLE' => Translator::trans('stats.message_logs_title'),
            'TRANS_FOLLOW_TITLE' => Translator::trans('stats.follow_title'),
            'TRANS_FOLLOW_DESC' => Translator::trans('stats.follow_desc'),
            'TRANS_AUTHOR_TITLE' => Translator::trans('stats.author_title'),
            'TRANS_AUTHOR_DESC' => Translator::trans('stats.author_desc'),
            'TRANS_THREAD_TITLE' => Translator::trans('stats.thread_title'),
            'TRANS_THREAD_DESC' => Translator::trans('stats.thread_desc'),
            'TRANS_TREE_TITLE' => Translator::trans('stats.tree_title'),
            'TRANS_TREE_DESC' => Translator::trans('stats.tree_desc'),
            'TRANS_UNDO_TITLE' => Translator::trans('stats.undo_title'),
            'TRANS_UNDO_DESC' => Translator::trans('stats.undo_desc'),
        ];
    }

    /**
     * Prepare form data for Twig rendering
     */
    protected function getFormData($dtitle, $dmsg, $dlink, $mode = '')
    {
        # Protect code generation
        $pcode = SecurityHelper::generateProtectCode();
        if (!$mode) {
            $mode = 'p';
        }

        # Hide post form
        $hideForm = ($this->config['HIDEFORM'] && $this->form['m'] != 'f' && !$this->form['write']);

        # Counter and member count
        $showCounter = false;
        $counter = '';
        if ($this->config['SHOW_COUNTER'] && $this->accessCounterRepo !== null) {
            $counter = number_format($this->accessCounterRepo->getCurrent());
            $showCounter = true;
        }

        $showMbrCount = false;
        $mbrcount = '';
        if ($this->config['CNTFILENAME'] && $this->participantCounterRepo !== null) {
            $mbrcount = number_format(
                $this->participantCounterRepo->getActiveCount(CURRENT_TIME, $this->config['CNTLIMIT'])
            );
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
        $kaomojiButtons = KaomojiHelper::generateButtons();

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
                '%maxline%' => $this->config['MAXMSGLINE'],
            ]),
            'TRANS_CONTENTS_HELP_IMAGE' => Translator::trans('form.contents_help_image', [
                '%maxcol%' => $this->config['MAXMSGCOL'],
                '%maxline%' => $this->config['MAXMSGLINE'],
                '%imagetext%' => $this->config['IMAGETEXT'],
            ]),
            'TRANS_URL' => Translator::trans('form.url'),
            'TRANS_URL_TITLE' => Translator::trans('form.url_title'),
            'TRANS_IMAGE_UPLOAD' => Translator::trans('form.image_upload'),
            'TRANS_IMAGE_UPLOAD_TITLE' => Translator::trans('form.image_upload_title'),
            'TRANS_IMAGE_UPLOAD_HELP' => Translator::trans('form.image_upload_help', [
                '%max_width%' => $this->config['MAX_IMAGEWIDTH'],
                '%max_height%' => $this->config['MAX_IMAGEHEIGHT'],
                '%max_size%' => $this->config['MAX_IMAGESIZE'],
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
        $message = $this->parseLogLine($result[0]);

        if (!$retry) {
            $formmsg = QuoteRegex::formatAsQuote(
                $message['MSG'],
                removeLinks: true,
                followLinkBase: route('follow', ['s' => ''])
            );
        } else {
            $formmsg = $this->removeFollowLinks($this->form['v']);
        }
        $formmsg .= "\r";

        if (!$message['THREAD']) {
            $message['THREAD'] = $message['POSTID'];
        }
        $filename ? $mode = 1 : $mode = 0;

        // Get message HTML using Twig
        $messageHtml = $this->renderMessage($message, $mode, $filename);

        // Get form HTML using Twig (hide form config on follow page)
        $formData = $this->getFormData('＞' . RegexPatterns::stripHtmlTags((string) $message['USER']) . $this->config['FSUBJ'], $formmsg, '');
        $formData['SHOW_FORMCONFIG'] = false;
        $formHtml = $this->renderTwig('components/form.twig', $formData);

        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'] . ' ' . Translator::trans('follow.followup_post'),
            'MESSAGE' => $messageHtml,
            'FORM' => $formHtml,
            'FOLLOW_POST_ID' => $this->form['s'],
            'FOLLOW_FILE' => $this->form['ff'],
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
            $messages .= $this->renderMessage($message, $mode, $this->form['ff']);
        }
        $success = count($result);

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
        $result = [];

        if ($this->form['ff']) {
            if (!ValidationRegex::isValidFilename((string) $this->form['ff'])) {
                $this->prterror(Translator::trans('error.file_open_failed', ['filename' => $this->form['ff']]));
            }

            if (!$this->oldLogRepository) {
                $this->prterror(Translator::trans('error.file_open_failed', ['filename' => $this->form['ff']]));
            }

            try {
                $logdata = $this->oldLogRepository->getAll($this->form['ff']);
            } catch (\RuntimeException $e) {
                $this->prterror(Translator::trans('error.file_open_failed', ['filename' => $this->form['ff']]));
            }

            $linecount = 0;
            $threadstart = false;
            foreach ($logdata as $logline) {
                if ($threadstart) {
                    $linecount++;
                }
                if ($linecount > $this->config['LOGSAVE']) {
                    break;
                }
                $message = $this->parseLogLine($logline);
                # Search by user
                if ($mode == 's' and RegexPatterns::stripHtmlTags((string) $message['USER']) == $this->form['s']) {
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
        } else {
            $logdata = $this->getLogLines();
            foreach ($logdata as $logline) {
                $message = $this->parseLogLine($logline);
                # Search by user
                if ($mode == 's' and RegexPatterns::stripHtmlTags((string) $message['USER']) == $this->form['s']) {
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
     * Save user preferences and redirect
     *
     * Processes user settings form submission (m=c):
     * - Validates and encodes color preferences
     * - Updates display flags (GZIP, autolink, etc.)
     * - Clears cookies if requested
     * - Redirects to main page with encoded preferences in URL
     *
     * @return void
     */
    public function saveUserPreferences()
    {
        // Clear cookies if requested
        if ($this->form['cr']) {
            $this->clearPreferenceCookies();
            $redirectUrl = $this->config['CGIURL'];
        } else {
            $colorString = $this->processColorChanges();
            $this->applyUserPreferences($colorString);
            $redirectUrl = $this->buildPreferenceRedirectUrl();

            if ($this->config['COOKIE']) {
                $this->setBbsCookie();
            }
        }

        header("Location: {$redirectUrl}");
        exit();
    }

    /**
     * Clear preference cookies
     */
    private function clearPreferenceCookies(): void
    {
        $this->form['c'] = '';
        if (!isset($this->pendingCookies['delete'])) {
            $this->pendingCookies['delete'] = [];
        }
        $this->pendingCookies['delete'][] = 'c';
        $this->pendingCookies['delete'][] = 'undo';
        $this->session['UNDO_P'] = '';
        $this->session['UNDO_K'] = '';
    }

    /**
     * Process color changes and return Base64 encoded string
     */
    private function processColorChanges(): string
    {
        $colors = [
            'C_BACKGROUND', 'C_TEXT', 'C_A_COLOR', 'C_A_VISITED',
            'C_SUBJ', 'C_QMSG', 'C_A_ACTIVE', 'C_A_HOVER',
        ];

        $lastChangedIndex = -1;
        foreach ($colors as $index => $colorKey) {
            if (ValidationRegex::isValidHexColor((string) $this->form[$colorKey])
                && $this->form[$colorKey] != $this->config[$colorKey]) {
                $this->config[$colorKey] = $this->form[$colorKey];
                $lastChangedIndex = $index;
            }
        }

        $encoded = '';
        for ($i = 0; $i <= $lastChangedIndex; $i++) {
            $encoded .= StringHelper::threeByteHexToBase64($this->config[$colors[$i]]);
        }

        return $encoded;
    }

    /**
     * Build redirect URL with preferences
     */
    private function buildPreferenceRedirectUrl(): string
    {
        $url = $this->config['CGIURL'] . '?c=' . $this->form['c'];

        foreach (['w', 'd'] as $key) {
            if (!empty($this->form[$key])) {
                $url .= "&{$key}=" . $this->form[$key];
            }
        }

        if (!empty($this->form['nm'])) {
            $url .= '&m=' . $this->form['nm'];
        }

        return $url;
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
            $message = $this->parseLogLine($loglines[0]);
            $undokey = substr(StringHelper::removeNonAlphanumeric(crypt((string) $message['PROTECT'], (string) $this->config['ADMINPOST'])), -8);
            if ($undokey != $this->session['UNDO_K']) {
                $this->prterror(Translator::trans('error.deletion_not_permitted'));
            }
            # Erase operation
            $bbsadmin = new Bbsadmin();
            $bbsadmin->killmessage($this->session['UNDO_P']);

            $this->session['UNDO_P'] = '';
            $this->session['UNDO_K'] = '';
            if (!isset($this->pendingCookies['delete'])) {
                $this->pendingCookies['delete'] = [];
            }
            $this->pendingCookies['delete'][] = 'undo';
        } else {
            $this->prterror(Translator::trans('error.deletion_not_permitted'));
        }
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
        $logdata = $this->getLogLines($filename);
        foreach ($logdata as $logline) {
            $message = $this->parseLogLine($logline);
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
     * Validate post message
     *
     * @access  public
     * @param   Boolean   $limithost  Whether or not to check for same host
     * @return  Integer   Error code (0: success, 2: retry)
     */
    public function validatePost($limithost = true)
    {
        $this->validatePostingEnabled();
        $this->validateAdminOnly();
        $this->validateReferer();
        $this->validateMessageFormat();
        $this->validateFieldLengths();
        $this->validatePostInterval($limithost);

        if (trim((string) $this->form['v']) == '') {
            return 2;
        }

        $this->validateProhibitedWords();

        return 0;
    }

    /**
     * Check if posting is enabled
     */
    private function validatePostingEnabled()
    {
        // Check if posting is suspended
        if ($this->config['RUNMODE'] == 1) {
            $this->prterror(Translator::trans('error.posting_suspended'));
        }

        // Prohibit access by hostname
        if (NetworkHelper::hostnameMatch($this->config['HOSTNAME_POSTDENIED'], $this->config['HOSTAGENT_BANNED'])) {
            $this->prterror(Translator::trans('error.posting_suspended'));
        }
    }

    /**
     * Check admin-only mode
     */
    private function validateAdminOnly()
    {
        // Admin-only mode check
        $isAdminOnlyMode = $this->config['BBSMODE_ADMINONLY'] == 1
            || ($this->config['BBSMODE_ADMINONLY'] == 2 && !$this->form['f']);

        if (!$isAdminOnlyMode) {
            return;
        }

        $isValidAdmin = crypt((string) $this->form['u'], (string) $this->config['ADMINPOST'])
            == $this->config['ADMINPOST'];

        if (!$isValidAdmin) {
            $this->prterror(Translator::trans('error.admin_only'));
        }
    }

    /**
     * Validate HTTP referer
     */
    private function validateReferer()
    {
        if (!$_SERVER['HTTP_REFERER'] || !$this->config['REFCHECKURL']) {
            return;
        }

        // Referer check
        $referer = (string) $_SERVER['HTTP_REFERER'];
        $checkUrl = (string) $this->config['REFCHECKURL'];

        if (!str_contains($referer, $checkUrl) || strpos($referer, $checkUrl) > 0) {
            $this->prterror("Posts cannot be made from any URLs besides <br>{$checkUrl}.");
        }
    }

    /**
     * Validate message format (length per line, number of lines, total size)
     */
    private function validateMessageFormat()
    {
        // Validate message length per line
        foreach (explode("\r", (string) $this->form['v']) as $line) {
            if (strlen($line) > $this->config['MAXMSGCOL']) {
                $this->prterror(Translator::trans('error.too_many_characters'));
            }
        }

        // Validate number of lines
        if (substr_count((string) $this->form['v'], "\r") > $this->config['MAXMSGLINE'] - 1) {
            $this->prterror(Translator::trans('error.too_many_linebreaks'));
        }

        // Validate total message size
        if (strlen((string) $this->form['v']) > $this->config['MAXMSGSIZE']) {
            $this->prterror(Translator::trans('error.file_size_too_large'));
        }
    }

    /**
     * Validate field lengths (name, email, title)
     */
    private function validateFieldLengths()
    {
        // Validate name length
        if (strlen((string) $this->form['u']) > $this->config['MAXNAMELENGTH']) {
            $this->prterror('There are too many characters in the name field. (Up to {MAXNAMELENGTH} characters)');
        }

        // Validate email length
        if (strlen((string) $this->form['i']) > $this->config['MAXMAILLENGTH']) {
            $this->prterror('There are too many characters in the email field. (Up to {MAXMAILLENGTH} characters)');
        }

        // Spam protection: reject if email field is filled
        if ($this->form['i']) {
            $this->prterror(Translator::trans('error.spam_detected'));
        }

        // Validate title length
        if (strlen((string) $this->form['t']) > $this->config['MAXTITLELENGTH']) {
            $this->prterror('There are too many characters in the title field. (Up to {MAXTITLELENGTH} characters)');
        }
    }

    /**
     * Validate post interval using protect code
     */
    private function validatePostInterval($limithost)
    {
        // Validate protect code and post interval
        $timestamp = SecurityHelper::verifyProtectCode($this->form['pc'], $limithost);

        if ((CURRENT_TIME - $timestamp) < $this->config['MINPOSTSEC']) {
            $this->prterror(Translator::trans('error.post_interval_too_short'));
        }
    }

    /**
     * Check for prohibited words (case-insensitive)
     */
    private function validateProhibitedWords()
    {
        if (!$this->config['NGWORD']) {
            return;
        }

        // Check for prohibited words (case-insensitive)
        foreach ($this->config['NGWORD'] as $ngword) {
            $ngword = strtolower((string) $ngword);

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

        // 20240204 猫 spam detection (https://php.o0o0.jp/article/php-spam)
        // Number of characters: char_num = mb_strlen($this->form['v'], 'UTF8');
        // Number of bytes: byte_num = strlen($this->form['v']);

        // $char_num = mb_strlen($this->form['v'], 'UTF8');
        // $byte_num = strlen($this->form['v']);

        // When single-byte characters makes up more than 90% of the total
        // if ((($char_num * 3 - $byte_num) / 2 / $char_num * 100) > 90) {
        //     // Treat as spam
        //     $this->prterror('This bulletin board\'s post function is currently disabled.');
        // }
        // disabled by TL: not suitable for languages that use single-byte characters (i.e. English)
    }

    /**
     * Build post message from form input
     *
     * @access  public
     * @return  Array|Integer  Message array or error code (3 for admin mode)
     */
    public function buildPostMessage()
    {
        $message = $this->extractFormData();
        $message['USER'] = $this->processUsername($message['USER'], $message['MSG']);

        // Check for admin mode entry
        if (is_int($message['USER'])) {
            return $message['USER']; // Return error code 3
        }

        $message['MSG'] = $this->processMessageContent($message['MSG'], $message['URL']);
        $message = $this->attachReference($message);

        // Final size check
        if (strlen((string) $message['MSG']) > $this->config['MAXMSGSIZE']) {
            $this->prterror('The post contents are too large.');
        }

        return $message;
    }

    /**
     * Extract basic form data into message array
     */
    private function extractFormData(): array
    {
        $message = [
            'PCODE' => substr((string) $this->form['pc'], 8, 4),
            'USER' => $this->form['u'],
            'MAIL' => $this->form['i'],
            'TITLE' => $this->form['t'] ?: ' ',
            'MSG' => $this->form['v'],
            'URL' => $this->form['l'],
            'PHOST' => $this->session['HOST'],
            'AGENT' => $this->session['AGENT'],
            'REFID' => $this->form['f'] ?: '',
        ];

        return $message;
    }

    /**
     * Process username with admin check, trip code, and handle name conversion
     *
     * @return string|int Username string or 3 for admin mode entry
     */
    private function processUsername(string $username, string $message)
    {
        // Anonymous user
        if (!$username) {
            return $this->config['ANONY_NAME'];
        }

        // Admin authentication
        if ($this->config['ADMINPOST'] && crypt($username, (string) $this->config['ADMINPOST']) == $this->config['ADMINPOST']) {
            // Check for admin mode entry
            if ($this->config['ADMINKEY'] && trim($message) == $this->config['ADMINKEY']) {
                return 3; // Admin mode entry code
            }
            return "<span class=\"muh\">{$this->config['ADMINNAME']}</span>";
        }

        // Prevent admin name spoofing
        if ($this->config['ADMINPOST'] && $username == $this->config['ADMINPOST']) {
            return $this->config['ADMINNAME'] . '<span class="muh"> (hacker)</span>';
        }

        if (str_contains($username, (string) $this->config['ADMINNAME'])) {
            return $this->config['ADMINNAME'] . '<span class="muh"> (fraudster)</span>';
        }

        // Fixed handle name fraud check
        if ($this->config['HANDLENAMES'][trim($username)]) {
            return $username . '<span class="muh"> (fraudster)</span>';
        }

        // Trip code processing
        if (str_contains($username, '#')) {
            return $this->processTripCode($username);
        }

        // Prevent trip code symbol spoofing
        if (str_contains($username, '◆')) {
            return $username . ' (fraudster)';
        }

        // Fixed handle name conversion
        if (isset($this->config['HANDLENAMES'])) {
            $handlename = array_search(trim($username), $this->config['HANDLENAMES']);
            if ($handlename !== false) {
                return "<span class=\"muh\">{$handlename}</span>";
            }
        }

        return $username;
    }

    /**
     * Process trip code in username
     */
    private function processTripCode(string $username): string
    {
        $hashPos = strpos($username, '#');
        $nameBeforeHash = substr($username, 0, $hashPos);
        $afterHash = substr($username, $hashPos);

        // 20210702 猫・管理パスばれ防止
        // Admin with trip code (prevent password leak)
        if ($this->config['ADMINPOST'] && crypt($nameBeforeHash, (string) $this->config['ADMINPOST']) == $this->config['ADMINPOST']) {
            return "<span class=\"muh\"><a href=\"mailto:{$this->config['ADMINMAIL']}\">{$this->config['ADMINNAME']}</a></span>" . $afterHash;
        }

        // 20210923 猫・固定ハンドル名 パスばれ防止
        // Fixed handle name with trip code (prevent password leak)
        if (isset($this->config['HANDLENAMES'])) {
            $handlename = array_search(trim($nameBeforeHash), $this->config['HANDLENAMES']);
            if ($handlename !== false) {
                return "<span class=\"muh\">{$handlename}</span>" . $afterHash;
            }
        }

        // Generate trip code
        $tripCode = substr(StringHelper::removeNonAlphanumeric(crypt($afterHash, '00')), -7);
        $tripUse = $this->tripuse($username);

        return $nameBeforeHash . ' <span class="mut">◆' . $tripCode . $tripUse . '</span>';
    }

    /**
     * Process message content: trim, auto-link, append URL field
     */
    private function processMessageContent(string $message, string $url): string
    {
        $message = rtrim($message);

        // Auto-link URLs (http and https only)
        if ($this->config['AUTOLINK']) {
            $message = AutoLink::convert($message);
        }

        // Append URL field
        $url = trim($url);
        if ($url) {
            $message .= "\r\r<a href=\"" . StringHelper::escapeUrl($url) . "\" target=\"link\">{$url}</a>";
        }

        return $message;
    }

    /**
     * Attach reference link to message
     */
    private function attachReference(array $message): array
    {
        if (!$message['REFID']) {
            return $message;
        }

        // Find reference post
        $refdata = $this->searchmessage('POSTID', $message['REFID'], false, $this->form['ff']);
        if (!$refdata) {
            $this->prterror(Translator::trans('error.reference_not_found'));
        }

        $refmessage = $this->parseLogLine($refdata[0]);
        $refmessage['WDATE'] = DateHelper::getDateString($refmessage['NDATE'], $this->config['DATEFORMAT']);
        $refLabel = Translator::trans('message.reference');

        // Append reference link
        $message['MSG'] .= "\r\r<a href=\"" . route('follow', ['s' => $message['REFID'], 'r' => '']) . "\">{$refLabel}: {$refmessage['WDATE']}</a>";

        // Mark self-reply
        if ($this->config['IPREC'] && $this->config['SHOW_SELFFOLLOW']
            && $refmessage['PHOST'] != '' && $refmessage['PHOST'] == $message['PHOST']) {
            $message['USER'] .= '<span class="muh"> (self-reply)</span>';
        }

        return $message;
    }

    /**
     * Resolve thread ID from archive file
     *
     * @param int $refId Reference post ID
     * @param string $archiveFile Archive filename
     * @return string|null Thread ID or null if not found
     */
    protected function resolveThreadFromArchive(int $refId, string $archiveFile): ?string
    {
        $refdata = $this->searchmessage('THREAD', $refId, false, $archiveFile);
        if (isset($refdata[0])) {
            $refmessage = $this->parseLogLine($refdata[0]);
            return $refmessage ? $refmessage['thread'] : null;
        }
        return null;
    }

    /**
     * Validate message and resolve thread ID from current log
     *
     * @param array $message Message data
     * @return array ['error' => int, 'thread' => string|null]
     */
    protected function validateAndResolveThread(array $message): array
    {
        $logdata = $this->bbsLogRepository->getAll();
        $thread = null;

        foreach ($logdata as $i => $logline) {
            $items = @explode(',', $logline);
            if (count($items) <= 8) {
                continue;
            }

            $items[9] = rtrim($items[9]);

            // Check for duplicate message content (only check recent posts)
            if ($i < $this->config['CHECKCOUNT'] && $message['MSG'] == $items[9]) {
                return ['error' => self::POST_ERROR_DUPLICATE, 'thread' => null];
            }

            // Check IP-based rate limit
            if ($this->config['IPREC']
                && CURRENT_TIME < ($items[0] + $this->config['SPTIME'])
                && $this->session['HOST'] == $items[4]) {
                return ['error' => self::POST_ERROR_RATE_LIMIT, 'thread' => null];
            }

            // Check for PCODE conflict (same protection code)
            if ($message['PCODE'] == $items[2]) {
                return ['error' => self::POST_ERROR_RATE_LIMIT, 'thread' => null];
            }

            // Find thread ID from reference message
            if ($message['REFID'] && $items[1] == $message['REFID']) {
                $thread = $items[3] ?: $items[1];
            }
        }

        return ['error' => self::POST_SUCCESS, 'thread' => $thread];
    }

    /**
     * Build message data array for CSV storage
     *
     * Escapes commas in user input fields to prevent CSV corruption.
     *
     * @param array $message Message data
     * @return array Associative array with message fields
     */
    protected function buildMessageData(array $message): array
    {
        return [
            'timestamp' => CURRENT_TIME,
            'postid' => $message['POSTID'],
            'pcode' => $message['PCODE'],
            'thread' => $message['THREAD'],
            'phost' => $message['PHOST'],
            'agent' => str_replace(',', '&#44;', $message['AGENT']),
            'user' => str_replace(',', '&#44;', $message['USER']),
            'mail' => str_replace(',', '&#44;', $message['MAIL']),
            'title' => str_replace(',', '&#44;', $message['TITLE']),
            'msg' => str_replace(',', '&#44;', $message['MSG']),
            'refid' => $message['REFID'],
        ];
    }

    /**
     * Save a new message to the bulletin board
     *
     * Validates the message against duplicate posts and rate limits,
     * then saves it to the main log and archive log.
     *
     * @param array $message Message data array (THREAD can be null as placeholder)
     * @return int Error code:
     *             - POST_SUCCESS (0): Message saved successfully
     *             - POST_ERROR_DUPLICATE (1): Duplicate message detected
     *             - POST_ERROR_RATE_LIMIT (2): Rate limit exceeded or PCODE conflict
     */
    public function saveMessage(array $message): int
    {
        $this->bbsLogRepository->lock();
        try {
            // Resolve thread ID and validate if not already set
            if ($message['THREAD'] === null) {
                $result = $this->validateAndResolveThread($message);
                if ($result['error'] !== self::POST_SUCCESS) {
                    return $result['error'];
                }
                $message['THREAD'] = $result['thread'];
            }

            // Assign post ID and thread ID
            $message['POSTID'] = $this->bbsLogRepository->getNextPostId();
            if (!$message['REFID']) {
                // New thread: thread ID = post ID
                $message['THREAD'] = $message['POSTID'];
            }

            // Build CSV data array
            $msgdata = $this->buildMessageData($message);

            // Save to main log
            $this->bbsLogRepository->prepend($msgdata, $this->config['LOGSAVE']);

            // Set cookies for user preferences and undo functionality
            if ($this->config['COOKIE']) {
                $this->setBbsCookie();
                if ($this->config['ALLOW_UNDO']) {
                    $this->setUndoCookie($message['POSTID'], $message['PCODE']);
                }
            }

            // Save to archive log (daily/monthly file)
            $this->writeOldLog(implode(',', array_values($msgdata)) . "\n");

            return self::POST_SUCCESS;
        } finally {
            $this->bbsLogRepository->unlock();
        }
    }

    protected function writeOldLog($msgdata)
    {
        if (!$this->config['OLDLOGFILEDIR'] || !$this->oldLogRepository) {
            return;
        }

        try {
            $this->oldLogRepository->append($msgdata);

            if ($this->oldLogRepository->isNewFile() && !$this->config['OLDLOGSAVESW']) {
                $this->deleteOldLogFiles(
                    $this->config['OLDLOGFILEDIR'],
                    $this->config['OLDLOGFMT'] ? 'dat' : 'html'
                );
            }

            $this->createArchive(
                $this->config['OLDLOGFILEDIR'],
                $this->config['OLDLOGFMT'] ? 'dat' : 'html'
            );
        } catch (\RuntimeException $e) {
            $this->prterror(Translator::trans('error.log_size_limit'));
        }
    }

    protected function deleteOldLogFiles($dir, $oldlogext)
    {
        $limitdate = CURRENT_TIME - $this->config['OLDLOGSAVEDAY'] * 60 * 60 * 24;
        $limitdate = date('Ymd', $limitdate);

        foreach (glob($dir . "*.$oldlogext") as $filepath) {
            $entry = basename($filepath);
            $info = pathinfo($entry);
            if (ctype_digit($info['filename'])) {
                $timestamp = $info['filename'];
                if (strlen($timestamp) == strlen($limitdate) && $timestamp < $limitdate) {
                    unlink($filepath);
                }
            }
        }
    }

    protected function createArchive($dir, $oldlogext)
    {
        $tmpdir = $this->config['OLDLOGFMT'] ? $this->config['ZIPDIR'] : $dir;
        $currentfile = $this->config['OLDLOGSAVESW']
            ? date('Ym', CURRENT_TIME) . '.html'
            : date('Ymd', CURRENT_TIME) . '.html';

        $files = [];
        foreach (glob($tmpdir . '*.html') as $filepath) {
            $entry = basename($filepath);
            if ($entry != $currentfile && ValidationRegex::isNumericFilename($entry, 'html')) {
                $files[] = $entry;
            }
        }

        $maxftime = 0;
        $checkedfile = null;
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

        $info = pathinfo($checkedfile);
        $zipfilename = $info['dirname'] . '/' . $info['filename'] . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipfilename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $zip->addFile($checkedfile, basename($checkedfile));
            $zip->close();
        }

        if ($this->config['OLDLOGFMT']) {
            unlink($checkedfile);
        }
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
     *
     * @see CookieService::setUserCookie()
     */
    public function setBbsCookie()
    {
        $this->pendingCookies['user'] = [
            'name' => $this->form['u'],
            'email' => $this->form['i'],
            'color' => $this->form['c'],
        ];
    }

    /**
     * Register cookie for post UNDO
     *
     * @see CookieService::setUndoCookie()
     */
    public function setUndoCookie($undoid, $pcode)
    {
        $undokey = substr(StringHelper::removeNonAlphanumeric(crypt((string) $pcode, (string) $this->config['ADMINPOST'])), -8);
        $this->session['UNDO_P'] = $undoid;
        $this->session['UNDO_K'] = $undokey;
        $this->pendingCookies['undo'] = [
            'post_id' => $undoid,
            'key' => $undokey,
        ];
    }

    /**
     * Get pending cookies to be set in response
     */
    public function getPendingCookies(): array
    {
        return $this->pendingCookies;
    }

    /**
     * Remove follow links from message
     *
     * @param string $message Message text
     * @return string Message with follow links removed
     */
    private function removeFollowLinks(string $message): string
    {
        $followUrl = route('follow', ['s' => '']);
        $pattern = '/<a href="' . preg_quote($followUrl, '/') . '[^"]*"[^>]*>[^<]+<\/a>/i';
        return preg_replace($pattern, '', $message);
    }
}
