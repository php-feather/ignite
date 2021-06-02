<?php

namespace Feather\Ignite;

use Feather\Init\Http\Routing\Router;
use Feather\Init\Http\Request;
use Feather\Init\Http\Response;
use Feather\Cache\ICache;
use Feather\Session\Drivers\ISessionHandler;
use Feather\Support\Container\Singleton as AppContainer;
use Feather\Ignite\ErrorHandler\IErrorHandler;
use Feather\Ignite\ErrorHandler\ErrorResolver;
use Feather\View\IView;

/**
 * Description of App
 *
 * @author fcarbah
 */
final class App
{

    /** @var string * */
    protected $controller;

    /** @var string * */
    protected $defaultController = 'Index';

    /** @var \Feather\Init\Http\Response * */
    protected $response;

    /** @var \Feather\Init\Http\Request * */
    protected $request;

    /** @var \Feather\Init\Http\Routing\Router * */
    protected $router;

    /** @var string * */
    protected $errorPage;

    /** @var \Feather\Ignite\ErrorHandler\ErrorResolver * */
    protected $errorResolver;

    /** @var string * */
    protected $errorViewEngine = 'native';

    /** @var array * */
    protected $viewEngines = [];

    /** @var \Feather\Ignite\ErrorHandler\IErrorHandler * */
    protected static $errorHandler;

    /** @var \Feather\Cache\ICache * */
    protected static $cacheHandler;

    /** @var \Feather\Session\Drivers\ISessionHandler * */
    protected static $sessionHandler;

    /** @var \Feather\Ignite\App * */
    private static $self;

    /**
     * List of objects registered in application Container
     * @var \Feather\Support\Container\Singleton
     */
    protected $container;

    /**
     * App Configurations
     * @var array
     */
    protected static $config = [];

    /** @var string * */
    protected static $rootPath;

    /** @var string * */
    protected static $configPath;

    /** @var string * */
    protected static $logPath;

    /** @var string * */
    protected static $viewsPath;

    /** @var string * */
    protected static $tempViewsPath;

    private function __construct()
    {
        $this->request = Request::getInstance();
        $this->response = Response::getInstance();
        $this->router = Router::getInstance();
        $this->container = AppContainer::getInstance();
    }

    /**
     *
     * @return \Feather\Ignite\App
     */
    public static function getInstance()
    {
        if (static::$self == NULL) {
            static::$self = new App();
        }
        return static::$self;
    }

    /**
     *
     * @return string
     */
    public function basePath()
    {
        return static::$rootPath;
    }

    /**
     * Load Require files
     * Array of absolute file paths
     * @param array $files
     */
    public function boot(array $files = [])
    {
        foreach ($files as $file) {
            require $file;
        }

        $this->loadConfigurations();
    }

    /**
     *
     * @return \Feather\Cache\ICache|null
     */
    public function cache()
    {
        return static::$cacheHandler;
    }

    /**
     *
     * @return string
     */
    public function configPath()
    {
        return static::$configPath;
    }

    /**
     * Retrieve object registered in app container by name
     * @param string $key key data to retrieve
     * @return mixed
     */
    public function container($key)
    {

        if ($this->container->hasKey($key)) {
            return $this->container->get($key);
        }

        $this->log("Key: $key - not registered in application container");

        return null;
    }

    /**
     * terminate application
     */
    public function end()
    {
        die;
    }

    /**
     * Get instance of App error Handler
     * @return \Feather\Ignite\ErrorHandler\IErrorHandler $errorhandler|null
     */
    public function errorHandler()
    {
        return static::$errorHandler;
    }

    /**
     *
     * @param string $msg
     * @param int| string $code
     * @param string $file
     * @param int $line
     * @return void
     */
    public function errorResponse($msg = '', $code = 400, $file = '', $line = null)
    {
        if (ob_get_level() > 0) {
            ob_clean();
        }

        if ($this->request->isAjax) {
            $this->response->renderJson($msg, [], $code);
        } else if ($this->errorResolver) {

            $errorPage = str_replace(static::$viewsPath, '', $this->errorResolver->resolve($code));
            $viewEngine = $this->viewEngines[strtolower($this->errorViewEngine)] ?? null;
            if ($errorPage && $viewEngine) {
                $this->response->renderView($viewEngine->render($errorPage, ['message' => $msg, 'code' => $code, 'file' => $file, 'line' => $line]), [], $code);
            } else {
                $this->response->rawOutput($msg, $code, ['Content-Type: text/html']);
            }
        } else {
            $this->response->rawOutput($msg, $code, ['Content-Type: text/html']);
        }

        return $this->response->send();
    }

    /**
     *
     * @param string $registeredName
     * @return \Feather\View\IView| null
     */
    public function getViewEngine($registeredName)
    {
        $name = $registeredName;
        if (isset($this->viewEngines[$name])) {
            return $this->viewEngines[$name];
        }
        return null;
    }

    /**
     * Configure router
     * @param array $routerConfig
     */
    public function initRouter($routerConfig)
    {

        $ctrlConfig = $routerConfig['controller'];

        $this->router->setAutoRouting($routerConfig['autoRouting']);

        $folderPath = $routerConfig['folderRouting']['path'] ?? 'public/';

        $this->router->setFolderRouting($routerConfig['folderRouting']['enabled'] ?? true,
                static::$rootPath . $folderPath,
                $routerConfig['folderRouting']['defaultFile'] ?? 'index.php');

        $this->router->setRoutingFallback($routerConfig['fallbackRouting']);

        $this->router->setDefaultController($ctrlConfig['default']);

        $this->router->setControllerNamespace($ctrlConfig['namespace']);

        $this->router->setControllerPath(static::$rootPath . $ctrlConfig['baseDirectory']);

        if ($routerConfig['cache']['enabled']) {

            $cache = $routerConfig['cache']['driver'];

            if ($cache) {

                if ($cache instanceof ICache) {
                    $this->router->setCacheHandler($cache);
                } else {
                    $this->router->setCacheHandler(static::getCache($cache));
                }
            } elseif (static::$cacheHandler) {
                $this->router->setCacheHandler(static::$cacheHandler);
            }
        }
        //load registered routes
        $this->load(static::$rootPath . $routerConfig['registeredRoutes']);
    }

    /**
     * Load file or list of files
     * @param string|array $file
     */
    public function load($file)
    {

        if (is_array($file)) {

            foreach ($file as $f) {
                require_once $f;
            }
        } else {
            require_once $file;
        }
    }

    /**
     * Register a object in the app container
     * @param string $key
     * @param mixed $object
     */
    public function register($key, $object)
    {
        $this->container->add($key, $object);
    }

    /**
     *
     * @param \Feather\Ignite\ErrorHandler\IErrorHandler $errorhandler
     */
    public function registerErrorHandler(IErrorHandler $errorhandler)
    {
        static::$errorHandler = $errorhandler;
        static::$errorHandler->register();
    }

    /**
     *
     * @param string $name
     * @param IView $engine
     */
    public function registerViewEngine($name, IView $engine)
    {
        $this->viewEngines[$name] = $engine;
    }

    /**
     * Sets page to display errors on and render engine to use when rendering page
     * @param string $defaultview
     * @param string $viewEngine Registered name of view Engine
     */

    /**
     *
     * @param string $rootDir
     * @param string $defaultview
     * @param string $viewEngine
     */
    public function setErrorPage($rootDir, $defaultview, $viewEngine)
    {
        $this->errorResolver = new ErrorResolver();
        $this->errorResolver->setRootPath(static::$rootPath . $rootDir, $defaultview);
        $this->errorViewEngine = $viewEngine;
    }

    /**
     * Enable or Disable routing fallback requests handling
     * @param bool $enable
     */
    public function setRouterFallback($enable)
    {
        $this->router->setRoutingFallback($enable);
    }

    /**
     *
     * @return string
     */
    public function viewsPath()
    {
        return static::$viewsPath;
    }

    /**
     *
     * @param string $driver
     * @return \Feather\Cache\ICache
     */
    public static function getCache($driver)
    {

        $cacheConfig = static::$config['cache'];

        switch ($driver) {
            case 'file':
            default :
                $conf = $cacheConfig['drivers']['file'];
                $driver = $conf['driver'];
                return $driver::getInstance($conf['path']);

            case 'database':
                $conf = $cacheConfig['drivers']['database'];
                $driver = $conf['driver'];
                $dbConfig = $conf['connections'][$conf['active']];
                return $driver::getInstance($dbConfig);

            case 'redis':
                $redisConfig = $cacheConfig['drivers']['redis'];
                $driver = $redisConfig['driver'];
                return $driver::getInstance($redisConfig['host'], $redisConfig['port'], $redisConfig['scheme'], $redisConfig['connOptions']);
        }
    }

    /**
     * Load configuration from config file path
     * @param string $configPath
     * @return mixed
     */
    public static function getConfig($configPath)
    {
        try {
            $fullPath = stripos($configPath, '/') === 0 ? $configPath : '/' . $configPath;
            $config = include static::$configPath . $fullPath;
            return $config;
        } catch (\Exception $e) {
            static::log($e->getMessage());
            return null;
        }
    }

    /**
     *
     * @param string|array $path Directory path(s) that contain the .env file
     * @param string|array $envFilename Env files to load. ENV files that are not name .env
     * @param array|string $requiredVariables ENV variables that are required
     */
    public static function loadEnv($path, $envFilename = null, $requiredVariables = array())
    {
        $dotenv = \Dotenv\Dotenv::createUnsafeImmutable($path, $envFilename);
        $dotenv->load();
        $dotenv->required($requiredVariables);
    }

    /**
     * Logs message to file
     * @param string $msg
     */
    public static function log($msg)
    {
        $filePath = static::$logPath . '/app_log';
        error_log(date('Y-m-d H:i:s') . ' - ' . $msg, 3, $filePath);
    }

    public function run()
    {
        return $this->router->processRequest($this->request->uri, $this->request->method);
    }

    /**
     *
     * @param \Feather\Cache\ICache $cacheHandler
     */
    public static function registerCacheHandler(Cache $cacheHandler)
    {
        static::$cacheHandler = $cacheHandler;
    }

    /**
     *
     * @param \Feather\Session\Drivers\ISessionHandler $sessionHandler
     */
    public static function registerSessionHandler(ISessionHandler $sessionHandler)
    {
        static::$sessionHandler = $sessionHandler;
        static::$sessionHandler->activate();
    }

    /**
     * Sets Application caching
     * @return void
     */
    public function setCaching()
    {

        if (static::$cacheHandler != null) {
            return;
        }

        $config = static::$config['cache'];

        static::$cacheHandler = static::getCacheDriver($config);
    }

    /**
     * Sets absolute paths for Application
     * @param string $root Absolute path of application directory
     * @param string $config Absolute path to configs directory
     * @param string $log Absolute path to log directory
     * @param string $views Absolute path to views directory
     * @param string $tempViews Absolute path to temporary | cache views path
     */
    public static function setBasePaths($root, $config, $log, $views, $tempViews = '')
    {
        static::$rootPath = preg_replace('/\/{2,}/', '/', $root);
        static::$configPath = preg_replace('/\/{2,}/', '/', $config);
        static::$logPath = preg_replace('/\/{2,}/', '/', $log);
        static::$viewsPath = preg_replace('/\/{2,}/', '/', $views);
        static::$tempViewsPath = preg_replace('/\/{2,}/', '/', $tempViews);
    }

    /**
     * Starts session
     * @returns void
     */
    public static function startSession()
    {

        $config = static::$config['session'];
        $defaultOptions = [
            'cookie_lifetime' => $config['lifetime'],
            'gc_max_lifetime' => $config['lifetime'],
            'cookie_path' => '/',
            'name' => 'FA_SESSION',
        ];
        $configOptions = $config['options'];

        $options = array_merge($defaultOptions, $configOptions);

        static::initSession($config);
        static::$sessionHandler->start($options);
    }

    /**
     *
     * @param array $cacheConfig
     * @return \Feather\Cache\ICache
     */
    protected static function getCacheDriver($cacheConfig)
    {

        switch ($cacheConfig['driver']) {
            case 'file':
            default :
                $conf = $cacheConfig['drivers']['file'];
                $driver = $conf['driver'];
                return $driver::getInstance(static::$rootPath . $conf['path']);

            case 'database':
                $conf = $cacheConfig['drivers']['database'];
                $driver = $conf['driver'];
                $dbConfig = $conf['connections'][$conf['active']];
                return $driver::getInstance($dbConfig);

            case 'redis':
                $redisConfig = $cacheConfig['drivers']['redis'];
                $driver = $redisConfig['driver'];
                return $driver::getInstance($redisConfig['host'], $redisConfig['port'], $redisConfig['scheme'], $redisConfig['connOptions']);
        }
    }

    /**
     *
     * @param array $sessionConfig
     * @return \Feather\Session\Drivers\ISessionHandler|null
     */
    protected static function getSessionDriver($sessionConfig)
    {

        switch ($sessionConfig['driver']) {
            case 'file':
            default :
                $conf = $sessionConfig['drivers']['file'];
                $driver = $conf['driver'];
                return new $driver(static::$rootPath . $conf['path']);

            case 'database':
                $conf = $sessionConfig['drivers']['database'];
                $driver = $conf['driver'];
                $dbConfig = $conf['connections'][$conf['active']];
                return new $driver($dbConfig);

            case 'redis':
                $conf = $sessionConfig['drivers']['redis'];
                $driver = $conf['driver'];
                return new $driver($conf['host'], $conf['port'], $conf['scheme']);
        }
    }

    /**
     *
     * @param array $config
     * @throws \RuntimeExceptiion
     * @return void
     */
    protected static function initSession($config)
    {

        if (static::$sessionHandler != null) {
            return;
        }

        static::$sessionHandler = static::getSessionDriver($config);

        if (static::$sessionHandler != null) {
            static::$sessionHandler->activate();
        } else {
            throw new \RuntimeException('Session Driver not configured');
        }
    }

    /**
     * Load application configuration files from config directory
     * Only top level configuration files are loaded
     */
    protected function loadConfigurations()
    {

        $files = feather_dir_files(static::$configPath);

        foreach ($files as $file) {

            if (is_file(static::$configPath . "/$file") && stripos($file, '.php') === strlen($file) - 4) {
                $filename = substr($file, 0, strripos($file, '.php'));
                static::$config[strtolower($filename)] = include static::$configPath . '/' . $file;
            }
        }
    }

}
