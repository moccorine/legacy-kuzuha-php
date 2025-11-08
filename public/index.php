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
