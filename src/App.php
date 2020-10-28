<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Feather\Ignite;

use Feather\View\ViewInterface;
/**
 * Description of App
 *
 * @author fcarbah
 */
use Feather\Init\Http\Router;
use Feather\Init\Http\Request;
use Feather\Init\Http\Response;
use Feather\Session\Drivers\SessionHandlerContract;
use Feather\Cache\Contracts\Cache;

/**
 * Handles errors thrown by application
 * @param int|string $code
 * @param string $message
 * @param string $file
 * @param int $line
 * @return void
 */
function myErrorHandler($code,$message,$file,$line){
    
    $msg ="ERR CODE: $code\nMESSAGE:$message\nFILE:$file || $line";

    $app = App::getInstance();
    
    if($app->errorHandler()){
        return $app->errorHandler()->handle($code,$message,$file,$line);
    }
    
    $app->log($msg);

    if(preg_match('/(.*?)Controllers(.*?)\'\snot\sfound/i',$message)){
        return $app->errorResponse('Page Not Found',404);
    }
    $app->errorResponse('Internal Server Error'.PHP_EOL.$message,500,$file,$line);
}
/**
 * Handles fatal errors thrown by application
 * @return boolean|void
 */
function fatalErrorHandler(){
    $last_error = error_get_last();
    
    if(!$last_error){
        return;
    }
    
    if ($last_error['type'] === E_ERROR) {
        myErrorHandler(E_ERROR, $last_error['message'], $last_error['file'], $last_error['line']);
    }else{
        $code = $last_error['type'];$message = $last_error['message'];$file=$last_error['file'];
        $line = $last_error['line'];
        App::log("ERR CODE: $code\nMESSAGE:$message\nFILE:$file || $line");
        return true;
    }
}

//Register fatal error handler
register_shutdown_function(function(){
    fatalErrorHandler();   
});



/**
 * Description of App
 *
 * @author fcarbah
 */
class App {
    
    protected $controller;
    protected $defaultController='Index';
    protected $response;
    protected $request;
    protected $router;
    protected $errorPage;
    protected $errorViewEngine = 'native';
    
    /** @var array **/
    protected $viewEngines = [];
    
    /** @var \Feather\Ignite\ErrorHandler **/
    protected static $errorHandler;
    
    /** @var \Feather\Cache\Contracts\Cache **/
    protected static $cacheHandler;
    
    /** @var \Feather\Session\Drivers\SessionHandlerContract **/
    protected static $sessionHandler;
    
    private static $self;
    
    /**
     * List of objects registered in application Container
     * @var array
     */
    private static $container = [];
    
    /**
     * App Configurations
     * @var array
     */
    protected static $config = [];
    
    protected static $rootPath;
    protected static $configPath;
    protected static $logPath;
    protected static $viewsPath;
    protected static $tempViewsPath;
    
    
    private function __construct() {
        $this->request = Request::getInstance();
        $this->response = Response::getInstance();
        $this->router = Router::getInstance();
    }
    /**
     * 
     * @return \Feather\Ignite\App
     */
    public static function getInstance(){
        if(self::$self == NULL){
            self::$self  = new App();
        }
        return self::$self;  
    }
    
    /**
     * 
     * @return string
     */
    public function basePath(){
        return self::$rootPath;
    }
    
    /**
     * Load Require files
     * Array of absolute file paths
     * @param array $files
     */
    public function boot(array $files=[]){
        foreach($files as $file){
            require $file;
        }
        
        $this->loadConfigurations();
    }
    
    /**
     * 
     * @return \Feather\Cache\Contracts\Cache |null
     */
    public function cache(){
        return self::$cacheHandler;
    }
    
    /**
     * 
     * @return string
     */
    public function configPath(){
        return self::$configPath;
    }
    
    /**
     * Configure router
     * @param array $routerConfig
     */
    public function configureRouter($routerConfig){
        
        $ctrlConfig = $routerConfig['controller'];
        
        $this->router->setAutoRouting($routerConfig['autoRouting']);
        $this->router->setRoutingFallback($routerConfig['fallbackRouting']);
        $this->router->setDefaultController($ctrlConfig['default']);
        $this->router->setControllerNamespace($ctrlConfig['namespace']);
        $this->router->setControllerPath($ctrlConfig['baseDirectory']);
        
        if($routerConfig['cache']['enabled']){
            
            $cache = $routerConfig['cache']['driver'];
            
            if($cache){
                
                if($cache instanceof Cache){
                    $this->router->setCacheHandler($cache);
                } else {
                    $this->router->setCacheHandler(self::getCache($cache));
                }
            } elseif (self::$cacheHandler){
                $this->router->setCacheHandler(self::$cacheHandler);
            }
            
        }
    }
    
    /**
     * Retrieve object registered in app container by name
     * @param string $name Registered name of ovalue to retrieve from container
     * @return mixed
     */
    public function container($name){
        
        if(key_exists(self::$container[$name])){
            return self::$container[$name];
        }
        
        $this->log("Requested $name not registered in application container");
        
        return null;

    }

    /**
     * terminate application
     */
    public function end(){
        die;
    }
    
    /**
     * Get instance of App error Handler
     * @return \Feather\Ignite\ErrorHandler $errorhandler | null
     */
    public function errorHandler(){
        return self::$errorHandler;
    }
    
    /**
     * 
     * @param string $msg
     * @param int| string $code
     * @param string $file
     * @param int $line
     * @return void
     */
    public function errorResponse($msg='',$code=400,$file='',$line=null){
        
        ob_clean();
        
        
        if($this->request->isAjax){
            $this->response->renderJson($msg,[],$code);
        }
        
        else if($this->errorPage){
            $viewEngine = $this->viewEngines[strtolower($this->errorViewEngine)];
            $this->response->renderView($viewEngine->render($this->errorPage,['message'=>$msg,'code'=>$code,'file'=>$file,'line'=>$line]),[],$code);
        }
        
        else{
            $this->response->rawOutput($msg,$code,['Content-Type: text/html']);
        }
        
        return $this->response->send();

    }
    
    /**
     * 
     * @param string $registeredName
     * @return \Feather\View\ViewInterface | null
     */
    public function getViewEngine($registeredName){
        $name = strtolower($registeredName);
        if(isset($this->viewEngines[$name])){
            return $this->viewEngines[$name];
        }
        return null;
    }
    
    /**
     * Load file or list of files
     * @param string|array $file
     */
    public function load($file){
        
        if(is_array($file)){
            
            foreach($file as $f){
                require_once $f;
            }
        }
        else{
            require_once $file;
        }
        
    }
    
    /**
     * Register a object in the app container
     * @param string $name
     * @param mixed $object
     */
    public function register($name,$object){
        self::$container[$name] = $object;
    }
    
    /**
     * 
     * @param \Feather\Ignite\ErrorHandler $errorhandler
     */
    public function registerErrorHandler(ErrorHandler $errorhandler){
        $this->errorHandler = $errorhandler;
    }
    
    /**
     * 
     * @param string $name
     * @param ViewInterface $engine
     */
    public function registerViewEngine($name, ViewInterface $engine){
        $this->viewEngines[strtolower($name)] = $engine;
    }
    
    /**
     * Sets page to display errors on and render engine to use when rendering page
     * @param string $page 
     * @param string $pageRenderer Registered name of view Engine
     */
    public function setErrorPage($page,$pageRenderer){
        
        if(stripos($page,'/') > 0){
            $page = '/'.$page;
        }
        
        if(file_exists(self::$viewsPath.$page)){
            $this->errorPage = $page;
        }
        
        $this->errorViewEngine = $pageRenderer;
    }
     
    /**
     * Enable or Disable routing fallback requests handling
     * @param bool $enable
     */
    public function setRouterFallback($enable){
        $this->router->setRoutingFallback($enable);
    }
    
    /**
     * 
     * @return string
     */
    public function viewsPath(){
        return self::$viewsPath;
    }
    
    /**
     * 
     * @param string $driver
     * @return \Feather\Cache\Contracts\Cache
     */
    public static function getCache($driver){
        
        $cacheConfig = self::$config['cache'];
        
        switch($driver){  
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
                return  $driver::getInstance($redisConfig['host'], $redisConfig['port'], $redisConfig['scheme'],$redisConfig['connOptions']);
        }
    }
    
    /**
     * Load configuration from config file path
     * @param string $configPath
     * @return mixed
     */
    public static function getConfig($configPath){
        try{
            $fullPath = stripos($configPath,'/') === 0? $configPath : '/'.$configPath;
            $config = include self::$configPath.$fullPath;
            return $config;
        }
        catch(\Exception $e){
            self::log($e->getMessage());
            return null;   
        }
    }
    
    /**
     * Logs message to file
     * @param string $msg
     */
    public static function log($msg){
        $filePath = self::$logPath.'/app_log';
        error_log(date('Y-m-d H:i:s').' - '.$msg,3,$filePath);
    }
    
    public function run(){

        try{
            return $this->router->processRequest($this->request->uri,$this->request->method);
        }
        catch (\Exception $e){
            return $this->errorResponse($e->getMessage(),$e->getCode());
        }
    }
    
    /**
     * 
     * @param \Feather\Cache\Contracts\Cache $cacheHandler
     */
    public static function registerCacheHandler(Cache $cacheHandler){
        self::$cacheHandler = $cacheHandler;
    }
    
    /**
     * 
     * @param \Feather\Session\Drivers\SessionHandlerContract $sessionHandler
     */
    public static function registerSessionHandler(SessionHandlerContract $sessionHandler){
        self::$sessionHandler = $sessionHandler;
        self::$sessionHandler->activate();
    }
    
    /**
     * Sets Application caching
     * @return void
     */
    public function setCaching(){

        if(self::$cacheHandler != null){
            return;
        }

        $config = self::$config['cache'];
        
        self::$cacheHandler = self::getCacheDriver($config);
        
    }
    
    /**
     * Sets absolute paths for Application
     * @param string $root Absolute path of application directory
     * @param string $config Absolute path to configs directory
     * @param string $log Absolute path to log directory
     * @param string $views Absolute path to views directory
     * @param string $tempViews Absolute path to temporary | cache views path 
     */
    public static function setBasePaths($root,$config,$log,$views,$tempViews=''){
        self::$rootPath = $root;
        self::$configPath = $config;
        self::$logPath = $log;
        self::$viewsPath = $views;
        self::$tempViewsPath = $tempViews;
    }
    
    /**
     * Starts session
     * @returns void
     */
    public static function startSession(){
        
        $config = self::$config['session'];
        
        if(!isset($_SESSION)){
            self::initSession($config);
            session_set_cookie_params($config['lifetime'], '/');
            session_name('fi_session');
            session_start();
        }
        else{
            setcookie(session_name('fi_session'),session_id(),time()+$config['lifetime']);
        }
        
    }
    
    /**
     * 
     * @param array $cacheConfig
     * @return \Feather\Cache\Contracts\Cache
     */
    protected static function getCacheDriver($cacheConfig){
 
        switch($cacheConfig['driver']){  
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
                return  $driver::getInstance($redisConfig['host'], $redisConfig['port'], $redisConfig['scheme'],$redisConfig['connOptions']);
        }
    }
    
    /**
     * 
     * @param array $sessionConfig
     * @return \Feather\Session\Drivers\SessionHandlerContract|null
     */
    protected static function getSessionDriver($sessionConfig){

        switch($sessionConfig['driver']){
            case 'file':
            default :
                $conf = $sessionConfig['drivers']['file'];
                $driver = $conf['driver'];
                return new $driver($conf['path']);

            case 'database':
                $conf = $sessionConfig['drivers']['database'];
                $driver = $conf['driver'];
                $dbConfig = $conf['connections'][$conf['active']];
                return new $driver($dbConfig);               
            
            case 'redis':
                $conf = $sessionConfig['drivers']['redis'];
                $driver = $conf['driver'];
                return  new $driver($conf['host'], $conf['port'], $conf['scheme']);                
        }
    }
    
    /**
     * 
     * @param array $config
     * @return void
     */
    protected static function initSession($config){
        
        if(self::$sessionHandler != null){
            return;
        }
        
        self::$sessionHandler = self::getSessionDriver($config);

        if(self::$sessionHandler != null){
            self::$sessionHandler->activate();
        }
        
    }
    
    /**
     * Load application configuration files from config directory
     * Only top level configuration files are loaded
     */
    protected function loadConfigurations(){
        
        $files = feather_dir_files(self::$configPath);
        
        foreach($files as $file){
            
            if(is_file(self::$configPath."/$file") && stripos($file,'.php') === strlen($file)-4){
                $filename = substr($file,0,strripos($file,'.php'));
                self::$config[strtolower($filename)] = include self::$configPath.'/'.$file;
            }
        }
    }
    
    
}
