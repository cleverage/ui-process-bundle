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
use CleverAge\UiProcessBundle\Controller\Admin\ProcessDashboardController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessExecutionCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessScheduleCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\UserCrudController;
use CleverAge\UiProcessBundle\Controller\ProcessExecuteController;
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\Entity\User;
use CleverAge\UiProcessBundle\EventSubscriber\ProcessEventSubscriber;
use CleverAge\UiProcessBundle\Http\Model\HttpProcessExecution;
use CleverAge\UiProcessBundle\Http\ValueResolver\HttpProcessExecuteValueResolver;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use CleverAge\UiProcessBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use CleverAge\UiProcessBundle\Security\HttpProcessExecutionAuthenticator;
use CleverAge\UiProcessBundle\Twig\Extension\LogLevelExtension;
use CleverAge\UiProcessBundle\Twig\Extension\MD5Extension;
use CleverAge\UiProcessBundle\Twig\Extension\ProcessExecutionExtension;
use CleverAge\UiProcessBundle\Twig\Extension\ProcessExtension;
use CleverAge\UiProcessBundle\Validator\IsValidProcessCodeValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\PasswordHasher\Hasher\Pbkdf2PasswordHasher;

/**
 * HTTP API: POST /http/process/execute, authenticated with a Bearer token.
 */
#[CoversClass(ProcessExecuteController::class)]
#[CoversClass(HttpProcessExecutionAuthenticator::class)]
#[UsesClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(LogRecordCrudController::class)]
#[UsesClass(ProcessDashboardController::class)]
#[UsesClass(ProcessExecutionCrudController::class)]
#[UsesClass(ProcessScheduleCrudController::class)]
#[UsesClass(UserCrudController::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
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
class HttpProcessExecuteTest extends FunctionalTestCase
{
    private const TOKEN = 'api-token';

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser('api@example.com');
        $user->setToken((new Pbkdf2PasswordHasher())->hash(self::TOKEN));
        $this->getEntityManager()->flush();
    }

    public function testQueuedExecution(): void
    {
        $this->execute(['code' => 'test.process', 'input' => 'data', 'context' => ['key' => 'value']]);

        self::assertResponseIsSuccessful();
        self::assertSame('"Process has been added to queue. It will start as soon as possible."', $this->client->getResponse()->getContent());
        $messages = $this->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertSame('test.process', $messages[0]->code);
        self::assertSame('data', $messages[0]->input);
        self::assertSame(['key' => 'value'], $messages[0]->context);
    }

    public function testSynchronousExecution(): void
    {
        $this->execute(['code' => 'test.process', 'queue' => '0']);

        self::assertResponseIsSuccessful();
        self::assertSame('"Process has been proceed well."', $this->client->getResponse()->getContent());
        self::assertSame([], $this->getDispatchedMessages());
    }

    public function testFailingSynchronousExecution(): void
    {
        $this->execute(['code' => 'test.failing', 'queue' => '0']);

        self::assertResponseStatusCodeSame(500);
        self::assertStringContainsString('output', (string) $this->client->getResponse()->getContent());
    }

    public function testJsonBody(): void
    {
        $this->client->request(
            'POST',
            '/http/process/execute',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN, 'CONTENT_TYPE' => 'application/json'],
            content: json_encode(['code' => 'test.process', 'input' => 'data', 'context' => '{"key":"value"}', 'queue' => true], \JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        $messages = $this->getDispatchedMessages();
        self::assertCount(1, $messages);
        // A JSON string context is decoded
        self::assertSame(['key' => 'value'], $messages[0]->context);
    }

    public function testUnknownProcess(): void
    {
        $this->execute(['code' => 'unknown']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->getDispatchedMessages());
    }

    public function testInvalidToken(): void
    {
        $this->client->request('POST', '/http/process/execute', ['code' => 'test.process'], server: ['HTTP_AUTHORIZATION' => 'Bearer invalid']);

        self::assertResponseStatusCodeSame(401);
        self::assertSame([], $this->getDispatchedMessages());
    }

    public function testMissingToken(): void
    {
        $this->client->request('POST', '/http/process/execute', ['code' => 'test.process']);

        self::assertResponseStatusCodeSame(401);
        self::assertSame([], $this->getDispatchedMessages());
    }

    public function testTokenGeneratedInTheUi(): void
    {
        $admin = $this->login(['ROLE_ADMIN']);
        $this->client->request('GET', '/process/user/'.$admin->getId().'/generate-token');
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        $token = $this->getGeneratedToken($crawler->filter('.alert-success')->text());
        $this->getEntityManager()->clear();
        $user = $this->getEntityManager()->find(User::class, $admin->getId());
        self::assertInstanceOf(User::class, $user);
        self::assertSame((new Pbkdf2PasswordHasher())->hash($token), $user->getToken());

        $this->client->restart();
        $this->client->disableReboot();
        $this->client->request('POST', '/http/process/execute', ['code' => 'test.process'], server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertResponseIsSuccessful();
    }

    public function testSynchronousExecutionWithJsonContext(): void
    {
        $this->client->request(
            'POST',
            '/http/process/execute',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN, 'CONTENT_TYPE' => 'application/json'],
            content: json_encode(['code' => 'test.process', 'context' => '{"key":"value"}', 'queue' => false], \JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        self::assertSame('"Process has been proceed well."', $this->client->getResponse()->getContent());
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function execute(array $parameters): void
    {
        $this->client->request('POST', '/http/process/execute', $parameters, server: ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN]);
    }
}
