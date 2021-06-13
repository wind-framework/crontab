<?php
/**
 * 定时任务分发进程
 *
 * 使用定时任务时需将此进程添加到自定义进程配置中启动才能生效。
 * 定时任务会分发到 TaskWorker 中执行，所以执行定时任务也务必需要启用 TaskWorker 进程。
 */
namespace Wind\Crontab;

use Cron\FieldFactory;
use Wind\Base\Channel;
use Wind\Base\Config;
use Wind\Process\Process;

use function Amp\call;
use function Amp\delay;

class CrontabDispatcherProcess extends Process
{

    public $name = 'CronDispatcher';

    /**
     * @var CronTask[]
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

        yield call([$this, 'statReporter']);
    }

    public function statReporter()
    {
        //消费进程状态上报
        $channel = di()->get(Channel::class);

        $channel->on('wind.stat.tick', function() use ($channel) {
            $stat = [];

            foreach ($this->crons as $cron) {
                $stat[$cron->getKey()] = [
                    'rule' => $cron->getRule(),
                    'run_count' => $cron->getRunCount(),
                    'last_run_at' => $cron->getLastRunAt(),
                    'next_run_at' => $cron->getNextRunAt(),
                    'desc' => $cron->getDesc()
                ];
            }

            $channel->publish('wind.stat.report', [
                'type' => 'crontab',
                'pid' => posix_getpid(),
                'stat' => $stat
            ]);
        });

        yield delay(500);

        $channel->publish('wind.stat.online', [
            'pid' => posix_getpid(),
            'type' => 'crontab',
            'name' => $this->name
        ]);
    }

}
