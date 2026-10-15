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

readonly class ProcessExecuteMessage
{
    /**
     * The context key set to the email of the connected user when a process is launched from the UI.
     */
    public const EXECUTION_USER_KEY = 'execution_user';

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(public string $code, public mixed $input, public array $context = [])
    {
    }
}
