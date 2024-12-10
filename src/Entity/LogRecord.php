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

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\UnicodeString;

#[ORM\Entity]
#[ORM\Index(name: 'idx_log_record_level', columns: ['level'])]
#[ORM\Index(name: 'idx_log_record_created_at', columns: ['created_at'])]
class LogRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    public readonly string $channel;

    #[ORM\Column(type: Types::INTEGER)]
    public readonly int $level;

    #[ORM\Column(type: Types::STRING, length: 512)]
    public readonly string $message;

    /** @var array<string, mixed> $context */
    #[ORM\Column(type: Types::JSON)]
    public readonly array $context;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public readonly \DateTimeImmutable $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function __construct(
        \Monolog\LogRecord $record,
        #[ORM\ManyToOne(targetEntity: ProcessExecution::class, cascade: ['all'])]
        #[ORM\JoinColumn(name: 'process_execution_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
        private readonly ProcessExecution $processExecution,
    ) {
        $this->channel = (string) (new UnicodeString($record->channel))->truncate(64);
        $this->level = $record->level->value;
        $this->message = (string) (new UnicodeString($record->message))->truncate(512);
        $this->context = $record->context;
        $this->createdAt = \DateTimeImmutable::createFromMutable(new \DateTime());
    }

    public function contextIsEmpty(): bool
    {
        return [] !== $this->context;
    }
}
