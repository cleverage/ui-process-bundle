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

namespace CleverAge\ProcessUiBundle\Monolog\Handler;

use CleverAge\ProcessUiBundle\Manager\ProcessExecutionManager;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ProcessHandler extends StreamHandler
{
    private Level $reportIncrementLevel = Level::Error;

    public function __construct(
        #[Autowire(param: 'kernel.logs_dir')] private readonly string $directory,
        private readonly ProcessExecutionManager $processExecutionManager,
    ) {
        parent::__construct($this->directory);
    }

    public function hasFilename(): bool
    {
        return $this->directory !== $this->url;
    }

    public function setFilename(string $filename): void
    {
        $this->url = \sprintf('%s/%s', $this->directory, $filename);
    }

    public function close(): void
    {
        $this->url = $this->directory;
        parent::close();
    }

    public function getFilename(): ?string
    {
        return $this->url;
    }

    public function write(LogRecord $record): void
    {
        parent::write($record);
        if ($record->level->value >= $this->reportIncrementLevel->value) {
            $this->processExecutionManager->increment($record->level->name);
        }
    }

    /**
     * @param 'ALERT'|'Alert'|'alert'|'CRITICAL'|'Critical'|'critical'|'DEBUG'|'Debug'|'debug'|'EMERGENCY'|'Emergency'|'emergency'|'ERROR'|'Error'|'error'|'INFO'|'Info'|'info'|'NOTICE'|'Notice'|'notice'|'WARNING'|'Warning'|'warning' $level
     */
    public function setReportIncrementLevel(string $level): void
    {
        $this->reportIncrementLevel = Level::fromName($level);
    }
}
