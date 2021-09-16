<?php

namespace CleverAge\ProcessUiBundle\Monolog\Handler;

use CleverAge\ProcessUiBundle\Entity\ProcessExecution;
use CleverAge\ProcessUiBundle\Entity\ProcessExecutionLogRecord;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class ProcessLogHandler extends StreamHandler
{
    private string $logDir;

    private ?string $logFilename;

    private ?ProcessExecution $processExecution;

    public function __construct(string $logDir)
    {
        $this->logDir = $logDir;

        $this->filePermission = null;
        $this->useLocking = false;
        $this->bubble = true;

        $this->setLevel(Logger::DEBUG);
    }

    /**
     * Get the filename of the log file
     */
    public function getFilename(): string
    {
        return (string) $this->url;
    }

    public function setSubDirectory(string $subDirectory): void
    {
        $this->url = $this->getRealPath($this->generateLogFilename(), $subDirectory);
    }

    /**
     * Get the real path of the log file
     */
    public function getRealPath(string $filename, ?string $subDirectory = null): string
    {
        if ($subDirectory) {
            return sprintf('%s/%s/%s', $this->logDir, $subDirectory, $filename);
        }

        return sprintf('%s/%s', $this->logDir, $filename);
    }

    /**
     * {@inheritdoc}
     */
    public function write(array $record): void
    {
        if (!$this->url) {
            $this->url = $this->getRealPath($this->getLogFilename());
        }

        if (
            ! is_dir(dirname($this->url)) && ! mkdir(
                $concurrentDirectory = dirname($this->url),
                0755,
                true
            ) && ! is_dir($concurrentDirectory)
        ) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        parent::write($record);
    }

    private function generateLogFilename(): string
    {
        return sprintf('process_%s.log', sha1(uniqid((string)mt_rand(), true)));
    }

    public function getLogFilename(): string
    {
        return $this->logFilename;
    }

    public function setLogFilename(string $logFilename): void
    {
        $this->logFilename = $logFilename;
    }
}
