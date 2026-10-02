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

namespace CleverAge\UiProcessBundle\Tests\Entity;

use CleverAge\UiProcessBundle\Entity\LogRecord;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;

#[CoversClass(LogRecord::class)]
#[UsesClass(ProcessExecution::class)]
class LogRecordTest extends TestCase
{
    public function testHasContextInfo(): void
    {
        self::assertTrue($this->createLogRecord(['file' => 'a.txt'])->hasContextInfo());
        self::assertFalse($this->createLogRecord([])->hasContextInfo());
    }

    public function testContextIsReadableByThePropertyAccessor(): void
    {
        // EasyAdmin reads the fields with the PropertyAccessor: a hasContext() method would be returned for "context"
        $context = PropertyAccess::createPropertyAccessor()->getValue($this->createLogRecord(['file' => 'a.txt']), 'context');

        self::assertSame(['file' => 'a.txt'], $context);
    }

    public function testDeprecatedContextIsEmptyIsUnchanged(): void
    {
        // Misnamed: returns true when the context is NOT empty
        self::assertTrue($this->createLogRecord(['file' => 'a.txt'])->contextIsEmpty());
        self::assertFalse($this->createLogRecord([])->contextIsEmpty());
    }

    /**
     * @param array<mixed> $context
     */
    private function createLogRecord(array $context): LogRecord
    {
        return new LogRecord(
            new \Monolog\LogRecord(new \DateTimeImmutable(), 'cleverage_process', Level::Info, 'message', $context),
            new ProcessExecution('demo.process', 'demo.process.log')
        );
    }
}
