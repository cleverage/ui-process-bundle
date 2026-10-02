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

namespace CleverAge\UiProcessBundle\Tests\Twig\Runtime;

use CleverAge\ProcessBundle\Configuration\ProcessConfiguration;
use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use CleverAge\UiProcessBundle\Twig\Runtime\ProcessExecutionExtensionRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcessExecutionExtensionRuntime::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
#[UsesClass(ProcessExecution::class)]
class ProcessExecutionExtensionRuntimeTest extends TestCase
{
    public function testGetLastExecutionDate(): void
    {
        $execution = new ProcessExecution('demo.process', 'demo.process.log');
        $repository = $this->createMock(ProcessExecutionRepository::class);
        $repository->expects(self::exactly(2))
            ->method('getLastProcessExecution')
            ->willReturnMap([['demo.process', $execution], ['demo.unknown', null]]);

        $runtime = new ProcessExecutionExtensionRuntime($repository, $this->createManager());

        self::assertSame($execution, $runtime->getLastExecutionDate('demo.process'));
        self::assertNull($runtime->getLastExecutionDate('demo.unknown'));
    }

    public function testGetProcessSourceAndTarget(): void
    {
        $runtime = new ProcessExecutionExtensionRuntime(
            $this->createStub(ProcessExecutionRepository::class),
            $this->createManager()
        );

        self::assertSame('Pim', $runtime->getProcessSource('demo.with_ui'));
        self::assertSame('Shop', $runtime->getProcessTarget('demo.with_ui'));
        self::assertNull($runtime->getProcessSource('demo.without_ui'));
        self::assertNull($runtime->getProcessTarget('demo.without_ui'));
        self::assertNull($runtime->getProcessSource('demo.unknown'));
        self::assertNull($runtime->getProcessTarget('demo.unknown'));
    }

    private function createManager(): ProcessConfigurationsManager
    {
        $configurations = [
            'demo.with_ui' => new ProcessConfiguration('demo.with_ui', [], ['ui' => ['source' => 'Pim', 'target' => 'Shop']]),
            'demo.without_ui' => new ProcessConfiguration('demo.without_ui', []),
        ];
        $registry = $this->createStub(ProcessConfigurationRegistry::class);
        $registry->method('hasProcessConfiguration')
            ->willReturnCallback(static fn (string $code): bool => isset($configurations[$code]));
        $registry->method('getProcessConfiguration')
            ->willReturnCallback(static fn (string $code): ProcessConfiguration => $configurations[$code]);

        return new ProcessConfigurationsManager($registry);
    }
}
