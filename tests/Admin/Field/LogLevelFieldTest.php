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

use CleverAge\UiProcessBundle\Admin\Field\LogLevelField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

#[CoversClass(LogLevelField::class)]
class LogLevelFieldTest extends TestCase
{
    public function testNew(): void
    {
        $dto = LogLevelField::new('myProperty', 'My label')->getAsDto();

        self::assertSame('myProperty', $dto->getProperty());
        self::assertSame('My label', $dto->getLabel());
        self::assertSame('@CleverAgeUiProcess/admin/field/log_level.html.twig', $dto->getTemplatePath());
    }

    public function testNewWithTranslatableLabel(): void
    {
        $label = new TranslatableMessage('my.label');

        self::assertSame($label, LogLevelField::new('myProperty', $label)->getAsDto()->getLabel());
    }

    public function testNewWithoutLabel(): void
    {
        self::assertNull(LogLevelField::new('myProperty')->getAsDto()->getLabel());
    }
}
