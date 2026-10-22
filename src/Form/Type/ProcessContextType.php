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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
/** @template-extends AbstractType<null> */
class ProcessContextType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $keyConstraints = [new NotBlank()];
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('reserved_keys', []);
        $resolver->setAllowedTypes('reserved_keys', 'string[]');
    }
}
