<?php

namespace Kuzuha;

use App\Config;
use App\Utils\HtmlHelper;

/*

KuzuhaScriptPHP ver0.0.7alpha (13:04 2003/02/18)
BBS with image upload function module

* Todo

* Memo


*/


/*
 * Module-specific settings
 *
 * They will be added to/overwritten by $CONF.
 */
$GLOBALS['CONF_IMAGEBBS'] = [

    # Image upload directory (please set it to be writable)
    'UPLOADDIR' => './upload/',

    # File containing latest image upload file number (please set it to be writable)
    'UPLOADIDFILE' => './upload/id.txt',

    # If this string is present in the post content, the uploaded image will be inserted into that position
    'IMAGETEXT' => '%image',

    # Total space dedicated to uploaded images (KB)
    'MAX_UPLOADSPACE' => 10000,

    # Maximum width for uploaded images
    'MAX_IMAGEWIDTH' => 1280,

    # Maximum height for uploaded images
    'MAX_IMAGEHEIGHT' => 1600,

    # Maximum file size for uploaded images (KB)
    'MAX_IMAGESIZE' => 200,

    # Image scale factor when displayed on the bulletin board (％)
    'IMAGE_PREVIEW_RESIZE' => 100,

];




// Include file path


/* Launch */
{
    if (!ini_get('file_uploads')) {
        print 'Error: The file upload feature is not allowed.';
        exit();
    }
    if (!function_exists('GetImageSize')) {
        print 'Error: The image processing feature is not supported.';
        exit();
    }
}




/**
 * BBS with image upload function module
 *
 *
 *
 * @package strangeworld.cnscript
 * @access  public
 */
class Imagebbs extends Bbs
{
    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $config = Config::getInstance();
        foreach ($GLOBALS['CONF_IMAGEBBS'] as $key => $value) {
            $config->set($key, $value);
        }
        parent::__construct();
    }





    /**
     * Apply user preferences with image mode enabled
     */
    #[\Override]
    public function applyUserPreferences(string $colorString = ''): string
    {
        $this->config['SHOWIMG'] = 1;
        return parent::applyUserPreferences($colorString);
    }






    /**
     * Get message from form input
     *
     * @access  public
     * @return  Array  Message array
     */
    #[\Override]
    public function buildPostMessage()
    {

        $message = parent::buildPostMessage();

        if (!is_array($message)) {
            return $message;
        }

        # Confirm file upload
        if ($_FILES['file']['name']) {

            if ($_FILES['file']['error'] == 2
            or (file_exists($_FILES['file']['tmp_name'])
            and filesize($_FILES['file']['tmp_name']) > ($this->config['MAX_IMAGESIZE'] * 1024))) {
                $this->prterror('The file size is over ' .$this->config['MAX_IMAGESIZE'] .'KB.');
            }

            if ($_FILES['file']['error'] > 0
            or !is_uploaded_file($_FILES['file']['tmp_name'])) {
                $this->prterror('File upload process failed. Code: ' . $_FILES['file']['error']);
            }

            # Locking the image upload process
            $fh = @fopen($this->config['UPLOADIDFILE'], 'rb+');
            if (!$fh) {
                $this->prterror('Failed to load the uploaded image file.');
            }
            flock($fh, 2);

            # Obtain file ID
            $fileid = trim(fgets($fh, 10));
            if (!$fileid) {
                $fileid = 0;
            }

            # File type check
            $imageinfo = GetImageSize($_FILES['file']['tmp_name']);
            if ($imageinfo[0] > $this->config['MAX_IMAGEWIDTH'] or $imageinfo[1] > $this->config['MAX_IMAGEHEIGHT']) {
                unlink($_FILES['file']['tmp_name']);
                $this->prterror('The width of the image exceeds the limit.');
            }

            # GIF
            if ($imageinfo[2] == 1) {
                $filetype = 'GIF';
                $fileext = '.gif';
            }
            # JPG
            elseif ($imageinfo[2] == 2) {
                $filetype = 'JPG';
                $fileext = '.jpg';
            }
            # PNG
            elseif ($imageinfo[2] == 3) {
                $filetype = 'PNG';
                $fileext = '.png';
            } else {
                unlink($_FILES['file']['tmp_name']);
                $this->prterror('The file format is incorrect.');
            }

            $fileid++;
            $filename = $this->config['UPLOADDIR'] . str_pad($fileid, 5, '0', STR_PAD_LEFT) . '_' . date('YmdHis', CURRENT_TIME) . $fileext;

            copy($_FILES['file']['tmp_name'], $filename);
            unlink($_FILES['file']['tmp_name']);

            $message['FILEID'] = $fileid;
            $message['FILENAME'] = $filename;
            $message['FILEMSG'] = '画像'.str_pad($fileid, 5, '0', STR_PAD_LEFT)." $filetype {$imageinfo[0]}*{$imageinfo[1]} ".floor(filesize($filename) / 1024).'KB';
            $message['FILETAG'] = "<a href=\"{$filename}\" target=\"link\">"
            . "<img src=\"{$filename}\" width=\"{$imageinfo[0]}\" height=\"{$imageinfo[1]}\" border=\"0\" alt=\"{$message['FILEMSG']}\" /></a>";

            # Embedding tags in messages.
            if (str_contains((string) $message['MSG'], (string) $this->config['IMAGETEXT'])) {
                $message['MSG'] = preg_replace("/\Q{$this->config['IMAGETEXT']}\E/", $message['FILETAG'], (string) $message['MSG'], 1);
                $message['MSG'] = preg_replace("/\Q{$this->config['IMAGETEXT']}\E/", '', $message['MSG']);
            } else {
                if (HtmlHelper::hasReferenceLinkAtEnd((string) $message['MSG'])) {
                    $message['MSG'] = HtmlHelper::insertBeforeReferenceLink((string) $message['MSG'], $message['FILETAG']);
                } else {
                    $message['MSG'] .= "\r\r" . $message['FILETAG'];
                }
            }

            fseek($fh, 0, 0);
            ftruncate($fh, 0);
            fwrite($fh, $fileid);
            flock($fh, 3);
            fclose($fh);
        }

        return $message;

    }





    /**
     * Message registration process
     *
     * @access  public
     * @return  Integer  Error code
     */
    #[\Override]
    public function putmessage($message)
    {

        $posterr = parent::saveMessage($message);

        if ($posterr) {
            return $posterr;
        } else {

            $dirspace = 0;
            $maxspace = $this->config['MAX_UPLOADSPACE'] * 1024;

            $files = [];
            foreach (glob($this->config['UPLOADDIR'] . '*.{gif,jpg,png}', GLOB_BRACE) as $filepath) {
                $files[] = $filepath;
                $dirspace += filesize($filepath);
            }

            # Delete old images
            if ($dirspace > $maxspace) {
                sort($files);
                foreach ($files as $filepath) {
                    $dirspace -= filesize($filepath);
                    unlink($filepath);
                    if ($dirspace <= $maxspace) {
                        break;
                    }
                }
            }
        }

    }

}
