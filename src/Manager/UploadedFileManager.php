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

namespace CleverAge\UiProcessBundle\Manager;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Deletes the files sent as input of a process (launch form, HTTP API) once the process has been executed, so that the
 * upload directory does not keep a copy of every file ever sent.
 *
 * @internal
 */
final readonly class UploadedFileManager
{
    public function __construct(
        private string $uploadDirectory,
        private bool $deleteUploadedFiles = true,
    ) {
    }

    /**
     * Deletes the input if it is a file of the upload directory.
     */
    public function deleteInput(mixed $input): void
    {
        if (!$this->deleteUploadedFiles || !\is_string($input) || !is_file($input)) {
            return;
        }
        $uploadDirectory = realpath($this->uploadDirectory);
        // Only the files written by the bundle, never a file given by its path
        if (false === $uploadDirectory || realpath(\dirname($input)) !== $uploadDirectory) {
            return;
        }

        (new Filesystem())->remove($input);
    }
}
