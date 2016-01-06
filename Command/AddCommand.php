<?php
/*
 * (c) Kuborgh GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Kuborgh\QueueBundle\Command;

use Kuborgh\QueueBundle\Model\QueueModel;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Add entry to the queue
 */
class AddCommand extends AbstractCommand
{
    const ARG_CMD = 'job_command';
    const OPT_PRIO = 'priority';

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('queue:add');
        $this->setDescription('Add entry to the queue');
        $this->addArgument(self::ARG_CMD, InputArgument::REQUIRED, 'console command to queue (e.g. "queue:list").');
        $this->addOption(self::OPT_PRIO, null, InputOption::VALUE_REQUIRED, 'priority (1-5) where 5 is the highest. Default is 3');
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
        $command = $input->getArgument(self::ARG_CMD);

        // Get priority
        $priority = $input->getOption(self::OPT_PRIO);

        // Add it to the queue
        $model = $this->getQueueModel();
        $jobId = $model->addCommand($command, $priority);

        $output->writeln(sprintf('Added "%s" to queue with Job Id %d.', $command, $jobId));

        $queueLength = $model->getQueueLength();
        $output->writeln(sprintf('There are <info>%d</info> commands in queue.', $queueLength));

        $pos = $model->getQueuePosition($jobId);
        $output->writeln(sprintf('Your command is at position <info>%d</info>', $pos));
    }
}
