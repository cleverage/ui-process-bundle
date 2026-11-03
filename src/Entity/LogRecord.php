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

#[ORM\Entity]
#[ORM\Table(name: 'log_record')]
#[ORM\Index(name: 'idx_log_record_level', columns: ['level'])]
#[ORM\Index(name: 'idx_log_record_created_at', columns: ['created_at'])]
class LogRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id')]
    private ?int $id = null;

    #[ORM\Column(name: 'channel', type: Types::STRING, length: 64)]
    public readonly string $channel;

    #[ORM\Column(name: 'level', type: Types::INTEGER)]
    public readonly int $level;

    #[ORM\Column(name: 'message', type: Types::STRING, length: 512)]
    public readonly string $message;

    /** @var array<mixed> $context */
    #[ORM\Column(name: 'context', type: Types::JSON)]
    public readonly array $context;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public readonly \DateTimeImmutable $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function __construct(
        \Monolog\LogRecord $record,
        #[ORM\ManyToOne(targetEntity: ProcessExecution::class, cascade: ['persist'])]
        #[ORM\JoinColumn(name: 'process_execution_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private readonly ProcessExecution $processExecution,
    ) {
        $this->channel = $this->truncate($record->channel, 64);
        $this->level = $record->level->value;
        $this->message = $this->truncate($record->message, 512);
        $this->context = $this->encodableContext($record->context);
        $this->createdAt = $record->datetime;
    }

    /**
     * Invalid UTF-8 sequences replaced (by "?"), NUL characters removed (rejected by PostgreSQL, or the value truncated
     * there), truncated to the length of the column: a number of characters (code points) in the database, not of
     * graphemes (an emoji or a letter with combining marks is a grapheme of several code points).
     */
    private function truncate(string $value, int $length): string
    {
        return mb_substr(str_replace("\0", '', mb_scrub($value, 'UTF-8')), 0, $length, 'UTF-8');
    }

    /**
     * The context, stored as JSON: unchanged when it can be encoded, otherwise its JSON representation where the
     * invalid UTF-8 sequences are replaced (by U+FFFD) and the values which cannot be encoded (e.g. a resource) are
     * replaced by null: the insertion of the log record, and of the other ones of the batch, would fail.
     *
     * @param array<mixed> $context
     *
     * @return array<mixed>
     */
    private function encodableContext(array $context): array
    {
        if (false !== json_encode($context, \JSON_PRESERVE_ZERO_FRACTION)) {
            return $context;
        }
        $json = json_encode($context, \JSON_PRESERVE_ZERO_FRACTION | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_PARTIAL_OUTPUT_ON_ERROR);
        $encodable = false === $json ? null : json_decode($json, true);

        return \is_array($encodable) ? $encodable : [];
    }

    /**
     * Not named hasContext(): the PropertyAccessor would use it to read the "context" property (e.g. in EasyAdmin).
     */
    public function hasContextInfo(): bool
    {
        return [] !== $this->context;
    }

    /**
     * @deprecated misnamed, returns true when the context is NOT empty: use hasContextInfo() instead
     */
    public function contextIsEmpty(): bool
    {
        trigger_deprecation('cleverage/ui-process-bundle', '3.1', 'The "%s()" method is deprecated, use "hasContextInfo()" instead.', __METHOD__);

        return $this->hasContextInfo();
    }

    public function getProcessExecution(): ProcessExecution
    {
        return $this->processExecution;
    }
}
