<?php

namespace Feather\Ignite\Provider;

use Feather\Ignite\App;
use Feather\Support\Contracts\IProvider;

/**
 * Description of Provider
 *
 * @author fcarbah
 */
abstract class Provider implements IProvider
{

    /** @var \Feather\Ignite\App * */
    protected $app;

    public function __construct()
    {
        $this->app = App::getInstance();
    }

}
