<?php

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

interface ProcessScheduleInterface
{
    public function getId(): ?int;

    public function getProcess(): ?string;

    public function setProcess(string $process): static;

    public function getNextExecution(): null;

    public function getType(): ProcessScheduleType;

    public function setType(ProcessScheduleType $type): static;

    public function getExpression(): ?string;

    public function setExpression(string $expression): static;

    public function getInput(): ?string;

    public function setInput(?string $input): static;

    /**
     * @return array<string|int, mixed>
     */
    public function getContext(): array;

    /**
     * @param array<string|int, mixed> $context
     */
    public function setContext(array $context): static;
}
