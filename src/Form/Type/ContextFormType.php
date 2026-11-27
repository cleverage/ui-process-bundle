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

use CleverAge\UiProcessBundle\Form\ContextValueException;
use CleverAge\UiProcessBundle\Form\ContextValueNormalizer;
use CleverAge\UiProcessBundle\Form\LaunchFormOptionsResolver;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The context of a process: the fields declared in the "form.context" UI option of the process (its launch form), and
 * the free key/value pairs ("extra") when the process allows them. Without process, or when the process declares no
 * launch form, only the free pairs are available.
 *
 * Its data is the context as a "key => value" array. With the "as_rows" option, it is read from and written to a list of
 * "{key, value}" rows, as stored by a ProcessSchedule: the values are then converted into scalars (enums, dates...),
 * so that they can be stored as JSON.
 *
 * @phpstan-import-type LaunchFormOptions from LaunchFormOptionsResolver
 *
 * @internal
 *
 * @template-extends AbstractType<array<string, mixed>|list<array{key: int|string, value: mixed}>|null>
 */
class ContextFormType extends AbstractType
{
    public function __construct(private readonly ProcessConfigurationsManager $configurationsManager)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var string|null $process */
        $process = $options['process'];
        /** @var array{context: array<string, array{type: class-string, options: array<string, mixed>, default: mixed}>, allow_extra_context: bool}|null $form */
        $form = $options['launch_form'] ?? $this->getLaunchForm($process);
        $fields = $form['context'] ?? [];
        $allowExtra = $form['allow_extra_context'] ?? true;

        // Not "required: false": a child of a form that is not required is never required (HTML attribute), the
        // declared fields follow their own "required" option, not required by default (a required checkbox must be
        // checked); the free context pairs are not required
        foreach ($fields as $key => $field) {
            /** @var class-string<FormTypeInterface<mixed>> $type */
            $type = $field['type'];
            $builder->add($key, $type, $field['options'] + ['required' => false]);
        }
        if ($allowExtra) {
            $builder->add(
                'extra',
                CollectionType::class,
                [
                    'entry_type' => ProcessContextType::class,
                    'entry_options' => ['reserved_keys' => array_keys($fields), 'reserve_execution_user' => true],
                    'allow_add' => true,
                    'allow_delete' => true,
                    'required' => false,
                    'mapped' => false,
                    'label' => [] === $fields ? false : 'Context (key/value)',
                ]
            );
            // On POST_SET_DATA: the data mapper resets the unmapped children with their configured data after PRE_SET_DATA
            $builder->addEventListener(FormEvents::POST_SET_DATA, static function (FormEvent $event) use ($fields): void {
                // The normalized data: the model data are rows with "as_rows"
                /** @var array<string, mixed> $data */
                $data = $event->getForm()->getNormData() ?? [];
                $rows = [];
                foreach (array_diff_key($data, $fields) as $key => $value) {
                    $rows[] = ['key' => $key, 'value' => $value];
                }
                $event->getForm()->get('extra')->setData($rows);
            });
        }
        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event) use ($fields): void {
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
            $event->setData(array_intersect_key($data, $fields) + $extra);
        });

        if (true === $options['as_rows']) {
            $normalizer = new ContextValueNormalizer();
            $factory = $builder->getFormFactory();
            $builder->addModelTransformer(new CallbackTransformer(
                static function (mixed $rows) use ($fields, $factory): array {
                    $context = [];
                    foreach (\is_array($rows) ? $rows : [] as $row) {
                        // The last row of a key wins, as when the schedule is run
                        if (\is_array($row) && \array_key_exists('key', $row)) {
                            $context[\is_scalar($row['key']) ? (string) $row['key'] : ''] = $row['value'] ?? null;
                        }
                    }
                    // A stored scalar that its field cannot read (e.g. an ATOM string in a "DateType" field with the
                    // default "datetime" input) is displayed empty, instead of failing the whole page
                    foreach ($fields as $key => $field) {
                        if (null !== ($context[$key] ?? null) && !self::isReadableByField($factory, (string) $key, $field, $context[$key])) {
                            unset($context[$key]);
                        }
                    }

                    return $context;
                },
                static function (mixed $context) use ($normalizer): array {
                    $rows = [];
                    foreach (\is_array($context) ? $context : [] as $key => $value) {
                        try {
                            $rows[] = ['key' => (string) $key, 'value' => $normalizer->normalize($value, (string) $key)];
                        } catch (ContextValueException $e) {
                            throw new TransformationFailedException($e->getMessage(), 0, $e, $e->messageTemplate, $e->parameters);
                        }
                    }

                    return $rows;
                }
            ));
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // The code of the process whose launch form declares the fields of the context
        $resolver->setDefault('process', null);
        $resolver->setAllowedTypes('process', ['null', 'string']);
        // @internal The launch form of the process, as resolved by ProcessConfigurationsManager::getLaunchFormOptions():
        // resolved from the process code when null
        $resolver->setDefault('launch_form', null);
        $resolver->setAllowedTypes('launch_form', ['null', 'array']);
        // The data are a list of "{key, value}" rows (the context of a ProcessSchedule), not a "key => value" array
        $resolver->setDefault('as_rows', false);
        $resolver->setAllowedTypes('as_rows', 'bool');
    }

    #[\Override]
    public function getParent(): string
    {
        return FormType::class;
    }

    /**
     * @return LaunchFormOptions|null null without declared launch form: only the free key/value pairs are available
     */
    private function getLaunchForm(?string $process): ?array
    {
        if (null === $process || '' === $process) {
            return null;
        }
        // Not a public process (unknown, private): scheduled processes are validated by IsValidProcessCode
        if (!\array_key_exists($process, $this->configurationsManager->getPublicProcesses())) {
            return null;
        }
        if ('form' !== ($this->configurationsManager->getUiOptions($process)['ui_launch_mode'] ?? null)) {
            return null;
        }

        try {
            return $this->configurationsManager->getLaunchFormOptions($process);
        } catch (InvalidOptionsException) {
            // An invalid launch form only breaks the launch page of its process (see ProcessConfigurationsManager),
            // not the forms of the other processes: the free key/value pairs are available
            return null;
        }
    }

    /**
     * @param array{type: class-string, options: array<string, mixed>, default: mixed} $field
     */
    private static function isReadableByField(FormFactoryInterface $factory, string $key, array $field, mixed $value): bool
    {
        /** @var class-string<FormTypeInterface<mixed>> $type */
        $type = $field['type'];
        try {
            $factory->createNamed($key, $type, null, $field['options'] + ['required' => false])->setData($value);
        } catch (TransformationFailedException|\LogicException) {
            // Form::setData() rethrows the TransformationFailedException of the transformers as a LogicException
            return false;
        }

        return true;
    }
}
