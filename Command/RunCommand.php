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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Runs a single command from the queue.
 * This one will be called from the queue:runner command to be run in background. So the job itself can be run in
 * foreground, being monitored all the time from this worker.
 */
class RunCommand extends AbstractCommand
{
    const ARG_ID = 'id';

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('queue:run');
        $this->setDescription('Runs a single job from the queue.');
        $this->addArgument(self::ARG_ID, InputArgument::REQUIRED, 'Id of the queue entry');
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
        $queueDb = $this->getQueueDatabase();
        $logger = $this->getLogger();

        // Load job
        $jobId = $input->getArgument(self::ARG_ID);
        $job = $queueDb->getJob($jobId);
        $jobCmd = $job->getCommand();

        if (!in_array($job->getStatus(), array(QueueModel::STATUS_WAITING, QueueModel::STATUS_STARTING))) {
            $logContext = array(
                'ID'     => $jobId,
                'status' => $job->getStatus(),
                'cmd'    => $job->getCommand(),
            );
            $logger->warning('Running job, that already ran', $logContext);
        }

        // Mark job running
        $queueDb->markJobRunning($jobId, getmypid());

        // Log status
        $logger->notice(sprintf('Started Job "%s" (ID %d)', $jobCmd, $jobId));

        // Send a short signal to the parent, so main worker can push us into the background and continue looking for free slots.
        $output->writeln('Marked job running');

        // Prepare command to run
        $consolePath = $this->getContainer()->getParameter('kuborgh_queue.console.path');
        $cmd = sprintf('php %s %s', $consolePath, $jobCmd);

        $process = new Process($cmd);
        try {
            $process->setTimeout(null);
            $process->run();
        } catch (\Exception $exc) {
            // The command failed. Log full output
            $msg = sprintf("Job \"%s\" (ID %d) failed with exception: %s\n%s\n%s", $jobCmd, $jobId, $exc->getMessage(), $process->getErrorOutput(), $process->getOutput());
            $this->getLogger()->error($msg);
            $queueDb->markJobFailed($jobId);

            return -1;
        }
        $logger->notice(sprintf('Job Ended "%s" (ID %d)', $jobCmd, $jobId));

        // Mark job according to the exit code
        $exitCode = $process->getExitCode();
        if (!$exitCode) {
            $logger->notice(sprintf('Finished Job "%s" (ID %d)', $jobCmd, $jobId));
            // log output
            $ctx = array('Output' => $process->getOutput());
            $logger->debug(sprintf('Output of Job "%s" (ID %d)', $jobCmd, $jobId), $ctx);
            $queueDb->markJobDone($jobId);
        } else {
            // log output + error output
            $ctx = array('Output' => $process->getOutput(), 'Error' => $process->getErrorOutput());
            $logger->warning(sprintf('Job Failed "%s" (ID %d)', $jobCmd, $jobId), $ctx);
            $queueDb->markJobFailed($jobId);
        }

        return $exitCode;
    }
}
