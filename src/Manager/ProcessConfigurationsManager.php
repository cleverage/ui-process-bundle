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
use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\ProcessBundle\Validator\ConstraintLoader;
use CleverAge\UiProcessBundle\Notifier\NotificationTrigger;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraint;

/**
 * @phpstan-type UiOptions array{
 *      'source': ?string,
 *      'target': ?string,
 *      'ui_launch_mode': ?string,
 *      'entrypoint_type': string,
 *      'constraints': Constraint[],
 *      'run': ?bool,
 *      'default': array{'input': mixed, 'context': array<array{'key': int|string, 'value': int|string}>}
 *  }
 * @phpstan-type NotificationOptions array{
 *      'enabled': ?bool,
 *      'statuses': ?string[],
 *      'channels': ?list<string>,
 *      'recipients': ?array<array{'email': ?string, 'phone': ?string}>
 *  }
 */
final readonly class ProcessConfigurationsManager
{
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

        return $this->resolveOptions($configuration->getOptions())['ui'];
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

        return $this->resolveOptions($configuration->getOptions())['notification'];
    }

    /**
     * @param array<int|string, mixed> $options
     *
     * @return array{'ui': UiOptions, 'notification': NotificationOptions}
     */
    private function resolveOptions(array $options): array
    {
        $resolver = new OptionsResolver();
        $resolver->setDefault('notification', []);
        $resolver->setAllowedTypes('notification', 'array');
        $resolver->setNormalizer('notification', static function (Options $options, array $notification): array {
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
        });
        $resolver->setDefault('ui', []);
        $resolver->setAllowedTypes('ui', 'array');
        $resolver->setNormalizer('ui', static function (Options $options, array $ui): array {
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

            return $uiResolver->resolve($ui);
        });
        /**
         * @var array{'ui': UiOptions, 'notification': NotificationOptions} $options
         */
        $options = $resolver->resolve($options);

        return $options;
    }

    /** @return ProcessConfiguration[] */
    private function getConfigurations(): array
    {
        return $this->registry->getProcessConfigurations();
    }
}
