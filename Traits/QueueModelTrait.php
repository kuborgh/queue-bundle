<?php
/*
 * (c) Kuborgh GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Kuborgh\QueueBundle\Traits;

use Kuborgh\QueueBundle\Model\QueueModel;

/**
 * Trait to inject the queue model
 */
trait QueueModelTrait
{
    /**
     * Instance of queue model
     *
     * @var QueueModel
     */
    private $queueModel;

    /**
     * Set queue model
     *
     * @param QueueModel $queueModel
     */
    public function setQueueModel($queueModel)
    {
        $this->queueModel = $queueModel;
    }

    /**
     * Get queue model
     *
     * @return QueueModel
     * @throws \RuntimeException
     */
    protected function getQueueModel()
    {
        if (!$this->queueModel instanceof QueueModel) {
            throw new \RuntimeException('QueueModel not injected into'.get_class($this));
        }

        return $this->queueModel;
    }
}
