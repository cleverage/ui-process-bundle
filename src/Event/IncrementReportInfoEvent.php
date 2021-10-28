<?php

namespace CleverAge\ProcessUiBundle\Event;

class IncrementReportInfoEvent
{
    public const NAME = 'cleverage_process_ui.increment_report_info';
    private string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function getKey(): string
    {
        return $this->key;
    }
}
