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

use Symfony\Component\Scheduler\Trigger\CronExpressionTrigger;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class CronExpressionValidator extends ConstraintValidator
{
    /**
     * @param CronExpression $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        try {
            CronExpressionTrigger::fromSpec($value);
        } catch (\InvalidArgumentException) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
