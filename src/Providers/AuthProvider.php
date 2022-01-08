<?php

namespace Feather\Ignite;

/**
 * Description of AuthProvider
 *
 * @author fcarbah
 */
class AuthProvider extends Provider
{

    public function register()
    {
        $authConfig = $this->app->getConfig('auth');

        if (!empty($authConfig)) {

        }
    }

}
