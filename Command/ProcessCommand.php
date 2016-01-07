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
 * Pick an entry from the queue and run
 * NOTE: RunnerCommand should be preferred, as it can process more than 1 entry per minute
 */
class ProcessCommand extends AbstractCommand
{
    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('queue:process');
        $this->setDescription('Pick an entry from the queue and run');
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
        $this->garbageCollect();

        $model = $this->getQueueModel();

        // Check if processing slots are available
        $running = $model->getRunningJobs();
        $numRunning = count($running);
        $maxRunning = $model->getConcurrency();
        $output->writeln(sprintf('<info>%d</info> of max. <info>%d</info> jobs running.', $numRunning, $maxRunning));

        // No slot free? we are done
        if ($numRunning >= $maxRunning) {
            $output->writeln('No slot free');

            return -1;
        }

        // Check if there are jobs waiting
        $jobs = $model->getQueuedJobs();
        if (!count($jobs)) {
            $output->writeln('No job waiting for processing');

            return -1;
        }

        // Run the next available job
        $job = $jobs[0];
        $output->writeln(sprintf('<info>Starting</info> %s (%d)', $job->getCommand(), $job->getId()));

        $exitCode = $model->run($job);
        if (!$exitCode) {
            $output->writeln('<info>Done</info>');
        } else {
            $output->writeln('<error>Failed</error>');
        }

        return $exitCode;
    }
}
