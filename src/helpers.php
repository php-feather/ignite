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
function base_path($relPath = '')
{
    global $app;
    $path = (stripos($relPath, '/') === 0) ? $app->basePath() . $relPath : $app->basePath() . "/$relPath";
    return str_replace('//', '/', $path);
}

/**
 * Autoload all files in the helpers directory
 * @param array $exclude List of files to not autoload
 * @param type $directory absolute path of directory files to autoload
 * @return type
 */
function feather_autoload($directory, $exclude = [])
{

    if (!is_dir($directory)) {
        require_once $directory;
        return;
    }

    $files = scandir($directory);

    $dirPath = strrpos($directory, '/') === strlen($directory) - 1 ? $directory : $directory . '/';

    foreach ($files as $file) {
        if (strpos($file, '.') === 0) {
            continue;
        }

        if (is_dir($file)) {
            feather_autoload($file);
        } else if (!in_array($file, $exclude)) {
            require_once $dirPath . $file;
        }
    }
}

/**
 * Generates csrf Token
 * @return string
 */
function fa_csrf_token()
{
    $csrf = \Feather\App\Security\Csrf::getInstance();
    $token = $csrf->generateToken();
    return $token;
}

/**
 * Generates csrf Form Element
 * @return type
 */
function fa_csrf_token_input()
{
    $id = CSRF_ID;
    $token = fa_csrf_token();
    return <<<TOKEN
    <input type="hidden" name="$id" value="$token" />
TOKEN;
}

/**
 *
 * @param string $encryptedText
 * @return string
 * @throws RuntimeException
 */
function fa_decrypt($encryptedText)
{
    $key = get_env('APP_KEY');
    if (!$key) {
        throw new RuntimeException('Application key not found! Please');
    }
    return fs_decrypt($encryptedText, hex2bin($key));
}

/**
 *
 * @param mixed $value
 * @return string
 * @throws RuntimeException
 */
function fa_encrypt($value)
{
    $key = get_env('APP_KEY');
    if (!$key) {
        throw new RuntimeException('Application key not found! Please');
    }
    return fs_encrypt($value, hex2bin($key));
}

/**
 * Loads a config file
 * @param string $relConfigFilepath
 * @return mixed
 */
function get_config($relConfigFilepath)
{
    global $app;
    try {
        $fullPath = stripos($relConfigFilepath, '/') === 0 ? $relConfigFilepath : '/' . $relConfigFilepath;
        $config = include $app->configPath() . $fullPath;
        return $config;
    } catch (Exception $e) {
        $app->log($e->getMessage());
        return null;
    }
}

/**
 * Wrapper for php getenv function that allows you to pass a default value
 * if the env variable is not found
 * @param string $key ENV variable name
 * @param mixed $default Default value if $key does not exist
 * @return string
 */
function get_env($key, $default = null)
{
    $val = getenv($key, true) ?: getenv($key);
    return $val === false ? $default : $val;
}

/**
 * Returns
 * @param string $relPath
 * @return return
 */
function views_path($relPath = '')
{
    global $app;
    $path = (stripos($relPath, '/') === 0) ? $app->viewsPath() . $relPath : $app->viewsPath() . "/$relPath";
    return str_replace('//', '/', $path);
}

/**
 *
 * @param string $template
 * @param array $data
 * @param string $viewEngine
 * @return type
 */
function view($template, array $data, $viewEngine = 'native')
{
    global $app;
    $engine = $app->getViewEngine($viewEngine);
    return $engine->render($template, $data);
}

/**
 *
 * @global \Feather\Init\Http\Input $input
 * @param string $name
 * @param mixed $default
 * @return mixed
 */
function get_value($name, $default = '')
{
    global $input;

    $all = $input->all();
    if (isset($all[$name])) {
        return $all[$name];
    }
    return $default;
}

/**
 * Returns fullpath to asset base on relative path
 * @param string $path
 * @return string
 */
function asset($path)
{
    $relPath = substr($path, 0, 1) == '/' ? substr($path, 1) : $path;
    return stripos($relPath, 'assets/') === 0 ? '/' . $relPath : '/assets/' . $relPath;
}

/**
 * Return full url
 * @param string $uri
 * @return string
 */
function url($uri)
{
    return (substr($uri, 0, 1) == '/') ? $uri : '/' . $uri;
}

/**
 * Return Previous Url
 * @return string|null
 */
function url_prev()
{
    return Feather\Http\Session::get(PREV_REQ_KEY);
}

/**
 *  Returns absolute path of file
 * @global Feather\Ignite\App $app
 * @param string $filename Name of file
 * @param string $basePath full path to parent directory to search. Defaults to views path
 * @return string
 */
function include_path($filename, $basePath = null)
{
    global $app;

    if ($basePath == null) {
        $basePath = $app->viewsPath();
    }

    $includePath = find_file($basePath, $filename);

    if ($includePath == null) {
        return;
    } else {
        $includePath = str_replace($basePath . '/', '', $includePath);
    }

    return $includePath;
}

/**
 *
 * @param string $path
 * @param string $fileToFind Nae of file to find
 * @return string
 */
function find_file($path, $fileToFind)
{

    $files = scandir($path);
    $found = null;

    foreach ($files as $file) {

        if ($file == '.' || $file == '..') {
            continue;
        }

        if (is_dir($path . '/' . $file)) {
            $found = find_file($path . '/' . $file, $fileToFind);
        }

        if (strcasecmp($file, $fileToFind) == 0) {
            $found = $path . '/' . $fileToFind;
            break;
        }
    }

    return $found;
}
