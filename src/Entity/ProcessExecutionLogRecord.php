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

namespace CleverAge\ProcessUiBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'process_execution_log_message', columns: ['message'])]
class ProcessExecutionLogRecord
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProcessExecution::class, inversedBy: 'logRecords')]
    #[ORM\JoinColumn(name: 'process_execution_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?ProcessExecution $processExecution = null;

    public function __construct(
        #[ORM\Column(type: Types::INTEGER)]
        private int $logLevel,

        #[ORM\Column(type: Types::STRING)]
        private string $message,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLogLevel(): int
    {
        return $this->logLevel;
    }

    public function setLogLevel(int $logLevel): self
    {
        $this->logLevel = $logLevel;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function setProcessExecution(ProcessExecution $processExecution): self
    {
        $this->processExecution = $processExecution;

        return $this;
    }

    public function getProcessExecution(): ?ProcessExecution
    {
        return $this->processExecution;
    }
}
