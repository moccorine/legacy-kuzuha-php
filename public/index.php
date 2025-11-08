<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use App\Config;

require __DIR__ . '/../vendor/autoload.php';

// Initialize Config singleton (loads .env and conf.php)
Config::getInstance();

// Change working directory to project root
chdir(__DIR__ . '/..');

// Load core BBS file (classes only, conf.php already loaded by Config)
require __DIR__ . '/../bbs.php';

$app = AppFactory::create();

// Load routes
require __DIR__ . '/../src/routes.php';

$app->run();
