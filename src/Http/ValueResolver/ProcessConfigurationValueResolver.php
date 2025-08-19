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

namespace CleverAge\UiProcessBundle\Http\ValueResolver;

use CleverAge\ProcessBundle\Configuration\ProcessConfiguration;
use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsTargetedValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * PHP 8.2 : Replace by readonly class.
 */
#[AsTargetedValueResolver('process')]
class ProcessConfigurationValueResolver implements ValueResolverInterface
{
    public function __construct(private readonly ProcessConfigurationRegistry $registry)
    {
    }

    /**
     * @return iterable<ProcessConfiguration>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        return [$this->registry->getProcessConfiguration($request->get('process'))];
    }
}
