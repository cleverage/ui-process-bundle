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

use CleverAge\UiProcessBundle\Entity\ProcessSchedule;

final readonly class CronProcessMessage
{
    public function __construct(public ProcessSchedule $processSchedule)
    {
    }
}
