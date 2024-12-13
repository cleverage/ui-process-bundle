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

final readonly class ProcessConfigurationsManager
{
    public function __construct(private ProcessConfigurationRegistry $registry)
    {
    }

    /** @return ProcessConfiguration[] */
    public function getPublicProcesses(): array
    {
        return array_filter($this->getConfigurations(), fn (ProcessConfiguration $cfg) => $cfg->isPublic());
    }

    /** @return ProcessConfiguration[] */
    public function getPrivateProcesses(): array
    {
        return array_filter($this->getConfigurations(), fn (ProcessConfiguration $cfg) => !$cfg->isPublic());
    }

    /**
     * @return null|array{
     *       'source': ?string,
     *       'target': ?string,
     *       'entrypoint_type': 'text|file',
     *       'constraints': Constraint[],
     *       'run': 'null|bool',
     *       'default': array{'input': mixed, 'context': array{array{'key': 'int|text', 'value':'int|text'}}}
     * }
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
     * @return array{
     *     'ui': array{
     *        'source': ?string,
     *        'target': ?string,
     *        'entrypoint_type': 'text|file',
     *        'constraints': Constraint[],
     *        'run': 'null|bool',
     *        'default': array{'input': mixed, 'context': array{array{'key': 'int|text', 'value':'int|text'}}}
     *      }
     * }
     */
    private function resolveUiOptions(array $options): array
    {
        $resolver = new OptionsResolver();
        $resolver->setDefault('ui', function (OptionsResolver $uiResolver): void {
            $uiResolver->setDefaults(
                [
                    'source' => null,
                    'target' => null,
                    'entrypoint_type' => 'text',
                    'input_context_launcher_form' => false,
                    'run_confirmation_modal' => false,
                    'constraints' => [],
                    'run' => null,
                    'default' => function (OptionsResolver $defaultResolver) {
                        $defaultResolver->setDefault('input', null);
                        $defaultResolver->setDefault('context', function (OptionsResolver $contextResolver) {
                            $contextResolver->setPrototype(true);
                            $contextResolver->setRequired(['key', 'value']);
                        });
                    },
                ]
            );
            $uiResolver->setDeprecated(
                'run',
                'cleverage/ui-process-bundle',
                '2',
                'run ui option is deprecated. Use public option instead to hide a process from UI'
            );
            $uiResolver->setAllowedValues('entrypoint_type', ['text', 'file']);
            $uiResolver->setNormalizer('constraints', fn (Options $options, array $values): array => (new ConstraintLoader())->buildConstraints($values));
            $uiResolver->setAllowedTypes('input_context_launcher_form', ['bool']);
            $uiResolver->setAllowedTypes('run_confirmation_modal', ['bool']);
        });
        /**
         * @var array{
         *      'ui': array{
         *         'source': ?string,
         *         'target': ?string,
         *         'entrypoint_type': 'text|file',
         *         'constraints': Constraint[],
         *         'run': 'null|bool',
         *         'default': array{'input': mixed, 'context': array{array{'key': 'int|text', 'value':'int|text'}}}
         *       }
         *  } $options
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
