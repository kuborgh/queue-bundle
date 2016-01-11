<?php
/*
 * (c) Kuborgh GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Kuborgh\QueueBundle\Database;

use Kuborgh\QueueBundle\Entity\JobEntity;
use Kuborgh\QueueBundle\Model\QueueModel;
use Kuborgh\QueueBundle\Traits\ParameterTrait;

/**
 * This is a slim database abstraction, helping to avoid doctrine.
 */
class QueueDatabase
{
    use ParameterTrait;

    /**
     * Database connection
     *
     * @var \PDO
     */
    protected $pdo;

    /**
     * Get number of currently as "running" marked jobs
     *
     * @return int
     */
    public function getNumRuning()
    {
        return $this->getNumStatus(array(QueueModel::STATUS_RUNNING, QueueModel::STATUS_STARTING));
    }

    /**
     * Number of entries in queue with status "waiting"
     *
     * @return int
     */
    public function getNumQueued()
    {
        return $this->getNumStatus(QueueModel::STATUS_WAITING);
    }

    /**
     * Fetch job, that is next to be processed
     *
     * @return JobEntity|boolean false, when nothing is found
     */
    public function getNextWaitingJob()
    {
        // Execute statement
        $query = 'SELECT * FROM queue_jobs WHERE status = :status ORDER BY priority DESC, insertTime ASC LIMIT 1';
        $stmt = $this->getPdo()->prepare($query);
        $stmt->bindValue(':status', QueueModel::STATUS_WAITING, \PDO::PARAM_STR);
        $stmt->execute();
        $entity = $stmt->fetchObject('Kuborgh\QueueBundle\Entity\JobEntity');

        return $entity;
    }

    /**
     * Fetch job ids of jobs that are currently starting
     *
     * @return int[]
     */
    public function getStartingJobsIds()
    {
        $ids = array();
        // Execute statement
        $query = 'SELECT id FROM queue_jobs WHERE status = :status';
        $stmt = $this->getPdo()->prepare($query);
        $stmt->bindValue(':status', QueueModel::STATUS_STARTING, \PDO::PARAM_STR);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
        }

        return $ids;
    }

    /**
     * Fetch jobs that are currently running
     *
     * @return JobEntity[]
     */
    public function getRunningJobs()
    {
        $entites = array();

        // Execute statement
        $query = 'SELECT id FROM queue_jobs WHERE status = :status';
        $stmt = $this->getPdo()->prepare($query);
        $stmt->bindValue(':status', QueueModel::STATUS_RUNNING, \PDO::PARAM_STR);
        $stmt->execute();

        // Fetch/convert objects
        while (false !== ($entity = $stmt->fetchObject('Kuborgh\QueueBundle\Entity\JobEntity'))) {
            $entites[] = $entity;
        }

        return $entites;
    }

    /**
     * Load Job
     *
     * @param int $jobId
     *
     * @return JobEntity
     * @throws \Exception
     */
    public function getJob($jobId)
    {
        // Execute statement
        $query = 'SELECT * FROM queue_jobs WHERE id = :id LIMIT 1';
        $stmt = $this->getPdo()->prepare($query);
        $stmt->bindValue(':id', $jobId);
        $stmt->execute();
        $entity = $stmt->fetchObject('Kuborgh\QueueBundle\Entity\JobEntity');
        if ($entity === false) {
            $error = $stmt->errorInfo();
            throw new \Exception('Error fetching job: '.$error[2]);
        }

        return $entity;
    }

    /**
     * @param int $jobId
     *
     * @return string status
     */
    public function getStatus($jobId)
    {
        $entity = $this->getJob($jobId);

        return $entity->getStatus();
    }

    /**
     * Mark the given job as "starting"
     *
     * @param int $jobId
     *
     * @throws \Exception
     */
    public function markJobStarting($jobId)
    {
        $this->markJobStatus($jobId, QueueModel::STATUS_STARTING);
    }

    /**
     * Mark the given job as "running"
     *
     * @param int $jobId
     * @param int $pid
     *
     * @throws \Exception
     */
    public function markJobRunning($jobId, $pid)
    {
        $additional = array(
            'pid'       => $pid,
            'startTime' => $this->getCurrentTime(),
        );
        $this->markJobStatus($jobId, QueueModel::STATUS_RUNNING, $additional);
    }

    /**
     * Mark the given job as "start_failed"
     *
     * @param int $jobId
     *
     * @throws \Exception
     */
    public function markJobStartingFailed($jobId)
    {
        $additional = array(
            'endTime' => $this->getCurrentTime(),
        );
        $this->markJobStatus($jobId, QueueModel::STATUS_START_FAILED, $additional);
    }

    /**
     * Mark the given job as "failed"
     *
     * @param int $jobId
     *
     * @throws \Exception
     */
    public function markJobFailed($jobId)
    {
        $additional = array(
            'endTime' => $this->getCurrentTime(),
            'pid'     => 'null',
        );
        $this->markJobStatus($jobId, QueueModel::STATUS_FAILED, $additional);
    }

    /**
     * Mark the given job as "done"
     *
     * @param int $jobId
     *
     * @throws \Exception
     */
    public function markJobDone($jobId)
    {
        $additional = array(
            'endTime' => $this->getCurrentTime(),
            'pid'     => 'null',
        );
        $this->markJobStatus($jobId, QueueModel::STATUS_DONE, $additional);
    }

    /**
     * Mark the given job as "waiting" again
     *
     * @param int $jobId
     *
     * @throws \Exception
     */
    public function markJobWaiting($jobId)
    {
        $this->markJobStatus($jobId, QueueModel::STATUS_WAITING);
    }

    /**
     * Mark the given job as "stalled"
     *
     * @param int $jobId
     *
     * @throws \Exception
     */
    public function markJobStalled($jobId)
    {
        $additional = array(
            'endTime' => $this->getCurrentTime(),
        );
        $this->markJobStatus($jobId, QueueModel::STATUS_STALLED, $additional);
    }

    /**
     * Cleanup finished entries from the queue, that are older than 3 days
     *
     * @return int number of removed jobs
     */
    public function cleanupQueue()
    {
        // Keep entries 1w back
        $time = new \DateTime();
        $time->sub(new \DateInterval('P7D'));

        $query = 'DELETE FROM queue_jobs WHERE status = :status AND startTime < :time';
        $stmt = $this->getPdo()->prepare($query);
        $stmt->bindValue(':status', QueueModel::STATUS_DONE, \PDO::PARAM_STR);
        $stmt->bindValue(':time', $time->format('Y-m-d H:i:s'));
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Get database connection
     *
     * @return \PDO
     */
    protected function getPdo()
    {
        if (!is_null($this->pdo)) {
            return $this->pdo;
        }
        $host = $this->getParameter('database_host');
        $db = $this->getParameter('database_name');
        $user = $this->getParameter('database_user');
        $pass = $this->getParameter('database_password');
        $dsn = sprintf('mysql:host=%s;dbname=%s', $host, $db);
        $this->pdo = new \Pdo($dsn, $user, $pass);

        return $this->pdo;
    }

    /**
     * Get number of jobs in given status
     *
     * @param array|string $status
     *
     * @return int
     * @throws \Exception
     */
    protected function getNumStatus($status)
    {
        if (is_array($status)) {
            $statusString = implode('","', $status);
            $query = sprintf('SELECT count(id) FROM queue_jobs WHERE status IN ("%s")', $statusString);
        } else {
            $query = sprintf('SELECT count(id) FROM queue_jobs WHERE status = "%s"', $status);
        }

        // Execute statement
        $stmt = $this->getPdo()->prepare($query);
        $stmt->execute();
        $num = $stmt->fetchColumn(0);
        if ($num === false) {
            $error = $stmt->errorInfo();
            throw new \Exception(sprintf('Error fetching num status %s: %s', $status, $error[2]));
        }

        return (int) $num;
    }

    /**
     * Mark the given job with the given status
     *
     * @param int    $jobId      Job Id
     * @param string $status     New Status
     * @param array  $additional Additional updates
     *
     * @throws \Exception
     */
    protected function markJobStatus($jobId, $status, $additional = array())
    {
        $updateStr = 'status = :status';
        foreach ($additional as $key => $val) {
            $updateStr .= sprintf(', %s = %s', $key, $val);
        }

        $query = sprintf('UPDATE queue_jobs SET %s WHERE id = :id', $updateStr);
        $stmt = $this->getPdo()->prepare($query);
        $stmt->bindValue(':status', $status, \PDO::PARAM_STR);
        $stmt->bindValue(':id', $jobId);
        $stmt->execute();
        if (!$stmt->rowCount()) {
            $error = $stmt->errorInfo();
            throw new \Exception('Error updating queue table: '.$error[2]);
        }
    }

    /**
     * Get current date in sql format
     *
     * @return string
     */
    protected function getCurrentTime()
    {
        $datetime = new \DateTime();

        return sprintf('"%s"', $datetime->format('Y-m-d H:i:s'));
    }
}
