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

namespace CleverAge\UiProcessBundle\Tests\Http\Model;

use CleverAge\ProcessBundle\Configuration\ProcessConfiguration;
use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\UiProcessBundle\Http\Model\HttpProcessExecution;
use CleverAge\UiProcessBundle\Validator\IsValidProcessCode;
use CleverAge\UiProcessBundle\Validator\IsValidProcessCodeValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;

#[CoversClass(HttpProcessExecution::class)]
#[UsesClass(IsValidProcessCode::class)]
#[UsesClass(IsValidProcessCodeValidator::class)]
class HttpProcessExecutionTest extends TestCase
{
    public function testDefaults(): void
    {
        $execution = new HttpProcessExecution();

        self::assertNull($execution->code);
        self::assertNull($execution->input);
        self::assertSame([], $execution->context);
        self::assertTrue($execution->queue);
    }

    public function testConstruct(): void
    {
        $execution = new HttpProcessExecution('demo.process', 'input', '{"key":"value"}', false);

        self::assertSame('demo.process', $execution->code);
        self::assertSame('input', $execution->input);
        self::assertSame('{"key":"value"}', $execution->context);
        self::assertFalse($execution->queue);
    }

    /**
     * @param string|array<string, mixed> $context
     * @param list<string>                $expectedViolations
     */
    #[DataProvider('provideValidation')]
    public function testValidation(?string $code, string|array $context, array $expectedViolations): void
    {
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
            iterator_to_array($validator->validate(new HttpProcessExecution($code, null, $context)))
        );

        self::assertSame($expectedViolations, $violations);
    }

    /**
     * @return iterable<string, array{?string, string|array<string, mixed>, list<string>}>
     */
    public static function provideValidation(): iterable
    {
        yield 'array context' => ['demo.process', ['key' => 'value'], []];
        yield 'json context' => ['demo.process', '{"key":"value"}', []];
        yield 'json list context' => ['demo.process', '["value"]', []];
        yield 'empty json object context' => ['demo.process', '{}', []];
        // Valid JSON accepted by the Json constraint, but not decoded to an array
        yield 'json integer context' => ['demo.process', '1', ['context: Context must be a JSON object or array.']];
        yield 'json boolean context' => ['demo.process', 'true', ['context: Context must be a JSON object or array.']];
        yield 'json string context' => ['demo.process', '"abc"', ['context: Context must be a JSON object or array.']];
        yield 'json null context' => ['demo.process', 'null', ['context: Context must be a JSON object or array.']];
        yield 'empty string context' => ['demo.process', '', ['context: Context must be a JSON object or array.']];
        yield 'missing code' => [null, [], ['code: Process code is required.']];
        yield 'unknown code' => ['demo.unknown', [], ['code: The process "demo.unknown" does not exist.']];
    }

    public function testInvalidJsonContext(): void
    {
        $registry = $this->createStub(ProcessConfigurationRegistry::class);
        $registry->method('hasProcessConfiguration')->willReturn(true);
        $registry->method('getProcessConfiguration')->willReturn(new ProcessConfiguration('demo.process', []));
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                IsValidProcessCodeValidator::class => new IsValidProcessCodeValidator($registry),
            ]))
            ->getValidator();

        $violations = $validator->validate(new HttpProcessExecution('demo.process', null, '{invalid'));

        self::assertCount(1, $violations);
        self::assertSame('context', $violations->get(0)->getPropertyPath());
    }
}
