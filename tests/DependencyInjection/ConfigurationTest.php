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

namespace CleverAge\UiProcessBundle\Tests\DependencyInjection;

use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
use CleverAge\UiProcessBundle\Notifier\NotificationTrigger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(Configuration::class)]
#[UsesClass(NotificationTrigger::class)]
class ConfigurationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideEnvironments(): iterable
    {
        yield 'dev' => ['dev', 'Debug'];
        yield 'prod' => ['prod', 'Info'];
        yield 'test' => ['test', 'Info'];
    }

    #[DataProvider('provideEnvironments')]
    public function testDefaultConfiguration(string $env, string $expectedLevel): void
    {
        self::assertSame(
            [
                'security' => [
                    'roles' => ['ROLE_ADMIN'],
                ],
                'logs' => [
                    'store_in_database' => true,
                    'database_level' => $expectedLevel,
                    'file_level' => $expectedLevel,
                    'report_increment_level' => 'Warning',
                ],
                'design' => [
                    'logo_path' => 'bundles/cleverageuiprocess/logo.jpg',
                ],
                'notification' => [
                    'enabled' => false,
                    'statuses' => ['failed', 'finish_with_report'],
                    'channels' => [],
                    'recipients' => [],
                ],
            ],
            $this->process($env, [])
        );
    }

    public function testRootName(): void
    {
        self::assertSame(
            'clever_age_ui_process',
            (new Configuration('prod'))->getConfigTreeBuilder()->buildTree()->getName()
        );
    }

    public function testCustomConfiguration(): void
    {
        self::assertSame(
            [
                'security' => [
                    'roles' => ['ROLE_ADMIN', 'ROLE_OPERATOR'],
                ],
                'logs' => [
                    'store_in_database' => false,
                    'database_level' => 'Error',
                    'file_level' => 'Notice',
                    'report_increment_level' => 'Critical',
                ],
                'design' => [
                    'logo_path' => 'images/my-logo.png',
                ],
                'notification' => [
                    'enabled' => true,
                    'statuses' => ['failed', 'finish'],
                    'channels' => ['chat/slack', 'email'],
                    'recipients' => [
                        ['email' => 'ops@example.com', 'phone' => null],
                        ['phone' => '+33600000000', 'email' => null],
                    ],
                ],
            ],
            $this->process('dev', [
                'security' => ['roles' => ['ROLE_ADMIN', 'ROLE_OPERATOR']],
                'logs' => [
                    'store_in_database' => false,
                    'database_level' => 'Error',
                    'file_level' => 'Notice',
                    'report_increment_level' => 'Critical',
                ],
                'design' => ['logo_path' => 'images/my-logo.png'],
                'notification' => [
                    'enabled' => true,
                    'statuses' => ['failed', 'finish'],
                    'channels' => ['chat/slack', 'email'],
                    'recipients' => [['email' => 'ops@example.com'], ['phone' => '+33600000000']],
                ],
            ])
        );
    }

    public function testPartialConfigurationKeepsOtherDefaults(): void
    {
        $config = $this->process('dev', ['logs' => ['file_level' => 'Error']]);

        self::assertSame(
            [
                'file_level' => 'Error',
                'store_in_database' => true,
                'database_level' => 'Debug',
                'report_increment_level' => 'Warning',
            ],
            $config['logs']
        );
        self::assertSame(['roles' => ['ROLE_ADMIN']], $config['security']);
    }

    public function testLastConfigurationWins(): void
    {
        $processor = new Processor();
        /** @var array<string, array<string, mixed>> $config */
        $config = $processor->processConfiguration(new Configuration('prod'), [
            ['design' => ['logo_path' => 'first.png'], 'logs' => ['store_in_database' => false]],
            ['design' => ['logo_path' => 'second.png']],
        ]);

        self::assertSame('second.png', $config['design']['logo_path']);
        self::assertFalse($config['logs']['store_in_database']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidConfigurations(): iterable
    {
        yield 'unknown root key' => [['unknown' => true], 'Unrecognized option "unknown"'];
        yield 'unknown logs key' => [['logs' => ['level' => 'Debug']], 'Unrecognized option "level"'];
        yield 'store_in_database not a boolean' => [
            ['logs' => ['store_in_database' => 'yes']],
            'clever_age_ui_process.logs.store_in_database',
        ];
        yield 'roles not an array' => [['security' => ['roles' => 'ROLE_ADMIN']], 'clever_age_ui_process.security.roles'];
        yield 'role not a scalar' => [['security' => ['roles' => [['ROLE_ADMIN']]]], 'clever_age_ui_process.security.roles'];
        yield 'logo_path not a scalar' => [['design' => ['logo_path' => ['a.png']]], 'clever_age_ui_process.design.logo_path'];
        yield 'unknown notification status' => [
            ['notification' => ['statuses' => ['started']]],
            'clever_age_ui_process.notification.statuses',
        ];
        yield 'notification recipient without email nor phone' => [
            ['notification' => ['recipients' => [[]]]],
            'A notification recipient must have an "email" or a "phone".',
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideInvalidConfigurations')]
    public function testInvalidConfiguration(array $config, string $expectedMessage): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->process('prod', $config);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(string $env, array $config): array
    {
        /** @var array<string, mixed> $processedConfig */
        $processedConfig = (new Processor())->processConfiguration(new Configuration($env), [$config]);

        return $processedConfig;
    }
}
