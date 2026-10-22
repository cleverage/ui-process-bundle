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

namespace CleverAge\UiProcessBundle\Form;

use CleverAge\ProcessBundle\Validator\ConstraintLoader;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use Symfony\Component\Form\ButtonTypeInterface;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormConfigBuilder;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\SubmitButtonTypeInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraint;

/**
 * Resolves the "form" UI option of a process (the launch form), the deprecated "entrypoint_type", "default" and
 * "constraints" UI options folded into it. Only used by the launch page: the other pages (process list, executions)
 * do not depend on the launch form.
 *
 * @internal
 *
 * @phpstan-import-type UiOptions from ProcessConfigurationsManager
 *
 * @phpstan-type LaunchFormField array{
 *      'type': class-string<FormTypeInterface<mixed>>,
 *      'options': array<string, mixed>,
 *      'default': mixed
 *  }
 * @phpstan-type LaunchFormData array{'input': mixed, 'context': array<string, mixed>}
 * @phpstan-type LaunchFormOptions array{
 *      'input': LaunchFormField,
 *      'context': array<string, LaunchFormField>,
 *      'allow_extra_context': bool,
 *      'constraints': Constraint[],
 *      'data': LaunchFormData
 *  }
 * @phpstan-type ResolvedForm array{
 *      'input': LaunchFormField|null,
 *      'context': array<string, LaunchFormField>,
 *      'allow_extra_context': bool,
 *      'constraints': Constraint[]
 *  }
 */
final class LaunchFormOptionsResolver
{
    /**
     * The form options of a launch form field that break the mapping of the context or the default values.
     */
    private const UNSUPPORTED_FIELD_OPTIONS = ['data', 'mapped', 'property_path', 'inherit_data'];

    /**
     * The deprecated UI options, with their replacement.
     */
    private const DEPRECATED_OPTIONS = [
        'entrypoint_type' => '"form.input.type"',
        'constraints' => '"form.constraints"',
        'default' => '"form.input.default" and "form.context.<key>.default"',
    ];

    /**
     * @param array<int|string, mixed> $ui        the raw UI options of the process, to know whether an option is given
     * @param UiOptions                $uiOptions the UI options of the process, as resolved by getUiOptions()
     *
     * @return LaunchFormOptions
     */
    public function resolve(array $ui, array $uiOptions, string $processCode): array
    {
        foreach (self::DEPRECATED_OPTIONS as $option => $replacement) {
            if (\array_key_exists($option, $ui)) {
                trigger_deprecation('cleverage/ui-process-bundle', '3.4', 'The "%s" UI option of the process "%s" is deprecated, use %s instead.', $option, $processCode, $replacement);
            }
        }

        $rawForm = $ui['form'] ?? [];
        if (!\is_array($rawForm)) {
            throw new InvalidOptionsException(\sprintf('The "form" UI option of the process "%s" must be an array, "%s" given.', $processCode, get_debug_type($rawForm)));
        }
        $form = $this->foldDeprecatedOptions($ui, $rawForm, $this->resolveForm($rawForm, $processCode), $uiOptions, $processCode);
        $form['data'] = $this->buildData($form, $uiOptions);

        return $form;
    }

    /**
     * @param array<int|string, mixed> $form
     *
     * @return ResolvedForm
     */
    private function resolveForm(array $form, string $processCode): array
    {
        $formResolver = new OptionsResolver();
        $formResolver->setDefaults(['input' => null, 'context' => [], 'allow_extra_context' => true, 'constraints' => []]);
        $formResolver->setAllowedTypes('input', ['null', 'array']);
        $formResolver->setAllowedTypes('context', 'array');
        $formResolver->setAllowedTypes('allow_extra_context', 'bool');
        $formResolver->setAllowedTypes('constraints', 'array');
        $formResolver->setNormalizer(
            'input',
            fn (Options $options, ?array $input): ?array => null === $input ? null : $this->resolveField($input, 'form.input', $processCode, true)
        );
        $formResolver->setNormalizer('context', function (Options $options, array $context) use ($processCode): array {
            $fields = [];
            foreach ($context as $key => $spec) {
                if (\is_int($key)) {
                    throw new InvalidOptionsException(\sprintf('The context key "%d" of "form.context" of the process "%s" is not supported: purely numeric context keys are cast to integers by PHP.', $key, $processCode));
                }
                if ('' === $key || !FormConfigBuilder::isValidName($key)) {
                    throw new InvalidOptionsException(\sprintf('The context key "%s" of "form.context" of the process "%s" is not a valid form field name (letters, digits, "_", "-" and ":", starting with a letter, a digit or "_").', $key, $processCode));
                }
                // "extra" is the child of the free context pairs
                if ('extra' === $key) {
                    throw new InvalidOptionsException(\sprintf('The context key "%s" of "form.context" of the process "%s" is reserved.', $key, $processCode));
                }
                if (null !== $spec && !\is_array($spec)) {
                    throw new InvalidOptionsException(\sprintf('The launch form field "form.context.%s" of the process "%s" must be an array or null, "%s" given.', $key, $processCode, get_debug_type($spec)));
                }
                $fields[$key] = $this->resolveField($spec ?? [], "form.context.$key", $processCode, false);
            }

            return $fields;
        });
        $formResolver->setNormalizer('constraints', static fn (Options $options, array $values): array => (new ConstraintLoader())->buildConstraints($values));

        /** @var ResolvedForm $resolved */
        $resolved = $formResolver->resolve($form);

        return $resolved;
    }

    /**
     * @param array<int|string, mixed> $spec
     *
     * @return LaunchFormField
     */
    private function resolveField(array $spec, string $path, string $processCode, bool $isInput): array
    {
        $fieldResolver = new OptionsResolver();
        $fieldResolver->setDefaults(['type' => TextType::class, 'options' => [], 'default' => null]);
        $fieldResolver->setAllowedTypes('type', 'string');
        $fieldResolver->setAllowedTypes('options', 'array');
        $fieldResolver->setNormalizer('type', fn (Options $options, string $type): string => $this->resolveFormType($type, $path, $processCode));
        $fieldResolver->setNormalizer('options', static function (Options $options, array $fieldOptions) use ($path, $processCode): array {
            foreach (self::UNSUPPORTED_FIELD_OPTIONS as $option) {
                if (\array_key_exists($option, $fieldOptions)) {
                    throw new InvalidOptionsException(\sprintf('The "%s" form option of the launch form field "%s" of the process "%s" is not supported, use "default" for the initial value.', $option, $path, $processCode));
                }
            }
            if (\array_key_exists('constraints', $fieldOptions)) {
                if (!\is_array($fieldOptions['constraints'])) {
                    throw new InvalidOptionsException(\sprintf('The "constraints" form option of the launch form field "%s" of the process "%s" must be an array of constraints.', $path, $processCode));
                }
                $fieldOptions['constraints'] = (new ConstraintLoader())->buildConstraints($fieldOptions['constraints']);
            }

            return $fieldOptions;
        });

        /** @var LaunchFormField $resolved */
        $resolved = $fieldResolver->resolve($spec);
        $field = ['type' => $resolved['type'], 'options' => $resolved['options'], 'default' => $resolved['default']];

        if (is_a($field['type'], FileType::class, true)) {
            // A temporary file, removed at the end of the request, would end as a string in the context
            if (!$isInput) {
                throw new InvalidOptionsException(\sprintf('The launch form field "%s" of the process "%s" is a file field: file fields are only supported for the input.', $path, $processCode));
            }
            if (null !== $field['default']) {
                throw new InvalidOptionsException(\sprintf('The launch form field "%s" of the process "%s" is a file field, it cannot have a default value.', $path, $processCode));
            }
            // The launch action handles a single uploaded file
            if (true === ($field['options']['multiple'] ?? false)) {
                throw new InvalidOptionsException(\sprintf('The launch form field "%s" of the process "%s" cannot upload multiple files.', $path, $processCode));
            }
        }
        if (is_a($field['type'], EnumType::class, true) && null !== $field['default']) {
            $this->checkEnumDefault($field, $path, $processCode);
        }

        return $field;
    }

    /**
     * The default value of an enum field is an enum case (a list of cases with "multiple"): a YAML scalar would not be
     * transformed by the field.
     *
     * @param LaunchFormField $field
     */
    private function checkEnumDefault(array $field, string $path, string $processCode): void
    {
        $class = $field['options']['class'] ?? null;
        $cases = true === ($field['options']['multiple'] ?? false) && \is_array($field['default']) ? $field['default'] : [$field['default']];
        foreach ($cases as $case) {
            if (!$case instanceof \UnitEnum || (\is_string($class) && !$case instanceof $class)) {
                throw new InvalidOptionsException(\sprintf('The default value of the launch form field "%s" of the process "%s" must be a case of the enum "%s" (with "multiple", a list of cases), "%s" given: use the "!php/enum" YAML tag, e.g. "default: !php/enum App\\Enum\\Status::Active".', $path, $processCode, \is_string($class) ? $class : 'options.class', get_debug_type($case)));
            }
        }
    }

    /**
     * @return class-string<FormTypeInterface<mixed>>
     */
    private function resolveFormType(string $type, string $path, string $processCode): string
    {
        $class = ltrim($type, '\\');
        if (!class_exists($class)) {
            throw new InvalidOptionsException(\sprintf('The type "%s" of the launch form field "%s" of the process "%s" must be the FQCN of a form type, the class does not exist.', $type, $path, $processCode));
        }
        if (!is_subclass_of($class, FormTypeInterface::class)) {
            throw new InvalidOptionsException(\sprintf('The type "%s" of the launch form field "%s" of the process "%s" must implement "%s".', $type, $path, $processCode, FormTypeInterface::class));
        }
        // SubmitType implements SubmitButtonTypeInterface, which does not extend ButtonTypeInterface
        if (is_subclass_of($class, ButtonTypeInterface::class) || is_subclass_of($class, SubmitButtonTypeInterface::class)) {
            throw new InvalidOptionsException(\sprintf('The type "%s" of the launch form field "%s" of the process "%s" is a button type, it holds no data.', $type, $path, $processCode));
        }

        return $class;
    }

    /**
     * Checks the conflicts between the deprecated UI options and the "form" option, and folds the deprecated options
     * into the effective launch form.
     *
     * @param array<int|string, mixed> $ui        the raw UI options, to know whether an option is given
     * @param array<int|string, mixed> $rawForm   the raw "form" option
     * @param ResolvedForm             $form      the resolved "form" option
     * @param UiOptions                $uiOptions the resolved UI options (the deprecated options)
     *
     * @return array{
     *      'input': LaunchFormField,
     *      'context': array<string, LaunchFormField>,
     *      'allow_extra_context': bool,
     *      'constraints': Constraint[]
     *  }
     */
    private function foldDeprecatedOptions(array $ui, array $rawForm, array $form, array $uiOptions, string $processCode): array
    {
        /** @var array<string, mixed>|null $rawInput */
        $rawInput = $rawForm['input'] ?? null;
        /** @var array<string, array<string, mixed>|null> $rawContext */
        $rawContext = $rawForm['context'] ?? [];
        /** @var array<string, mixed> $rawDefault */
        $rawDefault = $ui['default'] ?? [];

        if (\array_key_exists('entrypoint_type', $ui) && null !== $rawInput) {
            throw new InvalidOptionsException(\sprintf('The "entrypoint_type" and "form.input" UI options of the process "%s" cannot be used together, use "form.input" only.', $processCode));
        }
        if (\array_key_exists('constraints', $ui) && \array_key_exists('constraints', $rawForm)) {
            throw new InvalidOptionsException(\sprintf('The "constraints" and "form.constraints" UI options of the process "%s" cannot be used together, use "form.constraints" only.', $processCode));
        }
        $hasDefaultInput = \array_key_exists('input', $rawDefault);
        if ($hasDefaultInput && \array_key_exists('default', $rawInput ?? [])) {
            throw new InvalidOptionsException(\sprintf('The default value of the input of the process "%s" is set both in "default.input" and in "form.input.default", use "form.input.default" only.', $processCode));
        }
        // The last row of a key wins
        $defaultContext = array_column($uiOptions['default']['context'], 'value', 'key');
        foreach (array_keys($defaultContext) as $key) {
            $key = (string) $key;
            if (\array_key_exists($key, $form['context'])) {
                if (\array_key_exists('default', $rawContext[$key] ?? [])) {
                    throw new InvalidOptionsException(\sprintf('The default value of the context key "%1$s" of the process "%2$s" is set both in "default.context" and in "form.context.%1$s.default", use "form.context.%1$s.default" only.', $key, $processCode));
                }
            } elseif (false === $form['allow_extra_context']) {
                throw new InvalidOptionsException(\sprintf('The context key "%s" of "default.context" of the process "%s" is not declared in "form.context", and "form.allow_extra_context" is false.', $key, $processCode));
            }
        }

        $input = $form['input'];
        if (null === $input) {
            $isFile = 'file' === $uiOptions['entrypoint_type'];
            $input = ['type' => $isFile ? FileType::class : TextType::class, 'options' => [], 'default' => $isFile ? null : $uiOptions['default']['input']];
        } elseif ($hasDefaultInput && !\array_key_exists('default', $rawInput ?? []) && !is_a($input['type'], FileType::class, true)) {
            $input['default'] = $uiOptions['default']['input'];
        }

        $context = $form['context'];
        foreach ($context as $key => $field) {
            if (\array_key_exists($key, $defaultContext)) {
                $context[$key]['default'] = $defaultContext[$key];
            }
        }

        return [
            'input' => $input,
            'context' => $context,
            'allow_extra_context' => $form['allow_extra_context'],
            'constraints' => \array_key_exists('constraints', $rawForm) ? $form['constraints'] : $uiOptions['constraints'],
        ];
    }

    /**
     * The initial data of the launch form: the default value of the input, and the default values of the context, the
     * declared keys first (in their order of declaration), then the free keys of the deprecated "default.context"
     * option.
     *
     * @param array{'input': LaunchFormField, 'context': array<string, LaunchFormField>} $form
     * @param UiOptions                                                                  $uiOptions
     *
     * @return LaunchFormData
     */
    private function buildData(array $form, array $uiOptions): array
    {
        $context = array_map(static fn (array $field): mixed => $field['default'], $form['context']);
        foreach ($uiOptions['default']['context'] as $row) {
            $key = (string) $row['key'];
            if (!\array_key_exists($key, $form['context'])) {
                $context[$key] = $row['value'];
            }
        }

        return ['input' => $form['input']['default'], 'context' => $context];
    }
}
