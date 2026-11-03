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

namespace CleverAge\UiProcessBundle\Tests\Command;

use CleverAge\UiProcessBundle\Command\PurgeUploadedFilesCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(PurgeUploadedFilesCommand::class)]
class PurgeUploadedFilesCommandTest extends TestCase
{
    private string $uploadDirectory;

    protected function setUp(): void
    {
        $this->uploadDirectory = sys_get_temp_dir().'/cleverage_ui_process_purge_uploads_'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->uploadDirectory);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->uploadDirectory);
    }

    public function testName(): void
    {
        $command = new PurgeUploadedFilesCommand($this->uploadDirectory);

        self::assertSame('cleverage:ui-process:purge-uploaded-files', $command->getName());
        self::assertFalse($command->getDefinition()->getOption('dry-run')->acceptValue());
    }

    public function testNothingToPurgeWithoutRetention(): void
    {
        $file = $this->createFile('3f2b8a1e-6c4d-4e8f-9a0b-1c2d3e4f5a6b.csv', 400);

        $tester = $this->execute(null);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Nothing to purge: no retention configured', $tester->getDisplay());
        self::assertFileExists($file);
    }

    public function testPurge(): void
    {
        // Written by the launch form (with or without extension) and by the HTTP API
        $launchFile = $this->createFile('3f2b8a1e-6c4d-4e8f-9a0b-1c2d3e4f5a6b.csv', 10);
        $launchFileWithoutExtension = $this->createFile('8d7c6b5a-4f3e-4d2c-b1a0-9f8e7d6c5b4a', 10);
        $apiFile = $this->createFile('20261020093000_6718c7a85f3b2_import file.csv', 10);
        // Recent: kept
        $recentFile = $this->createFile('0a1b2c3d-4e5f-4a6b-8c7d-8e9f0a1b2c3d.csv', 1);
        // Not written by the bundle (e.g. a reference file given by its path to a schedule): kept
        $referenceFile = $this->createFile('reference.csv', 10);
        $notAnUuidV4 = $this->createFile('3f2b8a1e-6c4d-1e8f-9a0b-1c2d3e4f5a6b.csv', 10);
        // In a subdirectory: kept
        $subdirectoryFile = $this->createFile('archive/3f2b8a1e-6c4d-4e8f-9a0b-1c2d3e4f5a6b.csv', 10);

        $tester = $this->execute(7);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('3 uploaded file(s) older than 7 day(s) deleted.', $tester->getDisplay());
        self::assertFileDoesNotExist($launchFile);
        self::assertFileDoesNotExist($launchFileWithoutExtension);
        self::assertFileDoesNotExist($apiFile);
        self::assertFileExists($recentFile);
        self::assertFileExists($referenceFile);
        self::assertFileExists($notAnUuidV4);
        self::assertFileExists($subdirectoryFile);
    }

    public function testDryRun(): void
    {
        $file = $this->createFile('3f2b8a1e-6c4d-4e8f-9a0b-1c2d3e4f5a6b.csv', 10);

        $tester = $this->execute(7, ['--dry-run' => true]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('1 uploaded file(s) older than 7 day(s) to delete (dry run).', $tester->getDisplay());
        self::assertFileExists($file);
    }

    public function testMissingUploadDirectory(): void
    {
        (new Filesystem())->remove($this->uploadDirectory);

        $tester = $this->execute(7);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Nothing to purge: the upload directory', $tester->getDisplay());
    }

    public function testInvalidRetention(): void
    {
        $file = $this->createFile('3f2b8a1e-6c4d-4e8f-9a0b-1c2d3e4f5a6b.csv', 10);

        $tester = $this->execute(0);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('must be at least 1', (string) preg_replace('/\s+/', ' ', $tester->getDisplay()));
        self::assertFileExists($file);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function execute(?int $maxDays, array $input = []): CommandTester
    {
        $tester = new CommandTester(new PurgeUploadedFilesCommand($this->uploadDirectory, $maxDays));
        $tester->execute($input);

        return $tester;
    }

    private function createFile(string $name, int $daysAgo): string
    {
        $path = $this->uploadDirectory.'/'.$name;
        (new Filesystem())->dumpFile($path, 'data');
        touch($path, (new \DateTimeImmutable(\sprintf('-%d days', $daysAgo)))->getTimestamp());

        return $path;
    }
}
