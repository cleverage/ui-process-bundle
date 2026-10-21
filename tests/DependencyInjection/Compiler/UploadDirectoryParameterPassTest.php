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

namespace CleverAge\UiProcessBundle\Tests\DependencyInjection\Compiler;

use CleverAge\UiProcessBundle\DependencyInjection\Compiler\UploadDirectoryParameterPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(UploadDirectoryParameterPass::class)]
class UploadDirectoryParameterPassTest extends TestCase
{
    private const SETTING_DEPRECATION = 'Since cleverage/ui-process-bundle 3.3: Setting the "upload_directory" parameter is deprecated, set "cleverage_ui_process.upload_directory" instead. It will be ignored in 4.0.';
    private const READING_DEPRECATION = 'Since cleverage/ui-process-bundle 3.3: The "upload_directory" parameter is deprecated, use "cleverage_ui_process.upload_directory" instead. It will be removed in 4.0.';

    /**
     * @return iterable<string, array{array<string, string>, string, list<string>}>
     */
    public static function provideParameters(): iterable
    {
        yield 'defaults' => [[], '/app/var/storage/uploads', []];
        yield 'new parameter set' => [['cleverage_ui_process.upload_directory' => '/app/var/imports'], '/app/var/imports', []];
        yield 'deprecated parameter set' => [['upload_directory' => '/app/var/legacy'], '/app/var/legacy', [self::SETTING_DEPRECATION]];
        yield 'both set: the new one wins' => [
            ['upload_directory' => '/app/var/legacy', 'cleverage_ui_process.upload_directory' => '/app/var/imports'],
            '/app/var/imports',
            [self::SETTING_DEPRECATION],
        ];
    }

    /**
     * @param array<string, string> $applicationParameters
     * @param list<string>          $expectedDeprecations
     */
    #[DataProvider('provideParameters')]
    public function testProcess(array $applicationParameters, string $expectedDirectory, array $expectedDeprecations): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/app');
        // Default values of the bundle (config/services/parameters.yaml), overridden by the application
        $container->setParameter(UploadDirectoryParameterPass::PARAMETER, UploadDirectoryParameterPass::DEFAULT_VALUE);
        $container->setParameter(UploadDirectoryParameterPass::DEPRECATED_PARAMETER, UploadDirectoryParameterPass::DEPRECATED_DEFAULT_VALUE);
        foreach ($applicationParameters as $name => $value) {
            $container->setParameter($name, $value);
        }

        $deprecations = $this->collectDeprecations(static function () use ($container): void {
            (new UploadDirectoryParameterPass())->process($container);
            $container->getParameterBag()->resolve();
        });
        self::assertSame($expectedDeprecations, $deprecations);
        self::assertSame($expectedDirectory, $container->getParameter(UploadDirectoryParameterPass::PARAMETER));

        // Reading the deprecated parameter
        $deprecations = $this->collectDeprecations(static function () use ($container): void {
            $container->getParameter(UploadDirectoryParameterPass::DEPRECATED_PARAMETER);
        });
        self::assertSame([self::READING_DEPRECATION], $deprecations);
    }

    public function testWithoutDeprecatedParameter(): void
    {
        $container = new ContainerBuilder();

        $deprecations = $this->collectDeprecations(static function () use ($container): void {
            (new UploadDirectoryParameterPass())->process($container);
        });

        self::assertSame([], $deprecations);
        self::assertFalse($container->hasParameter(UploadDirectoryParameterPass::DEPRECATED_PARAMETER));
    }

    /**
     * @param callable(): void $callback
     *
     * @return list<string>
     */
    private function collectDeprecations(callable $callback): array
    {
        $deprecations = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
            $deprecations[] = $errstr;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            $callback();
        } finally {
            restore_error_handler();
        }

        return $deprecations;
    }
}
