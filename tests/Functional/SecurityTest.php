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
use CleverAge\UiProcessBundle\Admin\Filter\LogProcessFilter;
use CleverAge\UiProcessBundle\Admin\Filter\ProcessExecutionDurationFilter;
use CleverAge\UiProcessBundle\CleverAgeUiProcessBundle;
use CleverAge\UiProcessBundle\Controller\Admin\LogRecordCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\Process\LaunchAction;
use CleverAge\UiProcessBundle\Controller\Admin\Process\ListAction;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessDashboardController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessExecutionCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessScheduleCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\Security\LoginController;
use CleverAge\UiProcessBundle\Controller\Admin\Security\LogoutController;
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Every page of the UI requires an authenticated user, except the login page (see also AccessControlTest).
 */
#[CoversClass(LoginController::class)]
#[CoversClass(LogoutController::class)]
#[UsesClass(ContextField::class)]
#[UsesClass(EnumField::class)]
#[UsesClass(LogProcessFilter::class)]
#[UsesClass(ProcessExecutionDurationFilter::class)]
#[UsesClass(LogRecordCrudController::class)]
#[UsesClass(ProcessDashboardController::class)]
#[UsesClass(ProcessExecutionCrudController::class)]
#[UsesClass(ProcessScheduleCrudController::class)]
#[UsesClass(LaunchAction::class)]
#[UsesClass(ListAction::class)]
#[UsesClass(UserCrudController::class)]
#[UsesClass(User::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
#[UsesClass(ProcessExecutionRepository::class)]
#[UsesClass(HttpProcessExecutionAuthenticator::class)]
#[UsesClass(LogLevelExtension::class)]
#[UsesClass(MD5Extension::class)]
#[UsesClass(ProcessExecutionExtension::class)]
#[UsesClass(ProcessExtension::class)]
#[UsesClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProcessEventSubscriber::class)]
#[UsesClass(DoctrineProcessHandler::class)]
#[UsesClass(ProcessHandler::class)]
class SecurityTest extends FunctionalTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideProtectedUrls(): iterable
    {
        yield 'dashboard' => ['/process'];
        yield 'process list' => ['/process?routeName=process_list'];
        yield 'process launch' => ['/process?routeName=process_launch&process=test.process'];
        yield 'executions' => ['/process/process-execution'];
        yield 'logs' => ['/process/log-record'];
        yield 'scheduler' => ['/process/process-schedule'];
        yield 'new schedule' => ['/process/process-schedule/new'];
        yield 'users' => ['/process/user'];
        yield 'new user' => ['/process/user/new'];
    }

    #[DataProvider('provideProtectedUrls')]
    public function testAnonymousIsRedirectedToLogin(string $url): void
    {
        $this->client->request('GET', $url);

        self::assertResponseRedirects('http://localhost/process/login');
    }

    public function testLogin(): void
    {
        $this->createUser('admin@example.com', ['ROLE_ADMIN'], 'secret');

        $crawler = $this->client->request('GET', '/process/login');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form')->form(['_username' => 'admin@example.com', '_password' => 'secret']);
        $this->client->submit($form);

        self::assertResponseRedirects('/process');
        $this->client->followRedirect();
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testLoginWithAnInvalidPassword(): void
    {
        $this->createUser('admin@example.com', ['ROLE_ADMIN'], 'secret');

        $crawler = $this->client->request('GET', '/process/login');
        $form = $crawler->filter('form')->form(['_username' => 'admin@example.com', '_password' => 'invalid']);
        $this->client->submit($form);

        self::assertResponseRedirects('http://localhost/process/login');
        $this->client->request('GET', '/process');
        self::assertResponseRedirects('http://localhost/process/login');
    }

    public function testLogout(): void
    {
        $this->login();

        $this->client->request('GET', '/process/logout');
        self::assertResponseRedirects('http://localhost/process/login');

        $this->client->request('GET', '/process');
        self::assertResponseRedirects('http://localhost/process/login');
    }
}
