<?php

namespace App\Models;

use App\Config;
use App\Models\Repositories\AccessCounterCsvRepository;
use App\Models\Repositories\AccessCounterRepositoryInterface;
use App\Models\Repositories\AccessCounterSqliteRepository;
use App\Models\Repositories\BbsLogFileRepository;
use App\Models\Repositories\BbsLogRepositoryInterface;
use App\Models\Repositories\OldLogFileRepository;
use App\Models\Repositories\OldLogRepositoryInterface;
use App\Models\Repositories\ParticipantCounterCsvRepository;
use App\Models\Repositories\ParticipantCounterRepositoryInterface;
use App\Models\Repositories\ParticipantCounterSqliteRepository;

class RepositoryFactory
{
    public static function createAccessCounterRepository(): AccessCounterRepositoryInterface
    {
        $config = Config::getInstance();
        $backend = $config->get('STORAGE_BACKEND') ?? 'csv';

        if ($backend === 'sqlite') {
            return new AccessCounterSqliteRepository(
                $config->get('SQLITE_DATABASE')
            );
        }

        return new AccessCounterCsvRepository(
            $config->get('COUNTFILE'),
            $config->get('COUNTLEVEL')
        );
    }

    public static function createParticipantCounterRepository(): ParticipantCounterRepositoryInterface
    {
        $config = Config::getInstance();
        $backend = $config->get('STORAGE_BACKEND') ?? 'csv';

        if ($backend === 'sqlite') {
            return new ParticipantCounterSqliteRepository(
                $config->get('SQLITE_DATABASE')
            );
        }

        return new ParticipantCounterCsvRepository(
            $config->get('CNTFILENAME')
        );
    }

    public static function createBbsLogRepository(): BbsLogRepositoryInterface
    {
        $config = Config::getInstance();
        return new BbsLogFileRepository($config->get('LOGFILENAME'));
    }

    public static function createOldLogRepository(): OldLogRepositoryInterface
    {
        $config = Config::getInstance();
        $logDir = $config->get('OLDLOGFILEDIR');
        $extension = $config->get('OLDLOGFMT') ? 'dat' : 'html';
        $monthlyMode = (bool) $config->get('OLDLOGSAVESW', 1);
        $maxSize = $config->get('MAXOLDLOGSIZE', 1000000);

        return new OldLogFileRepository($logDir, $extension, $monthlyMode, $maxSize);
    }
}
