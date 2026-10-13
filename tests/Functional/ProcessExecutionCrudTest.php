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

    public function testRequestStop(): void
    {
        $this->login();
        $running = $this->createExecution('test.process', ProcessExecutionStatus::Started, false);
        $this->createExecution('test.form', ProcessExecutionStatus::Finish);

        $crawler = $this->client->request('GET', '/process/process-execution');
        self::assertResponseIsSuccessful();
        // Only for the running execution, as a POST form
        self::assertCount(1, $crawler->filter('form[method="POST"][action*="request-stop"]'));
        self::assertStringContainsString('/'.$running->getId().'/request-stop', (string) $crawler->filter('form[action*="request-stop"]')->attr('action'));

        $this->client->request('GET', '/process/process-execution/'.$running->getId().'/request-stop');
        self::assertResponseStatusCodeSame(405);

        $this->client->request('POST', '/process/process-execution/'.$running->getId().'/request-stop');
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Stop requested: the process will stop before its next task execution.');
        self::assertStringContainsString('Stop requested', $crawler->filter('table')->text());
        self::assertCount(0, $crawler->filter('form[action*="request-stop"]'));

        $this->getEntityManager()->clear();
        $running = $this->getEntityManager()->find(ProcessExecution::class, $running->getId());
        self::assertInstanceOf(\DateTimeImmutable::class, $running?->stopRequestedAt);
        self::assertSame(ProcessExecutionStatus::Started, $running->status);
    }

    public function testRequestStopOfAnEndedExecution(): void
    {
        $this->login();
        $ended = $this->createExecution('test.process', ProcessExecutionStatus::Finish);

        $this->client->request('POST', '/process/process-execution/'.$ended->getId().'/request-stop');
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-warning', 'The process execution is not running.');

        $this->getEntityManager()->clear();
        self::assertNull($this->getEntityManager()->find(ProcessExecution::class, $ended->getId())?->stopRequestedAt);
    }

    public function testMarkStaleExecutionAsFailed(): void
    {
        $this->login();
        $stale = $this->createExecution('test.process', ProcessExecutionStatus::Started, false);
        $running = $this->createExecution('test.form', ProcessExecutionStatus::Started, false);
        // No activity for 1 hour (default stale_after: 15 minutes)
        $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE process_execution SET start_date = ?, heartbeat_at = ? WHERE id = ?',
            [new \DateTimeImmutable('-2 hours'), new \DateTimeImmutable('-1 hour'), $stale->getId()],
            ['datetime_immutable', 'datetime_immutable', 'integer']
        );
        $this->getEntityManager()->clear();

        $crawler = $this->client->request('GET', '/process/process-execution');
        self::assertResponseIsSuccessful();
        $statuses = $crawler->filter('table td[data-column="status"]')->each(static fn ($cell): string => $cell->text());
        self::assertEqualsCanonicalizing(['Started Not responding', 'Started'], $statuses);
        self::assertCount(1, $crawler->filter('form[method="POST"][action*="mark-as-failed"]'));
        self::assertStringContainsString('/'.$stale->getId().'/mark-as-failed', (string) $crawler->filter('form[action*="mark-as-failed"]')->attr('action'));
        // A stale execution can still be stopped (its process may be blocked in a long task call)
        self::assertCount(2, $crawler->filter('form[method="POST"][action*="request-stop"]'));

        $this->client->request('POST', '/process/process-execution/'.$stale->getId().'/request-stop');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Stop requested: the process will stop before its next task execution.');
        $statuses = $crawler->filter('table td[data-column="status"]')->each(static fn ($cell): string => $cell->text());
        self::assertEqualsCanonicalizing(['Started Not responding Stop requested', 'Started'], $statuses);
        // Still available after the stop request: a killed process never reads it
        self::assertCount(1, $crawler->filter('form[method="POST"][action*="mark-as-failed"]'));
        self::assertCount(1, $crawler->filter('form[method="POST"][action*="request-stop"]'));

        $this->client->request('POST', '/process/process-execution/'.$running->getId().'/mark-as-failed');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-warning', 'Only a process execution which is not responding can be marked as failed.');

        $this->client->request('POST', '/process/process-execution/'.$stale->getId().'/mark-as-failed');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'The process execution has been marked as failed.');

        $this->getEntityManager()->clear();
        $stale = $this->getEntityManager()->find(ProcessExecution::class, $stale->getId());
        self::assertSame(ProcessExecutionStatus::Failed, $stale?->status);
        self::assertEquals($stale->heartbeatAt, $stale->endDate);
        self::assertSame(ProcessExecutionStatus::Started, $this->getEntityManager()->find(ProcessExecution::class, $running->getId())?->status);
    }

    public function testStoppedStatus(): void
    {
        $this->login();
        $this->createExecution('test.process', ProcessExecutionStatus::Stopped);

        $crawler = $this->client->request('GET', '/process/process-execution');

        self::assertResponseIsSuccessful();
        self::assertSame('Stopped', $crawler->filter('table td[data-column="status"] .badge-warning')->text());
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
