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

    public function end(){
        die;
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
    }
    
    /**
     * 
     * @param string $ctrlNamespace
     * @param string $defaultController Name of default Controller
     * @param string $controllersDir Adbsolute path to controllers directory
     */
    public function init($ctrlNamespace,$defaultController,$controllersDir){
        $this->router->setDefaultController($defaultController);
        $this->router->setControllerNamespace($ctrlNamespace);
        $this->router->setControllerPath($controllersDir);
    }
    
    public function configureRouter($routerConfig){
        $this->router->setAutoRouting($routerConfig['autoRouting']);
        $this->router->setRoutingFallback($routerConfig['fallbackRouting']);
    }
     
    /**
     * Enable or Disable routing fallback requests handling
     * @param bool $enable
     */
    public function setRouterFallback($enable){
        $this->router->setRoutingFallback($enable);
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
     * Get instance of App error Handler
     * @return \Feather\Ignite\ErrorHandler $errorhandler | null
     */
    public function errorHandler(){
        return self::$errorHandler;
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
     * 
     * @return string
     */
    public function basePath(){
        return self::$rootPath;
    }
    /**
     * 
     * @return string
     */
    public function configPath(){
        return self::$configPath;
    }
    /**
     * 
     * @return string
     */
    public function viewsPath(){
        return self::$viewsPath;
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
     * Enable router caching
     */
    public function activateRouterCache(){
        if(self::$cacheHandler){
            Router::getInstance()->setCacheHandler(self::$cacheHandler);
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
     * Starts session
     * @returns void
     */
    public static function startSession(){
        
        $config = include self::$configPath.'/session.php';
        
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
        
        
        $config = include self::$configPath.'/cache.php';
        
        switch($config['driver']){
            
            case 'file':
            default :
                self::$cacheHandler = \Feather\Cache\FileCache::getInstance($config['filePath']);
                break;
            
            case 'database':
                $dbConfig = $config['dbConfig'];
                self::$cacheHandler = \Feather\Cache\DatabaseCache::getInstance($dbConfig[$dbConfig['active']]); 
                break;
            
            case 'redis':
                $redisConfig = $config['redis'];
                self::$cacheHandler = \Feather\Cache\RedisCache::getInstance($redisConfig['host'], $redisConfig['port'], $redisConfig['scheme'],$redisConfig['connOptions']);
                break;
        }
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
     * 
     * @param array $config
     * @return void
     */
    protected static function initSession($config){
        
        if(self::$sessionHandler != null){
            return;
        }
        
        switch($config['driver']){
            
            case 'file':
            default :
                self::$sessionHandler = new \Feather\Session\Drivers\FileDriver($config['filePath']);
                break;
            
            case 'database':
                $dbConfig = $config['dbConfig'];
                self::$sessionHandler = new \Feather\Session\Drivers\DatabaseDriver($dbConfig[$dbConfig['ative']]);               
                break;
            
            case 'redis':
                $redisConfig = $config['redis'];
                self::$sessionHandler = new \Feather\Session\Drivers\RedisDriver($redisConfig['host'], $redisConfig['port'], $redisConfig['scheme']);                
                break;
        }
        
        if(self::$sessionHandler != null){
            self::$sessionHandler->activate();
        }
        
    }
    
    
    
}
