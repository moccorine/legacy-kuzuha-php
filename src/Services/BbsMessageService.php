<?php

namespace App\Services;

use App\Models\Repositories\BbsLogRepositoryInterface;
use App\Models\Repositories\OldLogRepositoryInterface;

class BbsMessageService
{
    // Post error codes
    public const POST_SUCCESS = 0;
    public const POST_ERROR_DUPLICATE = 1;
    public const POST_ERROR_RATE_LIMIT = 2;

    private BbsLogRepositoryInterface $bbsLogRepository;
    private ?OldLogRepositoryInterface $oldLogRepository;
    private array $config;
    private array $session;

    public function __construct(
        BbsLogRepositoryInterface $bbsLogRepository,
        ?OldLogRepositoryInterface $oldLogRepository,
        array $config,
        array $session
    ) {
        $this->bbsLogRepository = $bbsLogRepository;
        $this->oldLogRepository = $oldLogRepository;
        $this->config = $config;
        $this->session = $session;
    }

    /**
     * Save a new message to the bulletin board
     *
     * Validates the message against duplicate posts and rate limits,
     * then saves it to the main log and archive log.
     *
     * @param array $message Message data array (THREAD can be null as placeholder)
     * @param callable $setCookieCallback Callback to set cookies
     * @return int Error code
     */
    public function saveMessage(array $message, callable $setCookieCallback): int
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

            // Set cookies via callback
            $setCookieCallback($message['POSTID'], $message['PCODE']);

            // Save to archive log (daily/monthly file)
            if ($this->oldLogRepository) {
                $this->oldLogRepository->append(implode(',', array_values($msgdata)) . "\n");
            }

            return self::POST_SUCCESS;
        } finally {
            $this->bbsLogRepository->unlock();
        }
    }

    /**
     * Validate message and resolve thread ID from current log
     *
     * @param array $message Message data
     * @return array ['error' => int, 'thread' => string|null]
     */
    public function validateAndResolveThread(array $message): array
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
    public function buildMessageData(array $message): array
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
}
