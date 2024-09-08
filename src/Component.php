<?php

namespace Wind\Crontab;

use Symfony\Component\Console\Application;
use Wind\Base\Config;

class Component implements \Wind\Base\Component
{

    public static function provide($app)
    {
        if (WIND_MODE == 'console') {
            $console = $app->container->get(Application::class);
            $console->add(new CronRunCommand());
        }
    }

    public static function start($worker)
    {
    }

}
