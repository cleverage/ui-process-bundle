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

namespace CleverAge\UiProcessBundle\Tests\Admin\Field;

use CleverAge\UiProcessBundle\Admin\Field\ContextField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

#[CoversClass(ContextField::class)]
class ContextFieldTest extends TestCase
{
    public function testNew(): void
    {
        $dto = ContextField::new('myProperty', 'My label')->getAsDto();

        self::assertSame('myProperty', $dto->getProperty());
        self::assertSame('My label', $dto->getLabel());
        self::assertSame('@CleverAgeUiProcess/admin/field/array.html.twig', $dto->getTemplatePath());
    }

    public function testNewWithTranslatableLabel(): void
    {
        $label = new TranslatableMessage('my.label');

        self::assertSame($label, ContextField::new('myProperty', $label)->getAsDto()->getLabel());
    }

    public function testNewWithoutLabel(): void
    {
        self::assertNull(ContextField::new('myProperty')->getAsDto()->getLabel());
    }
}
