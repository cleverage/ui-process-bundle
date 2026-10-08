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
use CleverAge\UiProcessBundle\Admin\Field\EnumField;
use CleverAge\UiProcessBundle\Admin\Filter\ProcessExecutionDurationFilter;
use CleverAge\UiProcessBundle\CleverAgeUiProcessBundle;
use CleverAge\UiProcessBundle\Controller\Admin\LogRecordCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessDashboardController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessExecutionCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessScheduleCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\UserCrudController;
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
use CleverAge\UiProcessBundle\Entity\Enum\ProcessExecutionStatus;
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
use CleverAge\UiProcessBundle\Twig\Runtime\ProcessExecutionExtensionRuntime;
use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(ProcessExecutionCrudController::class)]
#[UsesClass(ContextField::class)]
#[UsesClass(EnumField::class)]
#[UsesClass(ProcessExecutionDurationFilter::class)]
#[UsesClass(ProcessDashboardController::class)]
#[UsesClass(ProcessExecutionStatus::class)]
#[UsesClass(LogRecord::class)]
#[UsesClass(ProcessExecution::class)]
#[UsesClass(User::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
#[UsesClass(ProcessExecutionRepository::class)]
#[UsesClass(HttpProcessExecutionAuthenticator::class)]
#[UsesClass(LogLevelExtension::class)]
#[UsesClass(MD5Extension::class)]
#[UsesClass(ProcessExecutionExtension::class)]
#[UsesClass(ProcessExtension::class)]
#[UsesClass(LogLevelExtensionRuntime::class)]
#[UsesClass(ProcessExecutionExtensionRuntime::class)]
#[UsesClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(LogRecordCrudController::class)]
#[UsesClass(ProcessScheduleCrudController::class)]
#[UsesClass(UserCrudController::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProcessEventSubscriber::class)]
#[UsesClass(DoctrineProcessHandler::class)]
#[UsesClass(ProcessHandler::class)]
class ProcessExecutionCrudTest extends FunctionalTestCase
{
    public function testIndex(): void
    {
        $this->login();
        $this->createExecution('test.process', ProcessExecutionStatus::Finish);
        $this->createExecution('test.form', ProcessExecutionStatus::Failed);

        $crawler = $this->client->request('GET', '/process/process-execution');

        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('table tbody tr');
        self::assertCount(2, $rows);
        self::assertStringContainsString('test.process', $crawler->filter('table')->text());
        self::assertStringContainsString('test.form', $crawler->filter('table')->text());
    }

    public function testDuration(): void
    {
        $this->login();
        $this->createExecution('test.process', ProcessExecutionStatus::Finish);
        $this->createExecution('test.form', ProcessExecutionStatus::Started, false);

        $crawler = $this->client->request('GET', '/process/process-execution');

        self::assertResponseIsSuccessful();
        $durations = $crawler->filter('table tbody td[data-column="duration"]')->each(static fn ($cell): string => $cell->text());
        self::assertEqualsCanonicalizing(['00 hour(s) 00 min(s) 00 s', 'Null'], $durations);
    }

    public function testFilters(): void
    {
        $this->login();
        $this->createExecution('test.process', ProcessExecutionStatus::Finish);
        $this->createExecution('test.form', ProcessExecutionStatus::Finish);

        $crawler = $this->client->request('GET', '/process/process-execution?filters[code][comparison]=like&filters[code][value]=form');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('table tbody tr'));

        $crawler = $this->client->request('GET', '/process/process-execution?filters[duration][comparison]=>&filters[duration][value]=3600');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('test.form', $crawler->filter('table')->text());

        $this->client->request('GET', '/process/process-execution/render-filters');
        self::assertResponseIsSuccessful();
    }

    public function testActionsDependOnTheLogs(): void
    {
        $this->login();
        $withLogs = $this->createExecution('test.process', ProcessExecutionStatus::Finish);
        $this->createLogRecord($withLogs, 'A log message');
        $this->writeLogFile($withLogs, 'log file content');
        $this->createExecution('test.form', ProcessExecutionStatus::Finish);

        $crawler = $this->client->request('GET', '/process/process-execution');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('a[href*="show-logs"]'));
        self::assertCount(1, $crawler->filter('a[href*="download-logs"]'));
    }

    public function testShowLogs(): void
    {
        $this->login();
        $execution = $this->createExecution('test.process', ProcessExecutionStatus::Finish);

        $this->client->request('GET', '/process/process-execution/show-logs?entityId='.$execution->getId());

        self::assertResponseRedirects();
        $location = urldecode((string) $this->client->getResponse()->headers->get('Location'));
        self::assertStringContainsString('/process/log-record', $location);
        self::assertStringContainsString('filters[process][value]='.$execution->getId(), $location);
    }

    public function testDownloadLogFile(): void
    {
        $this->login();
        $execution = $this->createExecution('test.process', ProcessExecutionStatus::Finish);
        $this->writeLogFile($execution, 'log file content');

        $this->client->request('GET', '/process/process-execution/download-logs?entityId='.$execution->getId());

        self::assertResponseIsSuccessful();
        self::assertSame('log file content', $this->client->getInternalResponse()->getContent());
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=utf-8');
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename="'.$execution->logFilename.'"');
    }

    public function testDownloadMissingLogFile(): void
    {
        $this->login();
        $execution = $this->createExecution('test.process', ProcessExecutionStatus::Finish);

        $this->client->request('GET', '/process/process-execution/download-logs?entityId='.$execution->getId());

        self::assertResponseStatusCodeSame(404);
    }

    private function createExecution(string $code, ProcessExecutionStatus $status, bool $ended = true): ProcessExecution
    {
        $execution = new ProcessExecution($code, $code.'_'.uniqid().'.log', ['key' => 'value']);
        $execution->setStatus($status);
        $execution->addReport('count', 3);
        if ($ended) {
            $execution->end();
        }
        $this->getEntityManager()->persist($execution);
        $this->getEntityManager()->flush();

        return $execution;
    }

    private function createLogRecord(ProcessExecution $execution, string $message): void
    {
        $this->getEntityManager()->persist(new LogRecord(new \Monolog\LogRecord(new \DateTimeImmutable(), 'cleverage_process', Level::Warning, $message, ['file' => 'a.csv']), $execution));
        $this->getEntityManager()->flush();
    }

    public function testEntityFqcn(): void
    {
        self::assertSame(ProcessExecution::class, ProcessExecutionCrudController::getEntityFqcn());
    }

    private function writeLogFile(ProcessExecution $execution, string $content): void
    {
        /** @var string $logDir */
        $logDir = static::getContainer()->getParameter('kernel.logs_dir');
        (new Filesystem())->dumpFile($logDir.'/'.$execution->code.'/'.$execution->logFilename, $content);
    }
}
