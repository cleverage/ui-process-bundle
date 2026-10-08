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

namespace CleverAge\UiProcessBundle\Validator;

use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class EveryExpressionValidator extends ConstraintValidator
{
    /**
     * @param EveryExpression $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        /* @var EveryExpression $constraint */
        $value = \is_scalar($value) ? (string) $value : '';

        // Checked as the Symfony Scheduler does (strtotime() accepted expressions failing in the Scheduler, e.g.
        // "0 seconds" or "yesterday", and refused ISO 8601 durations such as "PT1H"). Several run dates are computed:
        // the Scheduler detects an interval not moving the run date forward (e.g. "monday") on the next ones only.
        try {
            $trigger = new PeriodicalTrigger($value);
            $run = new \DateTimeImmutable();
            for ($i = 0; $i < 3 && $run instanceof \DateTimeImmutable; ++$i) {
                $run = $trigger->getNextRunDate($run);
            }

            return;
        } catch (\Exception) {
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $value)
            ->addViolation();
    }
}
