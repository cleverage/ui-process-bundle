<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Entity\Enum;

enum ProcessExecutionStatus: string
{
    case Started = 'started';
    case Finish = 'finish';
    case Failed = 'failed';
}
