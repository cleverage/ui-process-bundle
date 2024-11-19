<?php

declare(strict_types=1);

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
    public function validate($value, Constraint $constraint): void
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
