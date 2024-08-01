<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Entity;

use CleverAge\ProcessUiBundle\Entity\Enum\ProcessExecutionStatus;
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

    #[ORM\Column(type: 'string', length: 255)]
    public readonly string $code;

    #[ORM\Column(type: 'string', length: 255)]
    public readonly string $logFilename;

    #[ORM\Column(type: 'datetime_immutable')]
    public readonly \DateTimeImmutable $startDate;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(type: 'string', enumType: ProcessExecutionStatus::class)]
    public ProcessExecutionStatus $status;

    #[ORM\Column(type: 'json')]
    private array $report = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function __construct(string $code, string $logFilename)
    {
        $this->code = (string) (new UnicodeString($code))->truncate(255);
        $this->logFilename = $logFilename;
        $this->startDate = \DateTimeImmutable::createFromMutable(new \DateTime());
        $this->status = ProcessExecutionStatus::Started;
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

    public function getReport(string $key = null, mixed $default = null): mixed
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
}
