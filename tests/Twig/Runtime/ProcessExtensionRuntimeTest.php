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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;

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

        $options = [];
        $deprecations = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
            $deprecations[] = $errstr;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            $options = $runtime->getUiOptions('demo.process');
        } finally {
            restore_error_handler();
        }

        self::assertSame(['Since cleverage/ui-process-bundle 3.3: The "entrypoint_type" UI option of the process "demo.process" is deprecated, use "form.input.type" instead.'], $deprecations);
        // "default" is not compared: its nested resolution depends on the symfony/options-resolver version
        self::assertArrayHasKey('default', $options);
        unset($options['default']);
        // "form" is the effective launch form, the deprecated options folded into it
        self::assertArrayHasKey('form', $options);
        unset($options['form']);
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

    /**
     * A process with the "form" option: the effective launch form is returned, without deprecation.
     */
    public function testGetUiOptionsWithAForm(): void
    {
        $configuration = new ProcessConfiguration('demo.process', [], ['ui' => ['ui_launch_mode' => 'form', 'form' => [
            'input' => ['type' => FileType::class],
            'context' => ['delimiter' => ['type' => ChoiceType::class, 'default' => ',', 'options' => ['choices' => [',' => ',']]]],
            'allow_extra_context' => false,
        ]]]);
        $registry = $this->createStub(ProcessConfigurationRegistry::class);
        $registry->method('hasProcessConfiguration')->willReturn(true);
        $registry->method('getProcessConfiguration')->willReturn($configuration);
        $runtime = new ProcessExtensionRuntime(new ProcessConfigurationsManager($registry));

        $options = [];
        $deprecations = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
            $deprecations[] = $errstr;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            $options = $runtime->getUiOptions('demo.process');
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $deprecations);
        if ([] === $options) {
            self::fail('The UI options of a known process are expected.');
        }
        self::assertSame('form', $options['ui_launch_mode']);
        self::assertSame(
            [
                'input' => ['type' => FileType::class, 'options' => [], 'default' => null],
                'context' => ['delimiter' => ['type' => ChoiceType::class, 'options' => ['choices' => [',' => ',']], 'default' => ',']],
                'allow_extra_context' => false,
                'constraints' => [],
            ],
            $options['form']
        );
        // The deprecated options keep their default values
        self::assertSame('text', $options['entrypoint_type']);
        self::assertSame([], $options['constraints']);
    }
}
