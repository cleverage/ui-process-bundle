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

use CleverAge\UiProcessBundle\Entity\Enum\ProcessExecutionStatus;

interface ProcessExecutionInterface
{
    public function getId(): ?int;

    public function getCode(): string;

    public function end(): void;

    public function duration(string $format = '%H hour(s) %I min(s) %S s'): ?string;

    public function setStatus(ProcessExecutionStatus $status): static;

    public function addReport(string $key, mixed $value): void;

    public function getReport(?string $key = null, mixed $default = null): mixed;

    /**
     * @return array<string|int, mixed>
     */
    public function getContext(): array;

    /**
     * @param array<string|int, mixed> $context
     */
    public function setContext(array $context): static;
}
