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

    public function testInputIsNotDeletedByDefault(): void
    {
        self::assertFalse((new ProcessExecuteMessage('demo.process', '/uploads/file.csv'))->deleteInput);
        self::assertTrue((new ProcessExecuteMessage('demo.process', '/uploads/file.csv', [], true))->deleteInput);
    }

    public function testSerialization(): void
    {
        $message = unserialize(serialize(new ProcessExecuteMessage('demo.process', '/uploads/file.csv', [], true)));

        self::assertInstanceOf(ProcessExecuteMessage::class, $message);
        self::assertTrue($message->deleteInput);
    }

    /**
     * A message queued by a previous version of the bundle, without the "deleteInput" property (uninitialized).
     */
    public function testUnserializeMessageOfAPreviousVersion(): void
    {
        $message = unserialize(\sprintf(
            'O:%d:"%s":3:{s:4:"code";s:12:"demo.process";s:5:"input";s:17:"/uploads/file.csv";s:7:"context";a:0:{}}',
            \strlen(ProcessExecuteMessage::class),
            ProcessExecuteMessage::class
        ));

        self::assertInstanceOf(ProcessExecuteMessage::class, $message);
        self::assertSame('/uploads/file.csv', $message->input);
        self::assertFalse($message->deleteInput ?? false); // @phpstan-ignore nullCoalesce.property
    }

    /**
     * A subclass with properties of its own (the class is final since 3.3 only).
     */
    public function testSerializeSubclass(): void
    {
        $message = unserialize(serialize(new ProcessExecuteMessageSubclass('demo.process', 'input', 'extra')));

        self::assertInstanceOf(ProcessExecuteMessageSubclass::class, $message);
        self::assertSame('extra', $message->extra);
        self::assertFalse($message->deleteInput);
    }
}

/**
 * @internal
 */
readonly class ProcessExecuteMessageSubclass extends ProcessExecuteMessage // @phpstan-ignore class.extendsFinalByPhpDoc
{
    public function __construct(string $code, mixed $input, public string $extra)
    {
        parent::__construct($code, $input);
    }
}
