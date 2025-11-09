<?php

namespace App\Models\Repositories;

interface OldLogRepositoryInterface
{
    /**
     * Append a message to the current archive log file
     */
    public function append(string $message): void;

    /**
     * Get the current archive log filename based on date
     */
    public function getCurrentFilename(): string;

    /**
     * Get the size of the current archive log file
     */
    public function getSize(): int;

    /**
     * Set the current archive log file to read-only
     */
    public function setReadOnly(): void;

    /**
     * Check if a new date file was created
     */
    public function isNewFile(): bool;

    /**
     * Get all lines from a specific archive file
     */
    public function getAll(string $filename): array;
}
