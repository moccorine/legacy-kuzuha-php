<?php

namespace App\Models\Repositories;

interface AccessCounterRepositoryInterface
{
    /**
     * Increment counter and return new value
     * 
     * @return int New counter value
     */
    public function increment(): int;
    
    /**
     * Get current counter value without incrementing
     * 
     * @return int Current counter value
     */
    public function getCurrent(): int;
    
    /**
     * Get counter level (number of files for CSV, false for SQLite)
     * 
     * @return int|false Counter level or false if not applicable
     */
    public function getCountLevel(): int|false;
}
