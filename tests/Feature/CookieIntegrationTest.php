<?php

use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use App\Services\CookieService;

beforeEach(function () {
    // Create Slim app
    $this->app = AppFactory::create();
    $this->cookieService = new CookieService();
    $this->requestFactory = new ServerRequestFactory();
});

test('user cookie workflow: set and get', function () {
    // Setup route that sets cookie
    $this->app->get('/set-cookie', function ($request, $response) {
        $cookieService = new CookieService();
        return $cookieService->setUserCookie($response, 'TestUser', 'test@example.com', '#FF0000');
    });
    
    // Create request
    $request = $this->requestFactory->createServerRequest('GET', '/set-cookie');
    
    // Execute
    $response = $this->app->handle($request);
    
    // Verify Set-Cookie header
    $cookies = $response->getHeader('Set-Cookie');
    expect($cookies)->toHaveCount(1);
    expect($cookies[0])->toContain('c=');
    expect($cookies[0])->toContain('HttpOnly');
    
    // Extract cookie value
    preg_match('/c=([^;]+)/', $cookies[0], $matches);
    $cookieValue = urldecode($matches[1]);
    
    // Verify we can read it back
    $request2 = $this->requestFactory->createServerRequest('GET', '/')
        ->withCookieParams(['c' => $cookieValue]);
    
    $userData = $this->cookieService->getUserCookie($request2);
    expect($userData)->toBe([
        'name' => 'TestUser',
        'email' => 'test@example.com',
        'color' => '#FF0000',
    ]);
});

test('undo cookie workflow: set and get', function () {
    // Setup route that sets undo cookie
    $this->app->get('/set-undo', function ($request, $response) {
        $cookieService = new CookieService();
        return $cookieService->setUndoCookie($response, '12345', 'abcdef');
    });
    
    $request = $this->requestFactory->createServerRequest('GET', '/set-undo');
    $response = $this->app->handle($request);
    
    // Verify Set-Cookie header
    $cookies = $response->getHeader('Set-Cookie');
    expect($cookies)->toHaveCount(1);
    expect($cookies[0])->toContain('undo=');
    
    // Extract cookie value
    preg_match('/undo=([^;]+)/', $cookies[0], $matches);
    $cookieValue = urldecode($matches[1]);
    
    // Verify we can read it back
    $request2 = $this->requestFactory->createServerRequest('GET', '/')
        ->withCookieParams(['undo' => $cookieValue]);
    
    $undoData = $this->cookieService->getUndoCookie($request2);
    expect($undoData)->toBe([
        'post_id' => '12345',
        'key' => 'abcdef',
    ]);
});

test('delete cookie workflow', function () {
    // Setup route that deletes cookie
    $this->app->get('/delete-cookie', function ($request, $response) {
        $cookieService = new CookieService();
        return $cookieService->deleteCookie($response, 'c');
    });
    
    $request = $this->requestFactory->createServerRequest('GET', '/delete-cookie');
    $response = $this->app->handle($request);
    
    // Verify expired cookie
    $cookies = $response->getHeader('Set-Cookie');
    expect($cookies)->toHaveCount(1);
    expect($cookies[0])->toContain('c=');
    expect($cookies[0])->toContain('Expires=Thu, 01 Jan 1970'); // Expired
});

test('cookie persists across requests', function () {
    // First request: set cookie
    $this->app->get('/login', function ($request, $response) {
        $cookieService = new CookieService();
        return $cookieService->setUserCookie($response, 'User123', 'user@test.com', '#00FF00');
    });
    
    // Second request: read cookie
    $this->app->get('/profile', function ($request, $response) {
        $cookieService = new CookieService();
        $userData = $cookieService->getUserCookie($request);
        
        $response->getBody()->write(json_encode($userData));
        return $response->withHeader('Content-Type', 'application/json');
    });
    
    // Login
    $request1 = $this->requestFactory->createServerRequest('GET', '/login');
    $response1 = $this->app->handle($request1);
    
    // Extract cookie
    $cookies = $response1->getHeader('Set-Cookie');
    preg_match('/c=([^;]+)/', $cookies[0], $matches);
    $cookieValue = urldecode($matches[1]);
    
    // Access profile with cookie
    $request2 = $this->requestFactory->createServerRequest('GET', '/profile')
        ->withCookieParams(['c' => $cookieValue]);
    $response2 = $this->app->handle($request2);
    
    $body = (string) $response2->getBody();
    $data = json_decode($body, true);
    
    expect($data)->toBe([
        'name' => 'User123',
        'email' => 'user@test.com',
        'color' => '#00FF00',
    ]);
});

test('special characters in cookie values are handled correctly', function () {
    $response = $this->cookieService->setUserCookie(
        $this->app->getResponseFactory()->createResponse(),
        'User & Name',
        'test+email@example.com',
        '#FF00FF'
    );
    
    $cookies = $response->getHeader('Set-Cookie');
    preg_match('/c=([^;]+)/', $cookies[0], $matches);
    $cookieValue = urldecode($matches[1]);
    
    $request = $this->requestFactory->createServerRequest('GET', '/')
        ->withCookieParams(['c' => $cookieValue]);
    
    $userData = $this->cookieService->getUserCookie($request);
    
    expect($userData['name'])->toBe('User & Name');
    expect($userData['email'])->toBe('test+email@example.com');
});
