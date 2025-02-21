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

use Symfony\Component\String\UnicodeString;

class LogRecord implements LogRecordInterface
{
    protected ?int $id = null;

    public readonly string $channel;

    public readonly int $level;

    public readonly string $message;

    /** @var array<string, mixed> */
    public readonly array $context;

    public readonly \DateTimeImmutable $createdAt;

    public function __construct(\Monolog\LogRecord $record, protected readonly ProcessExecutionInterface $processExecution)
    {
        $this->channel = (string) (new UnicodeString($record->channel))->truncate(64);
        $this->level = $record->level->value;
        $this->message = (string) (new UnicodeString($record->message))->truncate(512);
        $this->context = $record->context;
        $this->createdAt = \DateTimeImmutable::createFromMutable(new \DateTime());
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function contextIsEmpty(): bool
    {
        return [] !== $this->context;
    }
}
