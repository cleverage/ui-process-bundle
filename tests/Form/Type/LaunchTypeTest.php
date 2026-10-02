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
use CleverAge\UiProcessBundle\Form\Type\LaunchType;
use CleverAge\UiProcessBundle\Form\Type\ProcessContextType;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\Validator\Validation;

#[CoversClass(LaunchType::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
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

        $context = $form->get('context')->getConfig();
        self::assertInstanceOf(CollectionType::class, $context->getType()->getInnerType());
        self::assertSame(ProcessContextType::class, $context->getOption('entry_type'));
        self::assertTrue($context->getOption('allow_add'));
        self::assertTrue($context->getOption('allow_delete'));
        self::assertFalse($context->getOption('required'));
    }

    public function testFileInput(): void
    {
        $form = $this->factory->create(LaunchType::class, null, ['process_code' => 'file.process']);

        self::assertInstanceOf(FileType::class, $this->getInnerType($form->get('input')));
        self::assertTrue($form->get('input')->getConfig()->getRequired(), 'The process has an entry point');
    }

    public function testSubmit(): void
    {
        $form = $this->factory->create(LaunchType::class, null, ['process_code' => 'text.process']);
        $form->submit([
            'input' => 'data.csv',
            'context' => [
                ['key' => 'foo', 'value' => 'bar'],
                ['key' => 'baz', 'value' => 'qux'],
            ],
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
        $form->submit(['input' => 'data.csv', 'context' => [['key' => 'foo', 'value' => '']]]);

        self::assertTrue($form->isSubmitted());
        self::assertFalse($form->isValid());
    }

    public function testProcessCodeIsRequired(): void
    {
        $this->expectException(MissingOptionsException::class);
        $this->factory->create(LaunchType::class);
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
