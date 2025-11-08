<?php

// PHP version check
if (!str_starts_with(phpversion(), '8')) {
    echo 'Error: PHP version is '.phpversion().'. This script is compatible with PHP 8.0 and above.';
    exit();
}

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use App\Config;
use App\Translator;

require __DIR__ . '/../vendor/autoload.php';

// Change working directory to project root
chdir(__DIR__ . '/..');

// Set error output level
error_reporting(E_ERROR | E_WARNING | E_PARSE);

// Exception handler for logging stack traces
set_exception_handler(function ($exception) {
    $logFile = __DIR__ . '/../log/error.log';
    $message = sprintf(
        "[%s] Uncaught %s: %s\nFile: %s:%d\nStack trace:\n%s\n\n",
        date('Y-m-d H:i:s'),
        get_class($exception),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );
    @error_log($message, 3, $logFile);
    
    // Display user-friendly error
    http_response_code(500);
    echo "<h1>An error occurred</h1>";
    echo "<p>Error details have been logged to log/error.log</p>";
    if (error_reporting() & E_ERROR) {
        echo "<pre>" . htmlspecialchars($message) . "</pre>";
    }
    exit(1);
});

// Demote "Undefined array key" warnings to notice
set_error_handler(function ($errno, $error) {
    if (!str_starts_with($error, 'Undefined array key')) {
        return false;
    } else {
        trigger_error($error, E_USER_NOTICE);
        return true;
    }
}, E_WARNING);

// Initialize Config singleton (loads .env and conf.php)
$config = Config::getInstance();

// Initialize Translator with locale from config
$locale = $_ENV['APP_LOCALE'] ?? 'en';
Translator::setLocale($locale);

// Check if bulletin board is in service
if ($config->get('RUNMODE') == 2) {
    echo 'This bulletin board is currently out of service.';
    exit();
}

// Check hostname ban
if (\App\Utils\NetworkHelper::hostnameMatch($config->get('HOSTNAME_BANNED'), $config->get('HOSTAGENT_BANNED'))) {
    echo 'Access is prohibited.';
    exit();
}

// Define constants
define('CURRENT_TIME', time() - $config->get('DIFFTIME') * 60 * 60 + $config->get('DIFFSEC'));
define('INCLUDED_FROM_BBS', true);

// Load patTemplate library
require_once __DIR__ . '/../lib/patTemplate.php';

$app = AppFactory::create();

// Load routes
require __DIR__ . '/../src/routes.php';

$app->run();
