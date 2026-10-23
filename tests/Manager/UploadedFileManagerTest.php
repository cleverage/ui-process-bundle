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

namespace CleverAge\UiProcessBundle\Tests\Manager;

use CleverAge\UiProcessBundle\Manager\UploadedFileManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(UploadedFileManager::class)]
class UploadedFileManagerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.uniqid('uploaded_file_test_', true);
        $filesystem = new Filesystem();
        $filesystem->dumpFile($this->directory.'/uploads/input.csv', 'data');
        $filesystem->dumpFile($this->directory.'/uploads/sub/input.csv', 'data');
        $filesystem->dumpFile($this->directory.'/other/input.csv', 'data');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testTheUploadedFileIsDeleted(): void
    {
        (new UploadedFileManager($this->directory.'/uploads'))->deleteInput($this->directory.'/uploads/input.csv');

        self::assertFileDoesNotExist($this->directory.'/uploads/input.csv');
    }

    public function testFilesOutsideOfTheUploadDirectoryAreKept(): void
    {
        $manager = new UploadedFileManager($this->directory.'/uploads');
        $manager->deleteInput($this->directory.'/other/input.csv');
        $manager->deleteInput($this->directory.'/uploads/sub/input.csv');
        $manager->deleteInput($this->directory.'/uploads/../other/input.csv');

        self::assertFileExists($this->directory.'/other/input.csv');
        self::assertFileExists($this->directory.'/uploads/sub/input.csv');
    }

    public function testNothingIsDeletedWhenDisabled(): void
    {
        (new UploadedFileManager($this->directory.'/uploads', false))->deleteInput($this->directory.'/uploads/input.csv');

        self::assertFileExists($this->directory.'/uploads/input.csv');
    }

    public function testOtherInputsAreIgnored(): void
    {
        $manager = new UploadedFileManager($this->directory.'/uploads');
        $manager->deleteInput(null);
        $manager->deleteInput(['input.csv']);
        $manager->deleteInput($this->directory.'/uploads/missing.csv');
        $manager->deleteInput($this->directory.'/uploads');
        (new UploadedFileManager($this->directory.'/missing'))->deleteInput($this->directory.'/uploads/input.csv');

        self::assertFileExists($this->directory.'/uploads/input.csv');
    }
}
