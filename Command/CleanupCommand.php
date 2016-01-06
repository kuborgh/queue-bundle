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
 * remove old (> 3 days) jobs that were successfull
 */
class CleanupCommand extends AbstractCommand
{
    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('queue:cleanup');
        $this->setDescription('Remove old, successfull jobs from the queue.');
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
        $model = $this->getQueueModel();
        $output->writeln('Starting cleanup');

        // Clean up stalled jobs
        $stalled = $model->cleanupStalledJobs();
        if ($stalled) {
            $output->writeln(sprintf('Cleaned up <error>%d</error> stalled jobs', $stalled));
        }

        // Remove finished jobs
        $cleaned = $model->cleanupQueue(true);
        if ($cleaned) {
            $output->writeln(sprintf('Cleaned up <info>%d</info> old entries from the queue', $cleaned));
        }

        $output->writeln('<info>Done</info>');
    }
}
