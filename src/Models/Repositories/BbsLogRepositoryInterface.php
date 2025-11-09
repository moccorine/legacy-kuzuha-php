<?php

namespace App\Models\Repositories;

interface BbsLogRepositoryInterface
{
    /**
     * Append a message to the log
     * 
     * @param array $message Message data
     * @return void
     */
    public function append(array $message): void;
    
    /**
     * Get all messages
     * 
     * @return array Array of message lines
     */
    public function getAll(): array;
    
    /**
     * Get messages in range
     * 
     * @param int $start Start index (0-based)
     * @param int $limit Number of messages to retrieve
     * @return array Array of message lines
     */
    public function getRange(int $start, int $limit): array;
    
    /**
     * Find message by post ID
     * 
     * @param int $postId Post ID
     * @return string|null Message line or null if not found
     */
    public function findById(int $postId): ?string;
    
    /**
     * Delete message by post ID
     * 
     * @param int $postId Post ID
     * @return bool True if deleted, false if not found
     */
    public function deleteById(int $postId): bool;
    
    /**
     * Count total messages
     * 
     * @return int Number of messages
     */
    public function count(): int;
    
    /**
     * Get next post ID
     * 
     * @return int Next post ID
     */
    public function getNextPostId(): int;
    
    /**
     * Prepend message to log (add at beginning)
     * 
     * @param array $message Message data
     * @param int $maxMessages Maximum number of messages to keep
     * @return void
     */
    public function prepend(array $message, int $maxMessages): void;
    
    /**
     * Lock the log file for exclusive access
     * 
     * @return void
     */
    public function lock(): void;
    
    /**
     * Unlock the log file
     * 
     * @return void
     */
    public function unlock(): void;
}
