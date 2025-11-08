<?php

use App\Utils\NetworkHelper;

test('checkIpRange validates IP in CIDR range', function () {
    $cidr = '192.168.1.0/24';
    $ip = '192.168.1.100';
    
    $result = NetworkHelper::checkIpRange($cidr, $ip);
    
    expect($result)->toBeTrue();
});

test('checkIpRange rejects IP outside CIDR range', function () {
    $cidr = '192.168.1.0/24';
    $ip = '192.168.2.100';
    
    $result = NetworkHelper::checkIpRange($cidr, $ip);
    
    expect($result)->toBeFalse();
});

test('checkIpRange handles /32 CIDR', function () {
    $cidr = '192.168.1.100/32';
    $ip = '192.168.1.100';
    
    $result = NetworkHelper::checkIpRange($cidr, $ip);
    
    expect($result)->toBeTrue();
});

test('hostnameMatch returns false with empty arrays', function () {
    $result = NetworkHelper::hostnameMatch([], []);
    
    expect($result)->toBeFalse();
});
