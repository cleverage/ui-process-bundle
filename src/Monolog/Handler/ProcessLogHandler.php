<?php

namespace CleverAge\ProcessUiBundle\Monolog\Handler;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Monolog\Handler\AbstractProcessingHandler;

class ProcessLogHandler extends AbstractProcessingHandler
{
    private string $logDir;

    private ?string $logFilename;

    private ?Filesystem $filesystem = null;

    /**
     * @required
     */
    public function setLogDir(string $processLogDir): void
    {
        $this->logDir = $processLogDir;
    }

    /**
     * @param array <string, mixed> $record
     * @throws FilesystemException
     */
    protected function write(array $record): void
    {
        if (null === $logFilename = $this->logFilename) {
            return;
        }
        if (null === $this->filesystem) {
            $this->filesystem = new Filesystem(
                new LocalFilesystemAdapter($this->logDir, null, FILE_APPEND)
            );
        }
        $this->filesystem->write($logFilename, $record['formatted']);
    }

    public function setLogFilename(string $logFilename): void
    {
        $this->logFilename = $logFilename;
    }

    public function getLogFilename(): ?string
    {
        return $this->logFilename;
    }
}
