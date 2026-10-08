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

use CleverAge\UiProcessBundle\Validator\EveryExpression;
use CleverAge\UiProcessBundle\Validator\EveryExpressionValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<EveryExpressionValidator>
 */
#[CoversClass(EveryExpressionValidator::class)]
#[CoversClass(EveryExpression::class)]
class EveryExpressionValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): EveryExpressionValidator
    {
        return new EveryExpressionValidator();
    }

    #[DataProvider('provideValidExpressions')]
    public function testValidExpression(string $expression): void
    {
        $this->validator->validate($expression, new EveryExpression());

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidExpressions(): iterable
    {
        yield 'seconds' => ['10 seconds'];
        yield 'day' => ['1 day'];
        yield 'relative' => ['+1 hour'];
        yield 'number of seconds' => ['3600'];
        yield 'relative date moving forward' => ['first monday of next month'];
        // Refused by strtotime(), accepted by the Scheduler
        yield 'ISO 8601 duration' => ['PT1H'];
        yield 'ISO 8601 days' => ['P1D'];
    }

    #[DataProvider('provideInvalidExpressions')]
    public function testInvalidExpression(?string $expression): void
    {
        $this->validator->validate($expression, new EveryExpression());

        $this->buildViolation('The value "{{ value }}" is not a valid "every" expression.')
            ->setParameter('{{ value }}', (string) $expression)
            ->assertRaised();
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function provideInvalidExpressions(): iterable
    {
        yield 'garbage' => ['foo'];
        yield 'empty' => [''];
        yield 'null' => [null];
        // Accepted by strtotime(), failing in the Scheduler
        yield 'zero' => ['0 seconds'];
        yield 'past' => ['yesterday'];
        yield 'negative' => ['-1 hour'];
        yield 'ago' => ['1 hour ago'];
        yield 'now' => ['now'];
        yield 'day of the week' => ['monday'];
        yield 'time' => ['noon'];
        yield 'timestamp' => ['@3600'];
    }
}
