<?php

namespace Feather\Ignite\ErrorHandler;

use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/**
 * Description of DefaultHandler
 *
 * @author fcarbah
 */
class WhoopsHandler implements IErrorHandler
{

    public function register()
    {
        $whoops = new Run();
        $whoops->pushHandler(new PrettyPageHandler());
        $whoops->register();
    }

}
