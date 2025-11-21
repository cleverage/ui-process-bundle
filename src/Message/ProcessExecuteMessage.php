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

namespace CleverAge\UiProcessBundle\Message;

/**
 * PHP 8.2 : Replace by readonly class.
 */
class ProcessExecuteMessage
{
    /**
     * @param mixed[] $context
     */
    public function __construct(public readonly string $code, public readonly mixed $input, public readonly array $context = [])
    {
    }
}
