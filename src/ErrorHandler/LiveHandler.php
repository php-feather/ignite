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

    /** @var int * */
    protected $errorType = E_ALL;

    /** @var callable * */
    protected $customHandler;

    public function errorHandler($code, $message, $file, $line, $errorContext = array())
    {
        $summary = preg_replace('/(.*?)(\r*\n)(.*)/', '$1', $message);

        $msg = "ERR CODE: $code\nMESSAGE:$message\nFILE:$file Line:$line";

        $app = App::getInstance();

        $app->log($msg);

        if ($this->customHandler) {
            return call_user_func_array($this->customHandler, [$code, $message, $file, $line]);
        }

        $app->errorResponse($summary, $code, $file, $line);
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

    public function setCustomHandler(callable $errHandler)
    {
        $this->customHandler = $errHandler;
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
