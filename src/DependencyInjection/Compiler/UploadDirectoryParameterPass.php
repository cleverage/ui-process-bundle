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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The "upload_directory" parameter is deprecated since 3.3 for "cleverage_ui_process.upload_directory": when the
 * application still overrides it, its value is used, with a deprecation. Reading it is deprecated too. When the
 * application also sets "cleverage_ui_process.upload_directory", its "upload_directory" parameter is its own (e.g. for
 * another bundle): it is left alone, without deprecation.
 *
 * @internal
 */
final readonly class UploadDirectoryParameterPass implements CompilerPassInterface
{
    public const PARAMETER = 'cleverage_ui_process.upload_directory';
    public const DEPRECATED_PARAMETER = 'upload_directory';
    /** Default values, see config/services/parameters.yaml */
    public const DEFAULT_VALUE = '%kernel.project_dir%/var/storage/uploads';
    public const DEPRECATED_DEFAULT_VALUE = '%'.self::PARAMETER.'%';

    public function process(ContainerBuilder $container): void
    {
        // Raw values: reading a deprecated parameter triggers its deprecation
        $parameters = $container->getParameterBag()->all();
        if (!\array_key_exists(self::DEPRECATED_PARAMETER, $parameters)) {
            return;
        }

        $value = $parameters[self::DEPRECATED_PARAMETER];
        if (self::DEPRECATED_DEFAULT_VALUE !== $value && self::DEFAULT_VALUE !== ($parameters[self::PARAMETER] ?? null)) {
            // Both parameters are set: "upload_directory" is a parameter of the application, unrelated to the bundle
            return;
        }
        if (self::DEPRECATED_DEFAULT_VALUE !== $value) {
            trigger_deprecation('cleverage/ui-process-bundle', '3.3', 'Setting the "%s" parameter is deprecated, set "%s" instead. It will be ignored in 4.0.', self::DEPRECATED_PARAMETER, self::PARAMETER);
            if (\is_string($value)) {
                $container->setParameter(self::PARAMETER, $value);
            }
        }

        $container->deprecateParameter(self::DEPRECATED_PARAMETER, 'cleverage/ui-process-bundle', '3.3', 'The "%s" parameter is deprecated, use "'.self::PARAMETER.'" instead. It will be removed in 4.0.');
    }
}
