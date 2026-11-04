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

namespace CleverAge\UiProcessBundle\Form;

/**
 * A value of the launch form that cannot be converted into a process value: reported as an error of its field, with a
 * message template translated in the "validators" domain.
 *
 * @internal
 */
final class ContextValueException extends \LogicException
{
    /**
     * @param array<string, string> $parameters
     */
    public function __construct(
        public readonly string $messageTemplate,
        public readonly array $parameters,
    ) {
        parent::__construct(strtr($messageTemplate, $parameters));
    }
}
