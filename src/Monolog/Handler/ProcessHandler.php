<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Monolog\Handler;

use CleverAge\ProcessUiBundle\Manager\ProcessExecutionManager;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ProcessHandler extends StreamHandler
{
    private readonly string $directory;
    private Level $reportIncrementLevel;

    public function __construct(
        #[Autowire(param: 'kernel.logs_dir')] string $directory,
        private ProcessExecutionManager $processExecutionManager
    ) {
        $this->directory = $directory;
        $this->reportIncrementLevel = Level::Error;
        parent::__construct($directory);
    }

    public function hasFilename(): bool
    {
        return $this->directory !== $this->url;
    }

    public function setFilename(string $filename): void
    {
        $this->url = sprintf('%s/%s', $this->directory, $filename);
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

    public function setReportIncrementLevel(string $level): void
    {
        $this->reportIncrementLevel = Level::fromName($level);
    }
}
