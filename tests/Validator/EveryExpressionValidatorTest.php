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
    }

    #[DataProvider('provideInvalidExpressions')]
    public function testInvalidExpression(string $expression): void
    {
        $this->validator->validate($expression, new EveryExpression());

        $this->buildViolation('The value "{{ value }}" is not every valid expression.')
            ->setParameter('{{ value }}', $expression)
            ->assertRaised();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidExpressions(): iterable
    {
        yield 'garbage' => ['foo'];
        yield 'empty' => [''];
    }
}
