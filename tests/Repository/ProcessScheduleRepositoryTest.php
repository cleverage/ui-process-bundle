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

namespace CleverAge\UiProcessBundle\Tests\Repository;

use CleverAge\UiProcessBundle\CleverAgeUiProcessBundle;
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
use CleverAge\UiProcessBundle\Entity\Enum\ProcessScheduleType;
use CleverAge\UiProcessBundle\Entity\ProcessSchedule;
use CleverAge\UiProcessBundle\EventSubscriber\ProcessEventSubscriber;
use CleverAge\UiProcessBundle\Repository\ProcessScheduleRepository;
use CleverAge\UiProcessBundle\Tests\App\TestKernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(ProcessScheduleRepository::class)]
#[UsesClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProcessEventSubscriber::class)]
#[UsesClass(ProcessSchedule::class)]
class ProcessScheduleRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel(['debug' => false]);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->entityManager = $entityManager;
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testRepositoryOfTheEntity(): void
    {
        self::assertInstanceOf(ProcessScheduleRepository::class, $this->entityManager->getRepository(ProcessSchedule::class));
    }

    public function testFindSchedules(): void
    {
        $schedule = (new ProcessSchedule())
            ->setProcess('test.process')
            ->setType(ProcessScheduleType::EVERY)
            ->setExpression('1 hour')
            ->setInput('data.csv');
        $schedule->setContext([['key' => 'foo', 'value' => 'bar']]);
        $this->entityManager->persist($schedule);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $repository = new ProcessScheduleRepository($this->entityManager);
        $schedules = $repository->findAll();

        self::assertCount(1, $schedules);
        self::assertSame($schedule->getId(), $schedules[0]->getId());
        self::assertSame('test.process', $schedules[0]->getProcess());
        self::assertSame(ProcessScheduleType::EVERY, $schedules[0]->getType());
        self::assertSame('1 hour', $schedules[0]->getExpression());
        self::assertSame('data.csv', $schedules[0]->getInput());
        self::assertSame([['key' => 'foo', 'value' => 'bar']], $schedules[0]->getContext());
    }
}
