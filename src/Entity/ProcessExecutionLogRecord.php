<?php
namespace CleverAge\ProcessUiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(
 *  indexes={
 *      @ORM\Index(name="process_execution_log_message", columns={"message"})
 *  }
 * )
 */
class ProcessExecutionLogRecord
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue()
     */
    private ?int $id;

    /**
     * @ORM\Column(type="integer")
     */
    private int $logLevel;

    /**
     * @ORM\Column(type="string")
     */
    private string $message;

    /**
     * @ORM\ManyToOne(targetEntity="ProcessExecution", inversedBy="logRecords")
     * @ORM\JoinColumn(name="process_execution_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private ?ProcessExecution $processExecution;

    public function __construct(int $logLevel, string $message)
    {
        $this->logLevel = $logLevel;
        $this->message = $message;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLogLevel(): int
    {
        return $this->logLevel;
    }

    public function setLogLevel(int $logLevel): ProcessExecutionLogRecord
    {
        $this->logLevel = $logLevel;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): ProcessExecutionLogRecord
    {
        $this->message = $message;

        return $this;
    }

    public function setProcessExecution(ProcessExecution $processExecution): ProcessExecutionLogRecord
    {
        $this->processExecution = $processExecution;

        return $this;
    }

    public function getProcessExecution(): ?ProcessExecution
    {
        return $this->processExecution;
    }
}
