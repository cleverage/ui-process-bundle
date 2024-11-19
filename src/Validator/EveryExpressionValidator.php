<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class EveryExpressionValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        /* @var EveryExpression $constraint */

        if (false !== strtotime($value)) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $value)
            ->addViolation();
    }
}
