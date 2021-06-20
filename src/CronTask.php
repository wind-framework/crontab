<?php

namespace Wind\Crontab;

use Cron\CronExpression;
use Cron\FieldFactory;
use DateTime;
use Wind\Event\EventDispatcher;
use Wind\Task\Task;
use Workerman\Timer;

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

    protected $key;
    protected $callback;
    protected $desc;

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

    public function __construct($key, $rule, $execute, $desc, FieldFactory $fieldFactory, EventDispatcher $eventDispatcher)
    {
        $this->key = $key;
        $this->callback = $execute;
        $this->desc = $desc;

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
        Timer::add($interval, [$this, 'schedule'], [true], false);

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

        Task::execute($this->callback)->onResolve(function($e, $result) {
            /* @var \Exception $e */
            $event = new CrontabEvent($this->key, CrontabEvent::TYPE_RESULT, 0, $e ?: $result);
            $this->eventDispatcher->dispatch($event);
        });
    }

}
