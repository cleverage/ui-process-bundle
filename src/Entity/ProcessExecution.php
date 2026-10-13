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

namespace CleverAge\UiProcessBundle\Entity;

use CleverAge\UiProcessBundle\Entity\Enum\ProcessExecutionStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\UnicodeString;

#[ORM\Entity]
#[ORM\Index(name: 'idx_process_execution_code', columns: ['code'])]
#[ORM\Index(name: 'idx_process_execution_start_date', columns: ['start_date'])]
class ProcessExecution implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public readonly string $code;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public readonly \DateTimeImmutable $startDate;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(type: Types::STRING, enumType: ProcessExecutionStatus::class)]
    public ProcessExecutionStatus $status = ProcessExecutionStatus::Started;

    /**
     * Requested from the UI: the process is stopped before its next task execution.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $stopRequestedAt = null;

    /**
     * Updated while the process runs (see ProcessExecutionStopChecker), written with DBAL by the running process.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $heartbeatAt = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $report = [];

    /**
     * @var array<string|int, mixed>
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $context;

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param array<string|int, mixed> $context
     */
    public function __construct(
        string $code,
        #[ORM\Column(type: Types::STRING, length: 255)] public readonly string $logFilename,
        ?array $context = [],
    ) {
        $this->code = (string) (new UnicodeString($code))->truncate(255);
        $this->startDate = \DateTimeImmutable::createFromMutable(new \DateTime());
        $this->context = $context ?? [];
    }

    public function __toString(): string
    {
        return \sprintf('%s (%s)', $this->id, $this->code);
    }

    public function setStatus(ProcessExecutionStatus $status): void
    {
        $this->status = $status;
    }

    public function end(): void
    {
        $this->endDate = \DateTimeImmutable::createFromMutable(new \DateTime());
    }

    public function requestStop(): void
    {
        $this->stopRequestedAt ??= new \DateTimeImmutable();
    }

    public function isStopRequested(): bool
    {
        return $this->stopRequestedAt instanceof \DateTimeImmutable;
    }

    /**
     * The last sign of life of the process: its last heartbeat, or its start.
     */
    public function getLastActivityDate(): \DateTimeImmutable
    {
        return $this->heartbeatAt ?? $this->startDate;
    }

    /**
     * A started execution without activity for more than $staleAfter seconds: its process has probably been killed
     * (worker crash, out of memory...), or is blocked in a long task call (SQL query, HTTP request...).
     */
    public function isStale(int $staleAfter, ?\DateTimeImmutable $now = null): bool
    {
        return ProcessExecutionStatus::Started === $this->status
            && $this->getLastActivityDate() < ($now ?? new \DateTimeImmutable())->modify(\sprintf('-%d seconds', $staleAfter));
    }

    /**
     * Ends a stale execution as failed, at its last activity date.
     */
    public function markAsFailed(): void
    {
        $this->status = ProcessExecutionStatus::Failed;
        $this->endDate = $this->getLastActivityDate();
    }

    public function addReport(string $key, mixed $value): void
    {
        $this->report[$key] = $value;
    }

    /**
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public function getReport(?string $key = null, mixed $default = null): mixed
    {
        if (null === $key) {
            return $this->report;
        }

        return $this->report[$key] ?? $default;
    }

    public function duration(string $format = '%H hour(s) %I min(s) %S s'): ?string
    {
        if (!$this->endDate instanceof \DateTimeImmutable) {
            return null;
        }
        $diff = $this->endDate->diff($this->startDate);

        return $diff->format($format);
    }

    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @return array<string|int, mixed>
     */
    public function getContext(): ?array
    {
        return $this->context;
    }

    /**
     * @param array<string|int, mixed> $context
     */
    public function setContext(array $context): void
    {
        $this->context = $context;
    }
}
