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

namespace CleverAge\UiProcessBundle\Tests\Form;

use CleverAge\UiProcessBundle\Form\ContextValueNormalizer;
use CleverAge\UiProcessBundle\Tests\Form\Fixtures\Entity;
use CleverAge\UiProcessBundle\Tests\Form\Fixtures\IntBackedEnum;
use CleverAge\UiProcessBundle\Tests\Form\Fixtures\PureEnum;
use CleverAge\UiProcessBundle\Tests\Form\Fixtures\StringableEntity;
use CleverAge\UiProcessBundle\Tests\Form\Fixtures\StringBackedEnum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[CoversClass(ContextValueNormalizer::class)]
class ContextValueNormalizerTest extends TestCase
{
    #[DataProvider('provideNormalizedValues')]
    public function testNormalize(mixed $value, mixed $expected): void
    {
        self::assertSame($expected, (new ContextValueNormalizer())->normalize($value, 'key'));
    }

    /**
     * @return iterable<string, array{mixed, mixed}>
     */
    public static function provideNormalizedValues(): iterable
    {
        yield 'null' => [null, null];
        yield 'true' => [true, true];
        yield 'integer' => [0, 0];
        yield 'float' => [1.5, 1.5];
        yield 'string' => ['a', 'a'];
        yield 'nested array' => [
            ['a' => [1 => StringBackedEnum::Comma, 'b' => true], 'c' => PureEnum::Foo],
            ['a' => [1 => ',', 'b' => true], 'c' => 'Foo'],
        ];
        yield 'string backed enum' => [StringBackedEnum::Comma, ','];
        yield 'int backed enum' => [IntBackedEnum::Ten, 10];
        yield 'pure enum' => [PureEnum::Foo, 'Foo'];
        $timezone = new \DateTimeZone('Europe/Paris');
        yield 'immutable date' => [new \DateTimeImmutable('2026-01-02 03:04:05', $timezone), '2026-01-02T03:04:05+01:00'];
        yield 'mutable date' => [new \DateTime('2026-01-02 03:04:05', $timezone), '2026-01-02T03:04:05+01:00'];
        // The data of a DateIntervalType with its default "input" option
        yield 'date interval' => [new \DateInterval('P1Y2M3DT4H5M6S'), 'P1Y2M3DT4H5M6S'];
        yield 'date interval with zero parts' => [new \DateInterval('P1D'), 'P0Y0M1DT0H0M0S'];
        $inverted = new \DateInterval('PT1H');
        $inverted->invert = 1;
        yield 'inverted date interval' => [$inverted, '-P0Y0M0DT1H0M0S'];
        yield 'stringable' => [new class implements \Stringable {
            public function __toString(): string
            {
                return 'stringable';
            }
        }, 'stringable'];
        // The data of an EntityType is the entity itself
        yield 'stringable entity' => [new StringableEntity(42), '42'];
    }

    public function testUploadedFile(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('The value of "data" is a file ("%s"): file fields are only supported for the input.', UploadedFile::class));
        (new ContextValueNormalizer())->normalize(new UploadedFile(__FILE__, 'data.csv', null, null, true), 'data');
    }

    public function testFile(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The value of "data" is a file ("SplFileInfo"): file fields are only supported for the input.');
        (new ContextValueNormalizer())->normalize(new \SplFileInfo(__FILE__), 'data');
    }

    #[DataProvider('provideInvalidValues')]
    public function testInvalidValue(mixed $value, string $name, string $debugType): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('The value of "%s" ("%s") cannot be used as a process context value: use a form type returning a scalar (e.g. "choice" with scalar choices), or make the class implement \Stringable.', $name, $debugType));
        (new ContextValueNormalizer())->normalize($value, 'k');
    }

    /**
     * @return iterable<string, array{mixed, string, string}>
     */
    public static function provideInvalidValues(): iterable
    {
        yield 'object' => [new \stdClass(), 'k', 'stdClass'];
        // The context ['k' => [0 => object]]: the value of the key "k" is normalized with the name "k"
        yield 'nested object' => [[0 => new \stdClass()], 'k[0]', 'stdClass'];
        yield 'entity' => [new Entity(42), 'k', Entity::class];
    }
}
