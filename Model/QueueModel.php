<?php
/*
 * (c) Kuborgh GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Kuborgh\QueueBundle\Model;

use Kuborgh\QueueBundle\Entity\JobEntity;
use Kuborgh\QueueBundle\Traits\ConcurrencyTrait;
use Kuborgh\QueueBundle\Traits\DoctrineTrait;
use Monolog\Logger;
use Symfony\Component\Process\Process;

/**
 * Model to handle queue related stuff
 */
class QueueModel
{
    // Priorities
    const PRIORITY_LOWEST = 1;
    const PRIORITY_LOW = 2;
    const PRIORITY_MEDIUM = 3;
    const PRIORITY_HIGH = 4;
    const PRIORITY_HIGHEST = 5;
    // Entry is waiting for being processed
    const STATUS_WAITING = 'WAITING';
    // Run job has been spawned. Will instantly be switching to running
    const STATUS_STARTING = 'STARTING';
    // Job is currently running. PID is set
    const STATUS_RUNNING = 'RUNNING';
    // Job exited with return code 0
    const STATUS_DONE = 'DONE';
    // Job exited with return code != 0
    const STATUS_FAILED = 'FAILED';
    // Starting of the job failed
    const STATUS_START_FAILED = 'START_FAILED';
    // Job was removed, as the pid didn't exist
    const STATUS_STALLED = 'STALLED';
    /**
     * Entity name
     */
    const ENTITY = 'KuborghQueueBundle:JobEntity';
    /**
     * Injection of doctrine registry
     */
    use DoctrineTrait;

    /**
     * Injection of concurrency parameter
     */
    use ConcurrencyTrait;

    /**
     * Path to console command
     *
     * @var String
     */
    protected $consolePath;

    /**
     * Logger
     *
     * @var Logger
     */
    protected $logger;

    /**
     * Add command to the queue
     *
     * @param String $command  Command name
     * @param Int    $priority Priority
     *
     * @throws \Exception
     * @return Int id of the command in the queue
     */
    public function addCommand($command, $priority = null)
    {
        // Entity Manager
        $enMan = $this->getEntityManager();

        // Set priority
        if (is_null($priority)) {
            $priority = self::PRIORITY_MEDIUM;
        }
        if (!is_numeric($priority) || $priority > 5 || $priority < 1) {
            throw new \Exception('Priority must be between 1-5');
        }

        // Check for duplicates
        $job = $this->findQueuedJobByCommand($command);

        // Job already exists
        if ($job instanceof JobEntity) {
            // No changes in priority -> nothing to do
            if ($job->getPriority() == $priority) {
                return $job->getId();
            }

            // Update priority
            $job->setPriority($priority);
        } else {
            // No job found -> create a new one
            $job = new JobEntity();
            $job->setCommand($command);
            $job->setPriority($priority);
            $job->setStatus(self::STATUS_WAITING);
        }

        // Save job
        $enMan->persist($job);
        $enMan->flush();
        $enMan->clear();

        return $job->getId();
    }

    /**
     * Load job
     *
     * @param Int $jobId Job ID
     *
     * @throws \Exception
     * @return JobEntity
     */
    public function getJobById($jobId)
    {
        $enMan = $this->getEntityManager();
        $repo = $enMan->getRepository(self::ENTITY);

        $job = $repo->findOneBy(array('id' => $jobId, 'status' => self::STATUS_WAITING));

        if (!$job instanceof JobEntity) {
            throw new \Exception(sprintf('No waiting job with id %d found', $jobId));
        }

        return $job;
    }

    /**
     * Size of the queue
     *
     * @return int
     */
    public function getQueueLength()
    {
        $jobs = $this->getQueuedJobs();

        return count($jobs);
    }

    /**
     * Position of the entry inside the queue. 1 means it is the next one being processed
     *
     * @param Int $jobId Job Id
     *
     * @throws \Exception
     * @return int
     */
    public function getQueuePosition($jobId)
    {
        $jobs = $this->getQueuedJobs();
        $num = 1;
        foreach ($jobs as $job) {
            if ($job->getId() == $jobId) {
                return $num;
            }
            $num++;
        }

        throw new \Exception(sprintf('Job with ID %d is not in queue', $jobId));
    }

    /**
     * Get all jobs, that are in the queue, ordered by priority
     *
     * @param int $limit optionally limit the query
     *
     * @return JobEntity[]
     */
    public function getQueuedJobs($limit = 0)
    {
        $enMan = $this->getEntityManager();

        // Build query
        $builder = $enMan->getRepository(self::ENTITY)->createQueryBuilder('j');
        $builder->where($builder->expr()->in('j.status', ':status'));
        $builder->setParameter('status', array(self::STATUS_WAITING));
        $builder->orderBy('j.priority DESC, j.insertTime');

        if ($limit) {
            $builder->setMaxResults($limit);
        }

        // Fetch results
        /** @var $results JobEntity[] */
        $results = $builder->getQuery()->getResult();

        return $results;
    }

    /**
     * Get all jobs, that are in the queue, ordered by priority
     *
     * @return JobEntity[]
     */
    public function getRunningJobs()
    {
        $enMan = $this->getEntityManager();

        // Build query
        $builder = $enMan->getRepository(self::ENTITY)->createQueryBuilder('j');
        $builder->where($builder->expr()->in('j.status', ':status'));
        $builder->setParameter('status', array(self::STATUS_RUNNING));
        $builder->orderBy('j.startTime');

        // Fetch results
        /** @var $results JobEntity[] */
        $results = $builder->getQuery()->getResult();

        return $results;
    }

    /**
     * Try to load a job with a given command (to check for duplicates)
     *
     * @param String $command Command
     *
     * @return JobEntity
     */
    public function findQueuedJobByCommand($command)
    {
        $enMan = $this->getEntityManager();
        $repo = $enMan->getRepository(self::ENTITY);

        return $repo->findOneBy(array('command' => $command, 'status' => self::STATUS_WAITING));
    }

    /**
     * Find jobs that are marked running but do not exist as process
     *
     * @return int number of stalled jobs, that were marked failed
     */
    public function cleanupStalledJobs()
    {
        $stalled = 0;

        // Get a list of tasks with PIDs
        $enMan = $this->getEntityManager();
        $repo = $enMan->getRepository(self::ENTITY);
        $queryBuilder = $repo->createQueryBuilder('j');
        $queryBuilder->where($queryBuilder->expr()->isNotNull('j.pid'));
        /** @var JobEntity[] $runningJobs */
        $runningJobs = $queryBuilder->getQuery()->getResult();

        // Check each job
        foreach ($runningJobs as $job) {
            // Job still running
            if ($this->isPidRunning($job->getPid())) {
                continue;
            }

            // Mark failed
            $job->setStatus(self::STATUS_STALLED);
            $job->setPid(null);
            $enMan->persist($job);
            $enMan->flush();
            $stalled++;
        }

        return $stalled;
    }

    /**
     * Mark the given job as running
     *
     * @param JobEntity $job Job object
     * @param Int       $pid PID of the job
     */
    public function markJobRunning($job, $pid)
    {
        $job->setPid($pid);
        $job->setStatus(self::STATUS_RUNNING);
        $job->setStartTime(new \DateTime());

        $enMan = $this->getEntityManager();
        $enMan->persist($job);
        $enMan->flush();
    }

    /**
     * Mark the given job as done
     *
     * @param JobEntity $job Job object
     */
    public function markJobDone($job)
    {
        $job->setPid(null);
        $job->setStatus(self::STATUS_DONE);
        $job->setEndTime(new \DateTime());

        $enMan = $this->getEntityManager();
        $enMan->persist($job);
        $enMan->flush();
    }

    /**
     * Sending job back to the queue
     *
     * @param JobEntity $job Job object
     */
    public function markJobWaiting($job)
    {
        $job->setPid(null);
        $job->setStatus(self::STATUS_WAITING);
        $job->setStartTime(null);

        $enMan = $this->getEntityManager();
        $enMan->persist($job);
        $enMan->flush();
    }

    /**
     * Mark job as failed
     *
     * @param JobEntity $job Job object
     */
    public function markJobFailed($job)
    {
        $job->setPid(null);
        $job->setStatus(self::STATUS_FAILED);
        $job->setEndTime(new \DateTime());

        $enMan = $this->getEntityManager();
        $enMan->persist($job);
        $enMan->flush();
    }

    /**
     * Run the job
     *
     * @param JobEntity $job Job
     *
     * @return int return code
     */
    public function run(JobEntity $job)
    {
        // Mark job running
        $this->markJobRunning($job, getmypid());

        // Last check, that we did not exceed the concurrency limit
        $running = $this->getRunningJobs();
        $maxRunning = $this->getConcurrency();
        if (count($running) > $maxRunning) {
            // Rollback
            $this->markJobWaiting($job);

            return -1;
        }

        // Execute
        $cmd = sprintf('php %s %s', $this->consolePath, $job->getCommand());

        $process = new Process($cmd);

        try {
            $process->setTimeout(null);
            $process->start(function () {
            });
        } catch (\Exception $e) {
            // @rfe log more info
            $msg = sprintf('Job %s (ID %d) failed with exception: %s', $job->getCommand(), $job->getId(), $e->getMessage());
            $this->getLogger()->err($msg);
            $this->markJobFailed($job);

            return -1;
        }

        // log output
        $this->getLogger()->info(sprintf('Process output (%d): %s', $process->getPid(), $process->getOutput()));

        $exitCode = $process->getExitCode();

        // Mark as done
        if (!$exitCode) {
            $this->markJobDone($job);
        } else {
            $this->markJobFailed($job);
        }

        return $process->getExitCode();
    }

    /**
     * Cleanup finished entries from the queue, that are older than 3 days
     *
     * @param bool $failedJobs When true, remove also failed jobs
     *
     * @return int number of removed jobs
     */
    public function cleanupQueue($failedJobs = false)
    {
        $time = new \DateTime();
        $time->sub(new \DateInterval('P3D'));

        // Get a list of old jobs
        $enMan = $this->getEntityManager();
        $repo = $enMan->getRepository(self::ENTITY);
        $queryBuilder = $repo->createQueryBuilder('j');
        $queryBuilder->where($queryBuilder->expr()->in('j.status', ':status'));
        $queryBuilder->andWhere($queryBuilder->expr()->lt('j.startTime', ':oldDate'));
        $queryBuilder->setParameter('oldDate', $time->format('Y-m-d H:i:s'));

        $status = array(self::STATUS_DONE);
        if ($failedJobs) {
            $status[] = self::STATUS_FAILED;
            $status[] = self::STATUS_STALLED;
        }
        $queryBuilder->setParameter('status', $status);

        // Remove jobs
        /** @var JobEntity[] $jobs */
        $jobs = $queryBuilder->getQuery()->getResult();
        foreach ($jobs as $job) {
            $enMan->remove($job);
        }
        $enMan->flush();

        return count($jobs);
    }

    /**
     * Clear (truncate) the whole queue (except the running ones)
     *
     * @return int number of entries cleared
     */
    public function clearQueue()
    {
        // Get a list of non-running jobs
        $enMan = $this->getEntityManager();
        $repo = $enMan->getRepository(self::ENTITY);
        $queryBuilder = $repo->createQueryBuilder('j');
        $queryBuilder->where($queryBuilder->expr()->notIn('j.status', ':status'));
        $queryBuilder->setParameter('status', self::STATUS_RUNNING);

        // Remove jobs
        /** @var JobEntity[] $jobs */
        $jobs = $queryBuilder->getQuery()->getResult();
        foreach ($jobs as $job) {
            $enMan->remove($job);
        }
        $enMan->flush();

        return count($jobs);
    }

    /**
     * Remove a job from the queue
     *
     * @param JobEntity $job Job
     *
     * @return Boolean true on success
     */
    public function removeJob(JobEntity $job)
    {
        $enMan = $this->getEntityManager();
        $enMan->remove($job);
        $enMan->flush();

        return true;
    }

    /**
     * Inject path to console
     *
     * @param String $consolePath
     */
    public function setConsolePath($consolePath)
    {
        $this->consolePath = $consolePath;
    }

    /**
     * @param \Monolog\Logger $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return \Monolog\Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Check if the given pid is currently running on this machine
     *
     * @param Int $pid PID
     *
     * @return Boolean
     */
    protected function isPidRunning($pid)
    {
        // Get list of running PIDs
        $pids = explode("\n", trim(`ps -e | grep php | awk '{print $1}'`));

        // Note: "kill -0" seems more simple, but may have the pittfall, that jobs running in other context return false.

        return in_array($pid, $pids);
    }
}
