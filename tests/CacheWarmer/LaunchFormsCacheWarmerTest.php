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

namespace CleverAge\UiProcessBundle\Tests\CacheWarmer;

use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\UiProcessBundle\CacheWarmer\LaunchFormsCacheWarmer;
use CleverAge\UiProcessBundle\Form\LaunchFormOptionsResolver;
use CleverAge\UiProcessBundle\Form\Type\LaunchType;
use CleverAge\UiProcessBundle\Form\Type\ProcessContextType;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Tests\App\Validator\Even;
use CleverAge\UiProcessBundle\Tests\App\Validator\WithoutValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\Validator\ContainerConstraintValidatorFactory;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;

#[CoversClass(LaunchFormsCacheWarmer::class)]
#[UsesClass(LaunchFormOptionsResolver::class)]
#[UsesClass(LaunchType::class)]
#[UsesClass(ProcessContextType::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
class LaunchFormsCacheWarmerTest extends TestCase
{
    public function testIsNotOptional(): void
    {
        self::assertFalse($this->createWarmer([])->isOptional());
    }

    /**
     * @param array<string, mixed> $ui
     */
    #[DataProvider('provideValidUiOptions')]
    public function testValidLaunchForms(array $ui): void
    {
        $warmer = $this->createWarmer(['test.process' => $this->rawProcess($ui), 'private.process' => $this->rawProcess($ui, false)]);

        $deprecations = $this->collectDeprecations(static fn () => self::assertSame([], $warmer->warmUp(sys_get_temp_dir())));

        // The deprecations are triggered when the launch form is displayed
        self::assertSame([], $deprecations);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideValidUiOptions(): iterable
    {
        yield 'no UI options' => [[]];
        yield 'form' => [[
            'ui_launch_mode' => 'form',
            'form' => [
                'input' => ['type' => FileType::class, 'options' => ['label' => 'CSV file', 'constraints' => [['NotNull' => null]]]],
                'context' => [
                    'delimiter' => ['type' => ChoiceType::class, 'default' => ',', 'options' => ['choices' => [',' => ',', ';' => ';']]],
                    'count' => ['type' => IntegerType::class, 'default' => 2, 'options' => ['constraints' => [['Positive' => null], [Even::class => null]]]],
                    'from' => ['type' => DateType::class, 'default' => '2026-01-02', 'options' => ['widget' => 'single_text', 'input' => 'string']],
                ],
                'constraints' => [['Collection' => ['fields' => ['context' => [['Collection' => ['fields' => ['delimiter' => [['NotBlank' => null]]], 'allowExtraFields' => true]]]], 'allowExtraFields' => true]]],
            ],
        ]];
        yield 'form with deprecated options' => [['form' => ['context' => ['delimiter' => null]], 'default' => ['input' => 'data.csv', 'context' => [['key' => 'foo', 'value' => 'bar']]]]];
        // Not checked: the process has no "form" option
        yield 'invalid deprecated option without form' => [['entrypoint_type' => 'text', 'default' => ['input' => ['not a string']]]];
    }

    /**
     * @param array<string, mixed>     $form
     * @param class-string<\Throwable> $previous
     */
    #[DataProvider('provideInvalidLaunchForms')]
    public function testInvalidLaunchForms(array $form, string $previous, string $message): void
    {
        $warmer = $this->createWarmer(['valid.process' => $this->rawProcess(), 'test.process' => $this->rawProcess(['form' => $form])]);

        try {
            $warmer->warmUp(sys_get_temp_dir());
            self::fail('An invalid launch form is expected to fail the warmup.');
        } catch (\LogicException $e) {
            self::assertStringStartsWith('The launch form ("form" UI option) of the process "test.process" is invalid: ', $e->getMessage());
            self::assertStringContainsString($message, $e->getMessage());
            self::assertInstanceOf($previous, $e->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, class-string<\Throwable>, string}>
     */
    public static function provideInvalidLaunchForms(): iterable
    {
        yield 'unknown option of the type' => [
            ['context' => ['comment' => ['type' => TextType::class, 'options' => ['choices' => ['a' => 'a']]]]],
            UndefinedOptionsException::class,
            'The option "choices" does not exist.',
        ];
        yield 'invalid option value' => [
            ['context' => ['delimiter' => ['type' => ChoiceType::class, 'options' => ['choices' => ',']]]],
            InvalidOptionsException::class,
            'The option "choices" with value "," is expected to be of type',
        ];
        yield 'default value that cannot be transformed' => [
            ['context' => ['from' => ['type' => DateType::class, 'default' => 'yesterday', 'options' => ['widget' => 'single_text']]]],
            TransformationFailedException::class,
            '',
        ];
        yield 'missing validator of a field constraint' => [
            ['context' => ['comment' => ['options' => ['constraints' => [[WithoutValidator::class => null]]]]]],
            ValidatorException::class,
            'App\Validator\MissingValidator',
        ];
        yield 'missing validator of a nested constraint' => [
            ['constraints' => [['Collection' => ['fields' => ['input' => [[WithoutValidator::class => null]]], 'allowExtraFields' => true]]]],
            ValidatorException::class,
            'App\Validator\MissingValidator',
        ];
    }

    /**
     * Without the validator component enabled, the validators of the constraints are not checked.
     */
    #[DoesNotPerformAssertions]
    public function testWithoutConstraintValidatorFactory(): void
    {
        $this->createWarmer(['test.process' => $this->rawProcess(['form' => ['context' => ['comment' => ['options' => ['constraints' => [[WithoutValidator::class => null]]]]]]])], false)
            ->warmUp(sys_get_temp_dir());
    }

    /**
     * @param array<string, array<string, mixed>> $configurations
     */
    private function createWarmer(array $configurations, bool $withValidatorFactory = true): LaunchFormsCacheWarmer
    {
        $registry = new ProcessConfigurationRegistry($configurations, 'stop');
        $manager = new ProcessConfigurationsManager($registry);
        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addType(new LaunchType($registry, $manager))
            ->getFormFactory();

        return new LaunchFormsCacheWarmer($manager, $formFactory, $withValidatorFactory ? new ContainerConstraintValidatorFactory(new Container()) : null);
    }

    /**
     * Raw configuration of a process with a single task, as the process bundle configuration would give it.
     *
     * @param array<string, mixed> $ui
     *
     * @return array<string, mixed>
     */
    private function rawProcess(array $ui = [], bool $public = true): array
    {
        return [
            'options' => [] === $ui ? [] : ['ui' => $ui],
            'entry_point' => 'data',
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

    /**
     * @return list<string>
     */
    private function collectDeprecations(\Closure $callback): array
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
