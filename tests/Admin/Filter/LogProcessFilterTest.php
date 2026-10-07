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

use CleverAge\UiProcessBundle\Admin\Filter\LogProcessFilter;
use CleverAge\UiProcessBundle\Entity\LogRecord;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogProcessFilter::class)]
class LogProcessFilterTest extends TestCase
{
    public function testNewWithChoices(): void
    {
        $dto = LogProcessFilter::new('Process', ['demo.a' => 'demo.a', 'demo.b' => 'demo.b'])->getAsDto();

        self::assertSame(LogProcessFilter::class, $dto->getFqcn());
        self::assertSame('process', $dto->getProperty());
        self::assertSame('Process', $dto->getLabel());
        self::assertSame(ChoiceFilterType::class, $dto->getFormType());
        self::assertSame(['choices' => ['demo.a' => 'demo.a', 'demo.b' => 'demo.b']], $dto->getFormTypeOption('value_type_options'));
        self::assertSame(['comparison' => ComparisonType::EQ, 'value' => null], $dto->getFormTypeOption('data'));
    }

    public function testNewWithExecutionIdReplacesChoices(): void
    {
        $dto = LogProcessFilter::new('Process', ['demo.a' => 'demo.a'], '12')->getAsDto();

        self::assertSame(['choices' => [12 => '12']], $dto->getFormTypeOption('value_type_options'));
        self::assertSame(['comparison' => ComparisonType::EQ, 'value' => '12'], $dto->getFormTypeOption('data'));
    }

    public function testNewWithNonNumericExecutionIdKeepsChoices(): void
    {
        $dto = LogProcessFilter::new('Process', ['demo.a' => 'demo.a'], 'demo.a')->getAsDto();

        self::assertSame(['choices' => ['demo.a' => 'demo.a']], $dto->getFormTypeOption('value_type_options'));
        self::assertSame(['comparison' => ComparisonType::EQ, 'value' => 'demo.a'], $dto->getFormTypeOption('data'));
    }

    public function testApplyWithExecutionId(): void
    {
        $queryBuilder = $this->createQueryBuilder();

        $this->apply($queryBuilder, '12');

        self::assertSame(
            'SELECT entity FROM '.LogRecord::class.' entity INNER JOIN entity.processExecution pe WHERE pe.id = :id',
            $queryBuilder->getDQL()
        );
        self::assertCount(1, $queryBuilder->getParameters());
        self::assertSame('12', $queryBuilder->getParameter('id')?->getValue());
    }

    public function testApplyWithProcessCodes(): void
    {
        $queryBuilder = $this->createQueryBuilder();

        $this->apply($queryBuilder, ['demo.a', 'demo.b']);

        self::assertSame(
            'SELECT entity FROM '.LogRecord::class.' entity INNER JOIN entity.processExecution pe WHERE pe.code IN (:codes)',
            $queryBuilder->getDQL()
        );
        self::assertCount(1, $queryBuilder->getParameters());
        self::assertSame(['demo.a', 'demo.b'], $queryBuilder->getParameter('codes')?->getValue());
    }

    public function testApplyWithExecutionIdNotEqual(): void
    {
        $queryBuilder = $this->createQueryBuilder();

        $this->apply($queryBuilder, '12', ComparisonType::NEQ);

        self::assertSame(
            'SELECT entity FROM '.LogRecord::class.' entity INNER JOIN entity.processExecution pe WHERE pe.id <> :id',
            $queryBuilder->getDQL()
        );
    }

    public function testApplyWithProcessCodesNotEqual(): void
    {
        $queryBuilder = $this->createQueryBuilder();

        $this->apply($queryBuilder, ['demo.a'], ComparisonType::NEQ);

        self::assertSame(
            'SELECT entity FROM '.LogRecord::class.' entity INNER JOIN entity.processExecution pe WHERE pe.code NOT IN (:codes)',
            $queryBuilder->getDQL()
        );
    }

    /**
     * EasyAdmin adds the search clause, then the filters in their configuration order: they must be kept.
     */
    #[DataProvider('provideValues')]
    public function testApplyKeepsThePreviousConditions(mixed $value, string $condition): void
    {
        $queryBuilder = $this->createQueryBuilder()
            ->andWhere('entity.message LIKE :query')
            ->setParameter('query', '%message%');

        $this->apply($queryBuilder, $value);

        self::assertSame(
            'SELECT entity FROM '.LogRecord::class.' entity INNER JOIN entity.processExecution pe WHERE entity.message LIKE :query AND '.$condition,
            $queryBuilder->getDQL()
        );
        self::assertCount(2, $queryBuilder->getParameters());
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideValues(): iterable
    {
        yield 'execution id' => ['12', 'pe.id = :id'];
        yield 'process codes' => [['demo.a', 'demo.b'], 'pe.code IN (:codes)'];
    }

    private function createQueryBuilder(): QueryBuilder
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getExpressionBuilder')->willReturn(new Expr());

        return (new QueryBuilder($entityManager))->select('entity')->from(LogRecord::class, 'entity');
    }

    private function apply(QueryBuilder $queryBuilder, mixed $value, string $comparison = ComparisonType::EQ): void
    {
        $filter = LogProcessFilter::new('Process', []);
        $filterDataDto = FilterDataDto::new(0, $filter->getAsDto(), 'entity', ['comparison' => $comparison, 'value' => $value]);

        /** @var EntityDto<object> $entityDto */
        $entityDto = new EntityDto(LogRecord::class, new ClassMetadata(LogRecord::class));

        $filter->apply($queryBuilder, $filterDataDto, null, $entityDto);
    }
}
