<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Message;

use CleverAge\ProcessUiBundle\Entity\ProcessSchedule;

readonly final class CronProcessMessage
{
    public function __construct(public ProcessSchedule $processSchedule)
    {
    }
}
