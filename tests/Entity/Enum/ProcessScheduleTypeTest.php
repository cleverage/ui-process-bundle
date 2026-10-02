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

namespace CleverAge\UiProcessBundle\Tests\Entity\Enum;

use CleverAge\UiProcessBundle\Entity\Enum\ProcessScheduleType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(ProcessScheduleType::class)]
class ProcessScheduleTypeTest extends TestCase
{
    public function testValues(): void
    {
        self::assertSame(['cron', 'every'], array_column(ProcessScheduleType::cases(), 'value'));
    }

    public function testTrans(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::once())
            ->method('trans')
            ->with('enum.process_schedule_type.every', [], 'enums', null)
            ->willReturn('Every');

        self::assertSame('Every', ProcessScheduleType::EVERY->trans($translator));
    }
}
