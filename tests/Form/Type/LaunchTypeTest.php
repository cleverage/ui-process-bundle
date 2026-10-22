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

namespace CleverAge\UiProcessBundle\Tests\Form\Type;

use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\UiProcessBundle\Form\LaunchFormOptionsResolver;
use CleverAge\UiProcessBundle\Form\Type\LaunchType;
use CleverAge\UiProcessBundle\Form\Type\ProcessContextType;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Validation;

#[CoversClass(LaunchType::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
#[UsesClass(LaunchFormOptionsResolver::class)]
#[UsesClass(ProcessContextType::class)]
class LaunchTypeTest extends TypeTestCase
{
    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();

        parent::setUp();
    }

    /**
     * @return list<FormExtensionInterface>
     */
    protected function getExtensions(): array
    {
        $registry = new ProcessConfigurationRegistry([
            'text.process' => $this->rawProcess(),
            'file.process' => $this->rawProcess(['ui' => ['entrypoint_type' => 'file']], 'data'),
            'fields.process' => $this->rawProcess(['ui' => ['ui_launch_mode' => 'form', 'form' => [
                'input' => ['options' => ['required' => false]],
                'context' => [
                    'delimiter' => [
                        'type' => ChoiceType::class,
                        'default' => ',',
                        'options' => ['label' => 'Delimiter', 'choices' => ['Comma' => ',', 'Semicolon' => ';'], 'required' => true],
                    ],
                    'dry_run' => ['type' => CheckboxType::class, 'default' => false],
                    'limit' => ['type' => IntegerType::class, 'options' => ['constraints' => [['Positive' => null]]]],
                ],
            ]]], 'data'),
            'strict.process' => $this->rawProcess(['ui' => ['ui_launch_mode' => 'form', 'form' => [
                'context' => ['delimiter' => null],
                'allow_extra_context' => false,
            ]]]),
        ], 'stop');

        return [
            new PreloadedExtension([new LaunchType($registry, new ProcessConfigurationsManager($registry))], []),
            new ValidatorExtension(Validation::createValidator()),
        ];
    }

    public function testTextInput(): void
    {
        $form = $this->factory->create(LaunchType::class, null, ['process_code' => 'text.process']);

        self::assertInstanceOf(TextType::class, $this->getInnerType($form->get('input')));
        self::assertFalse($form->get('input')->getConfig()->getRequired(), 'The process has no entry point');

        $context = $form->get('context');
        self::assertInstanceOf(FormType::class, $this->getInnerType($context));
        self::assertSame(['extra'], array_keys($context->all()));
        $extra = $context->get('extra')->getConfig();
        self::assertInstanceOf(CollectionType::class, $extra->getType()->getInnerType());
        self::assertSame(ProcessContextType::class, $extra->getOption('entry_type'));
        self::assertTrue($extra->getOption('allow_add'));
        self::assertTrue($extra->getOption('allow_delete'));
        self::assertFalse($extra->getOption('required'));
        self::assertFalse($extra->getOption('mapped'));
        self::assertFalse($extra->getOption('label'));
        self::assertSame([], $this->getReservedKeys($extra->getOption('entry_options')));
    }

    public function testFileInput(): void
    {
        $form = null;
        $deprecations = $this->collectDeprecations(function () use (&$form): void {
            $form = $this->factory->create(LaunchType::class, null, ['process_code' => 'file.process']);
        });
        self::assertInstanceOf(FormInterface::class, $form);

        self::assertSame(['Since cleverage/ui-process-bundle 3.4: The "entrypoint_type" UI option of the process "file.process" is deprecated, use "form.input.type" instead.'], $deprecations);

        self::assertInstanceOf(FileType::class, $this->getInnerType($form->get('input')));
        self::assertTrue($form->get('input')->getConfig()->getRequired(), 'The process has an entry point');
    }

    public function testSubmit(): void
    {
        $form = $this->factory->create(LaunchType::class, null, ['process_code' => 'text.process']);
        $form->submit([
            'input' => 'data.csv',
            'context' => ['extra' => [
                ['key' => 'foo', 'value' => 'bar'],
                ['key' => 'baz', 'value' => 'qux'],
            ]],
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertSame(['input' => 'data.csv', 'context' => ['foo' => 'bar', 'baz' => 'qux']], $form->getData());
    }

    public function testSubmitWithoutContext(): void
    {
        $form = $this->factory->create(LaunchType::class, null, ['process_code' => 'text.process']);
        $form->submit(['input' => '']);

        self::assertTrue($form->isValid());
        self::assertSame([], $form->get('context')->getData());
    }

    public function testDefaultData(): void
    {
        $form = $this->factory->create(LaunchType::class, ['input' => 'data.csv'], ['process_code' => 'text.process']);

        self::assertSame('data.csv', $form->get('input')->getData());
    }

    public function testInvalidContext(): void
    {
        $form = $this->factory->create(LaunchType::class, null, ['process_code' => 'text.process']);
        $form->submit(['input' => 'data.csv', 'context' => ['extra' => [['key' => 'foo', 'value' => '']]]]);

        self::assertTrue($form->isSubmitted());
        self::assertFalse($form->isValid());
    }

    public function testContextRowWithoutKey(): void
    {
        $form = $this->factory->create(LaunchType::class, null, ['process_code' => 'text.process']);
        $form->submit(['input' => 'data.csv', 'context' => ['extra' => [['key' => 'foo', 'value' => 'bar'], ['key' => null, 'value' => 'qux']]]]);

        // The row without key is left out of the context, and reported as invalid
        self::assertSame(['foo' => 'bar'], $form->get('context')->getData());
        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('context')->get('extra')->get('1')->get('key')->getErrors());
    }

    public function testDeclaredFields(): void
    {
        $form = $this->factory->create(LaunchType::class, null, ['process_code' => 'fields.process']);
        $context = $form->get('context');

        self::assertSame(['delimiter', 'dry_run', 'limit', 'extra'], array_keys($context->all()));
        $delimiter = $context->get('delimiter')->getConfig();
        self::assertInstanceOf(ChoiceType::class, $delimiter->getType()->getInnerType());
        self::assertSame(['Comma' => ',', 'Semicolon' => ';'], $delimiter->getOption('choices'));
        self::assertSame('Delimiter', $delimiter->getOption('label'));
        self::assertInstanceOf(CheckboxType::class, $this->getInnerType($context->get('dry_run')));
        self::assertInstanceOf(IntegerType::class, $this->getInnerType($context->get('limit')));
        $extra = $context->get('extra')->getConfig();
        self::assertSame('Context (key/value)', $extra->getOption('label'));
        self::assertSame(['delimiter', 'dry_run', 'limit'], $this->getReservedKeys($extra->getOption('entry_options')));
    }

    public function testSubmitDeclaredAndExtraContext(): void
    {
        $form = $this->factory->create(LaunchType::class, null, ['process_code' => 'fields.process']);
        $form->submit(['input' => '', 'context' => [
            'delimiter' => ';',
            'dry_run' => '1',
            'limit' => '3',
            'extra' => [['key' => 'k2', 'value' => 'v2']],
        ]]);

        self::assertTrue($form->isValid());
        self::assertSame(['delimiter' => ';', 'dry_run' => true, 'limit' => 3, 'k2' => 'v2'], $form->get('context')->getData());
    }

    public function testExtraContextRowWithADeclaredKey(): void
    {
        $form = $this->factory->create(LaunchType::class, null, ['process_code' => 'fields.process']);
        $form->submit(['context' => [
            'delimiter' => ';',
            'extra' => [['key' => 'k2', 'value' => 'v2'], ['key' => 'delimiter', 'value' => 'zz']],
        ]]);

        self::assertFalse($form->isValid());
        $errors = $form->get('context')->get('extra')->get('1')->get('key')->getErrors();
        self::assertCount(1, $errors);
        self::assertSame('The context key "delimiter" is a field of the launch form, use this field instead.', $errors[0]->getMessage());
        self::assertCount(0, $form->get('context')->get('delimiter')->getErrors());
    }

    public function testFieldConstraint(): void
    {
        $form = $this->factory->create(LaunchType::class, null, ['process_code' => 'fields.process']);
        $form->submit(['context' => ['delimiter' => ',', 'limit' => '-1']]);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('context')->get('limit')->getErrors());
    }

    public function testGlobalConstraintOnADeclaredField(): void
    {
        $form = $this->factory->create(LaunchType::class, null, [
            'process_code' => 'fields.process',
            'constraints' => [new Collection(
                fields: ['context' => new Collection(fields: ['delimiter' => new Choice(choices: [','])], allowExtraFields: true)],
                allowExtraFields: true,
            )],
        ]);
        $form->submit(['context' => ['delimiter' => ';']]);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('context')->get('delimiter')->getErrors());
    }

    public function testExtraContextNotAllowed(): void
    {
        $form = $this->factory->create(LaunchType::class, null, ['process_code' => 'strict.process']);

        self::assertSame(['delimiter'], array_keys($form->get('context')->all()));

        $form->submit(['context' => ['delimiter' => ',', 'extra' => [['key' => 'k', 'value' => 'v']]]]);
        self::assertFalse($form->isValid());
    }

    /**
     * Defensive: free keys in the data of a process without free context are not turned into rows, and are left out
     * of the submitted context.
     */
    public function testFreeDefaultsWithoutExtraContext(): void
    {
        $form = $this->factory->create(LaunchType::class, null, ['process_code' => 'strict.process']);
        $form->setData(['input' => null, 'context' => ['delimiter' => ';', 'foo' => 'bar']]);

        self::assertSame(';', $form->get('context')->get('delimiter')->getData());
        self::assertFalse($form->get('context')->has('extra'));

        $form->submit(['context' => ['delimiter' => ',']]);
        self::assertTrue($form->isValid());
        self::assertSame(['delimiter' => ','], $form->get('context')->getData());
    }

    public function testDefaultsOfDeclaredAndExtraContext(): void
    {
        $form = $this->factory->create(LaunchType::class, null, ['process_code' => 'fields.process']);
        $form->setData(['input' => null, 'context' => ['delimiter' => ';', 'dry_run' => true, 'limit' => null, 'foo' => 'bar']]);

        $context = $form->get('context');
        self::assertSame(';', $context->get('delimiter')->getData());
        self::assertSame([['key' => 'foo', 'value' => 'bar']], $context->get('extra')->getData());

        $form->submit(['context' => ['delimiter' => ',', 'extra' => []]]);
        self::assertTrue($form->isValid());
        self::assertSame(['delimiter' => ',', 'dry_run' => false, 'limit' => null], $context->getData());
    }

    public function testRequiredOptionOfTheDeclaredFields(): void
    {
        $view = $this->factory->create(LaunchType::class, null, ['process_code' => 'fields.process'])->createView();
        $context = $view->children['context'];

        self::assertFalse($context->vars['required'], 'The context group is not rendered as required');
        self::assertTrue($context->children['delimiter']->vars['required'], 'The required option of the field is configured');
        // Not the Symfony default: a required checkbox must be checked
        self::assertFalse($context->children['limit']->vars['required'], 'Not required by default');
        self::assertFalse($context->children['dry_run']->vars['required'], 'Not required by default');
        self::assertFalse($context->children['extra']->vars['required'], 'The free context pairs are not required');
    }

    public function testRequiredOptionOfTheInput(): void
    {
        $form = $this->factory->create(LaunchType::class, null, ['process_code' => 'fields.process']);

        self::assertFalse($form->get('input')->getConfig()->getRequired(), 'The required option of the input is configured');
    }

    /**
     * The launch form already resolved by the caller is not resolved again.
     */
    public function testResolvedLaunchForm(): void
    {
        $registry = new ProcessConfigurationRegistry(['file.process' => $this->rawProcess(['ui' => ['entrypoint_type' => 'file']], 'data')], 'stop');
        $launchForm = null;
        $resolutionDeprecations = $this->collectDeprecations(static function () use ($registry, &$launchForm): void {
            $launchForm = (new ProcessConfigurationsManager($registry))->getLaunchFormOptions('file.process');
        });
        self::assertSame(['Since cleverage/ui-process-bundle 3.4: The "entrypoint_type" UI option of the process "file.process" is deprecated, use "form.input.type" instead.'], $resolutionDeprecations);
        self::assertIsArray($launchForm);

        $form = null;
        $deprecations = $this->collectDeprecations(function () use (&$form, $launchForm): void {
            $form = $this->factory->create(LaunchType::class, null, ['process_code' => 'file.process', 'launch_form' => $launchForm]);
        });

        self::assertSame([], $deprecations);
        self::assertInstanceOf(FormInterface::class, $form);
        self::assertInstanceOf(FileType::class, $this->getInnerType($form->get('input')));
    }

    public function testLaunchFormMustBeAnArray(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->factory->create(LaunchType::class, null, ['process_code' => 'text.process', 'launch_form' => 'form']);
    }

    public function testProcessCodeIsRequired(): void
    {
        $this->expectException(MissingOptionsException::class);
        $this->factory->create(LaunchType::class);
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

    private function getReservedKeys(mixed $entryOptions): mixed
    {
        self::assertIsArray($entryOptions);

        return $entryOptions['reserved_keys'] ?? null;
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function getInnerType(FormInterface $form): object
    {
        return $form->getConfig()->getType()->getInnerType();
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function rawProcess(array $options = [], ?string $entryPoint = null): array
    {
        return [
            'options' => $options,
            'entry_point' => $entryPoint,
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
}
