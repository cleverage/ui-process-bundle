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

use CleverAge\UiProcessBundle\DependencyInjection\Compiler\CheckLaunchFormsPass;
use CleverAge\UiProcessBundle\Form\LaunchFormOptionsResolver;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Notifier\NotificationTrigger;
use CleverAge\UiProcessBundle\Tests\Form\Fixtures\StringBackedEnum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

#[CoversClass(CheckLaunchFormsPass::class)]
#[UsesClass(LaunchFormOptionsResolver::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
#[UsesClass(NotificationTrigger::class)]
class CheckLaunchFormsPassTest extends TestCase
{
    /**
     * @param array<string, mixed> $ui
     */
    #[DataProvider('provideValidUiOptions')]
    public function testValidLaunchForms(array $ui): void
    {
        $container = $this->createContainer(['test.process' => $this->rawProcess($ui)]);

        $deprecations = $this->collectDeprecations(static fn () => (new CheckLaunchFormsPass())->process($container));

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
                'input' => ['type' => FileType::class],
                'context' => [
                    'delimiter' => ['type' => ChoiceType::class, 'default' => ',', 'options' => ['choices' => [',' => ',', ';' => ';']]],
                    'enum' => ['type' => EnumType::class, 'default' => StringBackedEnum::Comma, 'options' => ['class' => StringBackedEnum::class]],
                ],
                'allow_extra_context' => false,
                'constraints' => [['NotNull' => null]],
            ],
        ]];
        yield 'form with deprecated options' => [['form' => ['context' => ['delimiter' => null]], 'default' => ['context' => [['key' => 'foo', 'value' => 'bar']]]]];
        // Not checked: the process has no "form" option (the deprecated options are checked as before 3.3)
        yield 'invalid deprecated option without form' => [['entrypoint_type' => 'csv']];
    }

    /**
     * @param array<string, mixed> $ui
     */
    #[DataProvider('provideInvalidUiOptions')]
    public function testInvalidLaunchForms(array $ui, string $message): void
    {
        $container = $this->createContainer(['valid.process' => $this->rawProcess(), 'test.process' => $this->rawProcess($ui)]);

        try {
            (new CheckLaunchFormsPass())->process($container);
            self::fail('An invalid launch form is expected to fail the compilation.');
        } catch (InvalidArgumentException $e) {
            self::assertSame(\sprintf('The launch form ("form" UI option) of the process "test.process" is invalid: %s', $message), $e->getMessage());
            self::assertInstanceOf(InvalidOptionsException::class, $e->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidUiOptions(): iterable
    {
        yield 'unknown type' => [
            ['form' => ['input' => ['type' => 'file']]],
            'The type "file" of the launch form field "form.input" of the process "test.process" must be the FQCN of a form type, the class does not exist.',
        ];
        yield 'file in the context' => [
            ['form' => ['context' => ['data' => ['type' => FileType::class]]]],
            'The launch form field "form.context.data" of the process "test.process" is a file field: file fields are only supported for the input.',
        ];
        yield 'scalar enum default' => [
            ['form' => ['context' => ['enum' => ['type' => EnumType::class, 'default' => ',', 'options' => ['class' => StringBackedEnum::class]]]]],
            \sprintf('The default value of the launch form field "form.context.enum" of the process "test.process" must be a case of the enum "%s" (with "multiple", a list of cases), "string" given: use the "!php/enum" YAML tag, e.g. "default: !php/enum App\Enum\Status::Active".', StringBackedEnum::class),
        ];
        yield 'conflict with a deprecated option' => [
            ['entrypoint_type' => 'file', 'form' => ['input' => ['type' => FileType::class]]],
            'The "entrypoint_type" and "form.input" UI options of the process "test.process" cannot be used together, use "form.input" only.',
        ];
        yield 'not an array' => [['form' => 'file'], 'The "form" UI option of the process "test.process" must be an array, "string" given.'];
    }

    /**
     * Only the UI options are checked: the tasks and the other options of the process are not concerned.
     */
    #[DoesNotPerformAssertions]
    public function testOtherOptionsAreNotChecked(): void
    {
        /** @var array<string, mixed> $process */
        $process = array_replace_recursive($this->rawProcess(['form' => ['context' => ['delimiter' => null]]]), [
            'options' => ['notification' => ['enabled' => 'yes']],
            'tasks' => ['data' => ['outputs' => ['missing']]],
        ]);
        $container = $this->createContainer(['test.process' => $process]);

        (new CheckLaunchFormsPass())->process($container);
    }

    public function testParametersAreResolved(): void
    {
        $container = $this->createContainer(['test.process' => $this->rawProcess(['form' => ['input' => ['type' => '%input_type%']]])]);
        $container->setParameter('input_type', 'file');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The type "file" of the launch form field "form.input"');

        (new CheckLaunchFormsPass())->process($container);
    }

    /**
     * A launch form using an environment variable is resolved when it is displayed.
     */
    public function testEnvironmentVariablesAreNotResolved(): void
    {
        $container = new ContainerBuilder();
        // The placeholder of an environment variable, as resolved by the configuration of the process bundle
        $type = $container->getParameterBag()->get('env(INPUT_TYPE)');
        self::assertIsString($type);
        $container->setDefinition(CheckLaunchFormsPass::REGISTRY_ID, new Definition(\stdClass::class, [
            ['test.process' => $this->rawProcess(['form' => ['input' => ['type' => $type]]])],
            'stop',
            [],
        ]));

        (new CheckLaunchFormsPass())->process($container);
    }

    #[DoesNotPerformAssertions]
    public function testWithoutTheProcessBundle(): void
    {
        (new CheckLaunchFormsPass())->process(new ContainerBuilder());
    }

    /**
     * @param array<string, array<string, mixed>> $configurations
     */
    private function createContainer(array $configurations): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(CheckLaunchFormsPass::REGISTRY_ID, new Definition(\stdClass::class, [$configurations, 'stop', []]));

        return $container;
    }

    /**
     * Raw configuration of a process with a single task, as the process bundle configuration would give it.
     *
     * @param array<string, mixed> $ui
     *
     * @return array<string, mixed>
     */
    private function rawProcess(array $ui = []): array
    {
        return [
            'options' => [] === $ui ? [] : ['ui' => $ui],
            'entry_point' => null,
            'end_point' => null,
            'description' => '',
            'help' => '',
            'public' => true,
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
