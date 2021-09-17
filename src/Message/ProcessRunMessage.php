<?php

namespace CleverAge\ProcessUiBundle\Message;

class ProcessRunMessage
{
    private string $processCode;

    /** @var array <string, string> */
    private array $processInput;

    /**
     * @param string $processCode
     * @param array <string, string> $processInput
     */
    public function __construct(string $processCode, array $processInput = [])
    {
        $this->processCode = $processCode;
        $this->processInput = $processInput;
    }

    /**
     * @return string
     */
    public function getProcessCode(): string
    {
        return $this->processCode;
    }

    /**
     * @return array <string, string>
     */
    public function getProcessInput(): array
    {
        return $this->processInput;
    }
}
