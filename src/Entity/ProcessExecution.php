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
use Symfony\Component\String\UnicodeString;

class ProcessExecution implements ProcessExecutionInterface, \Stringable
{
    protected ?int $id = null;

    protected readonly string $code;

    public readonly \DateTimeImmutable $startDate;

    public ?\DateTimeImmutable $endDate = null;

    public ProcessExecutionStatus $status = ProcessExecutionStatus::Started;

    /**
     * @var array<string, mixed>
     */
    protected array $report = [];

    /**
     * @var array<string|int, mixed>
     */
    protected array $context;

    /**
     * @param ?array<string|int, mixed> $context
     */
    public function __construct(string $code, public readonly string $logFilename, ?array $context = [])
    {
        $this->code = (string) (new UnicodeString($code))->truncate(255);
        $this->startDate = \DateTimeImmutable::createFromMutable(new \DateTime());
        $this->context = $context ?? [];
    }

    public function __toString(): string
    {
        return \sprintf('%s (%s)', $this->id, $this->code);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function end(): void
    {
        $this->endDate = \DateTimeImmutable::createFromMutable(new \DateTime());
    }

    public function duration(string $format = '%H hour(s) %I min(s) %S s'): ?string
    {
        if (!$this->endDate instanceof \DateTimeImmutable) {
            return null;
        }
        $diff = $this->endDate->diff($this->startDate);

        return $diff->format($format);
    }

    public function setStatus(ProcessExecutionStatus $status): static
    {
        $this->status = $status;

        return $this;
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

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }
}
