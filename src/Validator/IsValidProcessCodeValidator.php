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

namespace CleverAge\ProcessUiBundle\Validator;

use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class IsValidProcessCodeValidator extends ConstraintValidator
{
    public function __construct(private readonly ProcessConfigurationRegistry $registry)
    {
    }

    /**
     * @param IsValidProcessCode $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$this->registry->hasProcessConfiguration($value)) {
            $this->context->buildViolation($constraint->messageNotExists)
                ->setParameter('{{ value }}', $value)
                ->addViolation();

            return;
        }

        if (!$this->registry->getProcessConfiguration($value)->isPublic()) {
            $this->context->buildViolation($constraint->messageIsNotPublic)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
