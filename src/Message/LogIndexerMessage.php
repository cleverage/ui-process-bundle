<?php

namespace CleverAge\ProcessUiBundle\Message;

class LogIndexerMessage
{
    public const DEFAULT_OFFSET = 2500;
    private int $processExecutionId;
    private string $logPath;
    private int $start;
    private int $offset;

    public function __construct(
        int $processExecutionId,
        string $logPath,
        int $start,
        int $offset = self::DEFAULT_OFFSET
    ) {
        $this->processExecutionId = $processExecutionId;
        $this->logPath = $logPath;
        $this->start = $start;
        $this->offset = $offset;
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
