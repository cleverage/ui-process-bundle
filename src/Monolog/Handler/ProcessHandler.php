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

/**
 * @internal
 */
class ProcessHandler extends StreamHandler
{
    private Level $reportIncrementLevel = Level::Warning;
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
    protected function write(LogRecord $record): void
    {
        if (!$this->filenameSet) {
            // Skip writing if no filename has been set yet
            return;
        }
        if (\is_string($record->formatted)) {
            $record->formatted = $this->escapeControlCharacters($record->formatted);
        }
        parent::write($record);
        if ($record->level->value >= $this->reportIncrementLevel->value) {
            $this->processExecutionManager->increment($record->level->name);
        }
    }

    /**
     * The control characters of the processed data (e.g. a CSV cell logged by a task) must not be interpreted by the
     * terminal displaying the log file (cat, tail -f): ANSI escape sequences change the display, hide or forge lines.
     * The C0 characters (except the tabulation and the line feed), DEL and the C1 characters (encoded in UTF-8) are
     * escaped as "\x1B" and "\u{9B}".
     */
    private function escapeControlCharacters(string $line): string
    {
        $line = preg_replace_callback(
            '/[\x00-\x08\x0B-\x1F\x7F]/',
            static fn (array $matches): string => \sprintf('\\x%02X', \ord($matches[0])),
            $line
        ) ?? $line;

        return preg_replace_callback(
            '/\xC2([\x80-\x9F])/',
            static fn (array $matches): string => \sprintf('\\u{%X}', \ord($matches[1])),
            $line
        ) ?? $line;
    }
}
