<?php

namespace CleverAge\ProcessUiBundle\Entity;

use CleverAge\ProcessUiBundle\Repository\ProcessExecutionRepository;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\PersistentCollection;

/**
 * @ORM\Entity(repositoryClass=ProcessExecutionRepository::class)
 */
class ProcessExecution
{
    public const STATUS_START = 0;
    public const STATUS_SUCCESS = 1;
    public const STATUS_FAIL = -1;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue()
     */
    private ?int $id;

    /**
     * @ORM\Column(name="process_code", type="string", length=255, nullable=true)
     */
    private ?string $processCode;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $source;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $target;

    /**
     * @ORM\Column(type="datetime")
     */
    private DateTimeInterface $startDate;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTimeInterface $endDate;

    /**
     * @ORM\Column(type="integer")
     */
    private int $status;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $response;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $data;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $log;

    /**
     * @ORM\OneToMany(targetEntity="ProcessExecutionLogRecord", mappedBy="processExecution", cascade={"persist"})
     */
    private Collection $logRecords;

    public function __construct()
    {
        $this->status = self::STATUS_START;
        $this->startDate = new \DateTime();
        $this->logRecords = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProcessCode(): ?string
    {
        return $this->processCode;
    }

    public function setProcessCode(?string $processCode): self
    {
        $this->processCode = $processCode;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function setTarget(?string $target): self
    {
        $this->target = $target;

        return $this;
    }

    public function getStartDate(): \DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function setResponse(?string $response): self
    {
        $this->response = $response;

        return $this;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function setData(?string $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getLog(): ?string
    {
        return $this->log;
    }

    public function setLog(string $log): self
    {
        $this->log = $log;

        return $this;
    }

    public function addLogRecord(ProcessExecutionLogRecord $processExecutionLogRecord): void
    {
        $processExecutionLogRecord->setProcessExecution($this);
        $this->logRecords->add($processExecutionLogRecord);
    }

    public function getLogRecords(): Collection
    {
        return $this->logRecords;
    }

    public function setLogRecords(Collection $logRecords): ProcessExecution
    {
        foreach ($logRecords as $logRecord) {
            $this->addLogRecord($logRecord);
        }

        return $this;
    }
}
