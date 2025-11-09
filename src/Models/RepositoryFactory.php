<?php

namespace App\Models;

use App\Config;
use App\Models\Repositories\AccessCounterRepositoryInterface;
use App\Models\Repositories\AccessCounterCsvRepository;
use App\Models\Repositories\AccessCounterSqliteRepository;
use App\Models\Repositories\ParticipantCounterRepositoryInterface;
use App\Models\Repositories\ParticipantCounterCsvRepository;
use App\Models\Repositories\ParticipantCounterSqliteRepository;

class RepositoryFactory
{
    /**
     * Create Access Counter Repository based on configuration
     * 
     * @return AccessCounterRepositoryInterface
     */
    public static function createAccessCounterRepository(): AccessCounterRepositoryInterface
    {
        $config = Config::getInstance();
        $backend = $config->get('STORAGE_BACKEND') ?? 'csv';
        
        if ($backend === 'sqlite') {
            return new AccessCounterSqliteRepository(
                $config->get('SQLITE_DATABASE')
            );
        }
        
        // Default: CSV
        return new AccessCounterCsvRepository(
            $config->get('COUNTFILE'),
            $config->get('COUNTLEVEL')
        );
    }
    
    /**
     * Create Participant Counter Repository based on configuration
     * 
     * @return ParticipantCounterRepositoryInterface
     */
    public static function createParticipantCounterRepository(): ParticipantCounterRepositoryInterface
    {
        $config = Config::getInstance();
        $backend = $config->get('STORAGE_BACKEND') ?? 'csv';
        
        if ($backend === 'sqlite') {
            return new ParticipantCounterSqliteRepository(
                $config->get('SQLITE_DATABASE')
            );
        }
        
        // Default: CSV
        return new ParticipantCounterCsvRepository(
            $config->get('CNTFILENAME')
        );
    }
}
