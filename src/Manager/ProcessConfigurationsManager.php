<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Manager;

use CleverAge\ProcessBundle\Configuration\ProcessConfiguration;
use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\ProcessBundle\Validator\ConstraintLoader;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

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

    public function getUiOptions(string $processCode): ?array
    {
        if (false === $this->registry->hasProcessConfiguration($processCode)) {
            return null;
        }

        $configuration = $this->registry->getProcessConfiguration($processCode);

        return $this->resolveUiOptions($configuration->getOptions())['ui'];
    }

    private function resolveUiOptions(array $options): array
    {
        $resolver = new OptionsResolver();
        $resolver->setDefault('ui', function (OptionsResolver $uiResolver): void {
            $uiResolver->setDefaults(
                [
                    'source' => null,
                    'target' => null,
                    'entrypoint_type' => 'text',
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
                'cleverage/process-ui-bundle',
                '2',
                'run ui option is deprecated. Use public option instead to hide a process from UI'
            );
            $uiResolver->setAllowedValues('entrypoint_type', ['text', 'file']);
            $uiResolver->setNormalizer('constraints', function (Options $options, array $values): array {
                return (new ConstraintLoader())->buildConstraints($values);
            });
        });

        return $resolver->resolve($options);
    }

    /** @return ProcessConfiguration[] */
    private function getConfigurations(): array
    {
        return $this->registry->getProcessConfigurations();
    }
}
