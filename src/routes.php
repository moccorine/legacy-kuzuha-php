<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Config;

// Main bulletin board
$app->get('/', function (Request $request, Response $response) {
    ob_start();
    
    $config = Config::getInstance();
    if ($config->get('BBSMODE_IMAGE') == 1) {
        $imagebbs = new \Kuzuha\Imagebbs();
        $imagebbs->main();
    } else {
        $bbs = new \Kuzuha\Bbs();
        $bbs->main();
    }
    
    $output = ob_get_clean();
    $response->getBody()->write($output);
    return $response;
});

// Post message
$app->post('/', function (Request $request, Response $response) {
    ob_start();
    
    // Set $_POST and $_GET for legacy code
    $_POST = $request->getParsedBody() ?? [];
    $_GET = $request->getQueryParams();
    
    $config = Config::getInstance();
    if ($config->get('BBSMODE_IMAGE') == 1) {
        $imagebbs = new \Kuzuha\Imagebbs();
        $imagebbs->main();
    } else {
        $bbs = new \Kuzuha\Bbs();
        $bbs->main();
    }
    
    $output = ob_get_clean();
    $response->getBody()->write($output);
    return $response;
});

// Message log search
$app->map(['GET', 'POST'], '/search', function (Request $request, Response $response) {
    ob_start();
    
    $getlog = new \Kuzuha\Getlog();
    $getlog->main();
    
    $output = ob_get_clean();
    $response->getBody()->write($output);
    return $response;
});

// Tree view
$app->map(['GET', 'POST'], '/tree', function (Request $request, Response $response) {
    ob_start();
    
    $treeview = new \Kuzuha\Treeview();
    $treeview->main();
    
    $output = ob_get_clean();
    $response->getBody()->write($output);
    return $response;
});

// Admin mode
$app->post('/admin', function (Request $request, Response $response) {
    ob_start();
    
    $parsedBody = $request->getParsedBody();
    $_POST = $parsedBody ?? [];
    
    $config = Config::getInstance();
    if ($config->get('ADMINPOST') && $config->get('ADMINKEY') 
        && $_POST['v'] == $config->get('ADMINKEY')
        && crypt((string) $_POST['u'], (string) $config->get('ADMINPOST')) == $config->get('ADMINPOST')) {
        $bbsadmin = new \Kuzuha\Bbsadmin();
        $bbsadmin->main();
    } elseif ($config->get('BBSMODE_IMAGE') == 1) {
        $imagebbs = new \Kuzuha\Imagebbs();
        $imagebbs->main();
    } else {
        $bbs = new \Kuzuha\Bbs();
        $bbs->main();
    }
    
    $output = ob_get_clean();
    $response->getBody()->write($output);
    return $response;
});
