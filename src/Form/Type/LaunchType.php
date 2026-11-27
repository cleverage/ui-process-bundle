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

use CleverAge\ProcessBundle\Configuration\TaskConfiguration;
use CleverAge\ProcessBundle\Exception\MissingProcessException;
use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistryInterface;
use CleverAge\UiProcessBundle\Form\LaunchFormOptionsResolver;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @phpstan-import-type LaunchFormOptions from LaunchFormOptionsResolver
 *
 * @internal
 *
 * @template-extends AbstractType<array<string, mixed>>
 */
class LaunchType extends AbstractType
{
    public function __construct(
        private readonly ProcessConfigurationRegistryInterface $registry,
        private readonly ProcessConfigurationsManager $configurationsManager,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var string $code */
        $code = $options['process_code'];
        $configuration = $this->registry->getProcessConfiguration($code);
        /** @var LaunchFormOptions $form */
        $form = $options['launch_form'] ?? $this->configurationsManager->getLaunchFormOptions($code) ?? throw MissingProcessException::create($code);

        // A "required" form option of the input takes precedence
        $builder->add(
            'input',
            $form['input']['type'],
            $form['input']['options'] + ['required' => $configuration->getEntryPoint() instanceof TaskConfiguration]
        );

        $builder->add('context', ContextFormType::class, ['process' => $code, 'launch_form' => $form]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        // The context group itself is not required: its label is rendered as before 3.3, without the required mark
        $view['context']->vars['required'] = false;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('process_code');
        // @internal The launch form of the process, as resolved by ProcessConfigurationsManager::getLaunchFormOptions():
        // resolved from the process code when null
        $resolver->setDefault('launch_form', null);
        $resolver->setAllowedTypes('launch_form', ['null', 'array']);
    }

    #[\Override]
    public function getParent(): string
    {
        return FormType::class;
    }
}
