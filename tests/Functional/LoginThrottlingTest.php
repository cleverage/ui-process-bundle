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
use CleverAge\UiProcessBundle\Controller\Admin\ProcessDashboardController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessExecutionCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessScheduleCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\Security\LoginController;
use CleverAge\UiProcessBundle\Controller\Admin\UserCrudController;
use CleverAge\UiProcessBundle\Controller\ProcessExecuteController;
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\CheckDoctrineLazyGhostObjectsPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\LoginThrottlingPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\UploadDirectoryParameterPass;
use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\Entity\User;
use CleverAge\UiProcessBundle\Event\ProcessExecutionEndedEvent;
use CleverAge\UiProcessBundle\EventSubscriber\ProcessEventSubscriber;
use CleverAge\UiProcessBundle\Http\Model\HttpProcessExecution;
use CleverAge\UiProcessBundle\Http\ValueResolver\HttpProcessExecuteValueResolver;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;
use CleverAge\UiProcessBundle\Manager\UploadedFileManager;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use CleverAge\UiProcessBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use CleverAge\UiProcessBundle\Notifier\NotificationTrigger;
use CleverAge\UiProcessBundle\Notifier\ProcessExecutionNotifier;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use CleverAge\UiProcessBundle\Routing\DeprecatedEasyAdminRouteAliasLoader;
use CleverAge\UiProcessBundle\Security\HttpProcessExecutionAuthenticator;
use CleverAge\UiProcessBundle\Security\HttpProcessExecutionRequestRateLimiter;
use CleverAge\UiProcessBundle\Security\PeekableHttpProcessExecutionRequestRateLimiter;
use CleverAge\UiProcessBundle\StopChecker\ProcessExecutionStopChecker;
use CleverAge\UiProcessBundle\Twig\Extension\LogLevelExtension;
use CleverAge\UiProcessBundle\Twig\Extension\MD5Extension;
use CleverAge\UiProcessBundle\Twig\Extension\ProcessExecutionExtension;
use CleverAge\UiProcessBundle\Twig\Extension\ProcessExtension;
use CleverAge\UiProcessBundle\Validator\IsValidProcessCodeValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\PasswordHasher\Hasher\Pbkdf2PasswordHasher;

/**
 * Login throttling of the "main" firewall configured by the bundle: it limits the failed attempts of the login form,
 * not the requests of the HTTP API (authenticated by a token).
 *
 * The rate limiter state is stored in the cache of the test application, shared by the tests: each test uses an IP
 * address of its own.
 */
#[CoversClass(LoginThrottlingPass::class)]
#[CoversClass(HttpProcessExecutionRequestRateLimiter::class)]
#[CoversClass(PeekableHttpProcessExecutionRequestRateLimiter::class)]
#[CoversClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(HttpProcessExecutionAuthenticator::class)]
#[UsesClass(LoginController::class)]
#[UsesClass(ProcessExecuteController::class)]
#[UsesClass(UploadedFileManager::class)]
#[UsesClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(CheckDoctrineLazyGhostObjectsPass::class)]
#[UsesClass(UploadDirectoryParameterPass::class)]
#[UsesClass(ContextField::class)]
#[UsesClass(EnumField::class)]
#[UsesClass(LogProcessFilter::class)]
#[UsesClass(ProcessExecutionDurationFilter::class)]
#[UsesClass(LogRecordCrudController::class)]
#[UsesClass(ProcessDashboardController::class)]
#[UsesClass(ProcessExecutionCrudController::class)]
#[UsesClass(ProcessScheduleCrudController::class)]
#[UsesClass(UserCrudController::class)]
#[UsesClass(DeprecatedEasyAdminRouteAliasLoader::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProcessExecution::class)]
#[UsesClass(User::class)]
#[UsesClass(ProcessEventSubscriber::class)]
#[UsesClass(HttpProcessExecution::class)]
#[UsesClass(HttpProcessExecuteValueResolver::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
#[UsesClass(ProcessExecutionManager::class)]
#[UsesClass(ProcessExecuteMessage::class)]
#[UsesClass(DoctrineProcessHandler::class)]
#[UsesClass(ProcessHandler::class)]
#[UsesClass(ProcessExecutionRepository::class)]
#[UsesClass(LogLevelExtension::class)]
#[UsesClass(MD5Extension::class)]
#[UsesClass(ProcessExecutionExtension::class)]
#[UsesClass(ProcessExtension::class)]
#[UsesClass(IsValidProcessCodeValidator::class)]
#[UsesClass(ProcessExecutionEndedEvent::class)]
#[UsesClass(NotificationTrigger::class)]
#[UsesClass(ProcessExecutionNotifier::class)]
#[UsesClass(ProcessExecutionStopChecker::class)]
class LoginThrottlingTest extends FunctionalTestCase
{
    private const TOKEN = 'api-token';
    private const TOO_MANY_ATTEMPTS = 'Too many failed login attempts, please try again in 1 minute.';

    /**
     * Failed logins with the email of the API user do not block the API.
     */
    public function testFailedLoginsDoNotBlockTheHttpApi(): void
    {
        $this->client->setServerParameter('REMOTE_ADDR', '10.229.0.1');
        $this->createApiUser();

        for ($i = 0; $i < 5; ++$i) {
            $this->submitLogin('api@example.com', 'invalid');
            self::assertSelectorTextContains('.alert-danger', 'Invalid credentials.');
        }
        // The login form is still throttled
        $this->submitLogin('api@example.com', 'password');
        self::assertSelectorTextContains('.alert-danger', self::TOO_MANY_ATTEMPTS);

        $this->executeProcess(self::TOKEN);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->getDispatchedMessages());
    }

    /**
     * Requests with an invalid token (e.g. a cron job whose token has been regenerated) do not block the login form.
     */
    public function testInvalidTokensDoNotBlockTheLogin(): void
    {
        $this->client->setServerParameter('REMOTE_ADDR', '10.229.0.2');
        $this->createApiUser();
        $this->createUser('admin@example.com', ['ROLE_ADMIN'], 'secret');

        // More than the limit of an IP address (5 x 5 attempts)
        for ($i = 0; $i < 30; ++$i) {
            $this->executeProcess('invalid');
            self::assertResponseStatusCodeSame(401);
            self::assertSame(['message' => 'Invalid token.'], json_decode((string) $this->client->getResponse()->getContent(), true));
        }
        $this->executeProcess(self::TOKEN);
        self::assertResponseIsSuccessful();

        $this->submitLogin('admin@example.com', 'secret', followRedirect: false);
        self::assertResponseRedirects('/process');
    }

    /**
     * The settings of the application firewall still apply.
     */
    public function testApplicationSettings(): void
    {
        $this->createTestClient(['security' => ['firewalls' => ['main' => ['login_throttling' => ['max_attempts' => 2]]]]]);
        $this->client->setServerParameter('REMOTE_ADDR', '10.229.0.3');
        $this->createUser('admin@example.com', ['ROLE_ADMIN'], 'secret');

        for ($i = 0; $i < 2; ++$i) {
            $this->submitLogin('admin@example.com', 'invalid');
            self::assertSelectorTextContains('.alert-danger', 'Invalid credentials.');
        }
        $this->submitLogin('admin@example.com', 'secret');
        self::assertSelectorTextContains('.alert-danger', self::TOO_MANY_ATTEMPTS);
    }

    /**
     * "login_throttling: false" in the application firewall disables it.
     */
    public function testDisabledByTheApplication(): void
    {
        $this->createTestClient(['security' => ['firewalls' => ['main' => ['login_throttling' => false]]]]);
        $this->client->setServerParameter('REMOTE_ADDR', '10.229.0.4');
        $this->createUser('admin@example.com', ['ROLE_ADMIN'], 'secret');

        self::assertFalse(static::getContainer()->has('security.listener.login_throttling.main'));
        for ($i = 0; $i < 6; ++$i) {
            $this->submitLogin('admin@example.com', 'invalid');
            self::assertSelectorTextContains('.alert-danger', 'Invalid credentials.');
        }
        $this->submitLogin('admin@example.com', 'secret', followRedirect: false);
        self::assertResponseRedirects('/process');
    }

    public function testRateLimiterDisabled(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The login throttling of the "main" firewall requires the rate limiter, disabled by the "framework.rate_limiter" option: enable it, or disable the login throttling of the firewall ("login_throttling: false").');

        $this->createTestClient(['framework' => ['rate_limiter' => false]]);
    }

    public function testRateLimiterAndLoginThrottlingDisabled(): void
    {
        $this->createTestClient([
            'framework' => ['rate_limiter' => false],
            'security' => ['firewalls' => ['main' => ['login_throttling' => false]]],
        ]);
        $this->createUser('admin@example.com', ['ROLE_ADMIN'], 'secret');

        $this->submitLogin('admin@example.com', 'secret', followRedirect: false);
        self::assertResponseRedirects('/process');
    }

    private function createApiUser(): void
    {
        $user = $this->createUser('api@example.com');
        $user->setToken((new Pbkdf2PasswordHasher())->hash(self::TOKEN));
        $this->getEntityManager()->flush();
    }

    private function submitLogin(string $username, string $password, bool $followRedirect = true): void
    {
        $crawler = $this->client->request('GET', '/process/login');
        $this->client->submit($crawler->filter('form')->form(['_username' => $username, '_password' => $password]));
        if ($followRedirect) {
            self::assertResponseRedirects('http://localhost/process/login');
            $this->client->followRedirect();
        }
    }

    private function executeProcess(string $token): void
    {
        $this->client->request('POST', '/http/process/execute', ['code' => 'test.process'], server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
    }
}
