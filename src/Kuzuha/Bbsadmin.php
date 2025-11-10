<?php

namespace Kuzuha;

use App\Models\Repositories\BbsLogRepositoryInterface;
use App\Translator;
use App\Utils\DateHelper;
use App\Utils\FileHelper;
use App\Utils\HtmlHelper;
use App\Utils\PerformanceTimer;
use App\Utils\RegexPatterns;
use App\Utils\SecurityHelper;

/**
 * Admin mode module
 *
 * @package strangeworld.cnscript
 * @access  public
 */
class Bbsadmin extends Webapp
{
    /**
     * Constructor
     * 
     * @param BbsLogRepositoryInterface $bbsLogRepository BBS log repository
     */
    public function __construct(
        private BbsLogRepositoryInterface $bbsLogRepository
    ) {
        parent::__construct();
        $this->setBbsLogRepository($bbsLogRepository);
    }

    /**
     * Main process
     */
    public function main()
    {
        if (!defined('BBS_ACTIVATED')) {
            // Start execution time measurement
            PerformanceTimer::start();
            
            // Form acquisition preprocessing
            $this->loadAndSanitizeInput();
            
            // Reflect user settings
            $this->applyUserPreferences();
            $this->initializeSession();
            
            // gzip compression transfer
            if ($this->config['GZIPU'] && ob_get_level() === 0) {
                ob_start('ob_gzhandler');
            }
        }

        // Route to appropriate handler based on admin mode
        $adminMode = $this->form['ad'] ?? '';
        
        switch ($adminMode) {
            case 'l':
                // Log file viewer
                $this->renderLogFile(true);
                break;
            
            case 'k':
                // Message deletion mode
                $this->renderDeleteList();
                break;
            
            case 'x':
                // Message deletion process
                if (isset($this->form['x'])) {
                    $this->killmessage($this->form['x']);
                }
                $this->renderDeleteList();
                break;
            
            case 'p':
                // Encrypted password generation page
                $this->renderPasswordSetup();
                break;
            
            case 'ps':
                // Encrypted password generation & display
                $this->renderEncryptedPassword($this->form['ps'] ?? '');
                break;
            
            case 'phpinfo':
                // Display server PHP configuration information
                phpinfo();
                break;
            
            default:
                // Admin menu page
                if (empty($this->config['ADMINPOST'])) {
                    $this->renderPasswordSetup();
                } else {
                    $this->renderAdminMenu();
                }
                break;
        }

        if (!defined('BBS_ACTIVATED') && $this->config['GZIPU']) {
            ob_end_flush();
        }
    }

    /**
     * Display admin menu page
     * 
     * Shows main administration menu with links to:
     * - Message deletion
     * - Log file viewer
     * - Password regeneration
     * - PHP info
     * 
     * @return void
     */
    public function renderAdminMenu(): void
    {
        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'] . ' ' . Translator::trans('admin.menu_title'),
            'V' => trim((string) $this->form['v']),
            
            // Translations
            'TRANS_ADMIN_MENU' => Translator::trans('admin.menu_title'),
            'TRANS_WARNING' => Translator::trans('admin.warning'),
            'TRANS_UNAUTHORIZED_ACCESS' => Translator::trans('admin.unauthorized_access'),
            'TRANS_DELETE_MESSAGES' => Translator::trans('admin.delete_messages'),
            'TRANS_VIEW_LOG' => Translator::trans('admin.view_log'),
            'TRANS_REGENERATE_PASSWORD' => Translator::trans('admin.regenerate_password'),
            'TRANS_PHP_INFO' => Translator::trans('admin.php_info'),
            'TRANS_CLOSE' => Translator::trans('admin.close'),
        ]);
        
        echo $this->renderTwig('admin/menu.twig', $data);
    }

    /**
     * Display message deletion list
     * 
     * Shows list of messages with checkboxes for deletion.
     * 
     * @return void
     */
    public function renderDeleteList(): void
    {
        if (!file_exists($this->config['LOGFILENAME'])) {
            $this->prterror('Failed to load message');
        }
        $logdata = file($this->config['LOGFILENAME']);

        $messages = [];
        foreach ($logdata as $logline) {
            $message = $this->parseLogLine($logline);
            $message['MSG'] = HtmlHelper::removeReferenceLink((string) $message['MSG']);
            $message['MSG'] = RegexPatterns::stripHtmlTags(ltrim($message['MSG']));
            $msgsplit = explode("\r", (string) $message['MSG']);
            $message['MSGDIGEST'] = $msgsplit[0];
            $index = 1;
            while ($index < count($msgsplit) - 1 and strlen($message['MSGDIGEST'] . $msgsplit[$index]) < 50) {
                $message['MSGDIGEST'] .= $msgsplit[$index];
                $index++;
            }
            $message['WDATE'] = DateHelper::getDateString($message['NDATE']);
            $message['USER_NOTAG'] = RegexPatterns::stripHtmlTags((string) $message['USER']);
            $messages[] = $message;
        }

        $data = array_merge($this->config, $this->session, [
            'TITLE' => $this->config['BBSTITLE'] . ' ' . Translator::trans('admin.deletion_mode'),
            'TRANS_DELETION_MODE' => Translator::trans('admin.deletion_mode'),
            'TRANS_RETURN' => Translator::trans('admin.return'),
            'TRANS_PERFORM_DELETION' => Translator::trans('admin.perform_deletion'),
            'TRANS_DELETION_INSTRUCTION' => Translator::trans('admin.deletion_instruction'),
            'TRANS_DELETION_HEADER' => Translator::trans('admin.deletion_header'),
            'V' => trim((string) $this->form['v']),
            'messages' => $messages,
        ]);
        echo $this->renderTwig('admin/killlist.twig', $data);
    }

    /**
     * Delete messages by post IDs
     * 
     * Deletes messages from main log and archives, including associated images.
     * 
     * @param array|string $killids Post ID(s) to delete
     * @return void
     */
    public function killmessage($killids): void
    {
        if (!$killids) {
            return;
        }

        // Normalize to array
        $postIds = is_array($killids) ? $killids : [$killids];

        // Delete from main log and get deleted lines
        $deletedLines = $this->bbsLogRepository->deleteMessages($postIds);

        // Extract timestamps for archive deletion
        $timestamps = [];
        foreach ($deletedLines as $line) {
            $items = explode(',', $line, 3);
            if (count($items) > 2) {
                $timestamps[$items[1]] = (int) $items[0]; // postId => timestamp
            }
        }

        // Delete associated images
        $this->deleteImagesFromMessages($deletedLines);

        // Delete from archive logs
        if ($this->config['OLDLOGFILEDIR']) {
            $this->deleteFromArchiveLogs($timestamps);
        }
    }

    /**
     * Delete images referenced in deleted messages
     */
    private function deleteImagesFromMessages(array $messageLines): void
    {
        foreach ($messageLines as $line) {
            if (preg_match('/<img [^>]*?src="([^"]+)"[^>]+>/i', $line, $matches)) {
                if (file_exists($matches[1])) {
                    unlink($matches[1]);
                }
            }
        }
    }

    /**
     * Delete messages from archive logs
     */
    private function deleteFromArchiveLogs(array $timestamps): void
    {
        $oldlogext = $this->config['OLDLOGFMT'] ? 'dat' : 'html';

        foreach ($timestamps as $postId => $timestamp) {
            $filename = $this->config['OLDLOGSAVESW']
                ? date('Ym', $timestamp) . ".$oldlogext"
                : date('Ymd', $timestamp) . ".$oldlogext";

            $filepath = $this->config['OLDLOGFILEDIR'] . $filename;

            if (!file_exists($filepath)) {
                continue;
            }

            $fh = @fopen($filepath, 'r+');
            if (!$fh) {
                continue;
            }

            flock($fh, LOCK_EX);
            fseek($fh, 0);

            $newlogdata = [];
            $hit = false;

            if ($this->config['OLDLOGFMT']) {
                // DAT format
                $needle = $timestamp . ',' . $postId . ',';
                while (($logline = FileHelper::getLine($fh)) !== false) {
                    if (!$hit && str_starts_with($logline, $needle)) {
                        $hit = true;
                    } else {
                        $newlogdata[] = $logline;
                    }
                }
            } else {
                // HTML format
                $needle = "<div class=\"m\" id=\"m{$postId}\">";
                $flgbuffer = false;
                while (($htmlline = FileHelper::getLine($fh)) !== false) {
                    if (!$hit && str_contains($htmlline, $needle)) {
                        $hit = true;
                        $flgbuffer = true;
                    } elseif ($flgbuffer && str_contains($htmlline, '<hr')) {
                        $flgbuffer = false;
                    } elseif (!$flgbuffer) {
                        $newlogdata[] = $htmlline;
                    }
                }
            }

            fseek($fh, 0);
            ftruncate($fh, 0);
            fwrite($fh, implode('', $newlogdata));
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /**
     * Display password setup page
     * 
     * Shows form for generating encrypted admin password.
     * 
     * @return void
     */
    public function renderPasswordSetup(): void
    {
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
     * Display encrypted password
     * 
     * Generates and displays encrypted password for admin configuration.
     * 
     * @param string $inputpass Plain text password
     * @return void
     */
    public function renderEncryptedPassword(string $inputpass): void
    {
        if (empty($inputpass)) {
            $this->prterror('No password has been set.');
        }

        $cryptpass = SecurityHelper::encryptAdminPassword($inputpass);
        $inputsize = strlen($cryptpass) + 10;

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
     * Display log file contents
     * 
     * Outputs raw log file as plain text.
     * 
     * @param bool $htmlescape Whether to escape HTML (currently unused)
     * @return void
     */
    public function renderLogFile(bool $htmlescape = false): void
    {
        header('Content-type: text/plain; charset=UTF-8');
        readfile($this->config['LOGFILENAME']);
    }
}
