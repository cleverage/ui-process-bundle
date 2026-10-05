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
 *      'run': 'null|bool',
 *      'default': array{'input': mixed, 'context': array{array{'key': 'int|text', 'value':'int|text'}}}
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

        return $this->resolveUiOptions($configuration->getOptions())['ui'];
    }

    /**
     * @param array<int|string, mixed> $options
     *
     * @return array{'ui': UiOptions}
     */
    private function resolveUiOptions(array $options): array
    {
        $resolver = new OptionsResolver();
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

                    return array_map($contextResolver->resolve(...), $context);
                });

                return $defaultResolver->resolve($default);
            });
            $uiResolver->setNormalizer('constraints', static fn (Options $options, array $values): array => (new ConstraintLoader())->buildConstraints($values));
            $uiResolver->setAllowedValues('ui_launch_mode', ['modal', null, 'form']);

            return $uiResolver->resolve($ui);
        });
        /**
         * @var array{'ui': UiOptions} $options
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
