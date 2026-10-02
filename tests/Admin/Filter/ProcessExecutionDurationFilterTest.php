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

use CleverAge\UiProcessBundle\Admin\Filter\ProcessExecutionDurationFilter;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\NumericFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcessExecutionDurationFilter::class)]
class ProcessExecutionDurationFilterTest extends TestCase
{
    public function testNew(): void
    {
        $dto = ProcessExecutionDurationFilter::new('duration', 'Duration')->getAsDto();

        self::assertSame(ProcessExecutionDurationFilter::class, $dto->getFqcn());
        self::assertSame('duration', $dto->getProperty());
        self::assertSame('Duration', $dto->getLabel());
        self::assertSame(NumericFilterType::class, $dto->getFormType());
        self::assertSame('EasyAdminBundle', $dto->getFormTypeOption('translation_domain'));
    }

    #[DataProvider('provideComparisons')]
    public function testApplyWithComparison(string $comparison): void
    {
        $queryBuilder = $this->apply(['comparison' => $comparison, 'value' => 60]);

        self::assertSame(
            'SELECT entity FROM '.ProcessExecution::class.' entity WHERE entity.endDate '.$comparison
            .' date_add(entity.startDate, 60, \'SECOND\')',
            $queryBuilder->getDQL()
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideComparisons(): iterable
    {
        yield 'eq' => [ComparisonType::EQ];
        yield 'neq' => [ComparisonType::NEQ];
        yield 'gt' => [ComparisonType::GT];
        yield 'gte' => [ComparisonType::GTE];
        yield 'lt' => [ComparisonType::LT];
        yield 'lte' => [ComparisonType::LTE];
    }

    public function testApplyBetween(): void
    {
        $queryBuilder = $this->apply(['comparison' => ComparisonType::BETWEEN, 'value' => 10, 'value2' => 120]);

        self::assertSame(
            'SELECT entity FROM '.ProcessExecution::class.' entity WHERE entity.endDate BETWEEN '
            .'date_add(entity.startDate, 10, \'SECOND\') and date_add(entity.startDate, 120, \'SECOND\')',
            $queryBuilder->getDQL()
        );
    }

    public function testApplyWithUnsupportedComparisonDoesNothing(): void
    {
        $queryBuilder = $this->apply(['comparison' => ComparisonType::CONTAINS, 'value' => 10]);

        self::assertSame('SELECT entity FROM '.ProcessExecution::class.' entity', $queryBuilder->getDQL());
    }

    /**
     * @param array{comparison: string, value: mixed, value2?: mixed} $formData
     */
    private function apply(array $formData): QueryBuilder
    {
        $queryBuilder = (new QueryBuilder($this->createStub(EntityManagerInterface::class)))
            ->select('entity')
            ->from(ProcessExecution::class, 'entity');
        $filter = ProcessExecutionDurationFilter::new('duration');
        $filterDataDto = FilterDataDto::new(0, $filter->getAsDto(), 'entity', $formData);

        /** @var EntityDto<object> $entityDto */
        $entityDto = new EntityDto(ProcessExecution::class, new ClassMetadata(ProcessExecution::class));

        $filter->apply($queryBuilder, $filterDataDto, null, $entityDto);

        return $queryBuilder;
    }
}
