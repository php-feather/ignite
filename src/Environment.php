<?php

namespace Feather\Ignite;

use Feather\Ignite\ErrorHandler\WhoopsHandler;
use Feather\Ignite\ErrorHandler\LiveHandler;

/**
 * Description of Environment
 *
 * @author fcarbah
 */
final class Environment
{

    /** @var string * */
    protected $env;

    /** @var bool * */
    protected $debug;

    /** @var \Feather\Ignite\Environment * */
    protected static $self;

    const DEVELOPMENT = 'dev';
    const PRODUCTION = 'prod';
    const TEST = 'test';

    private function __construct($env, bool $debug)
    {
        $this->debug = $debug;
        $this->setEnvironment($env);
    }

    /**
     *
     * @param string $env
     * @param bool $debug
     * @return \Feather\Ignite\Environment
     */
    public static function getInstance($env, bool $debug)
    {
        if (!static::$self) {
            static::$self = new Environment($env, $debug);
        }

        return static::$self;
    }

    /**
     *
     * @return bool
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     *
     * @return string;
     */
    public function getEnvironment()
    {
        switch ($this->env) {
            case static::DEVELOPMENT:
                return 'development';
            case static::TEST:
                return 'test';
            case static::PRODUCTION:
                return 'production';
        }
    }

    /**
     *
     * @return \Feather\Ignite\ErrorHandler\IErrorHandler
     */
    public function getErrorHandler()
    {
        if ($this->env == static::PRODUCTION || !$this->debug) {
            return new LiveHandler();
        }

        return new WhoopsHandler();
    }

    /**
     *
     * @param string $env
     */
    protected function setEnvironment($env)
    {
        $e = strtolower($env);

        if ($e != static::DEVELOPMENT && $e != static::PRODUCTION && $env != static::TEST) {
            $this->env = static::DEVELOPMENT;
        }

        $this->env = $e;
    }

}
