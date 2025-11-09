<?php

namespace App\Models\Repositories;

class ParticipantCounterCsvRepository implements ParticipantCounterRepositoryInterface
{
    private string $filename;

    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    public function recordVisit(string $userKey, int $timestamp, int $timeoutSeconds): int
    {
        $newCntData = [];
        $mbrCount = 0;
        $userAdded = false;

        if (is_writable($this->filename)) {
            $cntData = file($this->filename);
            
            foreach ($cntData as $cntValue) {
                if (strrpos($cntValue, ',') !== false) {
                    [$cuser, $ctime] = explode(',', trim($cntValue));
                    
                    if ($cuser == $userKey) {
                        // Update current user's timestamp
                        $newCntData[] = "$userKey,$timestamp\n";
                        $userAdded = true;
                        $mbrCount++;
                    } elseif (($ctime + $timeoutSeconds) >= $timestamp) {
                        // Keep active users
                        $newCntData[] = "$cuser,$ctime\n";
                        $mbrCount++;
                    }
                    // Expired entries are automatically dropped
                }
            }
        }

        if (!$userAdded) {
            $newCntData[] = "$userKey,$timestamp\n";
            $mbrCount++;
        }

        // Write updated data
        $fh = @fopen($this->filename, 'w');
        if (!$fh) {
            throw new \RuntimeException('Participant file output error');
        }

        flock($fh, LOCK_EX);
        fwrite($fh, implode('', $newCntData));
        flock($fh, LOCK_UN);
        fclose($fh);

        return $mbrCount;
    }

    public function getActiveCount(int $currentTime, int $timeoutSeconds): int
    {
        if (!file_exists($this->filename)) {
            return 0;
        }

        $cntData = file($this->filename);
        $mbrCount = 0;

        foreach ($cntData as $cntValue) {
            if (strrpos($cntValue, ',') !== false) {
                [$cuser, $ctime] = explode(',', trim($cntValue));
                
                if (($ctime + $timeoutSeconds) >= $currentTime) {
                    $mbrCount++;
                }
            }
        }

        return $mbrCount;
    }
}
