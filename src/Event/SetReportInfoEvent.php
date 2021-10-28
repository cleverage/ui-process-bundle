<?php

namespace CleverAge\ProcessUiBundle\Event;

class SetReportInfoEvent
{
    public const NAME = 'cleverage_process_ui.set_report_info';
    private string $key;
    private mixed $value;

    public function __construct(string $key, mixed $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
