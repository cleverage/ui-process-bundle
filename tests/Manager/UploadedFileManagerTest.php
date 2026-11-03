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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideExtensions(): iterable
    {
        yield 'extension' => ['data.csv', 'csv'];
        yield 'case kept' => ['data.CSV', 'CSV'];
        yield 'last extension' => ['data.tar.gz', 'gz'];
        yield 'special characters' => ["data.c\"s v\n", 'csv'];
        yield 'long extension' => ['data.'.str_repeat('x', 300), str_repeat('x', 16)];
        yield 'no extension' => ['data', ''];
        yield 'no letter or digit' => ['data.é', ''];
    }

    #[DataProvider('provideExtensions')]
    public function testGetSafeExtension(string $clientName, string $expected): void
    {
        self::assertSame($expected, UploadedFileManager::getSafeExtension($this->createUploadedFile($clientName)));
    }

    public function testGetSafeNameKeepsANameWhichFits(): void
    {
        $name = str_repeat('a', 96).'.c"sv';

        self::assertSame($name, UploadedFileManager::getSafeName($this->createUploadedFile($name), 101));
    }

    public function testGetSafeNameTruncatesALongName(): void
    {
        // 2 bytes per character: truncated on a character boundary
        $name = UploadedFileManager::getSafeName($this->createUploadedFile(str_repeat('é', 200).'.csv'), 100);

        self::assertSame(str_repeat('é', 48).'.csv', $name);
    }

    public function testGetSafeNameTruncatesALongExtension(): void
    {
        $name = UploadedFileManager::getSafeName($this->createUploadedFile('data.'.str_repeat('x', 300)), 100);

        self::assertSame('data.'.str_repeat('x', 16), $name);
    }

    public function testGetSafeNameWithoutExtension(): void
    {
        self::assertSame(str_repeat('a', 100), UploadedFileManager::getSafeName($this->createUploadedFile(str_repeat('a', 300)), 100));
    }

    private function createUploadedFile(string $clientName): UploadedFile
    {
        return new UploadedFile($this->directory.'/uploads/input.csv', $clientName, null, null, true);
    }
}
