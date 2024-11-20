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

namespace CleverAge\ProcessUiBundle\Http\ValueResolver;

use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsTargetedValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

#[AsTargetedValueResolver('process')]
readonly class ProcessConfigurationValueResolver implements ValueResolverInterface
{
    public function __construct(private ProcessConfigurationRegistry $registry)
    {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        return [$this->registry->getProcessConfiguration($request->get('process'))];
    }
}
