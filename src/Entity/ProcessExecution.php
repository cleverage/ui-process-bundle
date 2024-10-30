<?php

declare(strict_types=1);

/*
 * This file is part of the CleverAge/UiProcessBundle package.
 *
 * Copyright (c) Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\ProcessUiBundle\Entity;

use CleverAge\ProcessUiBundle\Repository\ProcessExecutionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProcessExecutionRepository::class)]
class ProcessExecution
{
    public const STATUS_START = 0;
    public const STATUS_SUCCESS = 1;
    public const STATUS_FAIL = -1;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'process_code', type: Types::STRING, length: 255, nullable: true)]
    private ?string $processCode = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $target = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $startDate;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $status = self::STATUS_START;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $response = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $data = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $report = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $log = null;

    /**
     * @var Collection<int, ProcessExecutionLogRecord>
     */
    #[ORM\OneToMany(targetEntity: ProcessExecutionLogRecord::class, mappedBy: 'processExecution', cascade: ['persist'])]
    private readonly Collection $logRecords;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Process::class, inversedBy: 'executions')]
        #[ORM\JoinColumn(name: 'process_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
        private readonly Process $process,
    ) {
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

    public function setStartDate(\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): self
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

    /**
     * @return Collection<int, ProcessExecutionLogRecord>
     */
    public function getLogRecords(): Collection
    {
        return $this->logRecords;
    }

    /**
     * @param Collection<int, ProcessExecutionLogRecord> $logRecords
     */
    public function setLogRecords(Collection $logRecords): self
    {
        foreach ($logRecords as $logRecord) {
            $this->addLogRecord($logRecord);
        }

        return $this;
    }

    public function getProcess(): Process
    {
        return $this->process;
    }

    public function getReport(): array
    {
        return $this->report ?? [];
    }

    public function setReport(?array $report): self
    {
        $this->report = $report;

        return $this;
    }
}
