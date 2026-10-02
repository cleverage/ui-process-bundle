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

namespace CleverAge\UiProcessBundle\Tests\Scheduler;

use CleverAge\UiProcessBundle\Entity\Enum\ProcessScheduleType;
use CleverAge\UiProcessBundle\Entity\ProcessSchedule;
use CleverAge\UiProcessBundle\Message\CronProcessMessage;
use CleverAge\UiProcessBundle\Repository\ProcessScheduleRepository;
use CleverAge\UiProcessBundle\Scheduler\CronScheduler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Trigger\CronExpressionTrigger;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(CronScheduler::class)]
#[UsesClass(CronProcessMessage::class)]
#[UsesClass(ProcessSchedule::class)]
class CronSchedulerTest extends TestCase
{
    public function testEmptySchedule(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method(self::anything());

        $scheduler = new CronScheduler($this->createRepository([]), $this->createValidator(), $logger);

        self::assertSame([], $scheduler->getSchedule()->getRecurringMessages());
    }

    public function testCronAndEverySchedules(): void
    {
        $cron = $this->createSchedule(ProcessScheduleType::CRON, '*/5 * * * *');
        $every = $this->createSchedule(ProcessScheduleType::EVERY, '10 seconds');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method(self::anything());

        $scheduler = new CronScheduler($this->createRepository([$cron, $every]), $this->createValidator(), $logger);
        $messages = array_values($scheduler->getSchedule()->getRecurringMessages());

        self::assertCount(2, $messages);

        $cronTrigger = $messages[0]->getTrigger();
        self::assertInstanceOf(CronExpressionTrigger::class, $cronTrigger);
        self::assertSame('*/5 * * * *', (string) $cronTrigger);
        self::assertSame($cron, $this->getScheduledMessage($messages[0])->processSchedule);

        self::assertInstanceOf(PeriodicalTrigger::class, $messages[1]->getTrigger());
        self::assertStringContainsString('10 seconds', (string) $messages[1]->getTrigger());
        self::assertSame($every, $this->getScheduledMessage($messages[1])->processSchedule);
    }

    public function testInvalidSchedulesAreSkipped(): void
    {
        $invalid = $this->createSchedule(ProcessScheduleType::CRON, 'invalid');
        $valid = $this->createSchedule(ProcessScheduleType::EVERY, '1 hour');

        $violations = new ConstraintViolationList([
            new ConstraintViolation('First reason', null, [], $invalid, 'expression', 'invalid'),
            new ConstraintViolation('Second reason', null, [], $invalid, 'process', 'unknown'),
        ]);
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects(self::exactly(2))
            ->method('validate')
            ->willReturnCallback(static fn (mixed $value): ConstraintViolationList => $value === $invalid ? $violations : new ConstraintViolationList());

        $logged = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))
            ->method('info')
            ->willReturnCallback(static function (string|\Stringable $message, array $context) use (&$logged): void {
                $logged[] = [(string) $message, $context];
            });

        $scheduler = new CronScheduler($this->createRepository([$invalid, $valid]), $validator, $logger);
        $messages = array_values($scheduler->getSchedule()->getRecurringMessages());

        self::assertSame(
            [
                ['Scheduler configuration is not valid.', ['reason' => 'First reason']],
                ['Scheduler configuration is not valid.', ['reason' => 'Second reason']],
            ],
            $logged
        );
        self::assertCount(1, $messages);
        self::assertSame($valid, $this->getScheduledMessage($messages[0])->processSchedule);
    }

    public function testExceptionsAreLogged(): void
    {
        $repository = $this->createStub(ProcessScheduleRepository::class);
        $repository->method('findAll')->willThrowException(new \RuntimeException('Database is not available'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical')->with('Database is not available');

        $scheduler = new CronScheduler($repository, $this->createValidator(), $logger);

        self::assertSame([], $scheduler->getSchedule()->getRecurringMessages());
    }

    /**
     * @param list<ProcessSchedule> $schedules
     */
    private function createRepository(array $schedules): ProcessScheduleRepository
    {
        $repository = $this->createStub(ProcessScheduleRepository::class);
        $repository->method('findAll')->willReturn($schedules);

        return $repository;
    }

    private function createValidator(): ValidatorInterface
    {
        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        return $validator;
    }

    private function createSchedule(ProcessScheduleType $type, string $expression): ProcessSchedule
    {
        return (new ProcessSchedule())
            ->setProcess('test.process')
            ->setType($type)
            ->setExpression($expression);
    }

    private function getScheduledMessage(RecurringMessage $recurringMessage): CronProcessMessage
    {
        $context = new MessageContext('default', $recurringMessage->getId(), $recurringMessage->getTrigger(), new \DateTimeImmutable());
        $messages = iterator_to_array($recurringMessage->getMessages($context), false);

        self::assertCount(1, $messages);
        self::assertInstanceOf(CronProcessMessage::class, $messages[0]);

        return $messages[0];
    }
}
