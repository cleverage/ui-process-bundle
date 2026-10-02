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

use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcessExecuteMessage::class)]
class ProcessExecuteMessageTest extends TestCase
{
    public function testConstruct(): void
    {
        $message = new ProcessExecuteMessage('demo.process', ['input'], ['key' => 'value']);

        self::assertSame('demo.process', $message->code);
        self::assertSame(['input'], $message->input);
        self::assertSame(['key' => 'value'], $message->context);
    }

    public function testContextDefaultsToEmptyArray(): void
    {
        $message = new ProcessExecuteMessage('demo.process', null);

        self::assertNull($message->input);
        self::assertSame([], $message->context);
    }
}
