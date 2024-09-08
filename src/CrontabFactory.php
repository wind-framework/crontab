<?php

namespace Wind\Crontab;

use Cron\FieldFactory;
use Wind\Base\Config;

class CrontabFactory
{

    /**
     * Get crontab tasks
     *
     * @return CronTask[]
     */
    public static function taskLists()
    {
        $tabs = di()->get(Config::class)->get('crontab', []);
        $fieldFactory = new FieldFactory();

        $tasks = [];

        foreach ($tabs as $k => $set) {
            if (!$set['enable']) {
                continue;
            }

            $set['key'] = $k;
            $set['fieldFactory'] = $fieldFactory;

            if (!isset($set['command'])) {
                $set['command'] = null;
            } elseif (!isset($set['execute'])) {
                $set['execute'] = null;
            }

            $tasks[$k] = di()->make(CronTask::class, $set);
        }

        return $tasks;
    }

}
