<?php
/*
 * (c) Kuborgh GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Kuborgh\QueueBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Clear the whole queue!
 */
class ClearCommand extends AbstractCommand
{
    const OPT_FORCE = 'force';

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('queue:clear');
        $this->setDescription('Clear the whole queue');
        $this->addOption(self::OPT_FORCE, '', InputOption::VALUE_NONE, 'Must be set to realy clear the queue');
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

        if (!$input->getOption(self::OPT_FORCE)) {
            $output->writeln(sprintf('<error>Parameter --%s not set</error>', self::OPT_FORCE));

            return -1;
        }

        $output->writeln('Clearing queue');
        // Wait some time to allow the user abort the command
        sleep(5);
        $num = $this->getQueueModel()->clearQueue();
        $output->writeln(sprintf('Cleared <info>%d</info> entries', $num));
    }
}
