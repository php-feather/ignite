<?php

namespace Feather\Ignite\Providers;

use Feather\View\Native;
use Feather\View\Twig;
use Feather\View\Blade;
use Feather\View\IView;
use Feather\Support\Container\Container;

/**
 * Description of ViewProvider
 *
 * @author fcarbah
 */
class ViewProvider extends Provider
{

    /**
     *
     * @return \Feather\Support\Container\Container
     */
    public function register()
    {
        $basePath  = $this->app->basePath();
        $viewsPath = $this->app->viewsPath();
        $container = new Container();

        //blade engine
        $container->register('blade', function() use($basePath, $viewsPath) {
            return new Blade($viewsPath, $basePath . '/storage/app/');
        });

        //native engine
        $container->register('native', function() use($basePath, $viewsPath) {
            return new Native($viewsPath, $basePath . '/storage/app/');
        });

        //twig engine
        $container->register('twig', function() use($viewsPath) {
            return new Twig($viewsPath);
        });

        return $container;
    }

}
