<?php

namespace Feather\Ignite;

use Feather\Ignite\App;
use Feather\Init\Http\Routing\Router;
use Feather\Init\Http\Routing\Route;
use Feather\Init\Http\Request;
use Feather\Init\Http\Response;
use Feather\Init\Middleware\MiddlewareResolver;
use Feather\Init\Middleware\IMiddleware;

/**
 * Description of Core
 *
 * @author fcarbah
 */
class Core
{

    /** @var \Feather\Ignite\App * */
    protected $app;

    /** @var \Feather\Init\Http\Routing\Router * */
    protected $router;

    /** @var array * */
    protected $globalMiddlewares = [];

    /** @var array * */
    protected $reqMethodMiddlewares = [];

    /** @var array * */
    protected $routeMiddlewares = [];

    /**
     *
     * @param \Feather\Ignite\App $app
     * @param \Feather\Init\Http\Routing\Router $router
     */
    public function __construct(App $app, Router $router)
    {
        $this->app = $app;
        $this->router = $router;
        $mwResolver = new MiddlewareResolver();
        $mwResolver->registerMiddlewares($this->routeMiddlewares);
        Route::setMiddleWareResolver($mwResolver);
    }

    /**
     *
     * @param \Feather\Init\Http\Request $request
     */
    public function handle(Request $request)
    {
        $route = $this->router->processRequest($request);

        $closure = function() use($route) {
            return $route->run();
        };

        $next = \Closure::bind($closure, $this);
        $res = $this->runMiddlewares($request, $next);

        $res->send();
    }

    /**
     * Terminate request
     */
    public function terminate()
    {
        $this->app->end();
    }

    /**
     *
     * @param Feather\Init\Http\Request $request
     * @param \Closure|\Feather\Init\Http\Response $next
     * @return type
     */
    protected function runMiddlewares(Request $request, $next)
    {
        foreach ($this->globalMiddlewares as $middleware) {

            if (!($middleware instanceof IMiddleware)) {
                $middleware = new $middleware();
            }

            $next = $middleware->run($next);
            if (!$middleware->passed()) {
                return $next;
            }
        }

        $httpMethod = strtolower($request->getHttpMethod());
        $httpMiddlewares = $this->reqMethodMiddlewares[$httpMethod] ?? null;

        if (!$httpMiddlewares) {
            return $next;
        }

        if (!is_array($httpMiddlewares)) {
            $httpMiddlewares = [$httpMiddlewares];
        }

        foreach ($httpMiddlewares as $middleware) {

            if (!($middleware instanceof IMiddleware)) {
                $middleware = new $middleware();
            }

            $next = $middleware->run($next);
            if (!$middleware->passed()) {
                return $next;
            }
        }

        return $next;
    }

}
