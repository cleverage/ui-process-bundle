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

namespace CleverAge\UiProcessBundle\Tests\App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * A constraint whose validator does not exist.
 */
class WithoutValidator extends Constraint
{
    public function validatedBy(): string
    {
        return 'App\Validator\MissingValidator';
    }
}
