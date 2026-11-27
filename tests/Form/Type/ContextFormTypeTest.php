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
use CleverAge\UiProcessBundle\Form\ContextValueException;
use CleverAge\UiProcessBundle\Form\ContextValueNormalizer;
use CleverAge\UiProcessBundle\Form\LaunchFormOptionsResolver;
use CleverAge\UiProcessBundle\Form\Type\ContextFormType;
use CleverAge\UiProcessBundle\Form\Type\ProcessContextType;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

#[CoversClass(ContextFormType::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
#[UsesClass(LaunchFormOptionsResolver::class)]
#[UsesClass(ProcessContextType::class)]
#[UsesClass(ContextValueNormalizer::class)]
#[UsesClass(ContextValueException::class)]
class ContextFormTypeTest extends TypeTestCase
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
            'form.process' => $this->rawProcess(['ui' => ['ui_launch_mode' => 'form', 'form' => [
                'context' => ['delimiter' => ['type' => ChoiceType::class, 'default' => ',', 'options' => ['choices' => ['Comma' => ',', 'Semicolon' => ';']]]],
                'allow_extra_context' => false,
            ]]]),
            'modal.process' => $this->rawProcess(),
            'free.process' => $this->rawProcess(['ui' => ['ui_launch_mode' => 'form']]),
            'date.process' => $this->rawProcess(['ui' => ['ui_launch_mode' => 'form', 'form' => [
                'context' => ['day' => ['type' => DateType::class, 'options' => ['widget' => 'single_text']]],
                'allow_extra_context' => false,
            ]]]),
            'broken.process' => $this->rawProcess(['ui' => ['ui_launch_mode' => 'form', 'form' => [
                'context' => ['code' => ['type' => 'App\\NotAFormType']],
            ]]]),
        ], 'stop');

        return [
            new PreloadedExtension([new ContextFormType(new ProcessConfigurationsManager($registry))], []),
            new ValidatorExtension(Validation::createValidator()),
        ];
    }

    public function testDeclaredFieldsOfTheProcess(): void
    {
        $form = $this->factory->create(ContextFormType::class, null, ['process' => 'form.process']);

        self::assertSame(['delimiter'], array_keys($form->all()));
    }

    public function testFreePairsWithoutLaunchForm(): void
    {
        foreach ([null, 'unknown.process', 'modal.process', 'free.process'] as $process) {
            $form = $this->factory->create(ContextFormType::class, null, ['process' => $process]);

            self::assertSame(['extra'], array_keys($form->all()), (string) $process);
        }
    }

    public function testRows(): void
    {
        $form = $this->factory->create(ContextFormType::class, [['key' => 'delimiter', 'value' => ';']], ['process' => 'form.process', 'as_rows' => true]);
        self::assertSame(';', $form->get('delimiter')->getData());

        $form->submit(['delimiter' => ',']);

        self::assertTrue($form->isSynchronized());
        self::assertSame([['key' => 'delimiter', 'value' => ',']], $form->getData());
    }

    public function testStoredValueUnreadableByItsFieldIsDisplayedEmpty(): void
    {
        // The stored scalar of a date (ATOM string) cannot be read by a DateType field with the "datetime" input
        $form = $this->factory->create(ContextFormType::class, [['key' => 'day', 'value' => '2026-11-27T00:00:00+00:00']], ['process' => 'date.process', 'as_rows' => true]);

        self::assertNull($form->get('day')->getData());
    }

    public function testInvalidLaunchFormFallsBackToFreePairs(): void
    {
        $form = $this->factory->create(ContextFormType::class, null, ['process' => 'broken.process']);

        self::assertSame(['extra'], array_keys($form->all()));
    }

    public function testRowsWithFreePairs(): void
    {
        $form = $this->factory->create(ContextFormType::class, [['key' => 'a', 'value' => '1'], ['key' => 'b', 'value' => '2'], ['key' => 'a', 'value' => '3']], ['as_rows' => true]);

        // The last row of a key wins
        self::assertSame([['key' => 'a', 'value' => '3'], ['key' => 'b', 'value' => '2']], array_values((array) $form->get('extra')->getData()));

        $form->submit(['extra' => [['key' => 'c', 'value' => '4']]]);

        self::assertSame([['key' => 'c', 'value' => '4']], $form->getData());
    }

    public function testKeyValueArrayByDefault(): void
    {
        $form = $this->factory->create(ContextFormType::class, null, ['process' => 'form.process']);
        $form->submit(['delimiter' => ';']);

        self::assertSame(['delimiter' => ';'], $form->getData());
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function rawProcess(array $options = []): array
    {
        return [
            'options' => $options,
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
}
