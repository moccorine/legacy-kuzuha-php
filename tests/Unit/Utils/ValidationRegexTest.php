<?php

use App\Utils\ValidationRegex;

describe('isValidFilename', function () {
    test('accepts alphanumeric with dots and underscores', function () {
        expect(ValidationRegex::isValidFilename('file_name.txt'))->toBeTrue();
        expect(ValidationRegex::isValidFilename('test123.dat'))->toBeTrue();
        expect(ValidationRegex::isValidFilename('my.file.name'))->toBeTrue();
    });
    
    test('rejects special characters', function () {
        expect(ValidationRegex::isValidFilename('file name.txt'))->toBeFalse(); // space
        expect(ValidationRegex::isValidFilename('file/name.txt'))->toBeFalse(); // slash
        expect(ValidationRegex::isValidFilename('file@name.txt'))->toBeFalse(); // @
    });
    
    test('rejects empty string', function () {
        expect(ValidationRegex::isValidFilename(''))->toBeFalse();
    });
    
    test('accepts filename without extension', function () {
        expect(ValidationRegex::isValidFilename('filename'))->toBeTrue();
    });
});

describe('isValidHexColor', function () {
    test('accepts valid 6-digit hex colors', function () {
        expect(ValidationRegex::isValidHexColor('FF0000'))->toBeTrue();
        expect(ValidationRegex::isValidHexColor('00ff00'))->toBeTrue();
        expect(ValidationRegex::isValidHexColor('0000FF'))->toBeTrue();
        expect(ValidationRegex::isValidHexColor('abc123'))->toBeTrue();
    });
    
    test('rejects invalid hex colors', function () {
        expect(ValidationRegex::isValidHexColor('FFF'))->toBeFalse(); // too short
        expect(ValidationRegex::isValidHexColor('FFFFFFF'))->toBeFalse(); // too long
        expect(ValidationRegex::isValidHexColor('GGGGGG'))->toBeFalse(); // invalid chars
        expect(ValidationRegex::isValidHexColor('#FF0000'))->toBeFalse(); // with hash
    });
    
    test('rejects empty string', function () {
        expect(ValidationRegex::isValidHexColor(''))->toBeFalse();
    });
});

describe('isNumeric', function () {
    test('accepts numeric strings', function () {
        expect(ValidationRegex::isNumeric('123'))->toBeTrue();
        expect(ValidationRegex::isNumeric('0'))->toBeTrue();
        expect(ValidationRegex::isNumeric('999999'))->toBeTrue();
    });
    
    test('rejects non-numeric strings', function () {
        expect(ValidationRegex::isNumeric('12.3'))->toBeFalse(); // decimal
        expect(ValidationRegex::isNumeric('12a'))->toBeFalse(); // with letter
        expect(ValidationRegex::isNumeric('-123'))->toBeFalse(); // negative
        expect(ValidationRegex::isNumeric(''))->toBeFalse(); // empty
    });
});

describe('hasHttpProtocol', function () {
    test('detects http protocol', function () {
        expect(ValidationRegex::hasHttpProtocol('http://example.com'))->toBeTrue();
        expect(ValidationRegex::hasHttpProtocol('https://example.com'))->toBeTrue();
    });
    
    test('rejects other protocols', function () {
        expect(ValidationRegex::hasHttpProtocol('ftp://example.com'))->toBeFalse();
        expect(ValidationRegex::hasHttpProtocol('//example.com'))->toBeFalse();
        expect(ValidationRegex::hasHttpProtocol('example.com'))->toBeFalse();
    });
    
    test('is case sensitive for protocol', function () {
        expect(ValidationRegex::hasHttpProtocol('HTTP://example.com'))->toBeFalse();
    });
});

describe('hasExtension', function () {
    test('matches files with extension', function () {
        expect(ValidationRegex::hasExtension('file.txt', 'txt'))->toBeTrue();
        expect(ValidationRegex::hasExtension('archive.zip', 'zip'))->toBeTrue();
        expect(ValidationRegex::hasExtension('data.dat', 'dat'))->toBeTrue();
    });
    
    test('rejects files without extension', function () {
        expect(ValidationRegex::hasExtension('file.txt', 'dat'))->toBeFalse();
        expect(ValidationRegex::hasExtension('file', 'txt'))->toBeFalse();
    });
    
    test('handles special characters in extension', function () {
        expect(ValidationRegex::hasExtension('file.c++', 'c++'))->toBeTrue();
    });
});

describe('isNumericFilename', function () {
    test('matches numeric filenames with extension', function () {
        expect(ValidationRegex::isNumericFilename('12345.dat', 'dat'))->toBeTrue();
        expect(ValidationRegex::isNumericFilename('0.html', 'html'))->toBeTrue();
        expect(ValidationRegex::isNumericFilename('999.txt', 'txt'))->toBeTrue();
    });
    
    test('rejects non-numeric filenames', function () {
        expect(ValidationRegex::isNumericFilename('file.dat', 'dat'))->toBeFalse();
        expect(ValidationRegex::isNumericFilename('12a45.dat', 'dat'))->toBeFalse();
        expect(ValidationRegex::isNumericFilename('test123.dat', 'dat'))->toBeFalse();
    });
    
    test('rejects wrong extension', function () {
        expect(ValidationRegex::isNumericFilename('12345.txt', 'dat'))->toBeFalse();
    });
    
    test('rejects filename without extension', function () {
        expect(ValidationRegex::isNumericFilename('12345', 'dat'))->toBeFalse();
    });
});

describe('performance', function () {
    test('pre-compiled patterns are fast', function () {
        $start = microtime(true);
        for ($i = 0; $i < 10000; $i++) {
            ValidationRegex::isValidFilename('test_file.txt');
            ValidationRegex::isValidHexColor('FF0000');
            ValidationRegex::isNumeric('12345');
        }
        $duration = microtime(true) - $start;
        
        expect($duration)->toBeLessThan(0.5); // Should complete in <500ms
    });
});
