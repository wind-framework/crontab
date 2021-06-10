<?php
/**
 * 定时任务分发进程
 *
 * 使用定时任务时需将此进程添加到自定义进程配置中启动才能生效。
 * 定时任务会分发到 TaskWorker 中执行，所以执行定时任务也务必需要启用 TaskWorker 进程。
 */
namespace Wind\Crontab;

use Cron\CronExpression;
use Cron\FieldFactory;
use Wind\Base\Config;
use Wind\Process\Process;

class CrontabDispatcherProcess extends Process
{

    public $name = 'CronDispatcher';

    /**
     * @var CronExpression[]
     */
    private $crons = [];

    public function run()
    {
        $tabs = di()->get(Config::class)->get('crontab', []);
        $fieldFactory = new FieldFactory();

        foreach ($tabs as $k => $set) {
            if (!$set['enable']) {
                continue;
            }

            $set['key'] = $k;
            $set['fieldFactory'] = $fieldFactory;
            $cronTask = di()->make(CronTask::class, $set);
            $cronTask->schedule();

            $this->crons[$k] = $cronTask;
        }
    }

}
