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

use CleverAge\UiProcessBundle\Entity\User;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use CleverAge\UiProcessBundle\Tests\App\TestKernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Requests on the test application (see TestKernel), with an empty database created for each test.
 */
abstract class FunctionalTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        // Without debug: less memory (the whole test suite runs with the default 128M memory_limit)
        $this->client = static::createClient(['debug' => false]);
        $this->client->disableReboot();

        $entityManager = $this->getEntityManager();
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    /**
     * @param list<string> $roles
     */
    protected function createUser(string $email = 'user@example.com', array $roles = [], string $password = 'password'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setPassword($password);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();

        return $user;
    }

    /**
     * Messages dispatched to the execute_process transport (in memory in the test application).
     *
     * @return list<ProcessExecuteMessage>
     */
    protected function getDispatchedMessages(): array
    {
        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.execute_process');

        return array_values(array_map(static function (Envelope $envelope): ProcessExecuteMessage {
            $message = $envelope->getMessage();
            self::assertInstanceOf(ProcessExecuteMessage::class, $message);

            return $message;
        }, $transport->getSent()));
    }

    /**
     * @param list<string> $roles
     */
    protected function login(array $roles = []): User
    {
        $user = $this->createUser('user@example.com', $roles);
        $this->client->loginUser($user, 'main');

        return $user;
    }

    /**
     * Submits the "generateToken" action of the edit page of the user (POST form with a CSRF token), returns the new
     * API token displayed in the flash message.
     */
    protected function generateTokenInTheUi(User $user): string
    {
        $crawler = $this->client->request('GET', '/process/user/'.$user->getId().'/edit');
        $action = $crawler->filter('form[action*="generate-token"]');
        self::assertCount(1, $action);
        $this->client->request('POST', (string) $action->attr('action'));
        self::assertResponseRedirects();
        self::assertStringContainsString('/process/user/'.$user->getId().'/edit', (string) $this->client->getResponse()->headers->get('Location'));
        $crawler = $this->client->followRedirect();

        return $this->getGeneratedToken($crawler->filter('.alert-success')->text());
    }

    /**
     * Token displayed in the flash message of the "generateToken" action.
     */
    protected function getGeneratedToken(string $flashMessage): string
    {
        if (1 !== preg_match('/New token generated (\w+)/', $flashMessage, $matches)) {
            self::fail('No token in: '.$flashMessage);
        }

        return $matches[1];
    }
}
