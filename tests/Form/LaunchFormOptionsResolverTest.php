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

namespace CleverAge\UiProcessBundle\Tests\Form;

use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\UiProcessBundle\Form\LaunchFormOptionsResolver;
use CleverAge\UiProcessBundle\Form\Type\ProcessContextType;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Notifier\NotificationTrigger;
use CleverAge\UiProcessBundle\Tests\App\Validator\Even;
use CleverAge\UiProcessBundle\Tests\Form\Fixtures\PureEnum;
use CleverAge\UiProcessBundle\Tests\Form\Fixtures\StringBackedEnum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * The launch form, resolved through ProcessConfigurationsManager::getLaunchFormOptions().
 *
 * @phpstan-import-type LaunchFormOptions from LaunchFormOptionsResolver
 */
#[CoversClass(LaunchFormOptionsResolver::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
#[UsesClass(NotificationTrigger::class)]
class LaunchFormOptionsResolverTest extends TestCase
{
    public function testDefaultLaunchForm(): void
    {
        [$form, $deprecations] = $this->resolveLaunchForm([]);

        self::assertSame([], $deprecations);
        self::assertSame(
            [
                'input' => ['type' => TextType::class, 'options' => [], 'default' => null],
                'context' => [],
                'allow_extra_context' => true,
                'constraints' => [],
                'data' => ['input' => null, 'context' => []],
            ],
            $form
        );
    }

    public function testDeprecatedOptionsAreFolded(): void
    {
        [$form, $deprecations] = $this->resolveLaunchForm([
            'entrypoint_type' => 'file',
            'ui_launch_mode' => 'form',
            'constraints' => [['NotBlank' => null], ['Length' => ['max' => 10]]],
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
        self::assertSame(['type' => FileType::class, 'options' => [], 'default' => null], $form['input']);
        self::assertSame([], $form['context']);
        self::assertTrue($form['allow_extra_context']);
        self::assertCount(2, $form['constraints']);
        self::assertInstanceOf(NotBlank::class, $form['constraints'][0]);
        self::assertInstanceOf(Length::class, $form['constraints'][1]);
        self::assertSame(['input' => null, 'context' => ['foo' => 'bar']], $form['data']);
    }

    public function testFormInput(): void
    {
        [$form, $deprecations] = $this->resolveLaunchForm(['form' => ['input' => ['type' => FileType::class, 'options' => ['label' => 'CSV file']]]]);

        self::assertSame([], $deprecations);
        self::assertSame(['type' => FileType::class, 'options' => ['label' => 'CSV file'], 'default' => null], $form['input']);
    }

    /**
     * @param class-string $class
     */
    #[TestWith([ChoiceType::class, ChoiceType::class])]
    #[TestWith([ProcessContextType::class, ProcessContextType::class])]
    #[TestWith(['\\'.TextType::class, TextType::class])]
    public function testFormTypeClass(string $type, string $class): void
    {
        [$form] = $this->resolveLaunchForm(['form' => ['input' => ['type' => $type], 'context' => ['foo' => ['type' => $type]]]]);

        self::assertSame($class, $form['input']['type']);
        self::assertSame($class, $form['context']['foo']['type']);
    }

    #[DataProvider('provideInvalidFormTypes')]
    public function testInvalidFormType(string $type, string $message): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage($message);
        $this->resolveLaunchForm(['form' => ['input' => ['type' => $type]]]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidFormTypes(): iterable
    {
        $unknown = static fn (string $type): string => \sprintf('The type "%s" of the launch form field "form.input" of the process "test.process" must be the FQCN of a form type, the class does not exist.', $type);

        // The short names of the form types are not supported
        yield 'short name' => ['file', $unknown('file')];
        yield 'missing class' => ['App\Missing', $unknown('App\Missing')];
        yield 'not a form type' => [\stdClass::class, 'The type "stdClass" of the launch form field "form.input" of the process "test.process" must implement "Symfony\Component\Form\FormTypeInterface".'];
        yield 'button class' => [SubmitType::class, \sprintf('The type "%s" of the launch form field "form.input" of the process "test.process" is a button type, it holds no data.', SubmitType::class)];
    }

    public function testFieldConstraints(): void
    {
        $constraints = [['NotBlank' => null], ['Length' => ['max' => 10]]];
        [$form] = $this->resolveLaunchForm(['form' => [
            'input' => ['options' => ['constraints' => $constraints]],
            'context' => ['foo' => ['options' => ['constraints' => $constraints]]],
        ]]);

        foreach ([$form['input'], $form['context']['foo']] as $field) {
            $fieldConstraints = $field['options']['constraints'];
            self::assertIsArray($fieldConstraints);
            self::assertCount(2, $fieldConstraints);
            self::assertInstanceOf(NotBlank::class, $fieldConstraints[0]);
            self::assertInstanceOf(Length::class, $fieldConstraints[1]);
            self::assertSame(10, $fieldConstraints[1]->max);
        }
    }

    /**
     * A project-specific constraint, given by its FQCN.
     */
    public function testFieldConstraintClass(): void
    {
        [$form] = $this->resolveLaunchForm(['form' => ['context' => ['count' => ['options' => ['constraints' => [
            [Even::class => null],
            [Even::class => ['message' => 'Odd value.']],
        ]]]]]]);

        $constraints = $form['context']['count']['options']['constraints'];
        self::assertIsArray($constraints);
        self::assertCount(2, $constraints);
        self::assertInstanceOf(Even::class, $constraints[0]);
        self::assertInstanceOf(Even::class, $constraints[1]);
        self::assertSame('Odd value.', $constraints[1]->message);
    }

    public function testFieldConstraintsMustBeAnArray(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The "constraints" form option of the launch form field "form.context.foo" of the process "test.process" must be an array of constraints.');
        $this->resolveLaunchForm(['form' => ['context' => ['foo' => ['options' => ['constraints' => 'NotBlank']]]]]);
    }

    #[TestWith(['data'])]
    #[TestWith(['mapped'])]
    #[TestWith(['property_path'])]
    #[TestWith(['inherit_data'])]
    public function testUnsupportedFieldOption(string $option): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage(\sprintf('The "%s" form option of the launch form field "form.input" of the process "test.process" is not supported, use "default" for the initial value.', $option));
        $this->resolveLaunchForm(['form' => ['input' => ['options' => [$option => 'foo']]]]);
    }

    public function testContextFieldWithoutSpec(): void
    {
        [$form] = $this->resolveLaunchForm(['form' => ['context' => ['comment' => null]]]);

        self::assertSame(['comment' => ['type' => TextType::class, 'options' => [], 'default' => null]], $form['context']);
    }

    public function testContextFieldWithAnUnknownKey(): void
    {
        $this->expectException(UndefinedOptionsException::class);
        $this->expectExceptionMessage('The option "label" does not exist.');
        $this->resolveLaunchForm(['form' => ['context' => ['comment' => ['label' => 'Comment']]]]);
    }

    public function testContextFieldIsNotAnArray(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The launch form field "form.context.comment" of the process "test.process" must be an array or null, "string" given.');
        $this->resolveLaunchForm(['form' => ['context' => ['comment' => 'text']]]);
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
        $this->resolveLaunchForm(['form' => ['context' => [$key => null]]]);
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
        $this->resolveLaunchForm(['form' => ['context' => [(string) $key => null]]]);
    }

    #[TestWith(['a-b:c'])]
    #[TestWith(['_x'])]
    #[TestWith(['1a'])]
    #[TestWith(['A'])]
    public function testValidContextKey(string $key): void
    {
        [$form] = $this->resolveLaunchForm(['form' => ['context' => [$key => null]]]);

        self::assertSame([$key], array_keys($form['context']));
    }

    /**
     * The key is reserved whether the free context is allowed or not: "extra" is the child of the free context pairs.
     */
    #[TestWith(['extra', true])]
    #[TestWith(['extra', false])]
    public function testReservedContextKey(string $key, bool $allowExtraContext): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage(\sprintf('The context key "%s" of "form.context" of the process "test.process" is reserved.', $key));
        $this->resolveLaunchForm(['form' => ['allow_extra_context' => $allowExtraContext, 'context' => [$key => null]]]);
    }

    /**
     * @param array<string, mixed> $form
     */
    #[DataProvider('provideInvalidFileFields')]
    public function testInvalidFileField(array $form, string $message): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage($message);
        $this->resolveLaunchForm(['form' => $form]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidFileFields(): iterable
    {
        yield 'file in the context' => [
            ['context' => ['data' => ['type' => FileType::class]]],
            'The launch form field "form.context.data" of the process "test.process" is a file field: file fields are only supported for the input.',
        ];
        yield 'file with a default value' => [
            ['input' => ['type' => FileType::class, 'default' => 'data.csv']],
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
        $this->resolveLaunchForm(['form' => $form]);
    }

    /**
     * @return iterable<string, array{mixed, class-string<\Throwable>, string}>
     */
    public static function provideInvalidForms(): iterable
    {
        yield 'not an array' => ['foo', InvalidOptionsException::class, 'The "form" UI option of the process "test.process" must be an array, "string" given.'];
        yield 'unknown key' => [['fields' => []], UndefinedOptionsException::class, 'The option "fields" does not exist.'];
        yield 'allow_extra_context not a boolean' => [
            ['allow_extra_context' => 'yes'],
            InvalidOptionsException::class,
            'The option "allow_extra_context" with value "yes" is expected to be of type "bool"',
        ];
    }

    #[DataProvider('provideValidEnumDefaults')]
    public function testValidEnumDefault(mixed $default, bool $multiple): void
    {
        [$form] = $this->resolveLaunchForm(['form' => ['context' => ['delimiter' => [
            'type' => EnumType::class,
            'default' => $default,
            'options' => ['class' => StringBackedEnum::class, 'multiple' => $multiple],
        ]]]]);

        self::assertSame($default, $form['context']['delimiter']['default']);
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function provideValidEnumDefaults(): iterable
    {
        yield 'case' => [StringBackedEnum::Comma, false];
        yield 'no default' => [null, false];
        yield 'list of cases' => [[StringBackedEnum::Comma], true];
        yield 'empty list' => [[], true];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('provideInvalidEnumDefaults')]
    public function testInvalidEnumDefault(mixed $default, array $options, string $enum, string $given): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage(\sprintf('The default value of the launch form field "form.context.delimiter" of the process "test.process" must be a case of the enum "%s" (with "multiple", a list of cases), "%s" given: use the "!php/enum" YAML tag, e.g. "default: !php/enum App\\Enum\\Status::Active".', $enum, $given));
        $this->resolveLaunchForm(['form' => ['context' => ['delimiter' => ['type' => EnumType::class, 'default' => $default, 'options' => $options]]]]);
    }

    /**
     * @return iterable<string, array{mixed, array<string, mixed>, string, string}>
     */
    public static function provideInvalidEnumDefaults(): iterable
    {
        $options = ['class' => StringBackedEnum::class];

        yield 'scalar' => [',', $options, StringBackedEnum::class, 'string'];
        yield 'case of another enum' => [PureEnum::Foo, $options, StringBackedEnum::class, PureEnum::class];
        yield 'list without multiple' => [[StringBackedEnum::Comma], $options, StringBackedEnum::class, 'array'];
        yield 'list of scalars' => [[','], $options + ['multiple' => true], StringBackedEnum::class, 'string'];
        yield 'without class' => [',', [], 'options.class', 'string'];
    }

    public function testFormConstraints(): void
    {
        [$form, $deprecations] = $this->resolveLaunchForm(['form' => ['constraints' => [['NotBlank' => null]]]]);

        self::assertSame([], $deprecations);
        self::assertCount(1, $form['constraints']);
        self::assertInstanceOf(NotBlank::class, $form['constraints'][0]);
    }

    public function testDeprecatedConstraintsAreEffective(): void
    {
        [$form, $deprecations] = $this->resolveLaunchForm(['constraints' => [['NotBlank' => null]]]);

        self::assertSame([self::deprecation('constraints', 'form.constraints')], $deprecations);
        self::assertCount(1, $form['constraints']);
        self::assertInstanceOf(NotBlank::class, $form['constraints'][0]);
    }

    public function testConstraintsConflict(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The "constraints" and "form.constraints" UI options of the process "test.process" cannot be used together, use "form.constraints" only.');
        $this->resolveLaunchForm(['constraints' => [], 'form' => ['constraints' => []]]);
    }

    public function testEntrypointTypeConflict(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The "entrypoint_type" and "form.input" UI options of the process "test.process" cannot be used together, use "form.input" only.');
        $this->resolveLaunchForm(['entrypoint_type' => 'text', 'form' => ['input' => ['type' => TextType::class]]]);
    }

    /**
     * A null "form.input" is not given: no conflict with "entrypoint_type".
     */
    public function testEntrypointTypeWithANullFormInput(): void
    {
        [$form, $deprecations] = $this->resolveLaunchForm(['entrypoint_type' => 'file', 'form' => ['input' => null, 'context' => ['delimiter' => null]]]);

        self::assertSame([self::deprecation('entrypoint_type', 'form.input.type')], $deprecations);
        self::assertSame(['type' => FileType::class, 'options' => [], 'default' => null], $form['input']);
        self::assertSame(['delimiter'], array_keys($form['context']));
    }

    /**
     * The conflict depends on the presence of the options, not on their values.
     */
    public function testNullInputDefaultConflict(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The default value of the input of the process "test.process" is set both in "default.input" and in "form.input.default", use "form.input.default" only.');
        $this->resolveLaunchForm(['form' => ['input' => ['default' => null]], 'default' => ['input' => null]]);
    }

    /**
     * @param array<string, mixed> $ui
     * @param array<string, mixed> $input
     */
    #[DataProvider('provideDeprecatedInputs')]
    public function testDeprecatedInputIsFolded(array $ui, array $input): void
    {
        [$form] = $this->resolveLaunchForm($ui);

        self::assertSame($input, $form['input']);
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
            ['form' => ['input' => ['type' => TextareaType::class]], 'default' => ['input' => 'data.csv']],
            ['type' => TextareaType::class, 'options' => [], 'default' => 'data.csv'],
        ];
        yield 'declared file input without default' => [
            ['form' => ['input' => ['type' => FileType::class]], 'default' => ['input' => 'data.csv']],
            ['type' => FileType::class, 'options' => [], 'default' => null],
        ];
    }

    public function testDeprecatedContextDefaultIsFolded(): void
    {
        [$form] = $this->resolveLaunchForm([
            'form' => ['context' => ['delimiter' => ['type' => TextType::class], 'comment' => null]],
            'default' => ['context' => [['key' => 'delimiter', 'value' => ','], ['key' => 'delimiter', 'value' => ';']]],
        ]);

        self::assertSame(';', $form['context']['delimiter']['default']);
        self::assertNull($form['context']['comment']['default']);
    }

    public function testContextDefaultConflict(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The default value of the context key "delimiter" of the process "test.process" is set both in "default.context" and in "form.context.delimiter.default", use "form.context.delimiter.default" only.');
        $this->resolveLaunchForm([
            'form' => ['context' => ['delimiter' => ['default' => null]]],
            'default' => ['context' => [['key' => 'delimiter', 'value' => ',']]],
        ]);
    }

    public function testInputDefaultConflict(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The default value of the input of the process "test.process" is set both in "default.input" and in "form.input.default", use "form.input.default" only.');
        $this->resolveLaunchForm(['form' => ['input' => ['default' => 'a.csv']], 'default' => ['input' => 'b.csv']]);
    }

    public function testExtraContextKeyNotAllowed(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The context key "foo" of "default.context" of the process "test.process" is not declared in "form.context", and "form.allow_extra_context" is false.');
        $this->resolveLaunchForm([
            'form' => ['allow_extra_context' => false, 'context' => ['delimiter' => null]],
            'default' => ['context' => [['key' => 'foo', 'value' => 'bar']]],
        ]);
    }

    public function testDeclaredContextKeyWithoutExtraContext(): void
    {
        [$form] = $this->resolveLaunchForm([
            'form' => ['allow_extra_context' => false, 'context' => ['delimiter' => null]],
            'default' => ['context' => [['key' => 'delimiter', 'value' => ',']]],
        ]);

        self::assertFalse($form['allow_extra_context']);
        self::assertSame(',', $form['context']['delimiter']['default']);
    }

    /**
     * @param array<string, mixed> $ui
     */
    #[DataProvider('provideDeprecatedOptions')]
    public function testDeprecatedOption(array $ui, string $deprecation): void
    {
        [, $deprecations] = $this->resolveLaunchForm($ui);

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
        [, $deprecations] = $this->resolveLaunchForm([
            'ui_launch_mode' => 'form',
            'form' => [
                'input' => ['type' => FileType::class, 'options' => ['constraints' => [['NotNull' => null]]]],
                'context' => ['delimiter' => ['type' => ChoiceType::class, 'default' => ',', 'options' => ['choices' => [',' => ',']]]],
                'allow_extra_context' => false,
                'constraints' => [],
            ],
        ]);

        self::assertSame([], $deprecations);
    }

    public function testLaunchFormOfAnUnknownProcess(): void
    {
        self::assertNull($this->createManager(['test.process' => $this->rawProcess()])->getLaunchFormOptions('unknown'));
    }

    /**
     * @param array<string, mixed> $ui
     * @param array<string, mixed> $data
     */
    #[DataProvider('provideLaunchFormData')]
    public function testLaunchFormData(array $ui, array $data): void
    {
        [$form] = $this->resolveLaunchForm($ui);

        self::assertSame($data, $form['data']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function provideLaunchFormData(): iterable
    {
        yield 'new format' => [
            ['form' => [
                'input' => ['default' => 'data.csv'],
                'context' => ['delimiter' => ['default' => ','], 'comment' => null, 'dry_run' => ['type' => CheckboxType::class, 'default' => false]],
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
        yield 'file input' => [['form' => ['input' => ['type' => FileType::class]]], ['input' => null, 'context' => []]];
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
     * the last one wins in the launch form.
     */
    public function testDuplicatedDefaultContextKeys(): void
    {
        $rows = [['key' => 'delimiter', 'value' => ','], ['key' => 'foo', 'value' => 'a'], ['key' => 'foo', 'value' => 'b'], ['key' => 'delimiter', 'value' => ';']];
        $ui = ['form' => ['context' => ['delimiter' => null]], 'default' => ['context' => $rows]];
        [$form] = $this->resolveLaunchForm($ui);

        $uiOptions = $this->createManager(['test.process' => $this->rawProcess(['ui' => $ui])])->getUiOptions('test.process');
        self::assertSame($rows, $uiOptions['default']['context'] ?? null);
        self::assertSame(';', $form['context']['delimiter']['default']);
        self::assertSame(['delimiter' => ';', 'foo' => 'b'], $form['data']['context']);
    }

    public function testDeprecationsAreTriggeredOncePerResolution(): void
    {
        $manager = $this->createManager(['test.process' => $this->rawProcess(['ui' => ['entrypoint_type' => 'text']])]);

        $deprecations = $this->collectDeprecations(static function () use ($manager): void {
            $manager->getLaunchFormOptions('test.process');
            $manager->getLaunchFormOptions('test.process');
        });

        $expected = self::deprecation('entrypoint_type', 'form.input.type');
        self::assertSame([$expected, $expected], $deprecations);
    }

    /**
     * @param array<string, mixed> $ui
     *
     * @return array{LaunchFormOptions, list<string>}
     */
    private function resolveLaunchForm(array $ui): array
    {
        $manager = $this->createManager(['test.process' => $this->rawProcess(['ui' => $ui])]);

        $form = null;
        $deprecations = $this->collectDeprecations(static function () use ($manager, &$form): void {
            $form = $manager->getLaunchFormOptions('test.process');
        });
        self::assertIsArray($form);
        // Every resolution checks its deprecations: exactly one per deprecated option given
        self::assertSame(self::expectedDeprecations($ui), $deprecations);

        return [$form, $deprecations];
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
        return \sprintf('Since cleverage/ui-process-bundle 3.4: The "%s" UI option of the process "test.process" is deprecated, use "%s" instead.', $option, $replacement);
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
