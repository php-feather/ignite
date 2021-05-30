<?php

namespace Feather\Ignite\ErrorHandler;

use Feather\Ignite\App;

/**
 * Description of LiveHandler
 *
 * @author fcarbah
 */
class LiveHandler implements IErrorHandler
{

    protected $errorType = E_ALL;

    public function errorHandler($code, $message, $file, $line, $errorContext = array())
    {
        $msg = "ERR CODE: $code\nMESSAGE:$message\nFILE:$file Line:$line";

        $app = App::getInstance();

        $app->log($msg);

        if (preg_match('/(.*?)Controllers(.*?)\'\snot\sfound/i', $message)) {
            return $app->errorResponse('Requested Resource Not Found', 404);
        }
        $app->errorResponse('Internal Server Error' . PHP_EOL . $message, 500, $file, $line);
    }

    public function exceptionHandler(\Throwable $e)
    {
        $msg = $e->getMessage() . PHP_EOL . $e->getTraceAsString();
        $this->errorHandler($e->getCode(), $msg, $e->getFile(), $e->getLine());
    }

    public function register()
    {
        set_error_handler([$this, 'errorHandler'], $this->errorType);
        set_exception_handler([$this, 'exceptionHandler']);
        register_shutdown_function([$this, 'shutdownHandler']);
    }

    /**
     *
     * @param int $errorType
     * @return $this
     */
    public function setErrorType(int $errorType)
    {
        $this->errorType = $errorType;
        return $this;
    }

    public function shutdownHandler()
    {
        $last_error = error_get_last();

        if (!$last_error) {
            return;
        }

        if ($last_error['type'] === E_ERROR) {
            $this->errorHandler(E_ERROR, $last_error['message'], $last_error['file'], $last_error['line']);
        } else {
            $code = $last_error['type'];
            $message = $last_error['message'];
            $file = $last_error['file'];
            $line = $last_error['line'];
            App::log("ERR CODE: $code\nMESSAGE:$message\nFILE:$file || $line");
            return true;
        }
    }

    /**
     * @todo
     */
    protected function log()
    {

    }

}
