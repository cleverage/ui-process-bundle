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

use CleverAge\UiProcessBundle\CleverAgeUiProcessBundle;
use CleverAge\UiProcessBundle\Controller\Admin\LogRecordCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\Process\ListAction;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessDashboardController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessExecutionCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessScheduleCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\UserCrudController;
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
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
use CleverAge\UiProcessBundle\Twig\Runtime\ProcessExecutionExtensionRuntime;
use CleverAge\UiProcessBundle\Twig\Runtime\ProcessExtensionRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(ListAction::class)]
#[CoversClass(ProcessDashboardController::class)]
#[UsesClass(User::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
#[UsesClass(DoctrineProcessHandler::class)]
#[UsesClass(ProcessHandler::class)]
#[UsesClass(ProcessExecutionRepository::class)]
#[UsesClass(HttpProcessExecutionAuthenticator::class)]
#[UsesClass(LogLevelExtension::class)]
#[UsesClass(MD5Extension::class)]
#[UsesClass(ProcessExecutionExtension::class)]
#[UsesClass(ProcessExtension::class)]
#[UsesClass(ProcessExecutionExtensionRuntime::class)]
#[UsesClass(ProcessExtensionRuntime::class)]
#[UsesClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(LogRecordCrudController::class)]
#[UsesClass(ProcessExecutionCrudController::class)]
#[UsesClass(ProcessScheduleCrudController::class)]
#[UsesClass(UserCrudController::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProcessEventSubscriber::class)]
class ProcessListTest extends FunctionalTestCase
{
    public function testDashboardRedirectsToTheExecutions(): void
    {
        $this->login();

        $this->client->request('GET', '/process');

        self::assertResponseRedirects();
        self::assertStringContainsString('/process/process-execution', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testPublicProcessesAreListed(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/process?routeName=process_list');

        self::assertResponseIsSuccessful();
        $text = $crawler->filter('table')->text();
        self::assertStringContainsString('test.process', $text);
        self::assertStringContainsString('test.form', $text);
        self::assertStringNotContainsString('test.private', $text);
    }

    public function testUsersMenuIsReservedToAdmins(): void
    {
        $this->login();
        $crawler = $this->client->request('GET', '/process?routeName=process_list');
        self::assertStringNotContainsString('User List', $crawler->filter('nav, aside, .sidebar, body')->first()->text());

        $this->client->restart();
        $this->client->disableReboot();
        $this->client->loginUser($this->createUser('admin@example.com', ['ROLE_ADMIN']), 'main');
        $crawler = $this->client->request('GET', '/process?routeName=process_list');
        self::assertStringContainsString('User List', $crawler->filter('body')->text());
    }

    public function testUserLocale(): void
    {
        $user = $this->createUser('fr@example.com');
        $user->setLocale('fr');
        $this->getEntityManager()->flush();
        $this->client->loginUser($user, 'main');

        $crawler = $this->client->request('GET', '/process?routeName=process_list');

        self::assertResponseIsSuccessful();
        // EasyAdmin labels translated in the user locale
        self::assertStringContainsString('Déconnexion', $crawler->filter('body')->text());
    }
}
