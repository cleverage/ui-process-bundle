<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\UnicodeString;

#[ORM\Entity]
#[ORM\Index(columns: ['level'], name: 'idx_log_record_level')]
#[ORM\Index(columns: ['created_at'], name: 'idx_log_record_created_at')]
class LogRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProcessExecution::class, cascade: ['all'])]
    #[ORM\JoinColumn(name: 'process_execution_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private readonly ProcessExecution $processExecution;

    #[ORM\Column(type: 'string', length: 64)]
    public readonly string $channel;

    #[ORM\Column(type: 'integer')]
    public readonly int $level;

    #[ORM\Column(type: 'string', length: 512)]
    public readonly string $message;

    #[ORM\Column(type: 'json')]
    /** @var array<string, mixed> */
    public readonly array $context;

    #[ORM\Column(type: 'datetime_immutable')]
    public readonly \DateTimeImmutable $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function __construct(\Monolog\LogRecord $record, ProcessExecution $processExecution)
    {
        $this->channel = (string) (new UnicodeString($record->channel))->truncate(64);
        $this->level = $record->level->value;
        $this->message = (string) (new UnicodeString($record->message))->truncate(512);
        $this->context = $record->context;
        $this->processExecution = $processExecution;
        $this->createdAt = \DateTimeImmutable::createFromMutable(new \DateTime());
    }

    public function contextIsEmpty(): bool
    {
        return !empty($this->context);
    }
}
