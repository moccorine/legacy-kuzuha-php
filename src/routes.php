<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Main bulletin board
$app->get('/', function (Request $request, Response $response) {
    ob_start();
    
    if ($GLOBALS['CONF']['BBSMODE_IMAGE'] == 1) {
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
    
    if ($GLOBALS['CONF']['BBSMODE_IMAGE'] == 1) {
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
    
    if ($GLOBALS['CONF']['ADMINPOST'] && $GLOBALS['CONF']['ADMINKEY'] 
        && $_POST['v'] == $GLOBALS['CONF']['ADMINKEY']
        && crypt((string) $_POST['u'], (string) $GLOBALS['CONF']['ADMINPOST']) == $GLOBALS['CONF']['ADMINPOST']) {
        require_once PHP_BBSADMIN;
        $bbsadmin = new Bbsadmin();
        $bbsadmin->main();
    } elseif ($GLOBALS['CONF']['BBSMODE_IMAGE'] == 1) {
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
