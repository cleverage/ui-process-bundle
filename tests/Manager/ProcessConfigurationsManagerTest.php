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

use CleverAge\ProcessBundle\Configuration\ProcessConfiguration;
use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Notifier\NotificationTrigger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface as OptionsResolverException;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

#[CoversClass(ProcessConfigurationsManager::class)]
#[UsesClass(NotificationTrigger::class)]
class ProcessConfigurationsManagerTest extends TestCase
{
    public function testPublicAndPrivateProcesses(): void
    {
        $manager = $this->createManager([
            'public.one' => $this->rawProcess(),
            'private.one' => $this->rawProcess(public: false),
            'public.two' => $this->rawProcess(),
        ]);

        $codes = static fn (array $configurations): array => array_values(
            array_map(static fn (ProcessConfiguration $configuration): string => $configuration->getCode(), $configurations)
        );

        self::assertSame(['public.one', 'public.two'], $codes($manager->getPublicProcesses()));
        self::assertSame(['private.one'], $codes($manager->getPrivateProcesses()));
    }

    public function testUiOptionsOfAnUnknownProcess(): void
    {
        self::assertNull($this->createManager(['test.process' => $this->rawProcess()])->getUiOptions('unknown'));
    }

    public function testDefaultUiOptions(): void
    {
        $options = $this->createManager(['test.process' => $this->rawProcess()])->getUiOptions('test.process');

        self::assertNotNull($options);
        self::assertNull($options['source']);
        self::assertNull($options['target']);
        self::assertSame('text', $options['entrypoint_type']);
        self::assertSame('modal', $options['ui_launch_mode']);
        self::assertSame([], $options['constraints']);
        /** @var mixed $run the PHPDoc type of the option is wrong */
        $run = $options['run'];
        self::assertNull($run);
        self::assertSame(['input' => null, 'context' => []], $options['default']);
    }

    public function testConfiguredUiOptions(): void
    {
        $manager = $this->createManager([
            'test.process' => $this->rawProcess([
                'ui' => [
                    'source' => 'source',
                    'target' => 'target',
                    'entrypoint_type' => 'file',
                    'ui_launch_mode' => 'form',
                    'constraints' => [['NotBlank' => null], ['Length' => ['max' => 10]]],
                    'run' => true,
                    'default' => [
                        'input' => 'data.csv',
                        'context' => [['key' => 'foo', 'value' => 'bar']],
                    ],
                ],
            ]),
        ]);

        $options = $manager->getUiOptions('test.process');

        self::assertNotNull($options);
        self::assertSame('source', $options['source']);
        self::assertSame('target', $options['target']);
        self::assertSame('file', $options['entrypoint_type']);
        self::assertSame('form', $options['ui_launch_mode']);
        /** @var mixed $run the PHPDoc type of the option is wrong */
        $run = $options['run'];
        self::assertTrue($run);
        self::assertSame(['input' => 'data.csv', 'context' => [['key' => 'foo', 'value' => 'bar']]], $options['default']);
        self::assertCount(2, $options['constraints']);
        self::assertInstanceOf(NotBlank::class, $options['constraints'][0]);
        self::assertInstanceOf(Length::class, $options['constraints'][1]);
        self::assertSame(10, $options['constraints'][1]->max);
    }

    public function testDefaultWithoutContext(): void
    {
        $manager = $this->createManager([
            'test.process' => $this->rawProcess(['ui' => ['default' => ['input' => 'data.csv']]]),
        ]);

        $options = $manager->getUiOptions('test.process');

        self::assertNotNull($options);
        self::assertSame(['input' => 'data.csv', 'context' => []], $options['default']);
    }

    /**
     * @param array<string, mixed>                   $default
     * @param class-string<OptionsResolverException> $exception
     */
    #[DataProvider('provideInvalidDefault')]
    public function testInvalidDefault(array $default, string $exception): void
    {
        $manager = $this->createManager(['test.process' => $this->rawProcess(['ui' => ['default' => $default]])]);

        $this->expectException($exception);
        $manager->getUiOptions('test.process');
    }

    /**
     * @return iterable<string, array{array<string, mixed>, class-string<OptionsResolverException>}>
     */
    public static function provideInvalidDefault(): iterable
    {
        yield 'unknown option' => [['inputs' => 'data.csv'], UndefinedOptionsException::class];
        yield 'context is not an array' => [['context' => 'foo'], InvalidOptionsException::class];
        yield 'context item is not an array' => [['context' => ['foo']], InvalidOptionsException::class];
        yield 'context item without value' => [['context' => [['key' => 'foo']]], MissingOptionsException::class];
        yield 'context item with an unknown option' => [
            ['context' => [['key' => 'foo', 'value' => 'bar', 'type' => 'string']]],
            UndefinedOptionsException::class,
        ];
    }

    public function testDefaultIsNotAnArray(): void
    {
        $manager = $this->createManager(['test.process' => $this->rawProcess(['ui' => ['default' => 'data.csv']])]);

        $this->expectException(InvalidOptionsException::class);
        $manager->getUiOptions('test.process');
    }

    public function testInvalidEntrypointType(): void
    {
        $manager = $this->createManager(['test.process' => $this->rawProcess(['ui' => ['entrypoint_type' => 'csv']])]);

        $this->expectException(InvalidOptionsException::class);
        $manager->getUiOptions('test.process');
    }

    public function testInvalidLaunchMode(): void
    {
        $manager = $this->createManager(['test.process' => $this->rawProcess(['ui' => ['ui_launch_mode' => 'popup']])]);

        $this->expectException(InvalidOptionsException::class);
        $manager->getUiOptions('test.process');
    }

    public function testNotificationOptionsOfAnUnknownProcess(): void
    {
        self::assertNull($this->createManager(['test.process' => $this->rawProcess()])->getNotificationOptions('unknown'));
    }

    public function testDefaultNotificationOptions(): void
    {
        self::assertSame(
            ['enabled' => null, 'statuses' => null, 'channels' => null, 'recipients' => null],
            $this->createManager(['test.process' => $this->rawProcess()])->getNotificationOptions('test.process')
        );
    }

    public function testConfiguredNotificationOptions(): void
    {
        $manager = $this->createManager(['test.process' => $this->rawProcess([
            'ui' => ['source' => 'ERP'],
            'notification' => [
                'enabled' => true,
                'statuses' => ['finish'],
                'channels' => ['chat/slack'],
                'recipients' => [['email' => 'ops@example.com'], ['phone' => '+33600000000']],
            ],
        ])]);

        self::assertSame(
            [
                'enabled' => true,
                'statuses' => ['finish'],
                'channels' => ['chat/slack'],
                'recipients' => [['email' => 'ops@example.com', 'phone' => null], ['email' => null, 'phone' => '+33600000000']],
            ],
            $manager->getNotificationOptions('test.process')
        );
        // The "notification" option is accepted next to the "ui" one
        self::assertSame('ERP', $manager->getUiOptions('test.process')['source'] ?? null);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, class-string<\Throwable>}>
     */
    public static function provideInvalidNotificationOptions(): iterable
    {
        yield 'not an array' => [['notification' => true], InvalidOptionsException::class];
        yield 'unknown key' => [['notification' => ['level' => 'Error']], UndefinedOptionsException::class];
        yield 'enabled not a boolean' => [['notification' => ['enabled' => 'yes']], InvalidOptionsException::class];
        yield 'unknown status' => [['notification' => ['statuses' => ['started']]], InvalidOptionsException::class];
        yield 'channels not an array' => [['notification' => ['channels' => 'email']], InvalidOptionsException::class];
        yield 'recipient without email nor phone' => [['notification' => ['recipients' => [[]]]], InvalidOptionsException::class];
        yield 'recipient unknown key' => [['notification' => ['recipients' => [['name' => 'Ops']]]], UndefinedOptionsException::class];
    }

    /**
     * @param array<string, mixed>     $options
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('provideInvalidNotificationOptions')]
    public function testInvalidNotificationOptions(array $options, string $exception): void
    {
        $manager = $this->createManager(['test.process' => $this->rawProcess($options)]);

        $this->expectException($exception);
        $manager->getNotificationOptions('test.process');
    }

    /**
     * @param array<string, array<string, mixed>> $rawConfiguration
     */
    private function createManager(array $rawConfiguration): ProcessConfigurationsManager
    {
        return new ProcessConfigurationsManager(new ProcessConfigurationRegistry($rawConfiguration, 'stop'));
    }

    /**
     * Raw configuration of a process with a single task, as the process bundle configuration would give it.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function rawProcess(array $options = [], bool $public = true): array
    {
        return [
            'options' => $options,
            'entry_point' => null,
            'end_point' => null,
            'description' => '',
            'help' => '',
            'public' => $public,
            'tasks' => [
                'data' => [
                    'service' => '@CleverAge\ProcessBundle\Task\DummyTask',
                    'options' => [],
                    'description' => '',
                    'help' => '',
                    'outputs' => [],
                    'errors' => [],
                    'error_outputs' => [],
                    'error_strategy' => null,
                    'log_level' => null,
                ],
            ],
        ];
    }
}
