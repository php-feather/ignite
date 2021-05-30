<?php

namespace Feather\Ignite\ErrorHandler;

/**
 * Description of ErrorResolver
 *
 * @author fcarbah
 */
class ErrorResolver
{

    protected $basepath;
    protected $defaultFile;

    public function setRootPath($rootDir, $defaultFile)
    {

        if (feather_is_dir($rootDir)) {
            $this->basepath = preg_match('/(\/)$/', $rootDir) ? $rootDir : $rootDir . '/';
            $defaultFile = stripos($defaultFile, $this->basepath) === 0 ? $defaultFile : $this->basepath . '/' . $defaultFile;

            if (feather_file_exists($defaultFile)) {
                $this->defaultFile = $defaultFile;
            }
        }
    }

    public function resolve($errorCode)
    {

        $file = $this->basepath . $errorCode . '.php';

        if (feather_file_exists($file)) {
            return $file;
        }

        return $this->defaultFile;
    }

}
