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
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Deletes the files sent as input of a process (launch form, HTTP API) once the process has been executed, so that the
 * upload directory does not keep a copy of every file ever sent.
 *
 * @internal
 */
final readonly class UploadedFileManager
{
    /**
     * Maximum length of a file name, in bytes, on most file systems (e.g. ext4, XFS).
     */
    public const MAX_FILENAME_LENGTH = 255;

    /**
     * Maximum length of the extension kept from the client file name.
     */
    private const MAX_EXTENSION_LENGTH = 16;

    public function __construct(
        private string $uploadDirectory,
        private bool $deleteUploadedFiles = true,
    ) {
    }

    /**
     * Deletes the input if it is a file of the upload directory.
     *
     * Only call it for a file the bundle has just written for this execution (launch form, HTTP API): a file of the
     * upload directory can also be given by its path (HTTP API, text input of the launch form, schedule, default
     * input), it must then be kept.
     */
    public function deleteInput(mixed $input): void
    {
        if (!$this->deleteUploadedFiles || !\is_string($input) || !is_file($input)) {
            return;
        }
        $uploadDirectory = realpath($this->uploadDirectory);
        // Defense in depth: never a file outside of the upload directory
        if (false === $uploadDirectory || realpath(\dirname($input)) !== $uploadDirectory) {
            return;
        }

        (new Filesystem())->remove($input);
    }

    /**
     * The extension of the client file name, reduced to its letters and digits and to 16 characters (empty when none
     * is left): the client file name is chosen by the sender, neither its length nor its characters are checked.
     */
    public static function getSafeExtension(UploadedFile $file): string
    {
        $extension = preg_replace('/[^a-z0-9]/i', '', $file->getClientOriginalExtension()) ?? '';

        return substr($extension, 0, self::MAX_EXTENSION_LENGTH);
    }

    /**
     * The client file name, unchanged when it fits in $maxLength bytes; otherwise its name truncated (on a character
     * boundary) and its extension reduced by getSafeExtension(), so that the written file name stays under the limit
     * of the file system.
     */
    public static function getSafeName(UploadedFile $file, int $maxLength): string
    {
        $name = $file->getClientOriginalName();
        if (\strlen($name) <= $maxLength) {
            return $name;
        }
        $extension = self::getSafeExtension($file);
        $suffix = '' === $extension ? '' : '.'.$extension;

        return mb_strcut(pathinfo($name, \PATHINFO_FILENAME), 0, max(0, $maxLength - \strlen($suffix)), 'UTF-8').$suffix;
    }
}
