<?php

namespace Kuzuha;

use App\Config;
use App\Translator;
use App\Utils\DateHelper;
use App\Utils\StringHelper;

/*

KuzuhaScriptPHP ver0.0.7alpha (13:04 2003/02/18)
Tree view module

* Todo

* Memo

http://www.hlla.is.tsukuba.ac.jp/~yas/gen/it-2002-10-28/


*/

if (!defined('INCLUDED_FROM_BBS')) {
    header('Location: ../bbs.php?m=tree');
    exit();
}


/*
 * Module-specific settings
 *
 * They will be added to/overwritten by $CONF.
 */
$GLOBALS['CONF_TREEVIEW'] = [

    # Branch color
    'C_BRANCH' => '5ff',

    # Update time display color
    'C_UPDATE' => 'ccc',

    # New message color
    'C_NEWMSG' => 'fca',

    # Number of trees displayed
    'TREEDISP' => 32,

];





/**
 * Tree view module
 *
 *
 *
 * @package strangeworld.cnscript
 * @access  public
 */
class Treeview extends Bbs
{
    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $config = Config::getInstance();
        foreach ($GLOBALS['CONF_TREEVIEW'] as $key => $value) {
            $config->set($key, $value);
        }
        parent::__construct();
    }


    /**
     * Main processing
     */
    #[\Override]
    public function main()
    {

        # Start measuring execution time
        $this->setstarttime();

        # Form acquisiation preprocessing
        $this->procForm();

        # Reflect personal settings
        if (@$this->form['treem'] == 'p') {
            $this->form['m'] = 'p';
        }
        $this->refcustom();
        $this->setusersession();

        # gzip compressed transfer
        if ($this->config['GZIPU'] && ob_get_level() === 0) {
            ob_start('ob_gzhandler');
        }

        # Post operation
        if (@$this->form['treem'] == 'p' and trim((string) @$this->form['v'])) {

            # Get environment variables
            $this->setuserenv();

            # Parameter check
            $posterr = $this->chkmessage();

            # Post operation
            if (!$posterr) {
                $posterr = $this->putmessage($this->getformmessage());
            }

            # Double post error, etc
            if ($posterr == 1) {
                $this->prttreeview();
            }
            # Protect code redisplaying due to time lapse
            elseif ($posterr == 2) {
                if (@$this->form['f']) {
                    $this->prtfollow(true);
                } else {
                    $this->prttreeview(true);
                }
            }
            # Admin mode transition
            elseif ($posterr == 3) {
                define('BBS_ACTIVATED', true);
                $bbsadmin = new Bbsadmin($this);
                $bbsadmin->main();
            }
            # Post completion page
            elseif (@$this->form['f']) {
                $this->prtputcomplete();
            } else {
                $this->prttreeview();
            }
        }
        # User settings page display
        elseif (@$this->form['setup']) {
            $this->prtcustom('tree');
        }
        # Tree view of threads
        elseif (@$this->form['s']) {
            $this->prtthreadtree();
        }
        # Tree view main page
        else {
            $this->prttreeview();
        }

        if ($this->config['GZIPU']) {
            ob_end_flush();
        }
    }





    /**
     * Displaying tree view
     *
     * @todo  Measures for when some logs are deleted/removed
     */
    public function prttreeview($retry = false)
    {

        # Get display message
        [$logdata, $bindex, $eindex, $lastindex] = $this->getdispmessage();

        $isreadnew = false;
        #20200210 Gikoneko: unread pointer fix
        #        if ((@$this->form['readnew'] or ($this->session['MSGDISP'] == '0' and $bindex == 1)) and @$this->form['p'] > 0) {
        if ((@$this->form['readnew'] or ($this->session['MSGDISP'] == '0')) and @$this->form['p'] > 0) {
            $isreadnew = true;
        }

        $customstyle = $this->renderTwig('components/tree_customstyle.twig', $this->config);

        # HTML header partial output
        $this->sethttpheader();

        # Form section
        $dtitle = '';
        $dmsg = '';
        $dlink = '';
        if ($retry) {
            $dtitle = @$this->form['t'];
            $dmsg = @$this->form['v'];
            $dlink = @$this->form['l'];
        }
        $forminput = '<input type="hidden" name="m" value="tree" /><input type="hidden" name="treem" value="p" />';
        
        # Get form HTML using Twig
        $formData = $this->getFormData($dtitle, $dmsg, $dlink, $forminput);
        $formHtml = $this->renderTwig('components/form.twig', $formData);

        # Upper main section
        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'] . ' ' . Translator::trans('tree.tree_view'),
            'CUSTOMSTYLE' => $customstyle,
            'CUSTOMHEAD' => '',
            'FORM' => $formHtml,
            'TRANS_TREE_VIEW' => Translator::trans('tree.tree_view'),
            'TRANS_PR_OFFICE' => Translator::trans('tree.pr_office'),
            'TRANS_EMAIL_ADMIN' => Translator::trans('tree.email_admin'),
            'TRANS_CONTACT' => Translator::trans('tree.contact'),
            'TRANS_MESSAGE_LOGS' => Translator::trans('tree.message_logs'),
            'TRANS_MESSAGE_LOGS_TITLE' => Translator::trans('tree.message_logs_title'),
            'TRANS_STANDARD_VIEW' => Translator::trans('tree.standard_view'),
            'TRANS_STANDARD_VIEW_TITLE' => Translator::trans('tree.standard_view_title'),
            'TRANS_BOTTOM' => Translator::trans('tree.bottom'),
        ]);
        echo $this->renderTwig('tree/upper.twig', $data);

        $threadindex = 0;

        # Process in order of threads with the latest post time
        while (count($logdata) > 0) {

            $msgcurrent = $this->getmessage(array_shift($logdata));
            if (!$msgcurrent['THREAD']) {
                $msgcurrent['THREAD'] = $msgcurrent['POSTID'];
            }

            # Extract threads from $logdata and create message array $thread
            $thread = [$msgcurrent];
            $i = 0;
            while ($i < count($logdata)) {
                $message = $this->getmessage($logdata[$i]);
                if ($message['THREAD'] == $msgcurrent['THREAD']
                    or $message['POSTID'] == $msgcurrent['THREAD']) {
                    array_splice($logdata, $i, 1);
                    $thread[] = $message;
                    # Detect root
                    if ($message['POSTID'] == $message['THREAD'] or !$message['THREAD']) {
                        break;
                    }
                } else {
                    $i++;
                }
            }

            # Unread reload
            if ($isreadnew) {
                $hit = false;
                for ($i = 0; $i < count($thread); $i++) {
                    if ($thread[$i]['POSTID'] > $this->form['p']) {
                        $hit = true;
                        break;
                    }
                }
                if (!$hit) {
                    continue;
                }
            }
            # Beginning index
            if ($this->session['MSGDISP'] >= 0 && $threadindex < $bindex - 1) {
                $threadindex++;
                continue;
            }

            # Extract reference IDs from "reference"
            foreach ($thread as $message) {
                if (!@$message['REFID']) {
                    $followPattern = preg_quote(route('follow', ['s' => '']), '/');
                    if (preg_match("/<a href=\"{$followPattern}(\d+)[^>]+>([^<]+)<\/a>$/i", (string) $message['MSG'], $matches)) {
                        $message['REFID'] = $matches[1];
                    } elseif (preg_match("/<a href=\"mode=follow&search=(\d+)[^>]+>([^<]+)<\/a>$/i", (string) $message['MSG'], $matches)) {
                        $message['REFID'] = $matches[1];
                    }
                }
            }

            # Output $thread text tree
            $this->prttexttree($msgcurrent, $thread);

            $threadindex++;

            if ($threadindex > $eindex - 1) {
                break;
            }
        }

        $eindex = $threadindex;

        # Message information
        if ($this->session['MSGDISP'] < 0) {
            $msgmore = '';
        } elseif ($eindex > 0) {
            $msgmore = "Shown above are threads {$bindex} through {$eindex}, displayed in order of most recently updated to least recently updated.";
        } else {
            $msgmore = 'There are no unread messages. ';
        }
        if (count($logdata) == 0) {
            $msgmore .= 'There are no threads below this point.';
        }


        # Navigation button
        if ($eindex > 0) {
            if ($eindex >= $lastindex) {
            } else {
            }
            if (!$this->config['SHOW_READNEWBTN']) {
                $showReadnew = false;
            } else {
                $showReadnew = true;
            }
        }

        # Administrator post
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
            'SHOW_NEXTPAGE' => isset($eindex),
            'EINDEX' => $eindex ?? '',
            'SHOW_READNEW' => $showReadnew ?? false,
            'SHOW_ADMINLOGIN' => $showAdminLogin,
            'DURATION' => $duration,
            'TRANS_PAGE_GENERATION_TIME' => $transPageGenerationTime,
            'TRANS_NEXT_PAGE' => Translator::trans('tree.next_page'),
            'TRANS_RELOAD' => Translator::trans('tree.reload'),
            'TRANS_UNREAD' => Translator::trans('tree.unread'),
            'TRANS_TOP' => Translator::trans('tree.top'),
            'TRANS_POST_AS_ADMIN' => Translator::trans('tree.post_as_admin'),
        ]);
        echo $this->renderTwig('tree/lower.twig', $data);
    }





    /**
     * Text tree output
     *
     * @param   Array   &$msgcurrent  Parent message
     * @param   Array   &$thread      Array of messages containing parents and children
     */
    public function prttexttree(&$msgcurrent, &$thread)
    {

        $threadParams = ['s' => $msgcurrent['THREAD']];
        parse_str($this->session['QUERY'], $queryParams);
        $threadParams = array_merge($threadParams, $queryParams);
        print "<pre class=\"msgtree\"><a href=\"" . route('thread', $threadParams) . "\" target=\"link\">{$this->config['TXTTHREAD']}</a>";
        $msgcurrent['WDATE'] = DateHelper::getDateString($msgcurrent['NDATE']);
        $dateUpdatedLabel = Translator::trans('tree.date_updated');
        print "<span class=\"update\"> [{$dateUpdatedLabel}: {$msgcurrent['WDATE']}]</span>\r";
        $tree = & $this->gentree(array_reverse($thread), $msgcurrent['THREAD']);
        $tree = str_replace('</span><span class="bc">', '', $tree);
        $tree = str_replace('</span>　<span class="bc">', '　', $tree);
        $tree = '　' . str_replace("\r", "\r　", $tree);

        #20181110 Gikoneko: Escape special characters
        $tree = str_replace('{', '&#123;', $tree);
        $tree = str_replace('}', '&#125;', $tree);

        #20200207 Gikoneko: span style=tag enabled
        #    $tree = preg_replace("/&lt;span style=&quot;(.+?)&quot;&gt;(.+?)&lt;\/span&gt;/","<span style=\"$1\">$2</span>", $tree);

        #20200207 Gikoneko: font color="tag enabled
        #    $tree = preg_replace("/&lt;font color=&quot;([a-zA-Z#0-9]+)&quot;&gt;(.+?)&lt;\/font&gt;/","<font color=\"$1\">$2</font>", $tree);

        #20200201 Gikoneko: font color=tag enabled
        #    $tree = preg_replace("/&lt;font color=([a-zA-Z#0-9]+)&gt;(.+?)&lt;\/font&gt;/","<font color=$1>$2</font>", $tree);

        #20181110 Gikoneko: Unicode conversion
        #$tree  = preg_replace("/&amp;#(\d+);/","&#$1;", $tree );

        #20181115 Gikoneko: Personal word filter
        #$tree  = preg_replace("/(.+)/","<span class= \"ngline\">$1</span>", $tree );

        print $tree . "</pre>\n\n<hr>\n\n";

    }




    /**
     * Recursive function for text tree generation
     *
     * @param   Array   &$treemsgs  Array of messages containing parents and children
     * @param   Integer $parentid   Parent ID
     * @return  String  &$treeprint Parent-child tree string
     */
    public function &gentree(&$treemsgs, $parentid)
    {

        # Tree string
        $treeprint = '';

        # Outputting parent message
        reset($treemsgs);
        foreach ($treemsgs as $pos => $treemsg) {
            if ($treemsg['POSTID'] == $parentid) {

                # Delete reference
                $treemsg['MSG'] = preg_replace("/<a href=[^>]+>Reference: [^<]+<\/a>/i", '', (string) $treemsg['MSG'], 1);

                # Delete quotes
                $treemsg['MSG'] = preg_replace("/(^|\r)&gt;[^\r]*/", '', $treemsg['MSG']);
                $treemsg['MSG'] = preg_replace("/^\r+/", '', $treemsg['MSG']);
                $treemsg['MSG'] = rtrim($treemsg['MSG']);

                #20181117 Gikoneko: Personal word filter
                $treemsg['MSG']  = preg_replace('/(.+)/', "<span class= \"ngline\">$1</span>\r", $treemsg['MSG']);

                # Link to the follow-up post page
                $followParams = ['s' => $parentid];
                parse_str($this->session['QUERY'], $queryParams);
                $followParams = array_merge($followParams, $queryParams);
                $treeprint .= "<a href=\"" . route('follow', $followParams) . "\" target=\"link\">{$this->config['TXTFOLLOW']}</a>";

                # Username
                if ($treemsg['USER'] and $treemsg['USER'] != $this->config['ANONY_NAME']) {
                    $userLabel = Translator::trans('tree.user');
                    $treeprint .= $userLabel . ': '.preg_replace('/<[^>]*>/', '', (string) $treemsg['USER'])."\r";
                }

                # Display new arrivals
                if (@$this->form['p'] > 0 and $treemsg['POSTID'] > $this->form['p']) {
                    $treemsg['MSG'] = '<span class="newmsg">' . $treemsg['MSG'] . '</span>';
                }

                # Hide images on the imageBBS
                $treemsg['MSG'] = StringHelper::convertImageTag($treemsg['MSG']);

                $treeprint .= $treemsg['MSG'];

                # Delete from array
                array_splice($treemsgs, $pos, 1);
                break;
            }
        }

        # Enumerate child IDs
        $childids = [];
        reset($treemsgs);
        foreach ($treemsgs as $treemsg) {
            if ($treemsg['REFID'] == $parentid) {
                $childids[] = $treemsg['POSTID'];
            }
        }

        # If there's children, extend the "│" branch
        if ($childids) {
            $treeprint = str_replace("\r", "\r".'<span class="bc">│</span>', $treeprint);
        }
        # If not, make the start of the line blank
        else {
            $treeprint = str_replace("\r", "\r".'　', $treeprint);
        }

        # Get the tree strings of children and join them together
        $childidcount = count($childids) - 1;
        foreach ($childids as $idx => $childid) {
            $childtree = & $this->gentree($treemsgs, $childid);

            # If there's another child, extend from "├" branch with a "│"
            if ($idx < $childidcount) {
                $childtree = '<span class="bc">├</span>' . str_replace("\r", "\r".'<span class="bc">│</span>', $childtree);
            }
            # If it's the last child, make the start of the line blank and use "└" branch
            else {
                $childtree = '<span class="bc">└</span>' . str_replace("\r", "\r".'　', $childtree);
            }

            # Join child string to its parent
            $treeprint .= "\r" . $childtree;
        }

        return $treeprint;
    }





    /**
     * Get display range messages and parameters
     *
     * @access  public
     * @return  Array   $logdatadisp  Log line array
     * @return  Integer $bindex       Beginning index
     * @return  Integer $eindex       Ending index
     * @return  Integer $lastindex    Last index for all logs
     * @return  Integer $msgdisp      Display results
     */
    #[\Override]
    public function getdispmessage()
    {

        $logdata = $this->loadmessage();

        # Unread pointer (latest POSTID)
        $items = @explode(',', (string) $logdata[0], 3);
        $toppostid = @$items[1];

        # Display results
        $msgdisp = StringHelper::fixNumberString(@$this->form['d']);
        if ($msgdisp === '' || $msgdisp === false) {
            $msgdisp = $this->config['TREEDISP'];
        } elseif ($msgdisp < 0) {
            $msgdisp = -1;
        } elseif ($msgdisp > $this->config['LOGSAVE']) {
            $msgdisp = $this->config['LOGSAVE'];
        }
        if (@$this->form['readzero']) {
            $msgdisp = 0;
        }

        # Beginning index
        $bindex = @$this->form['b'];
        if (!$bindex) {
            $bindex = 0;
        }

        # Ending index
        $eindex = $bindex + $msgdisp;

        # Unread reload
        #20200210 Gikoneko: unread pointer fix
        #        if ((@$this->form['readnew'] or ($msgdisp == '0' and $bindex == 0)) and @$this->form['p'] > 0) {
        if ((@$this->form['readnew'] or ($msgdisp == '0')) and @$this->form['p'] > 0) {
            $bindex = 0;
            #            $eindex = 0;
            $eindex = $toppostid - $this->form['p'];
        }

        # For the last page, truncate
        $lastindex = count($logdata);
        if ($eindex > $lastindex) {
            $eindex = $lastindex;
        }

        # Display -1 item
        if ($msgdisp < 0) {
            $bindex = 0;
            $eindex = 0;
        }

        $this->session['TOPPOSTID'] = $toppostid;
        $this->session['MSGDISP'] = $msgdisp;

        return [$logdata, $bindex + 1, $eindex, $lastindex];
    }





    /**
     * Tree view of individual threads
     *
     */
    public function prtthreadtree()
    {

        if (!@$this->form['s']) {
            $this->prterror('There are no parameters.');
        }

        $customstyle = <<<__XHTML__
    .bc { color:#{$this->config['C_BRANCH']}; }
    .update { color:#{$this->config['C_UPDATE']}; }
    .newmsg { color:#{$this->config['C_NEWMSG']}; }

__XHTML__;

        $this->sethttpheader();
        
        // Output HTML header using Twig base template structure
        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'] . ' ' . Translator::trans('tree.tree_view'),
            'CUSTOMSTYLE' => $customstyle,
            'CUSTOMHEAD' => '',
        ]);
        echo $this->renderTwig('layout/base_header.twig', $data);
        echo "<hr>\n";

        $result = $this->msgsearchlist('t');
        if (@$this->form['ff']) {
            $msgcurrent = $result[count($result) - 1];
        } else {
            $msgcurrent = $result[0];
        }
        $this->prttexttree($msgcurrent, $result);

        $returnLabel = Translator::trans('tree.return');
        echo "<span class=\"bbsmsg\"><a href=\"{$this->session['DEFURL']}\">{$returnLabel}</a></span>\n";

        // Footer
        echo "<footer>\n";
        if ($this->config['SHOW_PRCTIME'] and $this->session['START_TIME']) {
            $duration = DateHelper::microtimeDiff($this->session['START_TIME'], microtime());
            $duration = sprintf('%0.6f', $duration);
            $pageGenLabel = Translator::trans('main.page_generation_time');
            $secondsLabel = Translator::trans('main.seconds');
            echo "<p><span class=\"msgmore\">{$pageGenLabel}: {$duration} {$secondsLabel}</span>　<a href=\"#top\" title=\"" . Translator::trans('main.top') . "\">▲</a></p>\n";
        } else {
            echo "<p><a href=\"#top\" title=\"" . Translator::trans('main.top') . "\">▲</a></p>\n";
        }
        echo "</footer>\n</body>\n</html>\n";

    }





}
