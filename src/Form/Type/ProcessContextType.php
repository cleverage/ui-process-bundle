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
use Symfony\Component\Validator\Constraints\NotBlank;

/** @template-extends AbstractType<null> */
class ProcessContextType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'key',
            null,
            [
                'label' => 'Context Key',
                'attr' => ['placeholder' => 'key'],
                'constraints' => [new NotBlank()],
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
    }
}
