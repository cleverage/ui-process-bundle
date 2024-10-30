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

namespace CleverAge\ProcessUiBundle\Event;

class SetReportInfoEvent
{
    public const NAME = 'cleverage_process_ui.set_report_info';

    public function __construct(private readonly string $processCode, private readonly string $key, private readonly mixed $value)
    {
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
