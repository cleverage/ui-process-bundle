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

namespace CleverAge\UiProcessBundle\Tests\Form\Fixtures;

/**
 * An entity as given by an EntityType, convertible to a string.
 */
final readonly class StringableEntity implements \Stringable
{
    public function __construct(public int $id)
    {
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }
}
