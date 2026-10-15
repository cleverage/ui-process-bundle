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
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Validator\Constraints\Choice;
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
            self::assertInstanceOf(NotBlank::class, $constraints[0]);
        }
        // The format of the key is checked
        self::assertCount(2, (array) $form->get('key')->getConfig()->getOption('constraints'));
        self::assertCount(1, (array) $form->get('value')->getConfig()->getOption('constraints'));
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

    public function testNoReservedKeysByDefault(): void
    {
        $form = $this->factory->create(ProcessContextType::class);
        $form->submit(['key' => 'delimiter', 'value' => ';']);

        self::assertSame([], $form->getConfig()->getOption('reserved_keys'));
        self::assertTrue($form->isValid());
    }

    public function testReservedKey(): void
    {
        $form = $this->factory->create(ProcessContextType::class, null, ['reserved_keys' => ['delimiter']]);
        $form->submit(['key' => 'delimiter', 'value' => ';']);

        self::assertFalse($form->isValid());
        $errors = $form->get('key')->getErrors();
        self::assertCount(1, $errors);
        self::assertSame('The context key "delimiter" is a field of the launch form, use this field instead.', $errors[0]->getMessage());
        self::assertCount(0, $form->get('value')->getErrors());
    }

    #[TestWith(['other'])]
    #[TestWith(['Delimiter'])]
    public function testKeyIsNotReserved(string $key): void
    {
        $form = $this->factory->create(ProcessContextType::class, null, ['reserved_keys' => ['delimiter']]);
        $form->submit(['key' => $key, 'value' => ';']);

        self::assertTrue($form->isValid());
    }

    public function testReservedKeysMustBeAnArray(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->factory->create(ProcessContextType::class, null, ['reserved_keys' => 'delimiter']);
    }

    #[TestWith(['foo'])]
    #[TestWith(['Delimiter'])]
    #[TestWith(['a-b:c'])]
    #[TestWith(['_x'])]
    #[TestWith(['1a'])]
    #[TestWith(['dry_run'])]
    public function testValidKeyFormat(string $key): void
    {
        $form = $this->factory->create(ProcessContextType::class);
        $form->submit(['key' => $key, 'value' => 'bar']);

        self::assertTrue($form->isValid());
    }

    #[TestWith(['zz)|x|(?:zz'])]
    #[TestWith(['a/b'])]
    #[TestWith(['a b'])]
    #[TestWith(['{{x}}'])]
    #[TestWith(['-a'])]
    #[TestWith(['123'])]
    public function testInvalidKeyFormat(string $key): void
    {
        $form = $this->factory->create(ProcessContextType::class);
        $form->submit(['key' => $key, 'value' => 'bar']);

        self::assertFalse($form->isValid());
        $errors = $form->get('key')->getErrors();
        self::assertCount(1, $errors);
        self::assertSame(
            \sprintf('The context key "%s" is not valid: use letters, digits, "_", "-" and ":", starting with a letter, a digit or "_", and not only digits.', $key),
            $errors[0]->getMessage()
        );
    }

    public function testEmptyKey(): void
    {
        $form = $this->factory->create(ProcessContextType::class);
        $form->submit(['key' => '', 'value' => 'bar']);

        self::assertFalse($form->isValid());
        // Reported by the NotBlank constraint only
        self::assertCount(1, $form->get('key')->getErrors());
    }

    public function testKeyConstraints(): void
    {
        $form = $this->factory->create(ProcessContextType::class, null, [
            'key_constraints' => [new Choice(choices: ['execution_user'], message: 'Reserved key {{ value }}.', match: false)],
        ]);
        $form->submit(['key' => 'execution_user', 'value' => 'bar']);

        self::assertFalse($form->isValid());
        $errors = $form->get('key')->getErrors();
        self::assertCount(1, $errors);
        self::assertSame('Reserved key "execution_user".', $errors[0]->getMessage());
    }

    public function testKeyConstraintsMustBeConstraints(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->factory->create(ProcessContextType::class, null, ['key_constraints' => ['NotBlank']]);
    }
}
