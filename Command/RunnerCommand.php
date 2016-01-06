<?php
/*
 * (c) Kuborgh GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Kuborgh\QueueBundle\Command;

use Kuborgh\QueueBundle\Model\QueueModel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Main queue runner
 */
class RunnerCommand extends AbstractCommand
{
    /**
     * Run parameter for main loop.
     *
     * @var boolean
     */
    protected $running = true;

    /**
     * List of children to check against zombie processes
     *
     * @var Process[]
     */
    protected $children = array();

    /**
     * Number of concurrent jobs (from parameters)
     *
     * @var int
     */
    protected $concurrency;

    /**
     * Handle signals by stopping the infinite loop
     *
     * @param int $sigNo Signal number
     */
    public function sigHandler($sigNo)
    {
        $logger = $this->getLogger();
        $logger->notice(sprintf('Queue Runner caught Signal #%d - Stopping', $sigNo));
        $this->running = false;
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('queue:runner');
        $this->setDescription('This is the main queue-runner. It should be triggered by cron to recover after a crash.');
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
        $logger = $this->getLogger();

        // Check if another queue-runner is already running.
        if (false !== ($pid = $this->isRunning())) {
            $logger->debug(sprintf('Queue Runner still running (PID %d)', $pid));

            return 0;
        }
        $logger->notice(sprintf('Queue Runner started (PID %d)', getmypid()));

        // Get concurrency
        $this->concurrency = $this->getContainer()->getParameter('kuborgh_queue.concurrency');

        // Initial cleanup of stalled jobs
        $this->cleanStalledJobs();

        // Initial cleanup of jobs stalled at starting
        $this->cleanStalledStartingJobs();

        // Catch Kill signals
        declare(ticks = 100);
        pcntl_signal(SIGINT, array($this, 'sigHandler'));
        pcntl_signal(SIGTERM, array($this, 'sigHandler'));

        do {
            // Check queue
            $abort = $this->checkQueue();
        } while ($this->running && !$abort);

        $logger->notice(sprintf('Queue Runner terminated (PID %d)', getmypid()));

        return 0;
    }

    /**
     * Check the queue and run jobs or pause
     *
     * @return boolean true, when aborted by signal (sleep returned > 0)
     */
    protected function checkQueue()
    {
        $queueDb = $this->getQueueDatabase();
        $logger = $this->getLogger();

        // Garbage collect once in a while
        if (rand(1, 1000) == 1) {
            $this->garbageCollect();
        }

        // Are there jobs running?
        $numRunning = $queueDb->getNumRuning();

        // Check status of the running children
        $this->checkChildren();
        $numChildren = count($this->children);

        // Is one of them probably stalled?
        if ($numRunning > $numChildren) {
            $logger->notice(sprintf('Number of running jobs differs from number of children: %d vs. %d', $numRunning, $numChildren));

            // Clean stalled jobs
            $numRunning -= $this->cleanStalledJobs();

            // Still some more marke running? Starting must have failed
            if ($numRunning > $numChildren) {
                // Clean ALL stalled "starting" jobs
                $this->cleanStalledStartingJobs();
            }

            $timeLeft = sleep(1);

            return $timeLeft > 0;
        }

        // Check if free slots are available
        if ($numRunning >= $this->concurrency) {
            // Queue is at the limit. Try again soon
            $logger->debug(sprintf('Queue is a the limit: %d/%d', $numRunning, $this->concurrency));
            $timeLeft = sleep(1);

            return $timeLeft > 0;
        }

        // Check if queue has a job
        $numQueued = $queueDb->getNumQueued();
        if (!$numQueued) {
            // Nothing in the queue. Sit back and relax for a while
            $logger->debug(sprintf('Queue is empty: 0/%d', $this->concurrency));
            $timeLeft = sleep(10);

            return $timeLeft > 0;
        }

        // We've got work. Let's do it (in background)
        $this->runJob();
        $timeLeft = sleep(1);

        return $timeLeft > 0;
    }

    /**
     * Prevent zombie processes, by fetching the return code of all exited children
     */
    protected function checkChildren()
    {
        $queueDb = $this->getQueueDatabase();

        // Check status of child processes an fetch exit code
        $runningChildren = array();
        foreach ($this->children as $jobId => $child) {
            if ($child->isRunning()) {
                // Still running -> keep it in the list
                $runningChildren[$jobId] = $child;
            } else {
                // Check status of the job.
                $status = $queueDb->getStatus($jobId);
                if ($status == QueueModel::STATUS_STARTING) {
                    // Job aborted while starting -> ouch
                    $logger = $this->getLogger();
                    $logger->error(sprintf('Queue Runner found failed starting Job: JobId %d. Will be marked as stalled', $jobId));
                    $queueDb->markJobStartingFailed($jobId);
                }
            }
        }
        $this->children = $runningChildren;
    }

    /**
     * Pick a job and run it as child process
     */
    protected function runJob()
    {
        $logger = $this->getLogger();
        $queueDb = $this->getQueueDatabase();
        $job = $queueDb->getNextWaitingJob();
        if ($job === false) {
            $logger->notice('Tried to start a job, but queue did not contain any');

            return;
        }
        $jobId = $job->getId();

        // Prepare command to run
        $consolePath = $this->getContainer()->getParameter('kuborgh_queue.console.path');
        $cmd = sprintf('php %s queue:run %d', $consolePath, $jobId);

        $logger->debug(sprintf('Queue Runner trying to start %s', $cmd));
        $queueDb->markJobStarting($jobId);

        // Prepare background process
        $process = new Process($cmd);

        // Remember which child has which job Id
        $this->children[$jobId] = $process;

        // Start job in background
        try {
            $process->start();
            $process->setTimeout(null);
        } catch (\Exception $exc) {
            // The command failed.
            $logger->error(sprintf('Queue Runner error starting job runner %s: %s', $cmd, $exc->getMessage()));
        }
    }

    /**
     * Check if current script is already running
     *
     * @return bool|int PID when running, false when not.
     */
    protected function isRunning()
    {
        // Fetch all similar command of the same user
        $username = trim(shell_exec('whoami'));
        $processes = trim(shell_exec(sprintf('ps -u %s -o pid,cmd | grep  -e "[0-9]\+ php .* queue:runner"', $username)));
        $cmds = explode("\n", $processes);

        // Only one runs (this one) => perfect
        if (count($cmds) <= 1) {
            return false;
        }

        // Check if command inside the same folder (with pid other than ours) is already running.
        $mypid = getmypid();
        $mycwd = $this->getContainer()->getParameter('kernel.root_dir');
        foreach ($cmds as $cmd) {
            // Extract PID
            if (!preg_match('/^(\d*) php (.*) /', $cmd, $match)) {
                $this->getLogger()->error('Queue Runner running check - could not detect PID: '.$cmd);

                return -1;
            }
            $pid = $match[1];
            $consolePath = dirname($match[2]);

            // This is us
            if ($pid == $mypid) {
                continue;
            }

            // Check folder
            $pwdx = trim(shell_exec(sprintf('pwdx %d', $pid)));
            if (!preg_match('/^\d+: (.*)$/', $pwdx, $match)) {
                $this->getLogger()->error('Queue Runner running check - could not detect folder: '.$pwdx);
                continue;
            }
            $folder = $match[1].'/'.$consolePath;

            if ($folder == $mycwd) {
                return $pid;
            }
        }

        // Nothing found
        return false;
    }

    /**
     * Find and clean stalled jobs
     *
     * @return int Number of stalled jobs
     */
    protected function cleanStalledJobs()
    {
        $logger = $this->getLogger();
        $queueDb = $this->getQueueDatabase();

        // Clean up stalled jobs
        $stalled = 0;

        // Get a list of tasks with PIDs
        $runningJobs = $queueDb->getRunningJobs();

        // Check each job
        foreach ($runningJobs as $job) {
            // Job still running
            if ($this->isPidRunning($job->getPid())) {
                continue;
            }

            // Mark failed
            $queueDb->markJobStalled($job->getId());
            $stalled++;
        }

        if ($stalled) {
            $logger->warning(sprintf('Cleaned up <error>%d</error> stalled jobs', $stalled));
        }

        return $stalled;
    }

    /**
     * Find and clean stalled starting jobs
     *
     * @return int Number of stalled jobs
     */
    protected function cleanStalledStartingJobs()
    {
        $stalled = 0;

        $queueDb = $this->getQueueDatabase();
        $startingJobIds = $queueDb->getStartingJobsIds();
        $logger = $this->getLogger();

        // Update each job
        foreach ($startingJobIds as $jobId) {
            $queueDb->markJobWaiting($jobId);
            $stalled++;
        }

        // Log
        if ($stalled) {
            $logger->warning(sprintf('Cleaned up <error>%d</error> stalled starting jobs', $stalled));
        }

        return $stalled;
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
