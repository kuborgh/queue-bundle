<?php
/*
 * (c) Kuborgh GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Kuborgh\QueueBundle\Command;

use Kuborgh\QueueBundle\Database\QueueDatabase;
use Kuborgh\QueueBundle\Model\QueueModel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

/**
 * Abstraction for all commands
 */
abstract class AbstractCommand extends ContainerAwareCommand
{
    /**
     * Get queue model
     *
     * @deprecated use queue database instead
     *
     * @return QueueModel
     */
    protected function getQueueModel()
    {
        return $this->getContainer()->get('kuborgh_queue.model');
    }

    /**
     * Do some garbage collection
     */
    protected function garbageCollect()
    {
        $queueDb = $this->getQueueDatabase();
        $logger = $this->getLogger();

        // Remove finished jobs
        if ($this->getContainer()->getParameter('kuborgh_queue.auto_cleanup')) {
            $cleaned = $queueDb->cleanupQueue();
            if ($cleaned) {
                $logger->info(sprintf('Cleaned up <info>%d</info> old entries from the queue', $cleaned));
            }
        }
    }

    /**
     * Get logger
     *
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        return $this->getContainer()->get('monolog.logger.queue');
    }

    /**
     * Get Queue Database abstraction
     *
     * @return QueueDatabase
     */
    protected function getQueueDatabase()
    {
        return $this->getContainer()->get('kuborgh_queue.database');
    }
}
