<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Validator;

use Symfony\Component\Scheduler\Trigger\CronExpressionTrigger;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class CronExpressionValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
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
