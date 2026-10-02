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

namespace CleverAge\UiProcessBundle\Tests\Functional;

use CleverAge\UiProcessBundle\Admin\Field\ContextField;
use CleverAge\UiProcessBundle\Admin\Field\LogLevelField;
use CleverAge\UiProcessBundle\Admin\Filter\LogProcessFilter;
use CleverAge\UiProcessBundle\CleverAgeUiProcessBundle;
use CleverAge\UiProcessBundle\Controller\Admin\LogRecordCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessDashboardController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessExecutionCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessScheduleCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\UserCrudController;
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
use CleverAge\UiProcessBundle\Entity\LogRecord;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\Entity\User;
use CleverAge\UiProcessBundle\EventSubscriber\ProcessEventSubscriber;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use CleverAge\UiProcessBundle\Security\HttpProcessExecutionAuthenticator;
use CleverAge\UiProcessBundle\Twig\Extension\LogLevelExtension;
use CleverAge\UiProcessBundle\Twig\Extension\MD5Extension;
use CleverAge\UiProcessBundle\Twig\Extension\ProcessExecutionExtension;
use CleverAge\UiProcessBundle\Twig\Extension\ProcessExtension;
use CleverAge\UiProcessBundle\Twig\Runtime\LogLevelExtensionRuntime;
use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(LogRecordCrudController::class)]
#[UsesClass(ContextField::class)]
#[UsesClass(LogLevelField::class)]
#[UsesClass(LogProcessFilter::class)]
#[UsesClass(ProcessDashboardController::class)]
#[UsesClass(LogRecord::class)]
#[UsesClass(ProcessExecution::class)]
#[UsesClass(User::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
#[UsesClass(HttpProcessExecutionAuthenticator::class)]
#[UsesClass(LogLevelExtension::class)]
#[UsesClass(MD5Extension::class)]
#[UsesClass(ProcessExecutionExtension::class)]
#[UsesClass(ProcessExtension::class)]
#[UsesClass(LogLevelExtensionRuntime::class)]
#[UsesClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(ProcessExecutionCrudController::class)]
#[UsesClass(ProcessScheduleCrudController::class)]
#[UsesClass(UserCrudController::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProcessEventSubscriber::class)]
#[UsesClass(ProcessExecutionRepository::class)]
#[UsesClass(DoctrineProcessHandler::class)]
#[UsesClass(ProcessHandler::class)]
class LogRecordCrudTest extends FunctionalTestCase
{
    public function testIndexAndProcessFilter(): void
    {
        $this->login();
        $first = $this->createExecution('test.process');
        $second = $this->createExecution('test.form');
        $this->createLogRecord($first, 'First process message', ['file' => 'a.csv']);
        $this->createLogRecord($second, 'Second process message', []);

        $crawler = $this->client->request('GET', '/process/log-record');
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('table tbody tr'));

        $crawler = $this->client->request('GET', '/process/log-record?filters[process][comparison]==&filters[process][value]='.$first->getId());
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('table tbody tr'));
        self::assertStringContainsString('First process message', $crawler->filter('table')->text());

        $crawler = $this->client->request('GET', '/process/log-record?filters[level][comparison]==&filters[level][value]='.Level::Error->value);
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('process message', $crawler->filter('table')->text());

        $this->client->request('GET', '/process/log-record/render-filters?filters[process][comparison]==&filters[process][value]='.$first->getId());
        self::assertResponseIsSuccessful();
    }

    public function testDetail(): void
    {
        $this->login();
        $record = $this->createLogRecord($this->createExecution('test.process'), 'A message', ['file' => 'a.csv']);

        $crawler = $this->client->request('GET', '/process/log-record/'.$record->getId());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('A message', $crawler->text());
        self::assertStringContainsString('a.csv', $crawler->text());
    }

    private function createExecution(string $code): ProcessExecution
    {
        $execution = new ProcessExecution($code, $code.'.log');
        $this->getEntityManager()->persist($execution);
        $this->getEntityManager()->flush();

        return $execution;
    }

    public function testEntityFqcn(): void
    {
        self::assertSame(LogRecord::class, LogRecordCrudController::getEntityFqcn());
    }

    /**
     * @param array<mixed> $context
     */
    private function createLogRecord(ProcessExecution $execution, string $message, array $context): LogRecord
    {
        $record = new LogRecord(new \Monolog\LogRecord(new \DateTimeImmutable(), 'cleverage_process', Level::Warning, $message, $context), $execution);
        $this->getEntityManager()->persist($record);
        $this->getEntityManager()->flush();

        return $record;
    }
}
