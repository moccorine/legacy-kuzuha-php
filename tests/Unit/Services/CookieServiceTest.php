<?php

use App\Services\CookieService;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

beforeEach(function () {
    $this->service = new CookieService();
    $this->responseFactory = new ResponseFactory();
    $this->requestFactory = new ServerRequestFactory();
});

test('setUserCookie sets cookie with correct format', function () {
    $response = $this->responseFactory->createResponse();
    
    $response = $this->service->setUserCookie($response, 'TestUser', 'test@example.com', '#FF0000');
    
    $cookies = $response->getHeader('Set-Cookie');
    expect($cookies)->toHaveCount(1);
    expect($cookies[0])->toContain('c=u%3DTestUser%26i%3Dtest%40example.com%26c%3D%23FF0000');
    expect($cookies[0])->toContain('HttpOnly');
    expect($cookies[0])->toContain('SameSite=Lax');
});

test('getUserCookie returns null when cookie not set', function () {
    $request = $this->requestFactory->createServerRequest('GET', '/');
    
    $result = $this->service->getUserCookie($request);
    
    expect($result)->toBeNull();
});

test('getUserCookie parses cookie correctly', function () {
    $request = $this->requestFactory->createServerRequest('GET', '/')
        ->withCookieParams(['c' => 'u=TestUser&i=test@example.com&c=#FF0000']);
    
    $result = $this->service->getUserCookie($request);
    
    expect($result)->toBe([
        'name' => 'TestUser',
        'email' => 'test@example.com',
        'color' => '#FF0000',
    ]);
});

test('setUndoCookie sets cookie with correct format', function () {
    $response = $this->responseFactory->createResponse();
    
    $response = $this->service->setUndoCookie($response, '12345', 'abcdef');
    
    $cookies = $response->getHeader('Set-Cookie');
    expect($cookies)->toHaveCount(1);
    expect($cookies[0])->toContain('undo=p%3D12345%26k%3Dabcdef');
    expect($cookies[0])->toContain('HttpOnly');
});

test('getUndoCookie returns null when cookie not set', function () {
    $request = $this->requestFactory->createServerRequest('GET', '/');
    
    $result = $this->service->getUndoCookie($request);
    
    expect($result)->toBeNull();
});

test('getUndoCookie parses cookie correctly', function () {
    $request = $this->requestFactory->createServerRequest('GET', '/')
        ->withCookieParams(['undo' => 'p=12345&k=abcdef']);
    
    $result = $this->service->getUndoCookie($request);
    
    expect($result)->toBe([
        'post_id' => '12345',
        'key' => 'abcdef',
    ]);
});

test('deleteCookie sets expired cookie', function () {
    $response = $this->responseFactory->createResponse();
    
    $response = $this->service->deleteCookie($response, 'c');
    
    $cookies = $response->getHeader('Set-Cookie');
    expect($cookies)->toHaveCount(1);
    expect($cookies[0])->toContain('c=');
    expect($cookies[0])->toContain('Expires=');
});
