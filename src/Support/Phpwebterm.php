<?php

namespace Phpwebterm\Support;

use Illuminate\Support\Facades\Facade;

/**
 * @method static start()
 * @method static addRoute(int|string $route, string $process)
 */
class Phpwebterm extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'phpwebterm';
    }
}
