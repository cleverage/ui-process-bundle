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

namespace CleverAge\ProcessUiBundle\Message;

class ProcessRunMessage
{
    /**
     * @param array <string, string> $processInput
     */
    public function __construct(private readonly string $processCode, private readonly array $processInput = [])
    {
    }

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
