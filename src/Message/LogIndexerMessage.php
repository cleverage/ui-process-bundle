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

namespace CleverAge\ProcessUiBundle\Message;

class LogIndexerMessage
{
    public const DEFAULT_OFFSET = 2500;

    public function __construct(
        private readonly int $processExecutionId,
        private readonly string $logPath,
        private readonly int $start,
        private readonly int $offset = self::DEFAULT_OFFSET,
    ) {
    }

    public function getProcessExecutionId(): int
    {
        return $this->processExecutionId;
    }

    public function getLogPath(): string
    {
        return $this->logPath;
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }
}
