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

namespace CleverAge\UiProcessBundle\DependencyInjection\Compiler;

use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Resolves the launch form of the processes declaring the "form" UI option (the deprecated "entrypoint_type",
 * "default" and "constraints" UI options folded into it), so that an invalid launch form fails at compile time
 * instead of on the launch page.
 *
 * Only the structure of the launch form is checked: the options of the form types are resolved by the form factory
 * when the form is built, and the values submitted by the user are converted after the submission. A process without
 * the "form" UI option is not checked, and a launch form using an environment variable is left to the launch page.
 *
 * @internal
 */
final readonly class CheckLaunchFormsPass implements CompilerPassInterface
{
    public const REGISTRY_ID = 'cleverage_process.registry.process_configuration';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::REGISTRY_ID)) {
            return;
        }
        $configurations = $container->getDefinition(self::REGISTRY_ID)->getArgument(0);
        if (!\is_array($configurations)) {
            return;
        }

        $parameterBag = $container->getParameterBag();
        foreach ($configurations as $processCode => $configuration) {
            $ui = \is_array($configuration) && \is_array($configuration['options'] ?? null) ? $configuration['options']['ui'] ?? null : null;
            if (!\is_array($ui) || !\array_key_exists('form', $ui)) {
                continue;
            }
            $usedEnvs = [];
            $container->resolveEnvPlaceholders($ui, null, $usedEnvs);
            if ([] !== $usedEnvs) {
                continue;
            }
            /** @var array<int|string, mixed> $ui */
            $ui = $parameterBag->unescapeValue($parameterBag->resolveValue($ui));

            $this->checkLaunchForm((string) $processCode, $ui);
        }
    }

    /**
     * @param array<int|string, mixed> $ui
     */
    private function checkLaunchForm(string $processCode, array $ui): void
    {
        // Only the UI options of the process: its tasks and its other options are not concerned
        $registry = new ProcessConfigurationRegistry([$processCode => [
            'options' => ['ui' => $ui],
            'entry_point' => null,
            'end_point' => null,
            'description' => '',
            'help' => '',
            'public' => true,
            'tasks' => [],
        ]], 'stop');

        // The deprecations of the launch form are triggered when it is displayed
        set_error_handler(static fn (): bool => true, \E_USER_DEPRECATED);
        try {
            (new ProcessConfigurationsManager($registry))->getLaunchFormOptions($processCode);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(\sprintf('The launch form ("form" UI option) of the process "%s" is invalid: %s', $processCode, $e->getMessage()), 0, $e);
        } finally {
            restore_error_handler();
        }
    }
}
