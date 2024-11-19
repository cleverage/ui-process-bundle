<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class EveryExpression extends Constraint
{
    public $message = 'The value "{{ value }}" is not every valid expression.';
}
