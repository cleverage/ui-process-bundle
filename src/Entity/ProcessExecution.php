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

use CleverAge\ProcessUiBundle\Entity\Enum\ProcessExecutionStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\UnicodeString;

#[ORM\Entity]
#[ORM\Index(columns: ['code'], name: 'idx_process_execution_code')]
#[ORM\Index(columns: ['start_date'], name: 'idx_process_execution_start_date')]
class ProcessExecution
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public readonly string $code;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public readonly string $logFilename;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public readonly \DateTimeImmutable $startDate;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(type: Types::STRING, enumType: ProcessExecutionStatus::class)]
    public ProcessExecutionStatus $status;

    #[ORM\Column(type: Types::JSON)]
    private array $report = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $context = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function __construct(string $code, string $logFilename, ?array $context = [])
    {
        $this->code = (string) (new UnicodeString($code))->truncate(255);
        $this->logFilename = $logFilename;
        $this->startDate = \DateTimeImmutable::createFromMutable(new \DateTime());
        $this->status = ProcessExecutionStatus::Started;
        $this->context = $context ?? [];
    }

    public function setStatus(ProcessExecutionStatus $status): void
    {
        $this->status = $status;
    }

    public function end(): void
    {
        $this->endDate = \DateTimeImmutable::createFromMutable(new \DateTime());
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->id, $this->code);
    }

    public function addReport(string $key, mixed $value): void
    {
        $this->report[$key] = $value;
    }

    public function getReport(?string $key = null, mixed $default = null): mixed
    {
        if (null === $key) {
            return $this->report;
        }

        return $this->report[$key] ?? $default;
    }

    public function duration(string $format = '%H hour(s) %I min(s) %S s'): ?string
    {
        if (null === $this->endDate) {
            return null;
        }
        $diff = $this->endDate->diff($this->startDate);

        return $diff->format($format);
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }
}
