<?php

namespace Feather\Ignite\Provider;

/**
 * Description of AuthProvider
 *
 * @author fcarbah
 */
class AuthProvider extends Provider
{

    /**
     *
     * @return \Feather\Auth\IAuthenticator
     * @throws \Exception
     */
    public function register()
    {
        $config = $this->app->getConfig('auth');

        if (!empty($config)) {

            $authConfig  = $config['authenticators'][$config['authenticator']];
            $guardConfig = $config['guards'][$config['guard']];

            $authenticator = $authConfig['class'];
            unset($authConfig['class']);
            $auth          = new $authenticator();

            if (!$auth instanceof \Feather\Auth\IAuthenticator) {
                throw new \Exception("$authenticator is not an instance of Feather\Auth\IAuthenticator");
            }

            foreach ($authConfig as $key => $data) {

                if ($key === 'connection') {
                    $data = $this->app->container('database')->get($data);
                }

                if (method_exists($auth, "set{$key}")) {
                    call_user_func_array([$auth, "set{$key}"], [$data]);
                }
            }

            $guard = $this->getGuard($guardConfig);

            $auth->setGuard($guard);

            return $auth;
        }

        throw new \Exception('Auth configuration missing');
    }

    /**
     *
     * @param array $guardConfig
     * @return \Feather\Auth\Guard
     */
    protected function getGuard(array $guardConfig)
    {
        $class = $guardConfig['class'];
        unset($guardConfig['class']);

        $guard = new $class();

        foreach ($guardConfig as $key => $data) {
            if (method_exists($guard, "set{$key}")) {
                call_user_func_array([$guard, "set{$key}"], [$data]);
            }
        }

        return $guard;
    }

}
