<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Change working directory to project root
chdir(__DIR__ . '/..');

// Load core BBS file (which includes conf.php and all classes)
require __DIR__ . '/../bbs.php';

$app = AppFactory::create();

// Load routes
require __DIR__ . '/../src/routes.php';

$app->run();
