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

namespace CleverAge\UiProcessBundle\Entity;

use CleverAge\UiProcessBundle\Entity\Enum\ProcessScheduleType;

class ProcessSchedule implements ProcessScheduleInterface
{
    protected ?int $id = null;

    protected string $process;

    protected ProcessScheduleType $type;

    protected string $expression;

    protected ?string $input = null;

    /**
     * @var array<string|int, mixed>
     */
    protected array $context = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProcess(): ?string
    {
        return $this->process;
    }

    public function setProcess(string $process): static
    {
        $this->process = $process;

        return $this;
    }

    public function getNextExecution(): null
    {
        return null;
    }

    public function getType(): ProcessScheduleType
    {
        return $this->type;
    }

    public function setType(ProcessScheduleType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getExpression(): ?string
    {
        return $this->expression;
    }

    public function setExpression(string $expression): static
    {
        $this->expression = $expression;

        return $this;
    }

    public function getInput(): ?string
    {
        return $this->input;
    }

    public function setInput(?string $input): static
    {
        $this->input = $input;

        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }
}
