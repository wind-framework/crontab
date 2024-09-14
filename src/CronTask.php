<?php

namespace Wind\Crontab;

use Amp\Process\Process;
use Cron\CronExpression;
use Cron\FieldFactory;
use DateTime;
use Revolt\EventLoop;
use Wind\Event\EventDispatcher;
use Wind\Task\Task;
use Workerman\Worker;

/**
 * CronTask
 *
 * @method string getKey() Get cron task key name
 * @method string getDesc() Get cron task description
 * @method int getRunCount() Get run times count
 * @method int getLastRunAt() Get last run timestamp
 * @method int getNextRunAt() Get next run timestamp
 */
class CronTask
{

    protected $callback;

    /**
     * Undocumented variable
     *
     * @var CronExpression
     */
    private $cronExpression;

    /**
     * Run Times Count
     *
     * @var int
     */
    private $runCount = 0;

    /**
     * Last Run Timestamp
     *
     * @var int
     */
    private $lastRunAt = 0;

    /**
     * Next Run Timestamp
     *
     * @var int
     */
    private $nextRunAt = 0;

    private $eventDispatcher;

    /**
     * Undocumented function
     *
     * @param string $rule
     * @param callable $execute
     */
    public function __construct(
        protected string $key,
        $rule,
        $execute,
        protected ?string $command,
        protected string $desc,
        FieldFactory $fieldFactory,
        EventDispatcher $eventDispatcher
    )
    {
        $this->callback = $execute;
        $this->cronExpression = new CronExpression($rule, $fieldFactory);
        $this->eventDispatcher = $eventDispatcher;
    }

    public function __call($name, $arguments)
    {
        if (substr($name, 0, 3) == 'get') {
            $key = lcfirst(substr($name, 3));
            if (in_array($key, ['key', 'desc', 'runCount', 'lastRunAt', 'nextRunAt'])) {
                return $this->$key;
            }
        }

        throw new \Error("Call to undefined method ".__CLASS__."::{$name}()");
    }

    /**
     * Get cron rule expression
     *
     * @return string
     */
    public function getRule()
    {
        return $this->cronExpression->getExpression();
    }

    public function isDue()
    {
        return $this->cronExpression->isDue();
    }

    /**
     * Schedule cron timer and run it
     *
     * @param bool $run Run the task at this time
     */
    public function schedule($run=false)
    {
        //计算和安排下一次运行的时间
        $now = new DateTime();
        $nextTimestamp = $this->cronExpression->getNextRunDate($now)->getTimestamp();
        $this->nextRunAt = $nextTimestamp;

        $interval = $nextTimestamp - $now->getTimestamp();
        EventLoop::delay($interval, fn() => $this->schedule(true));

        $this->eventDispatcher->dispatch(new CrontabEvent($this->key, CrontabEvent::TYPE_SCHED, $interval));

        $run && $this->run();
    }

    /**
     * Run the cron task
     */
    public function run()
    {
        $now = time();
        $this->lastRunAt = $now;
        $this->runCount++;

        $this->eventDispatcher->dispatch(new CrontabEvent($this->key, CrontabEvent::TYPE_EXECUTE));

        $e = $result = null;

        try {
            if ($this->callback) {
                $result = Task::await($this->callback);
            } else {
                $console = WIND_MODE == 'console' && !empty($_SERVER['argv']) ? $_SERVER['argv'][0] : BASE_DIR.'/wind';
                $command = PHP_BINARY." $console {$this->command} 2>&1"; //2>&1 代表将标准错误重定向到输出
                $process = Process::start($command);

                //输出需要被不断读出，否则缓冲区满时，进程可能会暂停运行
                //根据运行情况决定缓冲是输出还是抛弃，在非 daemon 模式下输出
                $stdout = $process->getStdout();
                while (($t = $stdout->read()) !== null) {
                    if (WIND_MODE == 'server' && !Worker::$daemonize) {
                        echo $t;
                    }
                }

                $code = $process->join();
                if ($code != 0) {
                    throw new \Exception("Process '{$this->command}' exit with code $code");
                }
            }
        } catch (\Throwable $ex) {
            $e = $ex;
        }

        $event = new CrontabEvent($this->key, CrontabEvent::TYPE_RESULT, 0, $e ?: $result);
        $this->eventDispatcher->dispatch($event);
    }

}
