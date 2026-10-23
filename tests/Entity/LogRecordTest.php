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

namespace CleverAge\UiProcessBundle\Tests\Entity;

use CleverAge\UiProcessBundle\Entity\LogRecord;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;

#[CoversClass(LogRecord::class)]
#[UsesClass(ProcessExecution::class)]
class LogRecordTest extends TestCase
{
    public function testHasContextInfo(): void
    {
        self::assertTrue($this->createLogRecord(['file' => 'a.txt'])->hasContextInfo());
        self::assertFalse($this->createLogRecord([])->hasContextInfo());
    }

    public function testContextIsReadableByThePropertyAccessor(): void
    {
        // EasyAdmin reads the fields with the PropertyAccessor: a hasContext() method would be returned for "context"
        $context = PropertyAccess::createPropertyAccessor()->getValue($this->createLogRecord(['file' => 'a.txt']), 'context');

        self::assertSame(['file' => 'a.txt'], $context);
    }

    public function testDeprecatedContextIsEmptyIsUnchanged(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
            $deprecations[] = $errstr;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            // Misnamed: returns true when the context is NOT empty
            self::assertTrue($this->createLogRecord(['file' => 'a.txt'])->contextIsEmpty());
            self::assertFalse($this->createLogRecord([])->contextIsEmpty());
        } finally {
            restore_error_handler();
        }

        $expected = 'Since cleverage/ui-process-bundle 3.1: The "CleverAge\\UiProcessBundle\\Entity\\LogRecord::contextIsEmpty()" method is deprecated, use "hasContextInfo()" instead.';
        self::assertSame([$expected, $expected], $deprecations);
    }

    public function testAccessors(): void
    {
        $execution = new ProcessExecution('demo.process', 'demo.process.log');
        $record = new LogRecord(new \Monolog\LogRecord(new \DateTimeImmutable('2026-01-01 10:00:00'), 'cleverage_process', Level::Warning, 'message', []), $execution);

        self::assertNull($record->getId());
        self::assertSame($execution, $record->getProcessExecution());
        self::assertSame('cleverage_process', $record->channel);
        self::assertSame(Level::Warning->value, $record->level);
        self::assertSame('message', $record->message);
        self::assertSame('2026-01-01 10:00:00', $record->createdAt->format('Y-m-d H:i:s'));
    }

    public function testInvalidUtf8MessageAndChannelAreScrubbed(): void
    {
        $record = new LogRecord(
            new \Monolog\LogRecord(new \DateTimeImmutable(), "chan\xE9l", Level::Info, "caf\xE9 cr\xE8me"),
            new ProcessExecution('demo.process', 'demo.process.log')
        );

        self::assertSame('chan?l', $record->channel);
        self::assertSame('caf? cr?me', $record->message);
    }

    public function testNulCharactersAreRemovedFromMessageAndChannel(): void
    {
        $record = new LogRecord(
            new \Monolog\LogRecord(new \DateTimeImmutable(), "chan\0nel", Level::Info, "before\0after"),
            new ProcessExecution('demo.process', 'demo.process.log')
        );

        self::assertSame('channel', $record->channel);
        self::assertSame('beforeafter', $record->message);
    }

    public function testInvalidUtf8ContextIsEncodable(): void
    {
        $record = $this->createLogRecord(['input' => ['name' => "caf\xE9", "k\xE9y" => 1.0], 'valid' => 'été']);

        // The invalid sequences are replaced, the rest of the context is kept
        self::assertSame(['input' => ['name' => "caf\u{FFFD}", "k\u{FFFD}y" => 1.0], 'valid' => 'été'], $record->context);
        self::assertNotFalse(json_encode($record->context));
    }

    public function testContextWithValuesWhichCannotBeEncoded(): void
    {
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);
        try {
            $record = $this->createLogRecord(['resource' => $resource, 'name' => 'value']);
        } finally {
            fclose($resource);
        }

        self::assertSame(['resource' => null, 'name' => 'value'], $record->context);
    }

    public function testEncodableContextIsUnchanged(): void
    {
        $object = new \stdClass();
        $object->name = 'value';
        $context = ['object' => $object, 'float' => 1.0, 'list' => [1, 2], 'emoji' => '👨‍👩‍👧‍👦'];

        self::assertSame($context, $this->createLogRecord($context)->context);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideGraphemes(): iterable
    {
        // 7 code points (4 emojis joined by 3 zero width joiners) for a single grapheme
        yield 'family emoji' => ['👨‍👩‍👧‍👦'];
        // 2 code points for a single grapheme
        yield 'combining mark' => ["e\u{301}"];
        yield 'multibyte character' => ['é'];
    }

    #[DataProvider('provideGraphemes')]
    public function testMessageIsTruncatedToTheColumnLengthInCodePoints(string $grapheme): void
    {
        $message = str_repeat($grapheme, 600);
        $record = new LogRecord(
            new \Monolog\LogRecord(new \DateTimeImmutable(), 'cleverage_process', Level::Info, $message),
            new ProcessExecution('demo.process', 'demo.process.log')
        );

        // The length of the column counts characters (code points), not graphemes nor bytes
        self::assertSame(512, mb_strlen($record->message));
        self::assertSame(mb_substr($message, 0, 512), $record->message);
    }

    public function testChannelIsTruncatedToTheColumnLengthInCodePoints(): void
    {
        $record = new LogRecord(
            new \Monolog\LogRecord(new \DateTimeImmutable(), str_repeat("e\u{301}", 40), Level::Info, 'message'),
            new ProcessExecution('demo.process', 'demo.process.log')
        );

        self::assertSame(64, mb_strlen($record->channel));
    }

    /**
     * @param array<mixed> $context
     */
    private function createLogRecord(array $context): LogRecord
    {
        return new LogRecord(
            new \Monolog\LogRecord(new \DateTimeImmutable(), 'cleverage_process', Level::Info, 'message', $context),
            new ProcessExecution('demo.process', 'demo.process.log')
        );
    }
}
