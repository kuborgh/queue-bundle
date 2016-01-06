<?php
/*
 * (c) Kuborgh GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Kuborgh\QueueBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Job inside the queue
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 *
 * @ORM\Table(name="queue_jobs")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks
 */
class JobEntity
{
    /**
     * Id
     *
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Command
     *
     * @var string
     *
     * @ORM\Column(name="command", type="text")
     */
    private $command;

    /**
     * PID (when running)
     *
     * @var int
     *
     * @ORM\Column(name="pid", type="integer", nullable=true)
     */
    private $pid;

    /**
     * PID (when running)
     *
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=255)
     */
    private $status;

    /**
     * Date, when the job was added to the queue
     *
     * @var \DateTime
     *
     * @ORM\Column(name="insertTime", type="datetime")
     */
    private $insertTime;

    /**
     * Priority
     *
     * @var int
     *
     * @ORM\Column(name="priority", type="smallint")
     */
    private $priority;

    /**
     * Date, when the job was started
     *
     * @var \DateTime
     *
     * @ORM\Column(name="startTime", type="datetime", nullable=true)
     */
    private $startTime;

    /**
     * Date, when the job was detected as ended (may be much later as it really finished!)
     *
     * @var \DateTime
     *
     * @ORM\Column(name="endTime", type="datetime", nullable=true)
     */
    private $endTime;

    /**
     * Set Id
     *
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Get Id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * setCommand
     *
     * @param string $command
     */
    public function setCommand($command)
    {
        $this->command = $command;
    }

    /**
     * getCommand
     *
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * setEndTime
     *
     * @param \DateTime $endTime
     */
    public function setEndTime($endTime)
    {
        $this->endTime = $endTime;
    }

    /**
     * getEndTime
     *
     * @return \DateTime
     */
    public function getEndTime()
    {
        return $this->endTime;
    }

    /**
     * setInsertTime
     *
     * @param \DateTime $insertTime
     */
    public function setInsertTime($insertTime)
    {
        $this->insertTime = $insertTime;
    }

    /**
     * getInsertTime
     *
     * @return \DateTime
     */
    public function getInsertTime()
    {
        return $this->insertTime;
    }

    /**
     * setPid
     *
     * @param int $pid
     */
    public function setPid($pid)
    {
        $this->pid = $pid;
    }

    /**
     * getPid
     *
     * @return int
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * setPriority
     *
     * @param int $priority
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;
    }

    /**
     * getPriority
     *
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * getPriority
     *
     * @return string
     */
    public function getPriorityName()
    {
        switch ($this->getPriority()) {
            case 1:
                return 'LOWEST';
            case 2:
                return 'LOW';
            case 3:
                return 'NORMAL';
            case 4:
                return 'HIGH';
            case 5:
                return 'HIGHEST';
            default:
                return 'ERROR';
        }
    }

    /**
     * setStartTime
     *
     * @param \DateTime $startTime
     */
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;
    }

    /**
     * getStartTime
     *
     * @return \DateTime
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * setStatus
     *
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * getStatus
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Lifecycle callback to set the insertTime to the current date, when persisting
     *
     * @ORM\PrePersist
     */
    public function setCurrentDate()
    {
        $this->insertTime = new \DateTime();
    }
}
