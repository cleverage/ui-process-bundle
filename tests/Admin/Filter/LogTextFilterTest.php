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

namespace CleverAge\UiProcessBundle\Tests\Admin\Filter;

use CleverAge\UiProcessBundle\Admin\Filter\LogTextFilter;
use CleverAge\UiProcessBundle\Entity\LogRecord;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\TextFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogTextFilter::class)]
class LogTextFilterTest extends TestCase
{
    public function testNew(): void
    {
        $dto = LogTextFilter::new('context', 'Context')->getAsDto();

        self::assertSame(LogTextFilter::class, $dto->getFqcn());
        self::assertSame('context', $dto->getProperty());
        self::assertSame('Context', $dto->getLabel());
        self::assertSame(TextFilterType::class, $dto->getFormType());
        self::assertSame('EasyAdminBundle', $dto->getFormTypeOption('translation_domain'));
    }

    /**
     * @return iterable<string, array{AbstractPlatform, string}>
     */
    public static function providePlatforms(): iterable
    {
        // Converted to text (PostgreSQL has no LIKE operator for the json type) and case-insensitive on every database
        $condition = "LOWER(CONCAT(entity.context, '')) like LOWER(:context_0)";
        yield 'PostgreSQL' => [new PostgreSQLPlatform(), $condition];
        yield 'MySQL' => [new MySQLPlatform(), $condition];
    }

    #[DataProvider('providePlatforms')]
    public function testApply(AbstractPlatform $platform, string $column): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $queryBuilder = (new QueryBuilder($entityManager))->select('entity')->from(LogRecord::class, 'entity');
        $filter = LogTextFilter::new('context');
        // The value as transformed by the TextFilterType
        $filterDataDto = FilterDataDto::new(0, $filter->getAsDto(), 'entity', ['comparison' => ComparisonType::CONTAINS, 'value' => '%a.csv%']);
        /** @var EntityDto<object> $entityDto */
        $entityDto = new EntityDto(LogRecord::class, new ClassMetadata(LogRecord::class));

        $filter->apply($queryBuilder, $filterDataDto, null, $entityDto);

        self::assertSame('SELECT entity FROM '.LogRecord::class.' entity WHERE '.$column, $queryBuilder->getDQL());
        self::assertSame('%a.csv%', $queryBuilder->getParameter('context_0')?->getValue());
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function provideEscapedValues(): iterable
    {
        $column = "LOWER(CONCAT(entity.context, ''))";
        yield 'contains' => [
            ComparisonType::CONTAINS, '%data/a.csv%',
            "({$column} like LOWER(:context_0) OR {$column} like LOWER(:context_0_json) ESCAPE '!')",
            '%data\\/a.csv%',
        ];
        yield 'not contains, escape character' => [
            ComparisonType::NOT_CONTAINS, '%a/b!%',
            "({$column} not like LOWER(:context_0) AND {$column} not like LOWER(:context_0_json) ESCAPE '!')",
            '%a\\/b!!%',
        ];
        yield 'exactly' => [
            ComparisonType::EQ, '"é"',
            "({$column} = LOWER(:context_0) OR {$column} = LOWER(:context_0_json))",
            '\\"\\u00e9\\"',
        ];
        yield 'not exactly' => [
            ComparisonType::NEQ, 'a/b',
            "({$column} != LOWER(:context_0) AND {$column} != LOWER(:context_0_json))",
            'a\\/b',
        ];
    }

    /**
     * The JSON context is stored as encoded by Doctrine, kept as it is by PostgreSQL, MariaDB and SQLite: the value is
     * also searched as encoded.
     */
    #[DataProvider('provideEscapedValues')]
    public function testApplyOnTheJsonContextWithEscapedCharacters(string $comparison, string $value, string $condition, string $encodedValue): void
    {
        $queryBuilder = $this->apply('context', $comparison, $value);

        self::assertSame('SELECT entity FROM '.LogRecord::class.' entity WHERE '.$condition, $queryBuilder->getDQL());
        self::assertSame($value, $queryBuilder->getParameter('context_0')?->getValue());
        self::assertSame($encodedValue, $queryBuilder->getParameter('context_0_json')?->getValue());
    }

    /**
     * Not for the message (a string column), nor for a value written the same in JSON.
     */
    #[TestWith(['message', '%a/b%'])]
    #[TestWith(['context', '%a.csv%'])]
    public function testApplyWithoutEscapedCharacters(string $property, string $value): void
    {
        $queryBuilder = $this->apply($property, ComparisonType::CONTAINS, $value);

        self::assertSame(
            'SELECT entity FROM '.LogRecord::class." entity WHERE LOWER(CONCAT(entity.{$property}, '')) like LOWER(:{$property}_0)",
            $queryBuilder->getDQL()
        );
        self::assertCount(1, $queryBuilder->getParameters());
    }

    private function apply(string $property, string $comparison, string $value): QueryBuilder
    {
        $queryBuilder = (new QueryBuilder($this->createStub(EntityManagerInterface::class)))->select('entity')->from(LogRecord::class, 'entity');
        $filter = LogTextFilter::new($property);
        $filterDataDto = FilterDataDto::new(0, $filter->getAsDto(), 'entity', ['comparison' => $comparison, 'value' => $value]);
        $metadata = new ClassMetadata(LogRecord::class);
        $metadata->mapField(['fieldName' => 'message', 'type' => 'string']);
        $metadata->mapField(['fieldName' => 'context', 'type' => 'json']);
        /** @var EntityDto<object> $entityDto */
        $entityDto = new EntityDto(LogRecord::class, $metadata);

        $filter->apply($queryBuilder, $filterDataDto, null, $entityDto);

        return $queryBuilder;
    }
}
