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

use CleverAge\UiProcessBundle\Entity\Enum\ProcessScheduleType;
use CleverAge\UiProcessBundle\Entity\ProcessSchedule;
use CleverAge\UiProcessBundle\Message\CronProcessMessage;
use CleverAge\UiProcessBundle\Message\CronProcessMessageHandler;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(CronProcessMessageHandler::class)]
#[UsesClass(CronProcessMessage::class)]
#[UsesClass(ProcessExecuteMessage::class)]
#[UsesClass(ProcessSchedule::class)]
class CronProcessMessageHandlerTest extends TestCase
{
    /**
     * @return iterable<string, array{list<array{key: string, value: string}>, array<string, string>, ?string}>
     */
    public static function provideSchedules(): iterable
    {
        yield 'without context nor input' => [[], [], null];
        yield 'with context and input' => [
            [['key' => 'foo', 'value' => 'bar'], ['key' => 'baz', 'value' => 'qux']],
            ['foo' => 'bar', 'baz' => 'qux'],
            'data.csv',
        ];
        yield 'last value of a duplicated key wins' => [
            [['key' => 'foo', 'value' => 'bar'], ['key' => 'foo', 'value' => 'baz']],
            ['foo' => 'baz'],
            null,
        ];
    }

    /**
     * @param list<array{key: string, value: string}> $context
     * @param array<string, string>                   $expectedContext
     */
    #[DataProvider('provideSchedules')]
    public function testDispatchProcessExecuteMessage(array $context, array $expectedContext, ?string $input): void
    {
        $schedule = (new ProcessSchedule())
            ->setProcess('test.process')
            ->setType(ProcessScheduleType::CRON)
            ->setExpression('* * * * *')
            ->setInput($input);
        $schedule->setContext($context);

        $dispatched = null;
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
                $dispatched = $message;

                return new Envelope($message);
            });

        (new CronProcessMessageHandler($bus))(new CronProcessMessage($schedule));

        self::assertInstanceOf(ProcessExecuteMessage::class, $dispatched);
        self::assertSame('test.process', $dispatched->code);
        self::assertSame($input, $dispatched->input);
        self::assertSame($expectedContext, $dispatched->context);
    }
}
