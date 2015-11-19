<?php
namespace WoodyAttachments\Model\Validation;

use Cake\Validation\Validator;
use Cake\Validation\Validation;

/**
 * Class FileuploadValidator
 * @package WoodyAttachments\Model\Validation
 */
class FileuploadValidator extends Validator
{

    public function __construct()
    {
        parent::__construct();
        // Add validation rules here.
    }

    /**
     * Check for file types. Only difference is that it is possible to upload multiple files
     * @param $files
     * @param $mimetypes
     * @return bool
     */
    public static function mimeType($files, $mimetypes)
    {
        $valid = false;
        if(isset($files[0]['tmp_name'])) {
            foreach($files as $file) {
                if(Validation::mimeType($file, $mimetypes) === false) {
                    return false;
                }
                $valid = true;
            }
        } else {
            $valid = Validation::mimeType($files, $mimetypes);
        }

        return $valid;
    }


    public static function uploadErrorCheck($files)
    {
        $valid = false;
        if(isset($files[0]['tmp_name'])) {
            foreach($files as $file) {
                if(Validation::uploadError($file) === false) {
                    return false;
                }
                $valid = true;
            }
        } else {
            $valid = Validation::uploadError($files);
        }

        return $valid;
    }


    /**
     * Check for file size. Only difference is that it is possible to upload multiple files
     * @param $files
     * @param null $operator
     * @param $maxSize
     * @return bool
     */
    public static function fileSize($files, $operator = null, $maxSize)
    {
        $valid = false;

        if(isset($files[0]['tmp_name'])) {
            foreach($files as $file) {
                if(Validation::fileSize($file, $operator, $maxSize) === false) {
                    return false;
                }
                $valid = true;
            }
        } else {
            $valid = Validation::fileSize($files, $operator, $maxSize);
        }

        return $valid;
    }
}