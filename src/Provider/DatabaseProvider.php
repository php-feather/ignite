<?php

namespace Feather\Ignite\Provider;

use Feather\Support\Database\Connection;
use Feather\Support\Container\Container;

/**
 * Description of DatabaseProvider
 *
 * @author fcarbah
 */
class DatabaseProvider extends Provider
{

    /**
     *
     * @return \Feather\Support\Container\Container
     */
    public function register()
    {
        $container = new Container();
        $dbConfig  = $this->app->getConfig('database');

        foreach ($dbConfig['connections'] as $key => $config) {
            $container->register($key, function() use($config) {
                $db = new Connection($config);
                $db->connect();
                return $db;
            });

            if ($key == $dbConfig['default']) {
                $container->register('default', function() use($config) {
                    $db = new Connection($config);
                    $db->connect();
                    return $db;
                });
            }
        }

        return $container;
    }

}
