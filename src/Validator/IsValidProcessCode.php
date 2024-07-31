<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class IsValidProcessCode extends Constraint
{
    public $messageIsNotPublic = 'The process "{{ value }}" is not public.';
    public $messageNotExists = 'The process "{{ value }}" does not exist.';
}
