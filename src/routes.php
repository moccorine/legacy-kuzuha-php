<?php

use App\Config;
use App\Services\CookieService;
use App\Utils\SecurityHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Middleware: Set HTTP headers for all responses
$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Content-Type', 'text/html; charset=UTF-8')
        ->withHeader('X-XSS-Protection', '1; mode=block')
        ->withoutHeader('X-Frame-Options')
        ->withHeader('Content-Security-Policy', 'frame-ancestors *;');
});

// Middleware: Redirect legacy m= parameter to RESTful paths
$app->add(function (Request $request, $handler) {
    $queryParams = $request->getQueryParams();

    if (isset($queryParams['m'])) {
        $m = $queryParams['m'];
        $pathMap = [
            'g' => '/search',
            'tree' => '/tree',
            't' => '/thread',
            'f' => '/follow',
            'ad' => '/admin',
        ];

        if (isset($pathMap[$m])) {
            unset($queryParams['m']);
            $newQuery = http_build_query($queryParams);
            $newPath = $pathMap[$m] . ($newQuery ? '?' . $newQuery : '');

            return $handler->handle($request)
                ->withStatus(301)
                ->withHeader('Location', $newPath);
        }
    }

    return $handler->handle($request);
});

// Main bulletin board
$app->get('/', function (Request $request, Response $response) use ($container) {
    ob_start();

    // Set $_GET for legacy code
    $_GET = $request->getQueryParams();

    $config = Config::getInstance();
    $bbs = null;

    if ($config->get('BBSMODE_IMAGE') == 1) {
        $imagebbs = new \Kuzuha\Imagebbs();
        $imagebbs->main();
    } else {
        // Get repositories from container (autowired)
        $accessCounterRepo = $container->get(\App\Models\Repositories\AccessCounterRepositoryInterface::class);
        $participantCounterRepo = $container->get(\App\Models\Repositories\ParticipantCounterRepositoryInterface::class);
        $bbsLogRepo = $container->get(\App\Models\Repositories\BbsLogRepositoryInterface::class);
        $oldLogRepo = $container->get(\App\Models\Repositories\OldLogRepositoryInterface::class);

        $bbs = new \Kuzuha\Bbs($accessCounterRepo, $participantCounterRepo, $bbsLogRepo, $oldLogRepo);
        $bbs->main();
    }

    $output = ob_get_clean();
    if ($output !== false) {
        $response->getBody()->write($output);
    }

    // Apply pending cookies
    if ($bbs) {
        $cookieService = new CookieService();
        $response = $cookieService->applyPendingCookies($response, $bbs->getPendingCookies());
    }

    return $response;
});

// Post message
$app->post('/', function (Request $request, Response $response) use ($container) {
    ob_start();

    // Set $_POST and $_GET for legacy code
    $_POST = $request->getParsedBody() ?? [];
    $_GET = $request->getQueryParams();

    $config = Config::getInstance();
    $bbs = null;

    if ($config->get('BBSMODE_IMAGE') == 1) {
        $imagebbs = new \Kuzuha\Imagebbs();
        $imagebbs->main();
    } else {
        // Get repositories from container (autowired)
        $accessCounterRepo = $container->get(\App\Models\Repositories\AccessCounterRepositoryInterface::class);
        $participantCounterRepo = $container->get(\App\Models\Repositories\ParticipantCounterRepositoryInterface::class);
        $bbsLogRepo = $container->get(\App\Models\Repositories\BbsLogRepositoryInterface::class);
        $oldLogRepo = $container->get(\App\Models\Repositories\OldLogRepositoryInterface::class);

        $bbs = new \Kuzuha\Bbs($accessCounterRepo, $participantCounterRepo, $bbsLogRepo, $oldLogRepo);
        $bbs->main();
    }

    $output = ob_get_clean();
    if ($output !== false) {
        $response->getBody()->write($output);
    }

    // Apply pending cookies
    if ($bbs) {
        $cookieService = new CookieService();
        $response = $cookieService->applyPendingCookies($response, $bbs->getPendingCookies());
    }

    return $response;
});

// Message log search
$app->map(['GET', 'POST'], '/search', function (Request $request, Response $response) use ($container) {
    ob_start();

    $oldLogRepo = $container->get(\App\Models\Repositories\OldLogRepositoryInterface::class);
    $getlog = new \Kuzuha\Getlog($oldLogRepo);
    $getlog->main();

    $output = ob_get_clean();
    if ($output !== false) {
        $response->getBody()->write($output);
    }
    return $response;
});

// Tree view
$app->map(['GET', 'POST'], '/tree', function (Request $request, Response $response) {
    ob_start();

    $treeview = new \Kuzuha\Treeview();
    $treeview->main();

    $output = ob_get_clean();
    if ($output !== false) {
        $response->getBody()->write($output);
    }
    return $response;
});

// Admin mode
$app->map(['GET', 'POST'], '/admin', function (Request $request, Response $response) {
    ob_start();

    $queryParams = $request->getQueryParams();
    $parsedBody = $request->getParsedBody();
    $_GET = $queryParams ?? [];
    $_POST = $parsedBody ?? [];

    $config = Config::getInstance();
    if ($config->get('ADMINPOST') && $config->get('ADMINKEY')
        && $_POST['v'] == $config->get('ADMINKEY')
        && SecurityHelper::verifyAdminPassword((string) $_POST['u'], (string) $config->get('ADMINPOST'))) {
        $bbsLogRepository = $container->get(\App\Models\Repositories\BbsLogRepositoryInterface::class);
        $bbsadmin = new \Kuzuha\Bbsadmin($bbsLogRepository);
        $bbsadmin->main();
    } elseif ($config->get('BBSMODE_IMAGE') == 1) {
        $imagebbs = new \Kuzuha\Imagebbs();
        $imagebbs->main();
    } else {
        $bbs = new \Kuzuha\Bbs();
        $bbs->main();
    }

    $output = ob_get_clean();
    if ($output !== false) {
        $response->getBody()->write($output);
    }
    return $response;
});

// Thread view
$app->map(['GET', 'POST'], '/thread', function (Request $request, Response $response) {
    ob_start();

    $_GET = $request->getQueryParams();
    $_POST = $request->getParsedBody() ?? [];

    $config = Config::getInstance();
    if ($config->get('BBSMODE_IMAGE') == 1) {
        $imagebbs = new \Kuzuha\Imagebbs();
        $imagebbs->loadAndSanitizeInput();
        $imagebbs->prtsearchlist();
    } else {
        $bbs = new \Kuzuha\Bbs();
        $bbs->loadAndSanitizeInput();
        $bbs->prtsearchlist();
    }

    $output = ob_get_clean();
    if ($output !== false) {
        $response->getBody()->write($output);
    }
    return $response;
});

// Follow-up post page
$app->map(['GET', 'POST'], '/follow', function (Request $request, Response $response) {
    ob_start();

    $_GET = $request->getQueryParams();
    $_POST = $request->getParsedBody() ?? [];

    $config = Config::getInstance();
    if ($config->get('BBSMODE_IMAGE') == 1) {
        $imagebbs = new \Kuzuha\Imagebbs();
        $imagebbs->loadAndSanitizeInput();
        $imagebbs->prtfollow();
    } else {
        $bbs = new \Kuzuha\Bbs();
        $bbs->loadAndSanitizeInput();
        $bbs->prtfollow();
    }

    $output = ob_get_clean();
    if ($output !== false) {
        $response->getBody()->write($output);
    }
    return $response;
});
