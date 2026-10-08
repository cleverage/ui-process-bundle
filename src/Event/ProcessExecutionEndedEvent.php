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

namespace CleverAge\UiProcessBundle\Event;

use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when a (top-level) process execution has ended and has been saved, whatever the way it was launched.
 */
final class ProcessExecutionEndedEvent extends Event
{
    public function __construct(
        public readonly ProcessExecution $processExecution,
        public readonly ?\Throwable $error = null,
    ) {
    }
}
