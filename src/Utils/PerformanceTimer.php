<?php

namespace App\Utils;

/**
 * Performance timer utility for measuring execution time
 */
class PerformanceTimer
{
    private static ?float $startTime = null;

    /**
     * Start the timer
     */
    public static function start(): void
    {
        self::$startTime = microtime(true);
    }

    /**
     * Get elapsed time since start
     *
     * @return float|null Elapsed time in seconds, or null if not started
     */
    public static function elapsed(): ?float
    {
        if (self::$startTime === null) {
            return null;
        }

        return microtime(true) - self::$startTime;
    }

    /**
     * Get formatted elapsed time
     *
     * @param int $precision Number of decimal places (default: 6)
     * @return string|null Formatted elapsed time, or null if not started
     */
    public static function elapsedFormatted(int $precision = 6): ?string
    {
        $elapsed = self::elapsed();

        if ($elapsed === null) {
            return null;
        }

        return sprintf('%0.' . $precision . 'f', $elapsed);
    }

    /**
     * Reset the timer
     */
    public static function reset(): void
    {
        self::$startTime = null;
    }

    /**
     * Check if timer is running
     *
     * @return bool
     */
    public static function isRunning(): bool
    {
        return self::$startTime !== null;
    }
}
