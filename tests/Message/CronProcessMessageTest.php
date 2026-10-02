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

namespace CleverAge\UiProcessBundle\Tests\Message;

use CleverAge\UiProcessBundle\Entity\ProcessSchedule;
use CleverAge\UiProcessBundle\Message\CronProcessMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CronProcessMessage::class)]
class CronProcessMessageTest extends TestCase
{
    public function testConstruct(): void
    {
        $schedule = new ProcessSchedule();

        self::assertSame($schedule, (new CronProcessMessage($schedule))->processSchedule);
    }
}
