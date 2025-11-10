<?php

namespace App\Models\Repositories;

interface ParticipantCounterRepositoryInterface
{
    /**
     * Record a visit and return active participant count
     *
     * @param string $userKey User identifier (IP-based hash)
     * @param int $timestamp Current timestamp
     * @param int $timeoutSeconds Timeout in seconds
     * @return int Number of active participants
     */
    public function recordVisit(string $userKey, int $timestamp, int $timeoutSeconds): int;

    /**
     * Get active participant count without recording
     *
     * @param int $currentTime Current timestamp
     * @param int $timeoutSeconds Timeout in seconds
     * @return int Number of active participants
     */
    public function getActiveCount(int $currentTime, int $timeoutSeconds): int;
}
