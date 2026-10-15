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

namespace CleverAge\UiProcessBundle\Form\Type;

use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/** @template-extends AbstractType<null> */
class ProcessContextType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $keyConstraints = [
            new NotBlank(),
            // The rule of FormConfigBuilder::isValidName() applied to the declared context keys of the launch form, and
            // not only digits (PHP casts such an array key to an integer)
            new Regex(
                pattern: '/^(?![0-9]+$)[a-zA-Z0-9_][a-zA-Z0-9_\-:]*$/D',
                message: 'The context key {{ value }} is not valid: use letters, digits, "_", "-" and ":", starting with a letter, a digit or "_", and not only digits.'
            ),
        ];
        /** @var string[] $reservedKeys */
        $reservedKeys = $options['reserved_keys'];
        if ([] !== $reservedKeys) {
            // The keys of the fields of the launch form
            $keyConstraints[] = new Choice(
                choices: $reservedKeys,
                message: 'The context key {{ value }} is a field of the launch form, use this field instead.',
                match: false
            );
        }
        /** @var Constraint[] $additionalKeyConstraints */
        $additionalKeyConstraints = $options['key_constraints'];
        $keyConstraints = [...$keyConstraints, ...$additionalKeyConstraints];
        $builder->add(
            'key',
            null,
            [
                'label' => 'Context Key',
                'attr' => ['placeholder' => 'key'],
                'constraints' => $keyConstraints,
            ]
        )->add(
            'value',
            null,
            [
                'label' => 'Context Value',
                'attr' => ['placeholder' => 'value'],
                'constraints' => [new NotBlank()],
            ]
        );
    }

    /**
     * The constraint of a context key reserving "execution_user", set to the email of the connected user when a process
     * is launched from the UI.
     */
    public static function createExecutionUserConstraint(): Choice
    {
        return new Choice(
            choices: [ProcessExecuteMessage::EXECUTION_USER_KEY],
            message: 'The context key {{ value }} is reserved: it is set to the email of the connected user.',
            match: false
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('reserved_keys', []);
        $resolver->setAllowedTypes('reserved_keys', 'string[]');
        // Additional constraints of the key
        $resolver->setDefault('key_constraints', []);
        $resolver->setAllowedTypes('key_constraints', Constraint::class.'[]');
    }
}
