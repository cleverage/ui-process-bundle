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

use CleverAge\UiProcessBundle\Admin\Field\EnumField;
use CleverAge\UiProcessBundle\CleverAgeUiProcessBundle;
use CleverAge\UiProcessBundle\Controller\Admin\LogRecordCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessDashboardController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessExecutionCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessScheduleCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\UserCrudController;
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
use CleverAge\UiProcessBundle\Entity\Enum\ProcessScheduleType;
use CleverAge\UiProcessBundle\Entity\ProcessSchedule;
use CleverAge\UiProcessBundle\Entity\User;
use CleverAge\UiProcessBundle\EventSubscriber\ProcessEventSubscriber;
use CleverAge\UiProcessBundle\Form\Type\ProcessContextType;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use CleverAge\UiProcessBundle\Repository\ProcessScheduleRepository;
use CleverAge\UiProcessBundle\Security\HttpProcessExecutionAuthenticator;
use CleverAge\UiProcessBundle\Twig\Extension\LogLevelExtension;
use CleverAge\UiProcessBundle\Twig\Extension\MD5Extension;
use CleverAge\UiProcessBundle\Twig\Extension\ProcessExecutionExtension;
use CleverAge\UiProcessBundle\Twig\Extension\ProcessExtension;
use CleverAge\UiProcessBundle\Validator\CronExpressionValidator;
use CleverAge\UiProcessBundle\Validator\EveryExpressionValidator;
use CleverAge\UiProcessBundle\Validator\IsValidProcessCodeValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(ProcessScheduleCrudController::class)]
#[UsesClass(EnumField::class)]
#[UsesClass(ProcessDashboardController::class)]
#[UsesClass(ProcessScheduleType::class)]
#[UsesClass(ProcessSchedule::class)]
#[UsesClass(User::class)]
#[UsesClass(ProcessContextType::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
#[UsesClass(ProcessScheduleRepository::class)]
#[UsesClass(HttpProcessExecutionAuthenticator::class)]
#[UsesClass(LogLevelExtension::class)]
#[UsesClass(MD5Extension::class)]
#[UsesClass(ProcessExecutionExtension::class)]
#[UsesClass(ProcessExtension::class)]
#[UsesClass(CronExpressionValidator::class)]
#[UsesClass(EveryExpressionValidator::class)]
#[UsesClass(IsValidProcessCodeValidator::class)]
#[UsesClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(LogRecordCrudController::class)]
#[UsesClass(ProcessExecutionCrudController::class)]
#[UsesClass(UserCrudController::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProcessEventSubscriber::class)]
#[UsesClass(ProcessExecutionRepository::class)]
#[UsesClass(DoctrineProcessHandler::class)]
#[UsesClass(ProcessHandler::class)]
class ProcessScheduleCrudTest extends FunctionalTestCase
{
    public function testIndexWarnsWhenNoWorkerIsRunning(): void
    {
        $this->login();
        $this->createSchedule(ProcessScheduleType::CRON, '0 4 * * *');
        $this->createSchedule(ProcessScheduleType::EVERY, '10 seconds');

        $crawler = $this->client->request('GET', '/process/process-schedule');

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('table tbody tr'));
        // No "messenger:consume scheduler_cron" process in the test environment
        self::assertSelectorTextContains('.alert-warning', 'bin/console messenger:consume scheduler_cron');
        // The next execution is only computed for CRON expressions
        self::assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T04:00:00/', $crawler->filter('table')->text());
    }

    public function testCreate(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/process/process-schedule/new');
        self::assertResponseIsSuccessful();
        // Only the public processes can be scheduled
        $options = $crawler->filter('select[name="ProcessSchedule[process]"] option')->each(static fn ($option): string => (string) $option->attr('value'));
        self::assertContains('test.process', $options);
        self::assertNotContains('test.private', $options);

        $form = $crawler->selectButton('Create')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();
        $values['ProcessSchedule']['process'] = 'test.process';
        $values['ProcessSchedule']['type'] = 'cron';
        $values['ProcessSchedule']['expression'] = '*/5 * * * *';
        $values['ProcessSchedule']['input'] = 'my input';
        $values['ProcessSchedule']['context'] = [['key' => 'key1', 'value' => 'value1']];
        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $schedules = $this->getEntityManager()->getRepository(ProcessSchedule::class)->findAll();
        self::assertCount(1, $schedules);
        self::assertSame('test.process', $schedules[0]->getProcess());
        self::assertSame(ProcessScheduleType::CRON, $schedules[0]->getType());
        self::assertSame('*/5 * * * *', $schedules[0]->getExpression());
        self::assertSame('my input', $schedules[0]->getInput());
    }

    public function testInvalidExpressionIsRejected(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/process/process-schedule/new');
        $form = $crawler->selectButton('Create')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();
        $values['ProcessSchedule']['process'] = 'test.process';
        $values['ProcessSchedule']['type'] = 'cron';
        $values['ProcessSchedule']['expression'] = 'not a cron expression';
        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->getEntityManager()->getRepository(ProcessSchedule::class)->findAll());
    }

    public function testEdit(): void
    {
        $this->login();
        $schedule = $this->createSchedule(ProcessScheduleType::EVERY, '10 seconds');

        $crawler = $this->client->request('GET', '/process/process-schedule/'.$schedule->getId().'/edit');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Save changes')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();
        $values['ProcessSchedule']['expression'] = '1 hour';
        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $this->getEntityManager()->clear();
        self::assertSame('1 hour', $this->getEntityManager()->find(ProcessSchedule::class, $schedule->getId())?->getExpression());
    }

    public function testEntityFqcn(): void
    {
        self::assertSame(ProcessSchedule::class, ProcessScheduleCrudController::getEntityFqcn());
    }

    private function createSchedule(ProcessScheduleType $type, string $expression): ProcessSchedule
    {
        $schedule = new ProcessSchedule();
        $schedule->setProcess('test.process');
        $schedule->setType($type);
        $schedule->setExpression($expression);
        $this->getEntityManager()->persist($schedule);
        $this->getEntityManager()->flush();

        return $schedule;
    }
}
