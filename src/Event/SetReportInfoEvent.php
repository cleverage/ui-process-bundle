<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Event;

class SetReportInfoEvent
{
    public const NAME = 'cleverage_process_ui.set_report_info';
    private string $key;
    private mixed $value;
    private string $processCode;

    public function __construct(string $processCode, string $key, mixed $value)
    {
        $this->key = $key;
        $this->value = $value;
        $this->processCode = $processCode;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getProcessCode(): string
    {
        return $this->processCode;
    }
}
