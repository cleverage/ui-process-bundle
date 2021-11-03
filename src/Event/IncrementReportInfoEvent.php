<?php

namespace CleverAge\ProcessUiBundle\Event;

class IncrementReportInfoEvent
{
    public const NAME = 'cleverage_process_ui.increment_report_info';
    private string $key;
    private string $processCode;

    public function __construct(string $processCode, string $key)
    {
        $this->key = $key;
        $this->processCode = $processCode;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getProcessCode(): string
    {
        return $this->processCode;
    }
}
