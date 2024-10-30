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

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class ProcessLogHandler extends AbstractProcessingHandler
{
    private ?array $logFilenames = [];
    private ?string $currentProcessCode = null;
    private ?Filesystem $filesystem = null;

    public function __construct(private readonly string $processLogDir)
    {
        parent::__construct();
    }

    /**
     * @param array <string, mixed> $record
     *
     * @throws FilesystemException
     */
    protected function write(array|LogRecord $record): void
    {
        if (null === $logFilename = ($this->logFilenames[$this->currentProcessCode] ?? null)) {
            return;
        }

        if ($record['level'] < Level::Info) {
            return;
        }

        if (!$this->filesystem instanceof Filesystem) {
            $this->filesystem = new Filesystem(
                new LocalFilesystemAdapter($this->processLogDir, null, \FILE_APPEND)
            );
        }
        $this->filesystem->write($logFilename, $record['formatted']);
    }

    public function setLogFilename(string $logFilename, string $processCode): void
    {
        $this->logFilenames[$processCode] = $logFilename;
    }

    public function setCurrentProcessCode(?string $code): void
    {
        $this->currentProcessCode = $code;
    }

    public function getLogFilename(): ?string
    {
        return $this->logFilenames[$this->currentProcessCode] ?? null;
    }
}
