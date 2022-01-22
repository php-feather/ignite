<?php

namespace Feather\Ignite;

use Feather\Init\Http\Routing\Router;
use Feather\Init\Http\Request;
use Feather\Init\Http\Response;
use Feather\Cache\ICache;
use Feather\Session\Drivers\ISessionHandler;
use Feather\Ignite\ErrorHandler\IErrorHandler;
use Feather\Ignite\ErrorHandler\ErrorResolver;
use Feather\View\IView;
use Feather\Support\Contracts\IApp;
use Feather\Ignite\Provider;

/**
 * Description of App
 *
 * @author fcarbah
 */
final class App implements IApp
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

    /** @var \Feather\Ignite\ErrorHandler\IErrorHandler * */
    protected $errorHandler;

    /** @var \Feather\Cache\ICache * */
    protected $cacheHandler;

    /** @var \Feather\Session\Drivers\ISessionHandler * */
    protected $sessionHandler;

    /** @var \Feather\Ignite\App * */
    private static $self;

    /**
     * List of objects registered in application Container
     * @var \Feather\Ignite\AppContainer
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
        $this->request   = Request::getInstance();
        $this->response  = Response::getInstance();
        $this->router    = Router::getInstance();
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

        $this->registerProviders();
    }

    /**
     *
     * @return \Feather\Cache\ICache|null
     */
    public function cache()
    {
        return $this->cacheHandler;
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
     * @param string|null $key key data to retrieve. If null, the container object is returned
     * @return \Feather\Support\Container\IContainer|mixed
     */
    public function container(?string $key = null)
    {
        if ($key === null) {
            return $this->container;
        }

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
        return $this->$errorHandler;
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

        $intCode = (int) $code;
        $resCode = ($intCode < 200) ? 500 : $intCode;

        if ($this->request->isAjax()) {
            $this->response->renderJson($msg, [], $code);
        } else if ($this->errorResolver) {

            $errorPage = str_replace(static::$viewsPath, '', $this->errorResolver->resolve($resCode));

            $viewEngine = $this->container->get('view')->get($this->errorViewEngine);

            if ($errorPage && $viewEngine) {
                $this->response->renderView($viewEngine->render($errorPage, ['message' => $msg, 'code' => $code, 'file' => $file, 'line' => $line]), [], $resCode);
            } else {
                $this->response->rawOutput($msg, $resCode, ['Content-Type: text/html']);
            }
        } else {
            $this->response->rawOutput($msg, $resCode, ['Content-Type: text/html']);
        }

        $this->response->send();

        exit();
    }

    /**
     *
     * @return \Feather\Init\Http\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     *
     * @return \Feather\Init\Http\Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     *
     * @param string $registeredName
     * @return \Feather\View\IView| null
     */
    public function getViewEngine($registeredName)
    {
        return $this->container->get($registeredName);
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
        $this->errorHandler = $errorhandler;
        $this->errorHandler->register();
    }

    /**
     *
     * @param string $name
     * @param IView $engine
     */
    public function registerViewEngine($name, IView $engine)
    {
        $this->container->add($name, $engine);
    }

    /**
     *
     * @param string $rootDir
     * @param string $defaultview
     * @param string $viewEngine
     */
    public function setErrorPage($rootDir, $defaultview, $viewEngine)
    {
        $this->errorResolver   = new ErrorResolver();
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
                $conf   = $cacheConfig['drivers']['file'];
                $driver = $conf['driver'];
                return $driver::getInstance($conf['path']);

            case 'database':
                $conf   = $cacheConfig['drivers']['database'];
                $driver = $conf['driver'];
                return $driver::getInstance($this->container->get('database.' . $conf['connection']), $conf['config']);

            case 'redis':
                $redisConfig = $cacheConfig['drivers']['redis'];
                $driver      = $redisConfig['driver'];
                return $driver::getInstance($redisConfig['host'], $redisConfig['port'], $redisConfig['scheme'], $redisConfig['connOptions']);
        }
    }

    /**
     * Get config data
     * @param string $config Use dot(.) to get nested config values
     * e.g. cache.expires or cache.drivers.database
     * @return mixed
     */
    public static function getConfig(string $config)
    {
        try {
            $keys = explode('.', $config);

            if (empty($keys)) {
                return null;
            }

            $value = static::$config;

            foreach ($keys as $key) {
                $value = $value[$key] ?? null;

                if ($value == null) {
                    break;
                }
            }

            return $value;
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

    /**
     * @deprecated
     * @return mixed
     */
    public function run()
    {
        return $this->router->processRequest($this->request);
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

        if ($this->cacheHandler != null) {
            return;
        }

        $config = static::$config['cache'];

        $this->cacheHandler = $this->getCacheDriver($config);
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
        static::$rootPath      = preg_replace('/\/{2,}/', '/', $root);
        static::$configPath    = preg_replace('/\/{2,}/', '/', $config);
        static::$logPath       = preg_replace('/\/{2,}/', '/', $log);
        static::$viewsPath     = preg_replace('/\/{2,}/', '/', $views);
        static::$tempViewsPath = preg_replace('/\/{2,}/', '/', $tempViews);
    }

    /**
     * Starts session
     * @returns void
     */
    public function startSession()
    {

        $config         = static::$config['session'];
        $defaultOptions = [
            'cookie_lifetime' => $config['lifetime'] * 60,
            'gc_max_lifetime' => $config['lifetime'] * 60,
            'cookie_path'     => '/',
            'name'            => 'FA_SESSION',
        ];
        $configOptions  = $config['options'];

        $options = array_merge($defaultOptions, $configOptions);

        $this->initSession($config);
        $this->sessionHandler->start($options);
    }

    /**
     *
     * @param array $cacheConfig
     * @return \Feather\Cache\ICache
     */
    protected function getCacheDriver($cacheConfig)
    {

        switch ($cacheConfig['driver']) {
            case 'file':
            default :
                $conf   = $cacheConfig['drivers']['file'];
                $driver = $conf['driver'];
                return $driver::getInstance(static::$rootPath . $conf['path']);

            case 'database':
                $conf   = $cacheConfig['drivers']['database'];
                $driver = $conf['driver'];
                return $driver::getInstance($this->container->get('database.' . $conf['connection']), $conf['config']);

            case 'redis':
                $redisConfig = $cacheConfig['drivers']['redis'];
                $driver      = $redisConfig['driver'];
                return $driver::getInstance($redisConfig['host'], $redisConfig['port'], $redisConfig['scheme'], $redisConfig['connOptions']);
        }
    }

    /**
     *
     * @param array $sessionConfig
     * @return \Feather\Session\Drivers\ISessionHandler|null
     */
    protected function getSessionDriver($sessionConfig)
    {

        switch ($sessionConfig['driver']) {
            case 'file':
            default :
                $conf   = $sessionConfig['drivers']['file'];
                $driver = $conf['driver'];
                return new $driver(static::$rootPath . $conf['path']);

            case 'database':
                $conf   = $sessionConfig['drivers']['database'];
                $driver = $conf['driver'];
                return new $driver($this->container->get('database.' . $conf['connection']), $config = $conf['config']);

            case 'redis':
                $conf   = $sessionConfig['drivers']['redis'];
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
    protected function initSession($config)
    {

        if ($this->sessionHandler != null) {
            return;
        }

        $this->sessionHandler = $this->getSessionDriver($config);

        if ($this->sessionHandler != null) {
            $this->sessionHandler->activate();
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
                $filename                              = substr($file, 0, strripos($file, '.php'));
                static::$config[strtolower($filename)] = include static::$configPath . '/' . $file;
            }
        }
    }

    /**
     *
     * @throws AppException
     */
    protected function registerProviders()
    {
        $appProviders = array_merge(static::$config['app']['providers'] ?? [], [
            'auth'     => Provider\AuthProvider::class,
            'database' => Provider\DatabaseProvider::class,
            'view'     => Provider\ViewProvider::class,
        ]);

        $providers = [];
        foreach ($appProviders as $key => $class) {
            if ($class instanceof Provider\Provider) {
                $this->container->register($key, function() use($class) {
                    return $class->register();
                });
            } elseif (class_exists($class) && ($class = new $class()) instanceof Provider\Provider) {
                $this->container->register($key, function() use($class) {
                    return $class->register();
                });
            } else {
                throw new AppException("$key is not an instance of \Feather\Ignite\Provider\Provider");
            }
            $providers[$key] = $class;
        }
    }

}
