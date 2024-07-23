<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Entity\Enum;

enum ProcessExecutionStatus: string
{
    case Started = 'started';
    case Finish = 'completed';
    case Failed = 'failed';
}
