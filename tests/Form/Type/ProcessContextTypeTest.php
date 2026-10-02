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

use CleverAge\UiProcessBundle\Form\Type\ProcessContextType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

#[CoversClass(ProcessContextType::class)]
class ProcessContextTypeTest extends TypeTestCase
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
        return [new ValidatorExtension(Validation::createValidator())];
    }

    public function testFields(): void
    {
        $form = $this->factory->create(ProcessContextType::class);

        foreach (['key' => 'Context Key', 'value' => 'Context Value'] as $name => $label) {
            $config = $form->get($name)->getConfig();
            self::assertSame($label, $config->getOption('label'));
            self::assertSame(['placeholder' => $name], $config->getOption('attr'));
            $constraints = $config->getOption('constraints');
            self::assertIsArray($constraints);
            self::assertCount(1, $constraints);
            self::assertInstanceOf(NotBlank::class, $constraints[0]);
        }
    }

    public function testSubmitValidData(): void
    {
        $form = $this->factory->create(ProcessContextType::class);
        $form->submit(['key' => 'foo', 'value' => 'bar']);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertSame(['key' => 'foo', 'value' => 'bar'], $form->getData());
    }

    #[TestWith(['', 'bar', 'key'])]
    #[TestWith(['foo', '', 'value'])]
    public function testKeyAndValueAreRequired(string $key, string $value, string $invalidField): void
    {
        $form = $this->factory->create(ProcessContextType::class);
        $form->submit(['key' => $key, 'value' => $value]);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get($invalidField)->getErrors());
    }
}
