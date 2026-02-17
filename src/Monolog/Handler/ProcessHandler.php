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

namespace CleverAge\UiProcessBundle\Monolog\Handler;

use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;

class ProcessHandler extends StreamHandler
{
    private Level $reportIncrementLevel = Level::Error;
    private bool $filenameSet = false;

    public function __construct(
        private readonly string $directory,
        private readonly ProcessExecutionManager $processExecutionManager,
        int|string|Level $level = Level::Debug,
    ) {
        // Initialize with php://memory as placeholder - actual file will be set via setFilename()
        parent::__construct('php://memory', $level);
    }

    /**
     * @param 'ALERT'|'Alert'|'alert'|'CRITICAL'|'Critical'|'critical'|'DEBUG'|'Debug'|'debug'|'EMERGENCY'|'Emergency'|'emergency'|'ERROR'|'Error'|'error'|'INFO'|'Info'|'info'|'NOTICE'|'Notice'|'notice'|'WARNING'|'Warning'|'warning' $level
     */
    public function setReportIncrementLevel(string $level): void
    {
        $this->reportIncrementLevel = Level::fromName($level);
    }

    public function hasFilename(): bool
    {
        return $this->filenameSet;
    }

    public function setFilename(string $filename): void
    {
        $this->close();
        $this->url = \sprintf('%s/%s', $this->directory, $filename);
        $this->filenameSet = true;
    }

    #[\Override]
    public function close(): void
    {
        parent::close();
        $this->filenameSet = false;
    }

    public function getFilename(): ?string
    {
        return $this->filenameSet ? $this->url : null;
    }

    #[\Override]
    public function write(LogRecord $record): void
    {
        if (!$this->filenameSet) {
            // Skip writing if no filename has been set yet
            return;
        }
        parent::write($record);
        if ($record->level->value >= $this->reportIncrementLevel->value) {
            $this->processExecutionManager->increment($record->level->name);
        }
    }
}
