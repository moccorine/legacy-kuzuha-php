<?php

namespace App\Models\Repositories;

class AccessCounterCsvRepository implements AccessCounterRepositoryInterface
{
    private string $filePrefix;
    private int $fileCount;

    public function __construct(string $filePrefix, int $fileCount)
    {
        $this->filePrefix = $filePrefix;
        $this->fileCount = $fileCount;
    }

    public function increment(): int
    {
        $count = [];
        $filenumber = [];
        
        // Read all counter files
        for ($i = 0; $i < $this->fileCount; $i++) {
            $filename = "{$this->filePrefix}{$i}.dat";
            if (is_writable($filename) && $fh = @fopen($filename, 'r')) {
                $count[$i] = (int) fgets($fh, 10);
                fclose($fh);
            } else {
                $count[$i] = 0;
            }
            $filenumber[$count[$i]] = $i;
        }
        
        // Find min and max
        sort($count, SORT_NUMERIC);
        $mincount = $count[0];
        $maxcount = $count[$this->fileCount - 1] + 1;
        
        // Write max+1 to file with min value
        $filename = "{$this->filePrefix}{$filenumber[$mincount]}.dat";
        if ($fh = @fopen($filename, 'w')) {
            fputs($fh, $maxcount);
            fclose($fh);
            return $maxcount;
        }
        
        throw new \RuntimeException('Counter file write error');
    }

    public function getCurrent(): int
    {
        $maxCount = 0;
        
        for ($i = 0; $i < $this->fileCount; $i++) {
            $filename = "{$this->filePrefix}{$i}.dat";
            if (file_exists($filename)) {
                $count = (int) file_get_contents($filename);
                if ($count > $maxCount) {
                    $maxCount = $count;
                }
            }
        }
        
        return $maxCount;
    }
    
    public function getCountLevel(): int|false
    {
        return $this->fileCount;
    }
}
