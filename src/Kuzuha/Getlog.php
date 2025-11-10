<?php

namespace Kuzuha;

use App\Config;
use App\Models\Repositories\OldLogRepositoryInterface;
use App\Translator;
use App\Utils\HtmlHelper;
use App\Utils\HtmlParser;
use App\Utils\PerformanceTimer;
use App\Utils\RegexPatterns;
use App\Utils\UserAgentHelper;
use App\Utils\ValidationRegex;

/*

KuzuhaScriptPHP ver0.0.7alpha (13:04 2003/02/18)
Message log viewer module

* Todo

*/

if (!defined('INCLUDED_FROM_BBS')) {
    header('Location: ../bbs.php?m=g');
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
    protected $oldLogRepository = null;

    /**
     * Constructor
     *
     */
    public function __construct(?OldLogRepositoryInterface $oldLogRepository = null)
    {
        $this->oldLogRepository = $oldLogRepository;

        $config = Config::getInstance();
        foreach ($GLOBALS['CONF_GETLOG'] as $key => $value) {
            $config->set($key, $value);
        }
        parent::__construct();
    }


    /**
     * Main process
     */
    public function main()
    {

        # Start measuring execution time
        PerformanceTimer::start();

        # Form acquisition preprocessing
        $this->loadAndSanitizeInput();

        # Reflect personal settings
        $this->applyUserPreferences();
        $this->initializeSession();

        # gzip compressed transfer
        if ($this->config['GZIPU'] && ob_get_level() === 0) {
            ob_start('ob_gzhandler');
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
        foreach (glob($dir . "*.$oldlogext") as $filepath) {
            $entry = basename($filepath);
            if (ValidationRegex::isNumericFilename($entry, $oldlogext)) {
                $files[] = $entry;
            }
        }

        # Sort by natural file name order
        natsort($files);

        # Check for files with the latest update time as standard
        $maxftime = 0;
        $checkedfile = '';
        foreach ($files as $filename) {
            $fstat = stat($dir . $filename);
            if ($fstat[9] > $maxftime) {
                $maxftime = $fstat[9];
                $checkedfile = $filename;
            }
        }

        $showZipLink = $this->config['ZIPDIR'] && function_exists('gzcompress');
        $showTopicLink = (bool)$this->config['OLDLOGFMT'];
        $showDlLink = $this->dlchk();

        $fileList = [];
        foreach ($files as $filename) {
            $fstat = stat($dir . $filename);
            $fsize = $fstat[7];
            $ftime = date('Y/m/d H:i:s', $fstat[9]);
            $ftitle = '';

            // Parse log filename (YYYYMMDD.dat or YYYYMM.dat)
            $info = pathinfo($filename);
            if ($info['extension'] === $oldlogext && ctype_digit($info['filename'])) {
                $len = strlen($info['filename']);
                if ($len === 8) {
                    // YYYYMMDD format
                    $ftitle = substr($info['filename'], 0, 4) . '/' .
                              substr($info['filename'], 4, 2) . '/' .
                              substr($info['filename'], 6, 2);
                } elseif ($len === 6) {
                    // YYYYMM format
                    $ftitle = substr($info['filename'], 0, 4) . '/' .
                              substr($info['filename'], 4, 2);
                } else {
                    $ftitle = $filename;
                }
            } else {
                $ftitle = $filename;
            }

            $checked = ($filename == $checkedfile);

            $fileList[] = [
                'FILENAME' => $filename,
                'FTITLE' => $ftitle,
                'FTIME' => $ftime,
                'FSIZE' => $fsize,
                'CHECKED' => $checked,
            ];
        }

        $chkSi = ($this->config['SHOWIMG']) ? ' checked="checked"' : '';
        $chkG = ($this->config['GZIPU']) ? ' checked="checked"' : '';
        $showImageCheck = ($this->config['BBSMODE_IMAGE'] == 1);
        $showButtonCheck = ($this->config['OLDLOGFMT'] && $this->config['OLDLOGBTN']);

        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'] . ' ' . Translator::trans('log.message_logs'),
            'files' => $fileList,
            'SHOW_ZIP_LINK' => $showZipLink,
            'SHOW_TOPIC_LINK' => $showTopicLink,
            'SHOW_DL_LINK' => $showDlLink,
            'MULTIPLE_SEARCH' => (bool)@$this->config['MULTIPLESEARCH'],
            'CHK_SI' => $chkSi,
            'CHK_G' => $chkG,
            'SHOW_IMAGE_CHECK' => $showImageCheck,
            'SHOW_BUTTON_CHECK' => $showButtonCheck,
            'TRANS_MESSAGE_LOGS' => Translator::trans('log.message_logs'),
            'TRANS_RETURN' => Translator::trans('log.return'),
            'TRANS_LIST_OF_LOGS' => Translator::trans('log.list_of_logs'),
            'TRANS_ZIP_ARCHIVES' => Translator::trans('log.zip_archives'),
            'TRANS_TOPIC_LIST' => Translator::trans('log.topic_list'),
            'TRANS_DOWNLOAD' => Translator::trans('log.download'),
            'TRANS_SELECT_DESELECT_ALL' => Translator::trans('log.select_deselect_all'),
            'TRANS_JAVASCRIPT_REQUIRED' => Translator::trans('log.javascript_required'),
            'TRANS_SEARCH_LOGS' => Translator::trans('log.search_logs'),
            'TRANS_SPECIFY_KEYWORDS' => Translator::trans('log.specify_keywords'),
            'TRANS_SEARCH' => Translator::trans('log.search'),
            'TRANS_SPECIFY_TIME_RANGE' => Translator::trans('log.specify_time_range'),
            'TRANS_FROM' => Translator::trans('log.from'),
            'TRANS_TO' => Translator::trans('log.to'),
            'TRANS_BOOLEAN_OPERATOR' => Translator::trans('log.boolean_operator'),
            'TRANS_AND_SEARCH' => Translator::trans('log.and_search'),
            'TRANS_OR_SEARCH' => Translator::trans('log.or_search'),
            'TRANS_SEARCH_TARGET' => Translator::trans('log.search_target'),
            'TRANS_ALL_TEXT' => Translator::trans('log.all_text'),
            'TRANS_USERNAMES' => Translator::trans('log.usernames'),
            'TRANS_TITLES' => Translator::trans('log.titles'),
            'TRANS_OTHER' => Translator::trans('log.other'),
            'TRANS_CASE_INSENSITIVE' => Translator::trans('log.case_insensitive'),
            'TRANS_SHOW_IMAGES' => Translator::trans('log.show_images'),
            'TRANS_SHOW_POST_BUTTONS' => Translator::trans('log.show_post_buttons'),
            'TRANS_GZIP_COMPRESSION' => Translator::trans('log.gzip_compression'),
            'TRANS_BULLETIN_BOARD' => Translator::trans('admin.bulletin_board'),
        ]);
        echo $this->renderTwig('log/list.twig', $data);
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

        // Support both old format (sh, si, eh, ei) and new HTML5 time input (start_time, end_time)
        if (!empty($this->form['start_time'])) {
            // Parse HTML5 time input (HH:MM format)
            list($sh, $si) = explode(':', $this->form['start_time']);
            $conditions['sh'] = str_pad($sh, 2, '0', STR_PAD_LEFT);
            $conditions['si'] = str_pad($si, 2, '0', STR_PAD_LEFT);
            // Only apply filter if not default value (00:00)
            if ($this->form['start_time'] !== '00:00') {
                $conditions['showall'] = false;
            }
        } else {
            $conditions['sh'] = str_pad((string) @$this->form['sh'], 2, '0', STR_PAD_LEFT);
            $conditions['si'] = str_pad((string) @$this->form['si'], 2, '0', STR_PAD_LEFT);
            if ($conditions['showall'] && (@$this->form['sh'] || @$this->form['si'])) {
                $conditions['showall'] = false;
            }
        }

        if (!empty($this->form['end_time'])) {
            // Parse HTML5 time input (HH:MM format)
            list($eh, $ei) = explode(':', $this->form['end_time']);
            $conditions['eh'] = str_pad($eh, 2, '0', STR_PAD_LEFT);
            $conditions['ei'] = str_pad($ei, 2, '0', STR_PAD_LEFT);
            // Only apply filter if not default value (23:59)
            if ($this->form['end_time'] !== '23:59') {
                $conditions['showall'] = false;
            }
        } else {
            $conditions['eh'] = str_pad((string) @$this->form['eh'], 2, '0', STR_PAD_LEFT);
            $conditions['ei'] = str_pad((string) @$this->form['ei'], 2, '0', STR_PAD_LEFT);
            if ($conditions['showall'] && (@$this->form['eh'] || @$this->form['ei'])) {
                $conditions['showall'] = false;
            }
        }

        // Handle day fields for monthly logs
        foreach (['sd', 'ed'] as $formvalue) {
            if ($conditions['showall'] and @$this->form[$formvalue]) {
                $conditions['showall'] = false;
            }
            $conditions[$formvalue] = str_pad((string) @$this->form[$formvalue], 2, '0', STR_PAD_LEFT);
        }

        // Set default values for monthly logs if not specified
        if ($this->config['OLDLOGSAVESW']) {
            if (empty($this->form['sd']) || $conditions['sd'] === '00') {
                $conditions['sd'] = '01';
            }
            if (empty($this->form['ed']) || $conditions['ed'] === '00') {
                $conditions['ed'] = '31';
            }
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
            // Check if filename starts with digits and file exists
            $info = pathinfo((string) $filename);
            $hasNumericPrefix = isset($info['filename'][0]) && ctype_digit($info['filename'][0]);

            if ($hasNumericPrefix && is_file($this->config['OLDLOGFILEDIR'] . $filename)) {
                $files[] = $filename;
            }
        }

        $customstyle = "  .sq { color: #{$this->config['C_QUERY']}; }\n";

        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'] . ' ' . Translator::trans('log.search_results'),
            'CUSTOMSTYLE' => $customstyle,
            'TRANS_SEARCH_RESULTS' => Translator::trans('log.search_results'),
            'TRANS_RETURN' => Translator::trans('log.return'),
        ]);
        echo $this->renderTwig('log/searchresult.twig', $data);

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
        if (!ValidationRegex::isNumericFilename((string) $filename, $oldlogext)) {
            return 1;
        } elseif (!is_file($this->config['OLDLOGFILEDIR'] . $filename)) {
            return 1;
        }

        $dlfilename = str_replace('.dat', '.html', $filename);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename='.$dlfilename);

        if ($this->config['OLDLOGFMT']) {
            print $this->prthtmlhead($this->config['BBSTITLE'] . ' ' . Translator::trans('log.message_logs'));
            $data = array_merge($this->config, $this->session, [
                'TITLE' => $this->config['BBSTITLE'] . ' ' . Translator::trans('log.message_logs'),
                'TRANS_MESSAGE_LOGS' => Translator::trans('log.message_logs'),
                'TRANS_RETURN' => Translator::trans('log.return'),
            ]);
            echo $this->renderTwig('log/htmldownload.twig', $data);
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
    public function prtoldlog($filename, $conditions = '', $isdownload = false)
    {

        $dir = $this->config['OLDLOGFILEDIR'];

        if ($this->config['OLDLOGFMT']) {
            $oldlogext = 'dat';
        } else {
            $oldlogext = 'html';
        }

        # Illegal file name
        if (!ValidationRegex::isNumericFilename((string) $filename, $oldlogext)) {
            return 1;
        } elseif (!is_file($dir . $filename)) {
            return 1;
        }

        if (!$this->oldLogRepository) {
            $data = array_merge($this->config, $this->session, [
                'FILENAME' => $filename,
                'SUCCESS' => false,
                'TRANS_UNABLE_TO_OPEN' => Translator::trans('log.unable_to_open'),
            ]);
            echo $this->renderTwig('log/oldlog_header.twig', $data);
            return 2;
        }

        try {
            $logdata = $this->oldLogRepository->getAll($filename);
        } catch (\RuntimeException $e) {
            $data = array_merge($this->config, $this->session, [
                'FILENAME' => $filename,
                'SUCCESS' => false,
                'TRANS_UNABLE_TO_OPEN' => Translator::trans('log.unable_to_open'),
            ]);
            echo $this->renderTwig('log/oldlog_header.twig', $data);
            return 2;
        }

        $timerangestr = '';
        if (!(!$this->config['OLDLOGFMT'] and !$conditions)) {
            if (!@$conditions['showall']) {
                if (@$conditions['savesw']) {
                    // Monthly logs: show day range only if not default (1-31)
                    if ($conditions['sd'] > 1 or $conditions['sh'] > 0 or $conditions['ed'] < 31 or $conditions['eh'] < 24) {
                        $timerangestr .= "Day {$conditions['sd']} Hour {$conditions['sh']} - Day {$conditions['ed']} Hour {$conditions['eh']}　";
                    }
                } else {
                    // Daily logs: show time range only if not default (00:00-23:59 or 24:00)
                    if ($conditions['sh'] > 0 or $conditions['si'] > 0 or $conditions['eh'] < 23 or ($conditions['eh'] == 23 and $conditions['ei'] < 59)) {
                        $timerangestr .= "Hour {$conditions['sh']} Minute {$conditions['si']} - Hour {$conditions['eh']} Minute {$conditions['ei']}　";
                    }
                }
            }
            $data = array_merge($this->config, $this->session, [
                'FILENAME' => $filename,
                'TIMERANGE' => $timerangestr,
            ]);
            echo $this->renderTwig('log/oldlog_header.twig', $data);
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
                foreach ($logdata as $logline) {
                    $message = $this->parseLogLine($logline);
                    $result = $this->msgsearch($message, $conditions);
                    # Search hit
                    if ($result == 1) {
                        $messageHtml = $this->renderMessage($message, $msgmode, $filename);
                        # Highlight search keywords
                        if ($conditions['q']) {
                            $needle = "\Q{$conditions['q']}\E";
                            $quoteq = preg_quote((string) $conditions['q'], '/');
                            if ($conditions['ci']) {
                                #$messageHtml = preg_replace("/($quoteq)/i", "<span class=\"sq\">$1</span>", $messageHtml);
                                #while (preg_match("/(<[^<>]*)<span class=\"sq\">$quoteq<\/span>/i", $messageHtml)) {
                                #  $messageHtml = preg_replace("/(<[^<>]*)<span class=\"sq\">$quoteq<\/span>/i", "$1", $messageHtml, 1);
                                #}
                                $messageHtml = preg_replace("/((?:\G|>)[^<]*?)($quoteq)/i", '$1<span class="sq"><mark>$2</mark></span>', $messageHtml);
                            } else {
                                #$messageHtml = str_replace($conditions['q'], "<span class=\"sq\">{$conditions['q']}</span>", $messageHtml);
                                #while (preg_match("/(<[^<>]*)<span class=\"sq\">$quoteq<\/span>/", $messageHtml)) {
                                #  $messageHtml = preg_replace("/(<[^<>]*)<span class=\"sq\">$quoteq<\/span>/", "$1", $messageHtml, 1);
                                #}
                                $messageHtml = preg_replace("/((?:\G|>)[^<]*?)($quoteq)/", '$1<span class="sq"><mark>$2</mark></span>', $messageHtml);
                            }
                        }
                        print $messageHtml;
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
                foreach ($logdata as $index => $logline) {
                    error_log("Line {$index}: " . var_export($logline, true));
                    $message = $this->parseLogLine($logline);
                    error_log("Message {$index}: " . var_export($message, true));
                    if ($message) {
                        $messagestr = $this->renderMessage($message, $msgmode, $filename);
                        print $messagestr;
                    }
                }
            }
        }
        # HTML search
        else {
            if (!$conditions['showall']) {
                // Buffers file reads for each message
                $buffer = '';
                $flgbuffer = false;
                $result = 0;
                foreach ($logdata as $htmlline) {
                    // Start message (check for div with id starting with 'm' followed by digits)
                    if (!$flgbuffer && str_contains($htmlline, '<div') && str_contains($htmlline, 'id="m')) {
                        $buffer = $htmlline;
                        $flgbuffer = true;
                    }
                    # End message
                    elseif ($flgbuffer and str_contains($htmlline, '<!--  -->')) {
                        $buffer .= $htmlline;
                        {
                            $result = $this->msgsearchhtml($buffer, $conditions);
                            if ($result == 1) {
                                # Search keyword highlighting
                                if ($conditions['q']) {
                                    $needle = "\Q{$conditions['q']}\E";
                                    $quoteq = preg_quote((string) $conditions['q'], '/');
                                    if ($conditions['ci']) {
                                        #$buffer = preg_replace("/($quoteq)/i", "<span class=\"sq\">$1</span>", $buffer);
                                        #while (preg_match("/(<[^<>]*)<span class=\"sq\">$quoteq<\/span>/i", $buffer)) {
                                        #  $buffer = preg_replace("/(<[^<>]*)<span class=\"sq\">$quoteq<\/span>/i", "$1", $buffer, 1);
                                        #}
                                        $buffer = preg_replace("/((?:\G|>)[^<]*?)($quoteq)/i", '$1<span class="sq"><mark>$2</mark></span>', $buffer);
                                    } else {
                                        #$buffer = str_replace($conditions['q'], "<span class=\"sq\">{$conditions['q']}</span>", $buffer);
                                        #while (preg_match("/(<[^<>]*)<span class=\"sq\">$quoteq<\/span>/", $buffer)) {
                                        #  $buffer = preg_replace("/(<[^<>]*)<span class=\"sq\">$quoteq<\/span>/", "$1", $buffer, 1);
                                        #}
                                        $buffer = preg_replace("/((?:\G|>)[^<]*?)($quoteq)/", '$1<span class="sq"><mark>$2</mark></span>', $buffer);
                                    }
                                }
                                print $buffer;
                                $resultcount++;
                            } elseif ($result == 2) {
                                break;
                            }
                        }
                        $buffer = '';
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
                foreach ($logdata as $htmlline) {
                    print $htmlline;
                }
            }
        }

        if (!(!$this->config['OLDLOGFMT'] and !$conditions)) {
            $resultmsg = '';
            if (!$conditions['showall']) {
                #$resultmsg = "{$filename}：&nbsp;{$timerangestr}&nbsp;";
                if (@$conditions['q'] != '') {
                    $value = htmlentities((string) $conditions['q'], ENT_QUOTES);
                    if ($resultcount > 0) {
                        $resultmsg .= Translator::trans('log.results_found', ['query' => $value, 'count' => $resultcount]);
                    } else {
                        $resultmsg .= Translator::trans('log.no_results', ['query' => $value]);
                    }
                } else {
                    if ($resultcount > 0) {
                        $resultmsg .= $resultcount . ' results found.';
                    }
                }
                $data = array_merge($this->config, $this->session, [
                    'RESULTMSG' => $resultmsg,
                ]);
                echo $this->renderTwig('log/oldlog_footer.twig', $data);
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

        // Parse HTML using DomCrawler (safer than regex)
        $parsed = HtmlParser::parseMessage($buffer);
        $message['USER'] = $parsed['USER'];
        $message['TITLE'] = $parsed['TITLE'];
        $message['MSG'] = $parsed['MSG'];

        if (isset($parsed['date_parts'])) {
            $dp = $parsed['date_parts'];
            if (@$conditions['savesw']) {
                $message['NDATESTR'] = $dp['day'] . $dp['hour'];
            } else {
                $message['NDATESTR'] = $dp['hour'] . $dp['minute'];
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
                $message['NDATESTR'] = date('dH', $message['NDATE']);
            }
        }
        # Daily
        else {
            $starttime = $conditions['sh'].$conditions['si'];
            $endtime = $conditions['eh'].$conditions['ei'];
            if (!@$message['NDATESTR']) {
                $message['NDATESTR'] = date('Hi', $message['NDATE']);
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
        if (!ValidationRegex::isNumericFilename((string) $filename, 'dat')) {
            return 1;
        } elseif (!is_file($this->config['OLDLOGFILEDIR'] . $filename)) {
            return 1;
        }

        if (!$this->oldLogRepository) {
            $this->prterror($filename . ' was unable to be opened.');
        }

        try {
            $logdata = $this->oldLogRepository->getAll($filename);
        } catch (\RuntimeException $e) {
            $this->prterror($filename . ' was unable to be opened.');
        }

        $tid = [];
        $tcount = [];
        $ttitle = [];
        $ttime = [];
        $tindex = 0;
        foreach ($logdata as $logline) {
            $message = $this->parseLogLine($logline);
            if (!$message['THREAD'] or $message['THREAD'] == $message['POSTID'] or !@$ttitle[$message['THREAD']]) {
                $tid[$tindex] = $message['POSTID'];
                $tcount[$message['POSTID']] = 0;

                $msg = ltrim((string) $message['MSG']);
                $msg = HtmlHelper::removeReferenceLink($msg);
                $msg = RegexPatterns::stripHtmlTags((string) $msg);
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

        $topics = [];
        $tidcount = count($tid);
        $i = 0;
        while ($i < $tidcount) {
            if ($tid[$i]) {
                $tc = sprintf('%02d', $tcount[$tid[$i]]);
                $tt = date('m/d H:i:s', $ttime[$tid[$i]]);
                $topics[] = [
                    'TID' => $tid[$i],
                    'TC' => $tc,
                    'TT' => $tt,
                    'TTITLE' => html_entity_decode($ttitle[$tid[$i]], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                ];
            }
            $i++;
        }

        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'] . ' ' . Translator::trans('log.topic_list_title') . ' ' . $filename,
            'FILENAME' => $filename,
            'topics' => $topics,
            'TRANS_TOPIC_LIST' => Translator::trans('log.topic_list_title'),
            'TRANS_THREAD_VIEW' => Translator::trans('log.thread_view'),
            'TRANS_TREE_VIEW' => Translator::trans('log.tree_view'),
            'TRANS_REPLIES' => Translator::trans('log.replies'),
            'TRANS_LAST_UPDATED' => Translator::trans('log.last_updated'),
            'TRANS_CONTENTS' => Translator::trans('log.contents'),
            'TRANS_MESSAGE_LOG_SEARCH' => Translator::trans('log.message_log_search'),
            'TRANS_BULLETIN_BOARD' => Translator::trans('admin.bulletin_board'),
        ]);
        echo $this->renderTwig('log/topiclist.twig', $data);

    }





    /**
     * Display ZIP archive list page
     *
     */
    public function prtarchivelist()
    {

        $dir = $this->config['ZIPDIR'];

        $archives = [];
        foreach (glob($dir . '*.{zip,lzh,rar,gz,tar.gz}', GLOB_BRACE) as $filepath) {
            $entry = basename($filepath);
            $fstat = stat($filepath);
            $archives[] = [
                'DIR' => $dir,
                'FILENAME' => $entry,
                'FTIME' => date('Y/m/d H:i:s', $fstat[9]),
                'FSIZE' => $fstat[7],
            ];
        }

        # Sort by natural file name order
        usort($archives, function ($a, $b) {
            return strnatcmp($a['FILENAME'], $b['FILENAME']);
        });

        print $this->prthtmlhead($this->config['BBSTITLE'] . ' ' . Translator::trans('log.archive_title'));
        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'] . ' ' . Translator::trans('log.archive_title'),
            'ARCHIVES' => $archives,
            'TRANS_MESSAGE_LOG_SEARCH' => Translator::trans('log.message_log_search'),
            'TRANS_BULLETIN_BOARD' => Translator::trans('log.bulletin_board'),
            'TRANS_ZIP_ARCHIVES' => Translator::trans('log.zip_archives'),
            'TRANS_ARCHIVE_HEADER' => Translator::trans('log.archive_header'),
        ]);
        echo $this->renderTwig('log/archivelist.twig', $data);
        print $this->prthtmlfoot();

    }




    /**
     * Check download function availability
     */
    /**
     * Check if browser supports download
     *
     * @return bool True if browser is modern enough
     */
    public function dlchk()
    {
        return UserAgentHelper::supportsDownload();
    }









    protected function prthtmlfoot()
    {
        return '';
    }
}
