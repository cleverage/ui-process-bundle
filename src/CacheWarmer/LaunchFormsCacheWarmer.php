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

namespace CleverAge\UiProcessBundle\CacheWarmer;

use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Resolves the launch form of the processes declaring the "form" UI option on cache:clear / cache:warmup, so that a
 * launch form with an invalid structure (types, keys, file fields, default values of the enum fields, constraints,
 * conflicts with the deprecated options, checked by LaunchFormOptionsResolver) fails there instead of on the launch
 * page.
 *
 * The form itself is not built here: building it may depend on the runtime (database, current user, request), so the
 * options of the form types, the default values and the constraint validators are checked when the launch form is
 * displayed. Optional: skipped by cache:warmup --no-optional-warmers (e.g. when the environment variables used by the
 * processes are missing at build time) and by the automatic rebuild of the container in debug mode.
 *
 * @internal
 */
final readonly class LaunchFormsCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        private ProcessConfigurationsManager $processConfigurationsManager,
    ) {
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $configurations = [...$this->processConfigurationsManager->getPublicProcesses(), ...$this->processConfigurationsManager->getPrivateProcesses()];
        foreach ($configurations as $configuration) {
            $ui = $configuration->getOptions()['ui'] ?? null;
            if (\is_array($ui) && \array_key_exists('form', $ui)) {
                $this->checkLaunchForm($configuration->getCode());
            }
        }

        return [];
    }

    private function checkLaunchForm(string $processCode): void
    {
        // The deprecations of the UI options are triggered when the launch form is displayed
        set_error_handler(static fn (): bool => true, \E_USER_DEPRECATED);
        try {
            $this->processConfigurationsManager->getLaunchFormOptions($processCode);
        } catch (\Throwable $e) {
            throw new \LogicException(\sprintf('The launch form ("form" UI option) of the process "%s" is invalid: %s', $processCode, $e->getMessage()), 0, $e);
        } finally {
            restore_error_handler();
        }
    }
}
