<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Message;

readonly class ProcessExecuteMessage
{
    public function __construct(public string $code, public mixed $input, public array $context = [])
    {
    }
}
