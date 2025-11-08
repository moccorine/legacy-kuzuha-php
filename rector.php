<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/bbs.php',
        __DIR__ . '/conf.php',
        __DIR__ . '/sub',
    ])
    ->withSkip([
        __DIR__ . '/lib',
        __DIR__ . '/vendor',
    ])
    ->withPhpSets(php84: true);
