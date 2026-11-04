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
    use EncodableContextTrait;

    /** Limits of the context, as in the log file (Monolog NormalizerFormatter) */
    private const MAX_ITEMS = 1000;
    private const MAX_DEPTH = 9;

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
        $this->context = $this->limit($this->encodableContext($record->context), 1);
        $this->createdAt = $record->datetime;
    }

    /**
     * The limits of the log file (Monolog NormalizerFormatter): at most 1000 items per array, 9 levels deep. An unbounded
     * context (e.g. the output of an aggregator) was stored whole, and over the maximum packet size of the database, it
     * made the insertion of the log records written with it fail.
     *
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private function limit(array $data, int $depth): array
    {
        $limited = [];
        $count = 0;
        foreach ($data as $key => $value) {
            if (++$count > self::MAX_ITEMS) {
                $limited['...'] = \sprintf('Over %d items (%d total), aborting normalization', self::MAX_ITEMS, \count($data));
                break;
            }
            if ($depth + 1 > self::MAX_DEPTH) {
                $limited[$key] = \sprintf('Over %d levels deep, aborting normalization', self::MAX_DEPTH);
            } else {
                $limited[$key] = \is_array($value) ? $this->limit($value, $depth + 1) : $value;
            }
        }

        return $limited;
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
     * Not named hasContext(): the PropertyAccessor would use it to read the "context" property (e.g. in EasyAdmin).
     */
    public function hasContextInfo(): bool
    {
        return [] !== $this->context;
    }

    /**
     * @deprecated since 3.1, misnamed, returns true when the context is NOT empty: use hasContextInfo() instead
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
