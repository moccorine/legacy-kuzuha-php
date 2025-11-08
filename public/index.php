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

// Initialize Config singleton (loads .env and conf.php)
$config = Config::getInstance();

// Initialize Translator with locale from config
$locale = $_ENV['APP_LOCALE'] ?? 'en';
Translator::setLocale($locale);

// Change working directory to project root
chdir(__DIR__ . '/..');

// Load core BBS file (classes only, conf.php already loaded by Config)
require __DIR__ . '/../bbs.php';

$app = AppFactory::create();

// Load routes
require __DIR__ . '/../src/routes.php';

$app->run();
