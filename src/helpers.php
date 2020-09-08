<?php
global $input;
$input = Feather\Init\Http\Input::getInstance();
global $app;
$app = \Feather\Ignite\App::getInstance();
/**
 * Returns absolute path to relative path
 * @param string $relPath
 * @return string
 */
function base_path($relPath=''){
    global $app;
    $path = (stripos($relPath,'/') === 0)? $app->basePath().$relPath : $app->basePath()."/$relPath";
    return str_replace('//','/',$path);
}

/**
 * Loads a config file
 * @param string $relConfigFilepath
 * @return mixed
 */
function get_config($relConfigFilepath){
    global $app;
    try {
        $fullPath = stripos($relConfigFilepath,'/') === 0? $relConfigFilepath : '/'.$relConfigFilepath;
        $config = include $app->configPath().$fullPath;
        return $config;
    } catch (Exception $e) {
        $app->log($e->getMessage());
        return null;
    }
}

/**
 * Returns
 * @param string $relPath
 * @return return
 */
function views_path($relPath=''){
    global $app;
    $path= (stripos($relPath,'/') === 0)? $app->viewsPath().$relPath : $app->viewsPath()."/$relPath";
    return str_replace('//','/',$path);
}

/**
 * 
 * @param string $template
 * @param array $data
 * @param string $viewEngine
 * @return type
 */
function view($template,array $data,$viewEngine='native'){   
    global $app;
    $engine = $app->getViewEngine($viewEngine);
    return $engine->render($template,$data);
}

/**
 * 
 * @global \Feather\Init\Http\Input $input
 * @param string $name
 * @param mixed $default
 * @return mixed
 */
function get_value($name,$default=''){
    global $input;

    $all = $input->all();
    if(isset($all[$name])){
        return $all[$name];
    }
    return $default;
}

/**
 * Returns fullpath to asset base on relative path
 * @param string $path
 * @return string
 */
function asset($path){
    $relPath = substr($path, 0,1) == '/'? substr($path, 1) : $path;
    return stripos($relPath,'assets/') === 0? '/'.$relPath : '/assets/'.$relPath;
    
}
/**
 * Return full url
 * @param string $uri
 * @return string
 */
function url($uri){
    return (substr($uri,0,1) == '/')? $uri : '/'.$uri;
}
/**
 * Return Previous Url
 * @return string|null
 */
function url_prev(){
    return Feather\Http\Session::get(PREV_REQ_KEY);
}

/**
 *  Returns absolute path of file
 * @global Feather\Ignite\App $app
 * @param string $filename Name of file
 * @param string $basePath full path to parent directory to search. Defaults to views path
 * @return string
 */
function include_path($filename,$basePath = null){
    global $app;
    
    if($basePath ==null){
        $basePath = $app->viewsPath();
    }
    
    $includePath = find_file($basePath, $filename);
    
    if($includePath == null){
        return;
    }else{
        $includePath = str_replace($basePath.'/','', $includePath);
    }

    return $includePath;
    
}
/**
 * 
 * @param string $path
 * @param string $fileToFind Nae of file to find
 * @return string
 */
function find_file($path,$fileToFind){
    
    $files = scandir($path);
    $found = null;
    
    foreach($files as $file){
        
        if($file == '.' || $file == '..'){
            continue;
        }
        
        if(is_dir($path.'/'.$file)){
            $found = find_file($path.'/'.$file,$fileToFind);
        }
        
        if(strcasecmp($file,$fileToFind) ==0){
            $found = $path.'/'.$fileToFind;
            break;
        }
        
    }
    
    return $found;
    
}

 