<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Config;

// Main bulletin board
$app->get('/', function (Request $request, Response $response) {
    ob_start();
    
    $config = Config::getInstance();
    if ($config->get('BBSMODE_IMAGE') == 1) {
        require_once PHP_IMAGEBBS;
        $imagebbs = new Imagebbs();
        $imagebbs->main();
    } else {
        $bbs = new Bbs();
        $bbs->main();
    }
    
    $output = ob_get_clean();
    $response->getBody()->write($output);
    return $response;
});

// Post message
$app->post('/', function (Request $request, Response $response) {
    ob_start();
    
    $config = Config::getInstance();
    if ($config->get('BBSMODE_IMAGE') == 1) {
        require_once PHP_IMAGEBBS;
        $imagebbs = new Imagebbs();
        $imagebbs->main();
    } else {
        $bbs = new Bbs();
        $bbs->main();
    }
    
    $output = ob_get_clean();
    $response->getBody()->write($output);
    return $response;
});

// Message log search
$app->map(['GET', 'POST'], '/search', function (Request $request, Response $response) {
    ob_start();
    
    require_once PHP_GETLOG;
    $getlog = new Getlog();
    $getlog->main();
    
    $output = ob_get_clean();
    $response->getBody()->write($output);
    return $response;
});

// Tree view
$app->map(['GET', 'POST'], '/tree', function (Request $request, Response $response) {
    ob_start();
    
    require_once PHP_TREEVIEW;
    $treeview = new Treeview();
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
        require_once PHP_BBSADMIN;
        $bbsadmin = new Bbsadmin();
        $bbsadmin->main();
    } elseif ($config->get('BBSMODE_IMAGE') == 1) {
        require_once PHP_IMAGEBBS;
        $imagebbs = new Imagebbs();
        $imagebbs->main();
    } else {
        $bbs = new Bbs();
        $bbs->main();
    }
    
    $output = ob_get_clean();
    $response->getBody()->write($output);
    return $response;
});
