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
use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @phpstan-import-type UiOptions from ProcessConfigurationsManager
 *
 * @template-extends AbstractType<array<string, mixed>>
 */
class LaunchType extends AbstractType
{
    public function __construct(
        private readonly ProcessConfigurationRegistry $registry,
        private readonly ProcessConfigurationsManager $configurationsManager,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var string $code */
        $code = $options['process_code'];
        $configuration = $this->registry->getProcessConfiguration($code);
        /** @var UiOptions $uiOptions */
        $uiOptions = $options['ui_options'] ?? $this->configurationsManager->getUiOptions($code) ?? throw MissingProcessException::create($code);
        $form = $uiOptions['form'];

        // A "required" form option of the input takes precedence
        $builder->add(
            'input',
            $form['input']['type'],
            $form['input']['options'] + ['required' => $configuration->getEntryPoint() instanceof TaskConfiguration]
        );

        // Not "required: false": a child of a form that is not required is never required (HTML attribute), the
        // declared fields follow their own "required" option; the free context pairs are not required
        $context = $builder->create('context', FormType::class);
        foreach ($form['context'] as $key => $field) {
            $context->add($key, $field['type'], $field['options']);
        }
        if ($form['allow_extra_context']) {
            $context->add(
                'extra',
                CollectionType::class,
                [
                    'entry_type' => ProcessContextType::class,
                    'entry_options' => [
                        'reserved_keys' => array_keys($form['context']),
                        'key_constraints' => [ProcessContextType::createExecutionUserConstraint()],
                    ],
                    'allow_add' => true,
                    'allow_delete' => true,
                    'required' => false,
                    'mapped' => false,
                    'label' => [] === $form['context'] ? false : 'Context (key/value)',
                ]
            );
            // On POST_SET_DATA: the data mapper resets the unmapped children with their configured data after PRE_SET_DATA
            $context->addEventListener(FormEvents::POST_SET_DATA, static function (FormEvent $event) use ($form): void {
                /** @var array<string, mixed> $data */
                $data = $event->getData() ?? [];
                $rows = [];
                foreach (array_diff_key($data, $form['context']) as $key => $value) {
                    $rows[] = ['key' => $key, 'value' => $value];
                }
                $event->getForm()->get('extra')->setData($rows);
            });
        }
        $context->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event) use ($form): void {
            /** @var array<string, mixed> $data */
            $data = $event->getData() ?? [];
            $extra = [];
            if ($event->getForm()->has('extra')) {
                /** @var array<mixed> $rows */
                $rows = $event->getForm()->get('extra')->getData() ?? [];
                // A row without key is left out (it is reported by the NotBlank constraint of ProcessContextType): a
                // null key is deprecated as array offset, and an empty key breaks the property paths of the process
                // constraints
                $extra = array_column(
                    array_filter($rows, static fn ($row): bool => \is_array($row) && null !== ($row['key'] ?? null) && '' !== $row['key']),
                    'value',
                    'key'
                );
            }
            // The declared keys take precedence, a free row with a declared key is reported by ProcessContextType
            $event->setData(array_intersect_key($data, $form['context']) + $extra);
        });
        $builder->add($context);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        // The context group itself is not required: its label is rendered as before 3.3, without the required mark
        $view['context']->vars['required'] = false;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('process_code');
        // The UI options of the process, as resolved by ProcessConfigurationsManager::getUiOptions(): resolved from
        // the process code when null
        $resolver->setDefault('ui_options', null);
        $resolver->setAllowedTypes('ui_options', ['null', 'array']);
    }

    #[\Override]
    public function getParent(): string
    {
        return FormType::class;
    }
}
