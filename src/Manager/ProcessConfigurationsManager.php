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

namespace CleverAge\UiProcessBundle\Manager;

use CleverAge\ProcessBundle\Configuration\ProcessConfiguration;
use CleverAge\ProcessBundle\Exception\MissingProcessException;
use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\ProcessBundle\Validator\ConstraintLoader;
use CleverAge\UiProcessBundle\Notifier\NotificationTrigger;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\ButtonTypeInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormConfigBuilder;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\SubmitButtonTypeInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraint;

/**
 * @phpstan-type FormFieldOptions array{
 *      'type': class-string<FormTypeInterface<mixed>>,
 *      'options': array<string, mixed>,
 *      'default': mixed
 *  }
 * @phpstan-type FormOptions array{
 *      'input': FormFieldOptions,
 *      'context': array<string, FormFieldOptions>,
 *      'allow_extra_context': bool,
 *      'constraints': Constraint[]
 *  }
 * @phpstan-type ResolvedFormOptions array{
 *      'input': FormFieldOptions|null,
 *      'context': array<string, FormFieldOptions>,
 *      'allow_extra_context': bool,
 *      'constraints': Constraint[]
 *  }
 * @phpstan-type ResolvedUiOptions array{
 *      'entrypoint_type': string,
 *      'constraints': Constraint[],
 *      'default': array{'input': mixed, 'context': array<array{'key': int|string, 'value': mixed}>},
 *      'form': ResolvedFormOptions
 *  }
 * @phpstan-type UiOptions array{
 *      'source': ?string,
 *      'target': ?string,
 *      'ui_launch_mode': ?string,
 *      'entrypoint_type': string,
 *      'constraints': Constraint[],
 *      'run': ?bool,
 *      'default': array{'input': mixed, 'context': array<array{'key': int|string, 'value': mixed}>},
 *      'form': FormOptions
 *  }
 * @phpstan-type LaunchFormData array{'input': mixed, 'context': array<string, mixed>}
 * @phpstan-type NotificationOptions array{
 *      'enabled': ?bool,
 *      'statuses': ?string[],
 *      'channels': ?list<string>,
 *      'recipients': ?array<array{'email': ?string, 'phone': ?string}>
 *  }
 * @phpstan-type LogsOptions array{
 *      'database_max_days': ?int,
 *      'file_max_days': ?int
 *  }
 */
final readonly class ProcessConfigurationsManager
{
    /**
     * The form type aliases of the launch form fields: the block prefixes of the Symfony core data types, and "entity".
     *
     * @var array<string, class-string>
     */
    private const FORM_TYPE_ALIASES = [
        'text' => Type\TextType::class,
        'textarea' => Type\TextareaType::class,
        'email' => Type\EmailType::class,
        'password' => Type\PasswordType::class,
        'search' => Type\SearchType::class,
        'url' => Type\UrlType::class,
        'tel' => Type\TelType::class,
        'hidden' => Type\HiddenType::class,
        'integer' => Type\IntegerType::class,
        'number' => Type\NumberType::class,
        'money' => Type\MoneyType::class,
        'percent' => Type\PercentType::class,
        'range' => Type\RangeType::class,
        'checkbox' => Type\CheckboxType::class,
        'radio' => Type\RadioType::class,
        'choice' => Type\ChoiceType::class,
        'enum' => Type\EnumType::class,
        'country' => Type\CountryType::class,
        'currency' => Type\CurrencyType::class,
        'language' => Type\LanguageType::class,
        'locale' => Type\LocaleType::class,
        'timezone' => Type\TimezoneType::class,
        'date' => Type\DateType::class,
        'datetime' => Type\DateTimeType::class,
        'time' => Type\TimeType::class,
        'birthday' => Type\BirthdayType::class,
        'week' => Type\WeekType::class,
        'dateinterval' => Type\DateIntervalType::class,
        'color' => Type\ColorType::class,
        'file' => Type\FileType::class,
        'uuid' => Type\UuidType::class,
        'ulid' => Type\UlidType::class,
        'collection' => Type\CollectionType::class,
        'repeated' => Type\RepeatedType::class,
        'entity' => EntityType::class,
    ];

    /**
     * The form options of a launch form field that break the mapping of the context or the default values.
     */
    private const UNSUPPORTED_FIELD_OPTIONS = ['data', 'mapped', 'property_path', 'inherit_data'];

    public function __construct(private ProcessConfigurationRegistry $registry)
    {
    }

    /** @return ProcessConfiguration[] */
    public function getPublicProcesses(): array
    {
        return array_filter($this->getConfigurations(), static fn (ProcessConfiguration $cfg) => $cfg->isPublic());
    }

    /** @return ProcessConfiguration[] */
    public function getPrivateProcesses(): array
    {
        return array_filter($this->getConfigurations(), static fn (ProcessConfiguration $cfg) => !$cfg->isPublic());
    }

    /**
     * @return UiOptions|null
     */
    public function getUiOptions(string $processCode): ?array
    {
        if (false === $this->registry->hasProcessConfiguration($processCode)) {
            return null;
        }

        $configuration = $this->registry->getProcessConfiguration($processCode);

        /** @var UiOptions $options */
        $options = $this->resolveOptions($configuration->getOptions(), $processCode, 'ui')['ui'];

        return $options;
    }

    /**
     * The initial data of the launch form of the process: the default value of the input, and the default values of
     * the context, the declared keys first (in their order of declaration), then the free keys of the deprecated
     * "default.context" option.
     *
     * @return LaunchFormData
     */
    public function getLaunchFormDefaults(string $processCode): array
    {
        return $this->buildLaunchFormDefaults($this->getUiOptions($processCode) ?? throw MissingProcessException::create($processCode));
    }

    /**
     * The initial data of the launch form, from the UI options already resolved by getUiOptions().
     *
     * @param UiOptions $uiOptions
     *
     * @return LaunchFormData
     */
    public function buildLaunchFormDefaults(array $uiOptions): array
    {
        $context = array_map(static fn (array $field): mixed => $field['default'], $uiOptions['form']['context']);
        foreach ($uiOptions['default']['context'] as $row) {
            $key = (string) $row['key'];
            if (!\array_key_exists($key, $uiOptions['form']['context'])) {
                $context[$key] = $row['value'];
            }
        }

        return ['input' => $uiOptions['form']['input']['default'], 'context' => $context];
    }

    /**
     * The "notification" option of the process, null values are inherited from the bundle configuration.
     *
     * @return NotificationOptions|null
     */
    public function getNotificationOptions(string $processCode): ?array
    {
        if (false === $this->registry->hasProcessConfiguration($processCode)) {
            return null;
        }

        $configuration = $this->registry->getProcessConfiguration($processCode);

        /** @var NotificationOptions $options */
        $options = $this->resolveOptions($configuration->getOptions(), $processCode, 'notification')['notification'];

        return $options;
    }

    /**
     * The "logs" option of the process, null values are inherited from the bundle configuration.
     *
     * @return LogsOptions|null
     */
    public function getLogsOptions(string $processCode): ?array
    {
        if (false === $this->registry->hasProcessConfiguration($processCode)) {
            return null;
        }

        $configuration = $this->registry->getProcessConfiguration($processCode);

        /** @var LogsOptions $options */
        $options = $this->resolveOptions($configuration->getOptions(), $processCode, 'logs')['logs'];

        return $options;
    }

    /**
     * Resolves the given option only: the other ones are only checked as arrays, so that an invalid option (e.g. the
     * launch form) does not break the others (e.g. the notification of the executions or the purge of the logs).
     *
     * @param array<int|string, mixed>   $options
     * @param 'ui'|'notification'|'logs' $option
     *
     * @return array{'ui': array<mixed>, 'notification': array<mixed>, 'logs': array<mixed>}
     */
    private function resolveOptions(array $options, string $processCode, string $option): array
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['notification' => [], 'logs' => [], 'ui' => []]);
        foreach (['notification', 'logs', 'ui'] as $name) {
            $resolver->setAllowedTypes($name, 'array');
        }
        $resolver->setNormalizer($option, match ($option) {
            'notification' => static fn (Options $options, array $notification): array => self::resolveNotificationOptions($notification),
            'logs' => static fn (Options $options, array $logs): array => self::resolveLogsOptions($logs),
            'ui' => static fn (Options $options, array $ui): array => self::resolveUiOptions($ui, $processCode),
        });

        /**
         * @var array{'ui': array<mixed>, 'notification': array<mixed>, 'logs': array<mixed>} $resolved
         */
        $resolved = $resolver->resolve($options);

        return $resolved;
    }

    /**
     * @param array<int|string, mixed> $notification
     *
     * @return array<mixed>
     */
    private static function resolveNotificationOptions(array $notification): array
    {
        $notificationResolver = new OptionsResolver();
        $notificationResolver->setDefaults(['enabled' => null, 'statuses' => null, 'channels' => null, 'recipients' => null]);
        $notificationResolver->setAllowedTypes('enabled', ['null', 'bool']);
        $notificationResolver->setAllowedTypes('statuses', ['null', 'string[]']);
        $notificationResolver->setAllowedValues('statuses', static function (?array $statuses): bool {
            /** @var string[]|null $statusValues */
            $statusValues = $statuses;

            return null === $statusValues || [] === array_diff($statusValues, NotificationTrigger::values());
        });
        $notificationResolver->setAllowedTypes('channels', ['null', 'string[]']);
        $notificationResolver->setAllowedTypes('recipients', ['null', 'array[]']);
        $notificationResolver->setNormalizer('recipients', static function (Options $options, ?array $recipients): ?array {
            if (null === $recipients) {
                return null;
            }
            $recipientResolver = new OptionsResolver();
            $recipientResolver->setDefaults(['email' => null, 'phone' => null]);
            $recipientResolver->setAllowedTypes('email', ['null', 'string']);
            $recipientResolver->setAllowedTypes('phone', ['null', 'string']);

            $recipientResolver->setNormalizer('phone', static function (Options $options, ?string $phone): ?string {
                if (null === $options['email'] && null === $phone) {
                    throw new InvalidOptionsException('A notification recipient must have an "email" or a "phone".');
                }

                return $phone;
            });

            /** @var array<array<string, mixed>> $recipientRows */
            $recipientRows = $recipients;

            return array_values(array_map($recipientResolver->resolve(...), $recipientRows));
        });

        return $notificationResolver->resolve($notification);
    }

    /**
     * @param array<int|string, mixed> $logs
     *
     * @return array<mixed>
     */
    private static function resolveLogsOptions(array $logs): array
    {
        $logsResolver = new OptionsResolver();
        $logsResolver->setDefaults(['database_max_days' => null, 'file_max_days' => null]);
        foreach (['database_max_days', 'file_max_days'] as $option) {
            $logsResolver->setAllowedTypes($option, ['null', 'int']);
            $logsResolver->setAllowedValues($option, static fn (?int $days): bool => null === $days || $days >= 1);
        }

        return $logsResolver->resolve($logs);
    }

    /**
     * @param array<int|string, mixed> $ui
     *
     * @return UiOptions
     */
    private static function resolveUiOptions(array $ui, string $processCode): array
    {
        $uiResolver = new OptionsResolver();
        $uiResolver->setDefaults(
            [
                'source' => null,
                'target' => null,
                'entrypoint_type' => 'text',
                'ui_launch_mode' => 'modal',
                'constraints' => [],
                'run' => null,
                'default' => [],
                'form' => [],
            ]
        );
        $uiResolver->setAllowedValues('entrypoint_type', ['text', 'file']);
        // Resolved by a normalizer rather than as nested options: nested options defined with setDefault() are
        // deprecated since symfony/options-resolver 7.3 and removed in 8.0, setOptions() does not exist before 7.3
        $uiResolver->setAllowedTypes('default', 'array');
        $uiResolver->setNormalizer('default', static function (Options $options, array $default): array {
            $defaultResolver = new OptionsResolver();
            $defaultResolver->setDefaults(['input' => null, 'context' => []]);
            $defaultResolver->setAllowedTypes('context', 'array[]');
            $defaultResolver->setNormalizer('context', static function (Options $options, array $context): array {
                $contextResolver = new OptionsResolver();
                $contextResolver->setRequired(['key', 'value']);

                /** @var array<array<string, mixed>> $contextRows */
                $contextRows = $context;

                return array_map($contextResolver->resolve(...), $contextRows);
            });

            return $defaultResolver->resolve($default);
        });
        $uiResolver->setNormalizer('constraints', static fn (Options $options, array $values): array => (new ConstraintLoader())->buildConstraints($values));
        $uiResolver->setAllowedValues('ui_launch_mode', ['modal', null, 'form']);
        $uiResolver->setAllowedTypes('form', 'array');
        $uiResolver->setNormalizer('form', static fn (Options $options, array $form): array => self::resolveForm($form, $processCode));
        // A Closure message: the deprecation is triggered only when the option is given, once per resolution
        $uiResolver->setDeprecated('entrypoint_type', 'cleverage/ui-process-bundle', '3.3', static fn (Options $options, mixed $value): string => \sprintf('The "entrypoint_type" UI option of the process "%s" is deprecated, use "form.input.type" instead.', $processCode));
        $uiResolver->setDeprecated('default', 'cleverage/ui-process-bundle', '3.3', static fn (Options $options, mixed $value): string => \sprintf('The "default" UI option of the process "%s" is deprecated, use "form.input.default" and "form.context.<key>.default" instead.', $processCode));
        $uiResolver->setDeprecated('constraints', 'cleverage/ui-process-bundle', '3.3', static fn (Options $options, mixed $value): string => \sprintf('The "constraints" UI option of the process "%s" is deprecated, use "form.constraints" instead.', $processCode));

        /** @var ResolvedUiOptions $resolved */
        $resolved = $uiResolver->resolve($ui);
        // The effective "form" option, the deprecated options folded into it
        $resolved['form'] = self::foldDeprecatedOptions($ui, $resolved, $processCode);

        /** @var UiOptions $uiOptions */
        $uiOptions = $resolved;

        return $uiOptions;
    }

    /**
     * @param array<int|string, mixed> $form
     *
     * @return ResolvedFormOptions
     */
    private static function resolveForm(array $form, string $processCode): array
    {
        $formResolver = new OptionsResolver();
        $formResolver->setDefaults(['input' => null, 'context' => [], 'allow_extra_context' => true, 'constraints' => []]);
        $formResolver->setAllowedTypes('input', ['null', 'array']);
        $formResolver->setAllowedTypes('context', 'array');
        $formResolver->setAllowedTypes('allow_extra_context', 'bool');
        $formResolver->setAllowedTypes('constraints', 'array');
        $formResolver->setNormalizer(
            'input',
            static fn (Options $options, ?array $input): ?array => null === $input ? null : self::resolveField($input, 'form.input', $processCode, true)
        );
        $formResolver->setNormalizer('context', static function (Options $options, array $context) use ($processCode): array {
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
                $fields[$key] = self::resolveField($spec ?? [], "form.context.$key", $processCode, false);
            }

            return $fields;
        });
        $formResolver->setNormalizer('constraints', static fn (Options $options, array $values): array => (new ConstraintLoader())->buildConstraints($values));

        /** @var ResolvedFormOptions $resolved */
        $resolved = $formResolver->resolve($form);

        return $resolved;
    }

    /**
     * @param array<int|string, mixed> $spec
     *
     * @return FormFieldOptions
     */
    private static function resolveField(array $spec, string $path, string $processCode, bool $isInput): array
    {
        $fieldResolver = new OptionsResolver();
        $fieldResolver->setDefaults(['type' => 'text', 'options' => [], 'default' => null]);
        $fieldResolver->setAllowedTypes('type', 'string');
        $fieldResolver->setAllowedTypes('options', 'array');
        $fieldResolver->setNormalizer('type', static fn (Options $options, string $type): string => self::resolveFormType($type, $path, $processCode));
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

        /** @var FormFieldOptions $resolved */
        $resolved = $fieldResolver->resolve($spec);
        $field = ['type' => $resolved['type'], 'options' => $resolved['options'], 'default' => $resolved['default']];

        if (is_a($field['type'], Type\FileType::class, true)) {
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

        return $field;
    }

    /**
     * @return class-string<FormTypeInterface<mixed>>
     */
    private static function resolveFormType(string $type, string $path, string $processCode): string
    {
        $class = self::FORM_TYPE_ALIASES[$type] ?? ltrim($type, '\\');
        if (!class_exists($class)) {
            throw new InvalidOptionsException(\sprintf('The type "%s" of the launch form field "%s" of the process "%s" is neither a form type alias (%s) nor an existing class.', $type, $path, $processCode, implode(', ', array_map(static fn (string $alias): string => \sprintf('"%s"', $alias), array_keys(self::FORM_TYPE_ALIASES)))));
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
     * into the effective "form" option.
     *
     * @param array<int|string, mixed> $ui       the raw UI options, to know whether an option is given
     * @param ResolvedUiOptions        $resolved the resolved UI options, before the folding
     *
     * @return FormOptions
     */
    private static function foldDeprecatedOptions(array $ui, array $resolved, string $processCode): array
    {
        /** @var array<string, mixed> $rawForm */
        $rawForm = $ui['form'] ?? [];
        /** @var array<string, mixed>|null $rawInput */
        $rawInput = $rawForm['input'] ?? null;
        /** @var array<string, array<string, mixed>|null> $rawContext */
        $rawContext = $rawForm['context'] ?? [];
        /** @var array<string, mixed> $rawDefault */
        $rawDefault = $ui['default'] ?? [];
        $form = $resolved['form'];

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
        $defaultContext = array_column($resolved['default']['context'], 'value', 'key');
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
            $isFile = 'file' === $resolved['entrypoint_type'];
            $input = ['type' => $isFile ? Type\FileType::class : Type\TextType::class, 'options' => [], 'default' => $isFile ? null : $resolved['default']['input']];
        } elseif ($hasDefaultInput && !\array_key_exists('default', $rawInput ?? []) && !is_a($input['type'], Type\FileType::class, true)) {
            $input['default'] = $resolved['default']['input'];
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
            'constraints' => \array_key_exists('constraints', $rawForm) ? $form['constraints'] : $resolved['constraints'],
        ];
    }

    /** @return ProcessConfiguration[] */
    private function getConfigurations(): array
    {
        return $this->registry->getProcessConfigurations();
    }
}
