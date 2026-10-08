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

namespace CleverAge\UiProcessBundle\Tests\Validator;

use CleverAge\UiProcessBundle\Validator\CronExpression;
use CleverAge\UiProcessBundle\Validator\CronExpressionValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<CronExpressionValidator>
 */
#[CoversClass(CronExpressionValidator::class)]
#[CoversClass(CronExpression::class)]
class CronExpressionValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): CronExpressionValidator
    {
        return new CronExpressionValidator();
    }

    #[DataProvider('provideValidExpressions')]
    public function testValidExpression(string $expression): void
    {
        $this->validator->validate($expression, new CronExpression());

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidExpressions(): iterable
    {
        yield 'every minute' => ['* * * * *'];
        yield 'every 5 minutes' => ['*/5 * * * *'];
        yield 'monday at 08:30' => ['30 8 * * 1'];
        yield 'macro' => ['@daily'];
    }

    #[DataProvider('provideInvalidExpressions')]
    public function testInvalidExpression(?string $expression): void
    {
        $this->validator->validate($expression, new CronExpression());

        $this->buildViolation('The value "{{ value }}" is not a valid cron expression.')
            ->setParameter('{{ value }}', (string) $expression)
            ->assertRaised();
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function provideInvalidExpressions(): iterable
    {
        // "Hashed" expressions need a context (a Stringable message), which the process schedules do not provide
        yield 'hashed' => ['#midnight'];
        yield 'null' => [null];
        yield 'garbage' => ['not a cron'];
        yield 'too few fields' => ['* * *'];
        yield 'out of range' => ['61 * * * *'];
        yield 'empty' => [''];
    }

    public function testCustomMessage(): void
    {
        $constraint = new CronExpression();
        $constraint->message = 'Invalid cron {{ value }}';

        $this->validator->validate('foo', $constraint);

        $this->buildViolation('Invalid cron {{ value }}')
            ->setParameter('{{ value }}', 'foo')
            ->assertRaised();
    }
}
