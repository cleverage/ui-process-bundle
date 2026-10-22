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

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * A project-specific constraint, given by its FQCN in the process configurations of the tests.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class Even extends Constraint
{
    public string $message = 'The value {{ value }} is not even.';

    /**
     * @param string[]|null $groups
     */
    #[HasNamedArguments]
    public function __construct(?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(groups: $groups, payload: $payload);
        $this->message = $message ?? $this->message;
    }
}
