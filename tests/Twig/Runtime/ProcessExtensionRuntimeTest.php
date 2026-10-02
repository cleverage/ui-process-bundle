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
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Twig\Runtime\ProcessExtensionRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcessExtensionRuntime::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
class ProcessExtensionRuntimeTest extends TestCase
{
    public function testGetUiOptions(): void
    {
        $configuration = new ProcessConfiguration('demo.process', [], ['ui' => ['source' => 'Pim', 'entrypoint_type' => 'file']]);
        $registry = $this->createStub(ProcessConfigurationRegistry::class);
        $registry->method('hasProcessConfiguration')
            ->willReturnCallback(static fn (string $code): bool => 'demo.process' === $code);
        $registry->method('getProcessConfiguration')->willReturn($configuration);

        $runtime = new ProcessExtensionRuntime(new ProcessConfigurationsManager($registry));

        $options = $runtime->getUiOptions('demo.process');

        // "default" is not compared: its nested resolution depends on the symfony/options-resolver version
        self::assertArrayHasKey('default', $options);
        unset($options['default']);
        self::assertEquals([
            'source' => 'Pim',
            'target' => null,
            'entrypoint_type' => 'file',
            'ui_launch_mode' => 'modal',
            'constraints' => [],
            'run' => null,
        ], $options);
        self::assertSame([], $runtime->getUiOptions('demo.unknown'));
    }
}
