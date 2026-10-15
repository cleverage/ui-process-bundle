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
use CleverAge\ProcessBundle\Exception\MissingProcessException;
use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\UiProcessBundle\Form\Type\ProcessContextType;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Notifier\NotificationTrigger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface as OptionsResolverException;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @phpstan-import-type UiOptions from ProcessConfigurationsManager
 */
#[CoversClass(ProcessConfigurationsManager::class)]
#[UsesClass(NotificationTrigger::class)]
class ProcessConfigurationsManagerTest extends TestCase
{
    /**
     * The form type aliases of the launch form fields, in their documented order.
     */
    private const FORM_TYPE_ALIASES = [
        'text' => TextType::class,
        'textarea' => TextareaType::class,
        'email' => Type\EmailType::class,
        'password' => Type\PasswordType::class,
        'search' => Type\SearchType::class,
        'url' => Type\UrlType::class,
        'tel' => Type\TelType::class,
        'hidden' => Type\HiddenType::class,
        'integer' => Type\IntegerType::class,
        'number' => Type\NumberType::class,
        'money' => Type\MoneyType::class,
        'percent' => Type\PercentType::class,
        'range' => Type\RangeType::class,
        'checkbox' => Type\CheckboxType::class,
        'radio' => Type\RadioType::class,
        'choice' => ChoiceType::class,
        'enum' => Type\EnumType::class,
        'country' => Type\CountryType::class,
        'currency' => Type\CurrencyType::class,
        'language' => Type\LanguageType::class,
        'locale' => Type\LocaleType::class,
        'timezone' => Type\TimezoneType::class,
        'date' => Type\DateType::class,
        'datetime' => Type\DateTimeType::class,
        'time' => Type\TimeType::class,
        'birthday' => Type\BirthdayType::class,
        'week' => Type\WeekType::class,
        'dateinterval' => Type\DateIntervalType::class,
        'color' => Type\ColorType::class,
        'file' => FileType::class,
        'uuid' => Type\UuidType::class,
        'ulid' => Type\UlidType::class,
        'collection' => Type\CollectionType::class,
        'repeated' => Type\RepeatedType::class,
        'entity' => EntityType::class,
    ];

    public function testPublicAndPrivateProcesses(): void
    {
        $manager = $this->createManager([
            'public.one' => $this->rawProcess(),
            'private.one' => $this->rawProcess(public: false),
            'public.two' => $this->rawProcess(),
        ]);

        $codes = static function (array $configurations): array {
            /** @var array<ProcessConfiguration> $processConfigurations */
            $processConfigurations = $configurations;

            return array_values(
                array_map(static fn (ProcessConfiguration $configuration): string => $configuration->getCode(), $processConfigurations)
            );
        };

        self::assertSame(['public.one', 'public.two'], $codes($manager->getPublicProcesses()));
        self::assertSame(['private.one'], $codes($manager->getPrivateProcesses()));
    }

    public function testUiOptionsOfAnUnknownProcess(): void
    {
        self::assertNull($this->createManager(['test.process' => $this->rawProcess()])->getUiOptions('unknown'));
    }

    public function testDefaultUiOptions(): void
    {
        [$options, $deprecations] = $this->resolveUi([]);

        self::assertSame([], $deprecations);
        self::assertNull($options['source']);
        self::assertNull($options['target']);
        self::assertSame('text', $options['entrypoint_type']);
        self::assertSame('modal', $options['ui_launch_mode']);
        self::assertSame([], $options['constraints']);
        /** @var mixed $run the PHPDoc type of the option is wrong */
        $run = $options['run'];
        self::assertNull($run);
        self::assertSame(['input' => null, 'context' => []], $options['default']);
        self::assertSame(
            [
                'input' => ['type' => TextType::class, 'options' => [], 'default' => null],
                'context' => [],
                'allow_extra_context' => true,
                'constraints' => [],
            ],
            $options['form']
        );
    }

    public function testConfiguredUiOptions(): void
    {
        [$options, $deprecations] = $this->resolveUi([
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
        ]);

        self::assertSame(
            [
                self::deprecation('entrypoint_type', 'form.input.type'),
                self::deprecation('constraints', 'form.constraints'),
                self::deprecation('default', 'form.input.default" and "form.context.<key>.default'),
            ],
            $deprecations
        );
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
        // The deprecated options are folded into the effective "form" option
        self::assertSame(['type' => FileType::class, 'options' => [], 'default' => null], $options['form']['input']);
        self::assertSame([], $options['form']['context']);
        self::assertTrue($options['form']['allow_extra_context']);
        self::assertSame($options['constraints'], $options['form']['constraints']);
    }

    public function testDefaultWithoutContext(): void
    {
        [$options, $deprecations] = $this->resolveUi(['default' => ['input' => 'data.csv']]);

        self::assertSame([self::deprecation('default', 'form.input.default" and "form.context.<key>.default')], $deprecations);
        self::assertSame(['input' => 'data.csv', 'context' => []], $options['default']);
    }

    /**
     * @param array<string, mixed>                   $default
     * @param class-string<OptionsResolverException> $exception
     */
    #[DataProvider('provideInvalidDefault')]
    public function testInvalidDefault(array $default, string $exception): void
    {
        $this->expectException($exception);
        $this->resolveUi(['default' => $default]);
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
        $this->expectException(InvalidOptionsException::class);
        $this->resolveUi(['default' => 'data.csv']);
    }

    public function testInvalidEntrypointType(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->resolveUi(['entrypoint_type' => 'csv']);
    }

    public function testFormInputAlias(): void
    {
        [$options, $deprecations] = $this->resolveUi(['form' => ['input' => ['type' => 'file', 'options' => ['label' => 'CSV file']]]]);

        self::assertSame([], $deprecations);
        self::assertSame(['type' => FileType::class, 'options' => ['label' => 'CSV file'], 'default' => null], $options['form']['input']);
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('provideFormTypeAliases')]
    public function testFormTypeAlias(string $alias, string $class): void
    {
        [$options] = $this->resolveUi(['form' => ['input' => ['type' => $alias]]]);

        self::assertSame($class, $options['form']['input']['type']);
    }

    /**
     * @return iterable<string, array{string, class-string}>
     */
    public static function provideFormTypeAliases(): iterable
    {
        foreach (self::FORM_TYPE_ALIASES as $alias => $class) {
            yield $alias => [$alias, $class];
        }
    }

    /**
     * @param class-string $class
     */
    #[TestWith([ChoiceType::class, ChoiceType::class])]
    #[TestWith([ProcessContextType::class, ProcessContextType::class])]
    #[TestWith(['\\'.TextType::class, TextType::class])]
    public function testFormTypeClass(string $type, string $class): void
    {
        [$options] = $this->resolveUi(['form' => ['input' => ['type' => $type], 'context' => ['foo' => ['type' => $type]]]]);

        self::assertSame($class, $options['form']['input']['type']);
        self::assertSame($class, $options['form']['context']['foo']['type']);
    }

    #[DataProvider('provideInvalidFormTypes')]
    public function testInvalidFormType(string $type, string $message): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage($message);
        $this->resolveUi(['form' => ['input' => ['type' => $type]]]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidFormTypes(): iterable
    {
        $aliases = implode(', ', array_map(static fn (string $alias): string => \sprintf('"%s"', $alias), array_keys(self::FORM_TYPE_ALIASES)));
        $unknown = static fn (string $type): string => \sprintf('The type "%s" of the launch form field "form.input" of the process "test.process" is neither a form type alias (%s) nor an existing class.', $type, $aliases);

        yield 'unknown alias' => ['foo', $unknown('foo')];
        yield 'missing class' => ['App\Missing', $unknown('App\Missing')];
        yield 'not a form type' => [\stdClass::class, 'The type "stdClass" of the launch form field "form.input" of the process "test.process" must implement "Symfony\Component\Form\FormTypeInterface".'];
        yield 'button alias' => ['submit', $unknown('submit')];
        yield 'button class' => [SubmitType::class, \sprintf('The type "%s" of the launch form field "form.input" of the process "test.process" is a button type, it holds no data.', SubmitType::class)];
    }

    public function testFieldConstraints(): void
    {
        $constraints = [['NotBlank' => null], ['Length' => ['max' => 10]]];
        [$options] = $this->resolveUi(['form' => [
            'input' => ['options' => ['constraints' => $constraints]],
            'context' => ['foo' => ['options' => ['constraints' => $constraints]]],
        ]]);

        foreach ([$options['form']['input'], $options['form']['context']['foo']] as $field) {
            $fieldConstraints = $field['options']['constraints'];
            self::assertIsArray($fieldConstraints);
            self::assertCount(2, $fieldConstraints);
            self::assertInstanceOf(NotBlank::class, $fieldConstraints[0]);
            self::assertInstanceOf(Length::class, $fieldConstraints[1]);
            self::assertSame(10, $fieldConstraints[1]->max);
        }
    }

    public function testFieldConstraintsMustBeAnArray(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The "constraints" form option of the launch form field "form.context.foo" of the process "test.process" must be an array of constraints.');
        $this->resolveUi(['form' => ['context' => ['foo' => ['options' => ['constraints' => 'NotBlank']]]]]);
    }

    #[TestWith(['data'])]
    #[TestWith(['mapped'])]
    #[TestWith(['property_path'])]
    #[TestWith(['inherit_data'])]
    public function testUnsupportedFieldOption(string $option): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage(\sprintf('The "%s" form option of the launch form field "form.input" of the process "test.process" is not supported, use "default" for the initial value.', $option));
        $this->resolveUi(['form' => ['input' => ['options' => [$option => 'foo']]]]);
    }

    public function testContextFieldWithoutSpec(): void
    {
        [$options] = $this->resolveUi(['form' => ['context' => ['comment' => null]]]);

        self::assertSame(['comment' => ['type' => TextType::class, 'options' => [], 'default' => null]], $options['form']['context']);
    }

    public function testContextFieldWithAnUnknownKey(): void
    {
        $this->expectException(UndefinedOptionsException::class);
        $this->expectExceptionMessage('The option "label" does not exist.');
        $this->resolveUi(['form' => ['context' => ['comment' => ['label' => 'Comment']]]]);
    }

    public function testContextFieldIsNotAnArray(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The launch form field "form.context.comment" of the process "test.process" must be an array or null, "string" given.');
        $this->resolveUi(['form' => ['context' => ['comment' => 'text']]]);
    }

    #[TestWith(['my key'])]
    #[TestWith([''])]
    #[TestWith(['-a'])]
    #[TestWith([':a'])]
    #[TestWith(['a.b'])]
    #[TestWith(['a[b]'])]
    public function testInvalidContextKey(int|string $key): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage(\sprintf('The context key "%s" of "form.context" of the process "test.process" is not a valid form field name (letters, digits, "_", "-" and ":", starting with a letter, a digit or "_").', $key));
        $this->resolveUi(['form' => ['context' => [$key => null]]]);
    }

    /**
     * A purely numeric key is cast to an integer by PHP, even quoted in YAML.
     */
    #[TestWith([0])]
    #[TestWith([123])]
    public function testNumericContextKey(int $key): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage(\sprintf('The context key "%d" of "form.context" of the process "test.process" is not supported: purely numeric context keys are cast to integers by PHP.', $key));
        $this->resolveUi(['form' => ['context' => [(string) $key => null]]]);
    }

    #[TestWith(['a-b:c'])]
    #[TestWith(['_x'])]
    #[TestWith(['1a'])]
    #[TestWith(['A'])]
    public function testValidContextKey(string $key): void
    {
        [$options] = $this->resolveUi(['form' => ['context' => [$key => null]]]);

        self::assertSame([$key], array_keys($options['form']['context']));
    }

    /**
     * The keys are reserved whether the free context is allowed or not: "extra" is the child of the free context
     * pairs, "execution_user" is set to the email of the connected user.
     */
    #[TestWith(['extra', true])]
    #[TestWith(['extra', false])]
    #[TestWith(['execution_user', true])]
    #[TestWith(['execution_user', false])]
    public function testReservedContextKey(string $key, bool $allowExtraContext): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage(\sprintf('The context key "%s" of "form.context" of the process "test.process" is reserved.', $key));
        $this->resolveUi(['form' => ['allow_extra_context' => $allowExtraContext, 'context' => [$key => null]]]);
    }

    /**
     * @param array<string, mixed> $form
     */
    #[DataProvider('provideInvalidFileFields')]
    public function testInvalidFileField(array $form, string $message): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage($message);
        $this->resolveUi(['form' => $form]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidFileFields(): iterable
    {
        yield 'file in the context' => [
            ['context' => ['data' => ['type' => 'file']]],
            'The launch form field "form.context.data" of the process "test.process" is a file field: file fields are only supported for the input.',
        ];
        yield 'file with a default value' => [
            ['input' => ['type' => 'file', 'default' => 'data.csv']],
            'The launch form field "form.input" of the process "test.process" is a file field, it cannot have a default value.',
        ];
        yield 'multiple files' => [
            ['input' => ['type' => FileType::class, 'options' => ['multiple' => true]]],
            'The launch form field "form.input" of the process "test.process" cannot upload multiple files.',
        ];
    }

    /**
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('provideInvalidForms')]
    public function testInvalidForm(mixed $form, string $exception, string $message): void
    {
        $this->expectException($exception);
        $this->expectExceptionMessage($message);
        $this->resolveUi(['form' => $form]);
    }

    /**
     * @return iterable<string, array{mixed, class-string<\Throwable>, string}>
     */
    public static function provideInvalidForms(): iterable
    {
        yield 'not an array' => ['foo', InvalidOptionsException::class, 'The option "form" with value "foo" is expected to be of type "array"'];
        yield 'unknown key' => [['fields' => []], UndefinedOptionsException::class, 'The option "fields" does not exist.'];
        yield 'allow_extra_context not a boolean' => [
            ['allow_extra_context' => 'yes'],
            InvalidOptionsException::class,
            'The option "allow_extra_context" with value "yes" is expected to be of type "bool"',
        ];
    }

    public function testFormConstraints(): void
    {
        [$options, $deprecations] = $this->resolveUi(['form' => ['constraints' => [['NotBlank' => null]]]]);

        self::assertSame([], $deprecations);
        self::assertCount(1, $options['form']['constraints']);
        self::assertInstanceOf(NotBlank::class, $options['form']['constraints'][0]);
    }

    public function testDeprecatedConstraintsAreEffective(): void
    {
        [$options, $deprecations] = $this->resolveUi(['constraints' => [['NotBlank' => null]]]);

        self::assertSame([self::deprecation('constraints', 'form.constraints')], $deprecations);
        self::assertCount(1, $options['form']['constraints']);
        self::assertInstanceOf(NotBlank::class, $options['form']['constraints'][0]);
    }

    public function testConstraintsConflict(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The "constraints" and "form.constraints" UI options of the process "test.process" cannot be used together, use "form.constraints" only.');
        $this->resolveUi(['constraints' => [], 'form' => ['constraints' => []]]);
    }

    public function testEntrypointTypeConflict(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The "entrypoint_type" and "form.input" UI options of the process "test.process" cannot be used together, use "form.input" only.');
        $this->resolveUi(['entrypoint_type' => 'text', 'form' => ['input' => ['type' => 'text']]]);
    }

    /**
     * A null "form.input" is not given: no conflict with "entrypoint_type".
     */
    public function testEntrypointTypeWithANullFormInput(): void
    {
        [$options, $deprecations] = $this->resolveUi(['entrypoint_type' => 'file', 'form' => ['input' => null, 'context' => ['delimiter' => null]]]);

        self::assertSame([self::deprecation('entrypoint_type', 'form.input.type')], $deprecations);
        self::assertSame(['type' => FileType::class, 'options' => [], 'default' => null], $options['form']['input']);
        self::assertSame(['delimiter'], array_keys($options['form']['context']));
    }

    /**
     * The conflict depends on the presence of the options, not on their values.
     */
    public function testNullInputDefaultConflict(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The default value of the input of the process "test.process" is set both in "default.input" and in "form.input.default", use "form.input.default" only.');
        $this->resolveUi(['form' => ['input' => ['default' => null]], 'default' => ['input' => null]]);
    }

    /**
     * @param array<string, mixed> $ui
     * @param array<string, mixed> $input
     */
    #[DataProvider('provideDeprecatedInputs')]
    public function testDeprecatedInputIsFolded(array $ui, array $input): void
    {
        [$options] = $this->resolveUi($ui);

        self::assertSame($input, $options['form']['input']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function provideDeprecatedInputs(): iterable
    {
        yield 'file entrypoint' => [['entrypoint_type' => 'file'], ['type' => FileType::class, 'options' => [], 'default' => null]];
        yield 'text entrypoint with a default input' => [
            ['entrypoint_type' => 'text', 'default' => ['input' => 'data.csv']],
            ['type' => TextType::class, 'options' => [], 'default' => 'data.csv'],
        ];
        yield 'file entrypoint with a default input' => [
            ['entrypoint_type' => 'file', 'default' => ['input' => 'data.csv']],
            ['type' => FileType::class, 'options' => [], 'default' => null],
        ];
        yield 'declared input without default' => [
            ['form' => ['input' => ['type' => 'textarea']], 'default' => ['input' => 'data.csv']],
            ['type' => TextareaType::class, 'options' => [], 'default' => 'data.csv'],
        ];
        yield 'declared file input without default' => [
            ['form' => ['input' => ['type' => 'file']], 'default' => ['input' => 'data.csv']],
            ['type' => FileType::class, 'options' => [], 'default' => null],
        ];
    }

    public function testDeprecatedContextDefaultIsFolded(): void
    {
        [$options] = $this->resolveUi([
            'form' => ['context' => ['delimiter' => ['type' => 'text'], 'comment' => null]],
            'default' => ['context' => [['key' => 'delimiter', 'value' => ','], ['key' => 'delimiter', 'value' => ';']]],
        ]);

        self::assertSame(';', $options['form']['context']['delimiter']['default']);
        self::assertNull($options['form']['context']['comment']['default']);
    }

    public function testContextDefaultConflict(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The default value of the context key "delimiter" of the process "test.process" is set both in "default.context" and in "form.context.delimiter.default", use "form.context.delimiter.default" only.');
        $this->resolveUi([
            'form' => ['context' => ['delimiter' => ['default' => null]]],
            'default' => ['context' => [['key' => 'delimiter', 'value' => ',']]],
        ]);
    }

    public function testInputDefaultConflict(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The default value of the input of the process "test.process" is set both in "default.input" and in "form.input.default", use "form.input.default" only.');
        $this->resolveUi(['form' => ['input' => ['default' => 'a.csv']], 'default' => ['input' => 'b.csv']]);
    }

    public function testExtraContextKeyNotAllowed(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The context key "foo" of "default.context" of the process "test.process" is not declared in "form.context", and "form.allow_extra_context" is false.');
        $this->resolveUi([
            'form' => ['allow_extra_context' => false, 'context' => ['delimiter' => null]],
            'default' => ['context' => [['key' => 'foo', 'value' => 'bar']]],
        ]);
    }

    public function testDeclaredContextKeyWithoutExtraContext(): void
    {
        [$options] = $this->resolveUi([
            'form' => ['allow_extra_context' => false, 'context' => ['delimiter' => null]],
            'default' => ['context' => [['key' => 'delimiter', 'value' => ',']]],
        ]);

        self::assertFalse($options['form']['allow_extra_context']);
        self::assertSame(',', $options['form']['context']['delimiter']['default']);
    }

    /**
     * @param array<string, mixed> $ui
     */
    #[DataProvider('provideDeprecatedOptions')]
    public function testDeprecatedOption(array $ui, string $deprecation): void
    {
        [, $deprecations] = $this->resolveUi($ui);

        self::assertSame([$deprecation], $deprecations);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideDeprecatedOptions(): iterable
    {
        yield 'entrypoint_type' => [['entrypoint_type' => 'text'], self::deprecation('entrypoint_type', 'form.input.type')];
        yield 'default' => [['default' => []], self::deprecation('default', 'form.input.default" and "form.context.<key>.default')];
        yield 'constraints' => [['constraints' => []], self::deprecation('constraints', 'form.constraints')];
    }

    public function testNewFormatIsNotDeprecated(): void
    {
        [, $deprecations] = $this->resolveUi([
            'ui_launch_mode' => 'form',
            'form' => [
                'input' => ['type' => 'file', 'options' => ['constraints' => [['NotNull' => null]]]],
                'context' => ['delimiter' => ['type' => 'choice', 'default' => ',', 'options' => ['choices' => [',' => ',']]]],
                'allow_extra_context' => false,
                'constraints' => [],
            ],
        ]);

        self::assertSame([], $deprecations);
    }

    public function testLaunchFormDefaultsOfAnUnknownProcess(): void
    {
        $this->expectException(MissingProcessException::class);
        $this->createManager(['test.process' => $this->rawProcess()])->getLaunchFormDefaults('unknown');
    }

    /**
     * @param array<string, mixed> $ui
     * @param array<string, mixed> $defaults
     */
    #[DataProvider('provideLaunchFormDefaults')]
    public function testLaunchFormDefaults(array $ui, array $defaults): void
    {
        $manager = $this->createManager(['test.process' => $this->rawProcess(['ui' => $ui])]);

        $actual = null;
        $deprecations = $this->collectDeprecations(static function () use ($manager, &$actual): void {
            $actual = $manager->getLaunchFormDefaults('test.process');
        });

        self::assertSame($defaults, $actual);
        self::assertSame(self::expectedDeprecations($ui), $deprecations);

        // From the UI options already resolved, without resolving them again
        [$uiOptions] = $this->resolveUi($ui);
        $fromUiOptions = null;
        self::assertSame([], $this->collectDeprecations(static function () use ($manager, $uiOptions, &$fromUiOptions): void {
            $fromUiOptions = $manager->buildLaunchFormDefaults($uiOptions);
        }));
        self::assertSame($defaults, $fromUiOptions);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function provideLaunchFormDefaults(): iterable
    {
        yield 'new format' => [
            ['form' => [
                'input' => ['default' => 'data.csv'],
                'context' => ['delimiter' => ['default' => ','], 'comment' => null, 'dry_run' => ['type' => 'checkbox', 'default' => false]],
            ]],
            ['input' => 'data.csv', 'context' => ['delimiter' => ',', 'comment' => null, 'dry_run' => false]],
        ];
        yield 'deprecated format' => [
            ['default' => ['input' => 'data.csv', 'context' => [['key' => 'foo', 'value' => 'bar']]]],
            ['input' => 'data.csv', 'context' => ['foo' => 'bar']],
        ];
        yield 'mixed format' => [
            [
                'form' => ['context' => ['delimiter' => null, 'comment' => ['default' => 'none']]],
                'default' => ['context' => [['key' => 'foo', 'value' => 'bar'], ['key' => 'delimiter', 'value' => ';']]],
            ],
            ['input' => null, 'context' => ['delimiter' => ';', 'comment' => 'none', 'foo' => 'bar']],
        ];
        yield 'file input' => [['form' => ['input' => ['type' => 'file']]], ['input' => null, 'context' => []]];
        // The last row of a key wins, the free keys keep the order of their first row
        yield 'duplicated free keys' => [
            ['default' => ['context' => [['key' => 'foo', 'value' => 'a'], ['key' => 'bar', 'value' => 'b'], ['key' => 'foo', 'value' => 'c']]]],
            ['input' => null, 'context' => ['foo' => 'c', 'bar' => 'b']],
        ];
        // The declared keys first, in their order of declaration, whatever the order of the deprecated rows
        yield 'declared keys first' => [
            [
                'form' => ['context' => ['b' => null, 'a' => ['default' => 'A']]],
                'default' => ['context' => [['key' => 'free', 'value' => 'F'], ['key' => 'b', 'value' => 'B']]],
            ],
            ['input' => null, 'context' => ['b' => 'B', 'a' => 'A', 'free' => 'F']],
        ];
    }

    /**
     * The duplicated rows of "default.context" are kept in the resolved "default" option (backward compatibility),
     * the last one wins in the effective "form" option.
     */
    public function testDuplicatedDefaultContextKeys(): void
    {
        $rows = [['key' => 'delimiter', 'value' => ','], ['key' => 'foo', 'value' => 'a'], ['key' => 'foo', 'value' => 'b'], ['key' => 'delimiter', 'value' => ';']];
        [$options] = $this->resolveUi(['form' => ['context' => ['delimiter' => null]], 'default' => ['context' => $rows]]);

        self::assertSame($rows, $options['default']['context']);
        self::assertSame(';', $options['form']['context']['delimiter']['default']);
    }

    public function testDeprecationsAreTriggeredOncePerResolution(): void
    {
        $manager = $this->createManager(['test.process' => $this->rawProcess(['ui' => ['entrypoint_type' => 'text']])]);

        $deprecations = $this->collectDeprecations(static function () use ($manager): void {
            $manager->getUiOptions('test.process');
            $manager->getUiOptions('test.process');
        });

        $expected = self::deprecation('entrypoint_type', 'form.input.type');
        self::assertSame([$expected, $expected], $deprecations);
    }

    /**
     * Each getter resolves only its own option: the deprecations of the UI options are not triggered by the
     * notification and logs getters.
     */
    public function testNoDeprecationsThroughTheNotificationAndLogsOptions(): void
    {
        $manager = $this->createManager(['test.process' => $this->rawProcess(['ui' => ['constraints' => []]])]);

        self::assertSame([], $this->collectDeprecations(static fn () => $manager->getNotificationOptions('test.process')));
        self::assertSame([], $this->collectDeprecations(static fn () => $manager->getLogsOptions('test.process')));
    }

    /**
     * An invalid launch form does not break the notification of the executions nor the purge of the logs.
     */
    public function testInvalidUiOptionsDoNotBreakTheNotificationAndLogsOptions(): void
    {
        $manager = $this->createManager(['test.process' => $this->rawProcess([
            'ui' => ['form' => ['input' => ['type' => 'App\\Missing']]],
            'notification' => ['enabled' => true],
            'logs' => ['file_max_days' => 30],
        ])]);

        self::assertTrue($manager->getNotificationOptions('test.process')['enabled'] ?? null);
        self::assertSame(30, $manager->getLogsOptions('test.process')['file_max_days'] ?? null);

        $this->expectException(InvalidOptionsException::class);
        $manager->getUiOptions('test.process');
    }

    /**
     * The other options are only checked as arrays when the UI options are resolved.
     */
    public function testInvalidNotificationOptionsDoNotBreakTheUiOptions(): void
    {
        $manager = $this->createManager(['test.process' => $this->rawProcess([
            'ui' => ['source' => 'ERP'],
            'notification' => ['enabled' => 'yes'],
            'logs' => ['file_max_days' => 0],
        ])]);

        self::assertSame('ERP', $manager->getUiOptions('test.process')['source'] ?? null);
    }

    public function testNewFormatIsNotDeprecatedThroughTheNotificationAndLogsOptions(): void
    {
        $manager = $this->createManager(['test.process' => $this->rawProcess(['ui' => [
            'ui_launch_mode' => 'form',
            'form' => ['input' => ['type' => 'file'], 'context' => ['delimiter' => ['default' => ',']], 'constraints' => []],
        ]])]);

        $notification = null;
        $logs = null;
        self::assertSame([], $this->collectDeprecations(static function () use ($manager, &$notification, &$logs): void {
            $notification = $manager->getNotificationOptions('test.process');
            $logs = $manager->getLogsOptions('test.process');
        }));
        self::assertSame(['enabled' => null, 'statuses' => null, 'channels' => null, 'recipients' => null], $notification);
        self::assertSame(['database_max_days' => null, 'file_max_days' => null], $logs);
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

    public function testLogsOptionsOfAnUnknownProcess(): void
    {
        self::assertNull($this->createManager(['test.process' => $this->rawProcess()])->getLogsOptions('unknown'));
    }

    public function testDefaultLogsOptions(): void
    {
        self::assertSame(
            ['database_max_days' => null, 'file_max_days' => null],
            $this->createManager(['test.process' => $this->rawProcess()])->getLogsOptions('test.process')
        );
    }

    public function testConfiguredLogsOptions(): void
    {
        $manager = $this->createManager(['test.process' => $this->rawProcess([
            'ui' => ['source' => 'ERP'],
            'notification' => ['enabled' => true],
            'logs' => ['database_max_days' => 30, 'file_max_days' => 365],
        ])]);

        self::assertSame(['database_max_days' => 30, 'file_max_days' => 365], $manager->getLogsOptions('test.process'));
        // The "logs" option is accepted next to the "ui" and "notification" ones
        self::assertSame('ERP', $manager->getUiOptions('test.process')['source'] ?? null);
        self::assertTrue($manager->getNotificationOptions('test.process')['enabled'] ?? null);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, class-string<\Throwable>}>
     */
    public static function provideInvalidLogsOptions(): iterable
    {
        yield 'not an array' => [['logs' => 30], InvalidOptionsException::class];
        yield 'unknown key' => [['logs' => ['max_days' => 30]], UndefinedOptionsException::class];
        yield 'database_max_days not an integer' => [['logs' => ['database_max_days' => '30']], InvalidOptionsException::class];
        yield 'database_max_days lower than 1' => [['logs' => ['database_max_days' => 0]], InvalidOptionsException::class];
        yield 'file_max_days lower than 1' => [['logs' => ['file_max_days' => -1]], InvalidOptionsException::class];
    }

    /**
     * @param array<string, mixed>     $options
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('provideInvalidLogsOptions')]
    public function testInvalidLogsOptions(array $options, string $exception): void
    {
        $manager = $this->createManager(['test.process' => $this->rawProcess($options)]);

        $this->expectException($exception);
        $manager->getLogsOptions('test.process');
    }

    /**
     * @param array<string, mixed> $ui
     *
     * @return array{0: UiOptions, 1: list<string>}
     */
    private function resolveUi(array $ui): array
    {
        $manager = $this->createManager(['test.process' => $this->rawProcess(['ui' => $ui])]);

        $options = null;
        $deprecations = $this->collectDeprecations(static function () use ($manager, &$options): void {
            $options = $manager->getUiOptions('test.process');
        });
        self::assertIsArray($options);
        // Every resolution checks its deprecations: exactly one per deprecated option given
        self::assertSame(self::expectedDeprecations($ui), $deprecations);

        return [$options, $deprecations];
    }

    /**
     * The deprecations triggered by the resolution of the given UI options, in their order of resolution.
     *
     * @param array<string, mixed> $ui
     *
     * @return list<string>
     */
    private static function expectedDeprecations(array $ui): array
    {
        $replacements = [
            'entrypoint_type' => 'form.input.type',
            'constraints' => 'form.constraints',
            'default' => 'form.input.default" and "form.context.<key>.default',
        ];
        $deprecations = [];
        foreach ($replacements as $option => $replacement) {
            if (\array_key_exists($option, $ui)) {
                $deprecations[] = self::deprecation($option, $replacement);
            }
        }

        return $deprecations;
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

    private static function deprecation(string $option, string $replacement): string
    {
        return \sprintf('Since cleverage/ui-process-bundle 3.3: The "%s" UI option of the process "test.process" is deprecated, use "%s" instead.', $option, $replacement);
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
