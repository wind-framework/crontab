<?php

namespace Wind\Crontab;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CronRunCommand extends Command
{

    protected function configure()
    {
        $this->setName('cron:run')
            ->setDescription('Crontab run schedule every minute.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $tasks = CrontabFactory::taskLists();

        foreach ($tasks as $task) {
            if ($task->isDue()) {
                $task->run();
            }
        }

        return self::SUCCESS;
    }

}
