<?php

namespace App\Services;

use App\Translator;
use App\Utils\SecurityHelper;
use App\Utils\StringHelper;

class BbsPostValidator
{
    private array $config;
    private array $form;
    private array $session;

    public function __construct(array $config, array $form, array $session)
    {
        $this->config = $config;
        $this->form = $form;
        $this->session = $session;
    }

    /**
     * Validate post message
     *
     * @param bool $limithost Whether or not to check for same host
     * @return int Error code (0: success, 2: retry, 3: admin mode)
     */
    public function validate(bool $limithost = true): int
    {
        // Admin mode check
        if ($this->form['v'] == $this->config['ADMINPOST']) {
            return 3; // Admin mode
        }

        // Required field validation
        if (!trim((string) $this->form['v'])) {
            return 2; // Retry
        }

        // Host limit check
        if ($limithost && $this->config['HOSTLIMIT']) {
            $limitHosts = explode(',', $this->config['HOSTLIMIT']);
            foreach ($limitHosts as $limitHost) {
                if (trim($limitHost) && strpos($this->session['HOST'], trim($limitHost)) !== false) {
                    return 2; // Retry
                }
            }
        }

        return 0; // Success
    }

    /**
     * Build post message array from form data
     *
     * @return array Message data
     */
    public function buildMessage(): array
    {
        $message = [];
        
        // Basic fields
        $message['USER'] = StringHelper::checkValue((string) $this->form['u']);
        $message['MAIL'] = StringHelper::checkValue((string) $this->form['m']);
        $message['TITLE'] = StringHelper::checkValue((string) $this->form['t']);
        $message['MSG'] = StringHelper::checkValue((string) $this->form['v']);
        $message['REFID'] = (int) ($this->form['s'] ?? 0);
        
        // Protection code
        if ($this->form['p']) {
            $message['PCODE'] = StringHelper::checkValue((string) $this->form['p']);
        } else {
            $message['PCODE'] = SecurityHelper::generateProtectionCode();
        }
        
        // Host and agent
        $message['PHOST'] = $this->session['HOST'];
        $message['AGENT'] = $this->session['AGENT'];
        
        return $message;
    }
}
