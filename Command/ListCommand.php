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
 * Show Queue Status
 */
class ListCommand extends AbstractCommand
{
    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('queue:list');
        $this->setDescription('Show current queue');
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
        // Standard garbage collection
        $this->garbageCollect($output);

        // Queued Jobs
        $this->printQueued($output);

        // Running Jobs
        $this->printRunning($output);

        $output->writeln('');
    }

    /**
     * Print queued Jobs
     *
     * @param OutputInterface $output Output
     */
    protected function printQueued(OutputInterface $output)
    {
        // Fetch data
        $model = $this->getQueueModel();
        $jobs = $model->getQueuedJobs();

        if (!count($jobs)) {
            return;
        }

        // Print headline
        $output->writeln('');
        $output->writeln('.------------------------------------------------------------------------------.');
        $output->writeln('| <info>QUEUED</info>                                                                       |');
        $output->writeln('|------.-------.---------.-----------------.-----------------------------------|');
        $output->writeln('| Pos. | ID    | Prio    | Date            | Command                           |');
        $output->writeln('|------|-------|---------|-----------------|-----------------------------------|');

        // Print jobs
        $i = 1;
        foreach ($jobs as $job) {
            // Priority colors
            if ($job->getPriority() == 5) {
                $prioWrapOpen = '<fg=red>';
                $prioWrapClose = '</fg=red>';
            } elseif ($job->getPriority() == 4) {
                $prioWrapOpen = '<fg=yellow>';
                $prioWrapClose = '</fg=yellow>';
            } elseif ($job->getPriority() == 3) {
                $prioWrapOpen = '<fg=green>';
                $prioWrapClose = '</fg=green>';
            } elseif ($job->getPriority() == 2) {
                $prioWrapOpen = '<fg=cyan>';
                $prioWrapClose = '</fg=cyan>';
            } else {
                $prioWrapOpen = '';
                $prioWrapClose = '';
            }

            $output->writeln(sprintf('| %4d | %5d | %s%-7s%s | %s | %-33s |', $i++, $job->getId(), $prioWrapOpen, $job->getPriorityName(), $prioWrapClose, $job->getInsertTime()
                ->format('d.m. H:i:s'), $job->getCommand()));
        }

        // Print footer
        $output->writeln("'------'-------'---------'-----------------'-----------------------------------'");
    }

    /**
     * Print queued Jobs
     *
     * @param OutputInterface $output Output
     */
    protected function printRunning(OutputInterface $output)
    {
        // Fetch data
        $model = $this->getQueueModel();
        $jobs = $model->getRunningJobs();

        if (!count($jobs)) {
            return;
        }

        // Print headline
        $output->writeln('');
        $output->writeln('.------------------------------------------------------------------------------.');
        $output->writeln('| <fg=yellow>RUNNING</fg=yellow>                                                                      |');
        $output->writeln('|-------.-------.-----------------.----------.---------------------------------|');
        $output->writeln('| PID   | ID    | Start           | Time     | Command                         |');
        $output->writeln('|-------|-------|-----------------|----------|---------------------------------|');

        // Print jobs
        foreach ($jobs as $job) {
            // Check how long the command runs
            $date = new \DateTime();
            $diff = $date->diff($job->getStartTime());
            $hours = $diff->h + ($diff->d * 24) + ($diff->m * 30 * 24) + ($diff->y * 365 * 24);
            $time = sprintf('%02d:%02d:%02d', $hours, $diff->i, $diff->s);

            // More than 1 hour
            if ($hours) {
                $timeWrapOpen = '<fg=red>';
                $timeWrapClose = '</fg=red>';
                // More than 10 min.
            } elseif ($diff->i > 10) {
                $timeWrapOpen = '<fg=yellow>';
                $timeWrapClose = '</fg=yellow>';
                // Less than 10 min.
            } else {
                $timeWrapOpen = '<fg=green>';
                $timeWrapClose = '</fg=green>';
            }

            $output->writeln(sprintf('| %5d | %5d | %s | %s%8s%s | %-31s |', $job->getPid(), $job->getId(), $job->getInsertTime()
                ->format('d.m. H:i:s'), $timeWrapOpen, $time, $timeWrapClose, $job->getCommand()));
        }

        // Print footer
        $output->writeln("'-------'-------'-----------------'----------'---------------------------------'");
    }
}
