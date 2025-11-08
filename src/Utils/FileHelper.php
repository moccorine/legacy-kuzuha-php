<?php

namespace App\Utils;

class FileHelper
{
    /**
     * Read a line from file handle
     *
     * @param resource $fh File handle
     * @return string|false Line content or false on EOF
     */
    public static function getLine($fh)
    {
        $line = '';
        while (!feof($fh)) {
            $c = fgetc($fh);
            if ($c === false) {
                break;
            }
            $line .= $c;
            if ($c == "\n") {
                break;
            }
        }
        if ($line == '') {
            return false;
        }
        return $line;
    }

    /**
     * Write debug message to file
     *
     * @param string $debugstr Debug message
     * @param bool $printdate Whether to print date
     * @param string $debugfile Debug file path
     */
    public static function debugWrite(string $debugstr, bool $printdate = true, string $debugfile = 'debug.txt'): void
    {
        $fhdebug = @fopen($debugfile, 'ab');
        if (!$fhdebug) {
            return;
        }
        flock($fhdebug, 2);
        if ($printdate) {
            fwrite($fhdebug, date("Y/m/d H:i:s\t (T)", CURRENT_TIME));
        }
        fwrite($fhdebug, "$debugstr\n");
        flock($fhdebug, 3);
        fclose($fhdebug);
    }
}
