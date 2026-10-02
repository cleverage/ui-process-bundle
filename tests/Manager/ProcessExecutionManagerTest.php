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

use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcessExecutionManager::class)]
#[UsesClass(ProcessExecution::class)]
class ProcessExecutionManagerTest extends TestCase
{
    public function testNoCurrentProcessExecutionByDefault(): void
    {
        $repository = $this->createMock(ProcessExecutionRepository::class);
        $repository->expects(self::never())->method('save');

        $manager = new ProcessExecutionManager($repository);
        $manager->increment('Warning');
        $manager->setReport('key', 'value');

        self::assertNull($manager->getCurrentProcessExecution());
        self::assertSame($manager, $manager->save());
        self::assertSame($manager, $manager->unsetProcessExecution('test.process'));
    }

    public function testTheFirstProcessExecutionIsKept(): void
    {
        $first = new ProcessExecution('first', 'first.log');
        $manager = new ProcessExecutionManager($this->createStub(ProcessExecutionRepository::class));

        self::assertSame($manager, $manager->setCurrentProcessExecution($first));
        $manager->setCurrentProcessExecution(new ProcessExecution('second', 'second.log'));

        self::assertSame($first, $manager->getCurrentProcessExecution());
    }

    public function testUnsetProcessExecutionOnlyForItsCode(): void
    {
        $processExecution = new ProcessExecution('test.process', 'test.log');
        $manager = new ProcessExecutionManager($this->createStub(ProcessExecutionRepository::class));
        $manager->setCurrentProcessExecution($processExecution);

        $manager->unsetProcessExecution('other.process');
        self::assertSame($processExecution, $manager->getCurrentProcessExecution());

        $manager->unsetProcessExecution('test.process');
        self::assertNull($manager->getCurrentProcessExecution());

        $other = new ProcessExecution('other.process', 'other.log');
        $manager->setCurrentProcessExecution($other);
        self::assertSame($other, $manager->getCurrentProcessExecution());
    }

    public function testSaveTheCurrentProcessExecution(): void
    {
        $processExecution = new ProcessExecution('test.process', 'test.log');
        $repository = $this->createMock(ProcessExecutionRepository::class);
        $repository->expects(self::once())->method('save')->with($processExecution);

        $manager = new ProcessExecutionManager($repository);
        $manager->setCurrentProcessExecution($processExecution);

        self::assertSame($manager, $manager->save());
    }

    public function testReports(): void
    {
        $processExecution = new ProcessExecution('test.process', 'test.log');
        $manager = new ProcessExecutionManager($this->createStub(ProcessExecutionRepository::class));
        $manager->setCurrentProcessExecution($processExecution);

        $manager->increment('Warning');
        $manager->increment('Warning');
        $manager->increment('Error', 5);
        $manager->setReport('file', 'data.csv');

        self::assertSame(['Warning' => 2, 'Error' => 5, 'file' => 'data.csv'], $processExecution->getReport());
    }
}
