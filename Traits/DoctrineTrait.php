<?php
/*
 * (c) Kuborgh GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Kuborgh\QueueBundle\Traits;

use Doctrine\Bundle\DoctrineBundle\Registry;

/**
 * This trait helps injecting the doctrine entity manager for database operations
 */
trait DoctrineTrait
{
    /**
     * Instance of doctrine, to get entity manager and repository from
     *
     * @var Registry
     */
    protected $doctrine;

    /**
     * Retrieve the entity manager
     *
     * @param Registry $doctrine
     */
    public function setDoctrineRegistry($doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * Get doctrine registry
     *
     * @return Registry
     * @throws \Exception
     */
    protected function getDoctrineRegistry()
    {
        if (!$this->doctrine instanceof Registry) {
            throw new \Exception('Doctrine registry not set');
        }

        return $this->doctrine;
    }

    /**
     * Retrieve the entity manager
     *
     * @return \Doctrine\ORM\EntityManager
     */
    protected function getEntityManager()
    {
        return $this->getDoctrineRegistry()->getManager();
    }
}
