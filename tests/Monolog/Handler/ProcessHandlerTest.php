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

namespace CleverAge\UiProcessBundle\Tests\Monolog\Handler;

use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcessHandler::class)]
class ProcessHandlerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/'.uniqid('process_handler_test_', true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
    }

    public function testWarningsAreCountedByDefault(): void
    {
        // Same default as the bundle configuration (logs.report_increment_level)
        $manager = $this->createMock(ProcessExecutionManager::class);
        $manager->expects(self::once())->method('increment')->with('Warning');

        $handler = new ProcessHandler($this->directory, $manager);
        $handler->setFilename('process.log');
        $handler->handle($this->createRecord(Level::Info));
        $handler->handle($this->createRecord(Level::Warning));

        self::assertTrue($handler->hasFilename());
        self::assertStringContainsString('warning message', (string) file_get_contents($this->directory.'/process.log'));
    }

    public function testReportIncrementLevel(): void
    {
        $manager = $this->createMock(ProcessExecutionManager::class);
        $manager->expects(self::once())->method('increment')->with('Error');

        $handler = new ProcessHandler($this->directory, $manager);
        $handler->setReportIncrementLevel('Error');
        $handler->setFilename('process.log');
        $handler->handle($this->createRecord(Level::Warning));
        $handler->handle($this->createRecord(Level::Error));
    }

    public function testNothingIsWrittenWithoutFilename(): void
    {
        $manager = $this->createMock(ProcessExecutionManager::class);
        $manager->expects(self::never())->method('increment');

        $handler = new ProcessHandler($this->directory, $manager);
        $handler->handle($this->createRecord(Level::Error));
        $handler->close();

        self::assertFalse($handler->hasFilename());
        self::assertSame([], glob($this->directory.'/*'));
    }

    public function testFilename(): void
    {
        $handler = new ProcessHandler($this->directory, $this->createStub(ProcessExecutionManager::class));
        self::assertNull($handler->getFilename());

        $handler->setFilename('process.log');
        self::assertSame($this->directory.'/process.log', $handler->getFilename());

        $handler->close();
        self::assertNull($handler->getFilename());
    }

    private function createRecord(Level $level): LogRecord
    {
        return new LogRecord(new \DateTimeImmutable(), 'cleverage_process', $level, strtolower($level->name).' message');
    }
}
