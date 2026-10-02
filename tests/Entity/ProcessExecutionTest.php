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

use CleverAge\UiProcessBundle\Entity\Enum\ProcessExecutionStatus;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcessExecution::class)]
class ProcessExecutionTest extends TestCase
{
    public function testConstruct(): void
    {
        $before = new \DateTimeImmutable();
        $execution = new ProcessExecution('demo.process', 'demo.process.log', ['key' => 'value']);
        $after = new \DateTimeImmutable();

        self::assertNull($execution->getId());
        self::assertSame('demo.process', $execution->code);
        self::assertSame('demo.process', $execution->getCode());
        self::assertSame('demo.process.log', $execution->logFilename);
        self::assertSame(['key' => 'value'], $execution->getContext());
        self::assertSame(ProcessExecutionStatus::Started, $execution->status);
        self::assertNull($execution->endDate);
        self::assertSame([], $execution->getReport());
        self::assertGreaterThanOrEqual($before, $execution->startDate);
        self::assertLessThanOrEqual($after, $execution->startDate);
    }

    public function testCodeIsTruncatedTo255Characters(): void
    {
        $execution = new ProcessExecution(str_repeat('a', 300), 'demo.process.log');

        self::assertSame(str_repeat('a', 255), $execution->getCode());
    }

    public function testContextDefaultsToEmptyArray(): void
    {
        self::assertSame([], (new ProcessExecution('demo.process', 'demo.process.log'))->getContext());
        self::assertSame([], (new ProcessExecution('demo.process', 'demo.process.log', null))->getContext());
    }

    public function testSetContext(): void
    {
        $execution = new ProcessExecution('demo.process', 'demo.process.log');
        $execution->setContext(['foo' => 'bar']);

        self::assertSame(['foo' => 'bar'], $execution->getContext());
    }

    public function testToString(): void
    {
        $execution = new ProcessExecution('demo.process', 'demo.process.log');
        self::assertSame(' (demo.process)', (string) $execution);

        (new \ReflectionProperty(ProcessExecution::class, 'id'))->setValue($execution, 42);
        self::assertSame(42, $execution->getId());
        self::assertSame('42 (demo.process)', (string) $execution);
    }

    public function testSetStatus(): void
    {
        $execution = new ProcessExecution('demo.process', 'demo.process.log');
        $execution->setStatus(ProcessExecutionStatus::Failed);

        self::assertSame(ProcessExecutionStatus::Failed, $execution->status);
    }

    public function testEnd(): void
    {
        $execution = new ProcessExecution('demo.process', 'demo.process.log');
        $execution->end();

        self::assertInstanceOf(\DateTimeImmutable::class, $execution->endDate);
        self::assertGreaterThanOrEqual($execution->startDate, $execution->endDate);
    }

    public function testReport(): void
    {
        $execution = new ProcessExecution('demo.process', 'demo.process.log');
        $execution->addReport('Warning', 3);
        $execution->addReport('Error', 1);
        $execution->addReport('Warning', 4);

        self::assertSame(['Warning' => 4, 'Error' => 1], $execution->getReport());
        self::assertSame(4, $execution->getReport('Warning'));
        self::assertNull($execution->getReport('Info'));
        self::assertSame(0, $execution->getReport('Info', 0));
    }

    public function testDuration(): void
    {
        $execution = new ProcessExecution('demo.process', 'demo.process.log');
        self::assertNull($execution->duration());

        $execution->endDate = $execution->startDate->modify('+1 hour +2 minutes +3 seconds');
        self::assertSame('01 hour(s) 02 min(s) 03 s', $execution->duration());
        self::assertSame('1:2:3', $execution->duration('%h:%i:%s'));
    }
}
