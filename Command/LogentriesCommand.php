<?php

/*
 * (c) Kuborgh GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Kuborgh\QueueBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Send stats to logentries.com
 */
class LogentriesCommand extends AbstractCommand
{
    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('queue:logentries');
        $this->setDescription('Send stats to logentries');
    }

    /**
     * Execute command
     *
     * @param InputInterface  $input  Input
     * @param OutputInterface $output Output
     *
     * @return null|integer null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stats = array();
        $qDb = $this->getQueueDatabase();

        // Numeric stats
        $stats['numWaiting'] = $qDb->getNumQueued();
        $stats['numRunning'] = $qDb->getNumRuning();

        // Check longest running job
        $runningJobs = $qDb->getRunningJobs();
        $runTime = 0;
        $now = new \DateTime();
        $runningJobNames = array();
        foreach ($runningJobs as $runningJob) {
            $startTime = $runningJob->getStartTime();
            $diff = $now->getTimestamp() - $startTime->getTimestamp();
            $runTime = max($runTime, $diff);
            $runningJobNames[$runningJob->getCommand()] = $runningJob->getPriorityName();
        }
        $stats['currentJobTime'] = $runTime;
        $stats['runningJobs'] = $runningJobNames;

        // Send stats to logentries
        $logSvc = $this->getContainer()->get('kuborgh_logentries.queue');
        $logSvc->log($stats);
        $this->getLogger()->info('Logentries data sent');

        return 0;
    }
}
