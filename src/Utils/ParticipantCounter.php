<?php

namespace App\Utils;

class ParticipantCounter
{
    /**
     * Count and update participant count based on unique IP addresses
     *
     * @param string $cntFilename Path to the count file
     * @param int $cntLimit Time limit in seconds for counting participants
     * @param int $currentTime Current timestamp
     * @return int|string Participant count or error message
     */
    public static function count(string $cntFilename, int $cntLimit, int $currentTime)
    {
        if (!$cntFilename) {
            return 0;
        }

        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ukey = hexdec(substr(md5($remoteAddr), 0, 8));
        $newCntData = [];
        $mbrCount = 0;
        $userAdded = false;

        if (is_writable($cntFilename)) {
            $cntData = file($cntFilename);

            foreach ($cntData as $cntValue) {
                if (strrpos($cntValue, ',') !== false) {
                    [$cuser, $ctime] = explode(',', trim($cntValue));

                    if ($cuser == $ukey) {
                        // Update current user's timestamp
                        $newCntData[] = "$ukey,$currentTime\n";
                        $userAdded = true;
                        $mbrCount++;
                    } elseif (($ctime + $cntLimit) >= $currentTime) {
                        // Keep active users
                        $newCntData[] = "$cuser,$ctime\n";
                        $mbrCount++;
                    }
                    // Expired entries are automatically dropped
                }
            }
        }

        if (!$userAdded) {
            $newCntData[] = "$ukey,$currentTime\n";
            $mbrCount++;
        }

        // Write updated data
        $fh = @fopen($cntFilename, 'w');
        if (!$fh) {
            return 'Participant file output error';
        }

        flock($fh, LOCK_EX);
        fwrite($fh, implode('', $newCntData));
        flock($fh, LOCK_UN);
        fclose($fh);

        return $mbrCount;
    }
}
