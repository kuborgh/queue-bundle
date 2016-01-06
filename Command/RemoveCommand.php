<?php
/*
 * (c) Kuborgh GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Kuborgh\QueueBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Remove entry from the queue
 */
class RemoveCommand extends AbstractCommand
{
    const ARG_ID = 'job_id';

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('queue:remove');
        $this->setDescription('Remove entry from the queue');
        $this->addArgument(self::ARG_ID, InputArgument::REQUIRED, 'Job Id');
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

        // Get the command
        $jobId = $input->getArgument(self::ARG_ID);

        // Remove from queue
        $model = $this->getQueueModel();
        $job = $model->getJobById($jobId);
        $succ = $model->removeJob($job);

        if ($succ) {
            $output->writeln(sprintf('Removed "%s" from queue.', $job->getCommand()));

            return 0;
        } else {
            $output->writeln(sprintf('Cannot remove "%s" from queue.', $job->getCommand()));

            return -1;
        }
    }
}
