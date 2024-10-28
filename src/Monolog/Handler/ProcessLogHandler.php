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
use Monolog\Logger;
use Symfony\Contracts\Service\Attribute\Required;

class ProcessLogHandler extends AbstractProcessingHandler
{
    private string $logDir;
    private ?array $logFilenames = [];
    private ?string $currentProcessCode = null;
    private ?Filesystem $filesystem = null;

    #[Required]
    public function setLogDir(string $processLogDir): void
    {
        $this->logDir = $processLogDir;
    }

    /**
     * @param array <string, mixed> $record
     *
     * @throws FilesystemException
     */
    protected function write(array $record): void
    {
        if (null === $logFilename = ($this->logFilenames[$this->currentProcessCode] ?? null)) {
            return;
        }

        if ($record['level'] < Logger::INFO) {
            return;
        }

        if (!$this->filesystem instanceof Filesystem) {
            $this->filesystem = new Filesystem(
                new LocalFilesystemAdapter($this->logDir, null, \FILE_APPEND)
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
