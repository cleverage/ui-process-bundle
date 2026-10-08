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

use CleverAge\ProcessBundle\Configuration\ProcessConfiguration;
use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\UiProcessBundle\Entity\Enum\ProcessScheduleType;
use CleverAge\UiProcessBundle\Entity\ProcessSchedule;
use CleverAge\UiProcessBundle\Validator\CronExpression;
use CleverAge\UiProcessBundle\Validator\CronExpressionValidator;
use CleverAge\UiProcessBundle\Validator\EveryExpression;
use CleverAge\UiProcessBundle\Validator\EveryExpressionValidator;
use CleverAge\UiProcessBundle\Validator\IsValidProcessCode;
use CleverAge\UiProcessBundle\Validator\IsValidProcessCodeValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;

#[CoversClass(ProcessSchedule::class)]
#[UsesClass(CronExpression::class)]
#[UsesClass(CronExpressionValidator::class)]
#[UsesClass(EveryExpression::class)]
#[UsesClass(EveryExpressionValidator::class)]
#[UsesClass(IsValidProcessCode::class)]
#[UsesClass(IsValidProcessCodeValidator::class)]
class ProcessScheduleTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $schedule = new ProcessSchedule();

        self::assertNull($schedule->getId());
        self::assertNull($schedule->getInput());
        self::assertSame([], $schedule->getContext());
        self::assertNull($schedule->getNextExecution()); // @phpstan-ignore staticMethod.alreadyNarrowedType

        self::assertSame($schedule, $schedule->setProcess('demo.process'));
        self::assertSame($schedule, $schedule->setType(ProcessScheduleType::CRON));
        self::assertSame($schedule, $schedule->setExpression('* * * * *'));
        self::assertSame($schedule, $schedule->setInput('input'));
        $schedule->setContext(['key' => 'value']);

        self::assertSame('demo.process', $schedule->getProcess());
        self::assertSame(ProcessScheduleType::CRON, $schedule->getType());
        self::assertSame('* * * * *', $schedule->getExpression());
        self::assertSame('input', $schedule->getInput());
        self::assertSame(['key' => 'value'], $schedule->getContext());

        $schedule->setInput(null);
        self::assertNull($schedule->getInput());
    }

    public function testGetContextDecodesJsonString(): void
    {
        // The JSON column may hold a raw JSON string (legacy rows)
        $schedule = new ProcessSchedule();
        (new \ReflectionProperty(ProcessSchedule::class, 'context'))->setValue($schedule, '["a","b"]');

        self::assertSame(['a', 'b'], $schedule->getContext());
    }

    /**
     * @param list<string> $expectedViolations
     */
    #[DataProvider('provideValidation')]
    public function testValidation(string $process, ProcessScheduleType $type, string $expression, array $expectedViolations): void
    {
        $schedule = (new ProcessSchedule())
            ->setProcess($process)
            ->setType($type)
            ->setExpression($expression);

        $registry = $this->createStub(ProcessConfigurationRegistry::class);
        $registry->method('hasProcessConfiguration')
            ->willReturnCallback(static fn (string $code): bool => 'demo.process' === $code);
        $registry->method('getProcessConfiguration')->willReturn(new ProcessConfiguration('demo.process', []));
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                IsValidProcessCodeValidator::class => new IsValidProcessCodeValidator($registry),
            ]))
            ->getValidator();

        $violations = array_map(
            static fn (ConstraintViolationInterface $violation): string => $violation->getPropertyPath().': '.$violation->getMessage(),
            iterator_to_array($validator->validate($schedule))
        );

        self::assertSame($expectedViolations, $violations);
    }

    /**
     * @return iterable<string, array{string, ProcessScheduleType, string, list<string>}>
     */
    public static function provideValidation(): iterable
    {
        yield 'valid cron' => ['demo.process', ProcessScheduleType::CRON, '*/5 * * * *', []];
        yield 'valid every' => ['demo.process', ProcessScheduleType::EVERY, '10 seconds', []];
        yield 'invalid cron' => [
            'demo.process',
            ProcessScheduleType::CRON,
            '10 seconds',
            ['expression: The value "10 seconds" is not a valid cron expression.'],
        ];
        yield 'invalid every' => [
            'demo.process',
            ProcessScheduleType::EVERY,
            '*/5 * * * *',
            ['expression: The value "*/5 * * * *" is not a valid "every" expression.'],
        ];
        yield 'unknown process' => [
            'demo.unknown',
            ProcessScheduleType::CRON,
            '* * * * *',
            ['process: The process "demo.unknown" does not exist.'],
        ];
    }
}
