<?php

use App\Utils\UserAgentHelper;

beforeEach(function () {
    // Save original user agent
    $this->originalUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
});

afterEach(function () {
    // Restore original user agent
    if ($this->originalUserAgent !== null) {
        $_SERVER['HTTP_USER_AGENT'] = $this->originalUserAgent;
    } else {
        unset($_SERVER['HTTP_USER_AGENT']);
    }
});

test('supportsDownload returns true for Chrome', function () {
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    
    expect(UserAgentHelper::supportsDownload())->toBeTrue();
});

test('supportsDownload returns true for Firefox', function () {
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0';
    
    expect(UserAgentHelper::supportsDownload())->toBeTrue();
});

test('supportsDownload returns true for Safari', function () {
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';
    
    expect(UserAgentHelper::supportsDownload())->toBeTrue();
});

test('supportsDownload returns true for Edge', function () {
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0';
    
    expect(UserAgentHelper::supportsDownload())->toBeTrue();
});

test('supportsDownload returns true when no user agent', function () {
    unset($_SERVER['HTTP_USER_AGENT']);
    
    expect(UserAgentHelper::supportsDownload())->toBeTrue();
});

test('getBrowserName detects Chrome', function () {
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    
    expect(UserAgentHelper::getBrowserName())->toBe('Chrome');
});

test('getBrowserName detects Firefox', function () {
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0';
    
    expect(UserAgentHelper::getBrowserName())->toBe('Firefox');
});

test('isMac detects Mac OS', function () {
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';
    
    expect(UserAgentHelper::isMac())->toBeTrue();
});

test('isWindows detects Windows', function () {
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    
    expect(UserAgentHelper::isWindows())->toBeTrue();
});

test('isMobile detects mobile device', function () {
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
    
    expect(UserAgentHelper::isMobile())->toBeTrue();
});

test('isTablet detects tablet device', function () {
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
    
    expect(UserAgentHelper::isTablet())->toBeTrue();
});

test('getOSName detects Windows', function () {
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    
    expect(UserAgentHelper::getOSName())->toBe('Windows');
});

test('getOSName detects Mac OS', function () {
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';
    
    expect(UserAgentHelper::getOSName())->toBe('OS X');
});

test('getOSName detects Android', function () {
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.144 Mobile Safari/537.36';
    
    expect(UserAgentHelper::getOSName())->toBe('Android');
});

test('getOSName detects iOS', function () {
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
    
    expect(UserAgentHelper::getOSName())->toBe('iOS');
});
