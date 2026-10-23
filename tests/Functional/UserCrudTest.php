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
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\CheckDoctrineLazyGhostObjectsPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\LoginThrottlingPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\UploadDirectoryParameterPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\UploadedFileSubscriberPass;
use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
use CleverAge\UiProcessBundle\Entity\User;
use CleverAge\UiProcessBundle\EventSubscriber\ProcessEventSubscriber;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use CleverAge\UiProcessBundle\Routing\DeprecatedEasyAdminRouteAliasLoader;
use CleverAge\UiProcessBundle\Security\HttpProcessExecutionAuthenticator;
use CleverAge\UiProcessBundle\Twig\Extension\LogLevelExtension;
use CleverAge\UiProcessBundle\Twig\Extension\MD5Extension;
use CleverAge\UiProcessBundle\Twig\Extension\ProcessExecutionExtension;
use CleverAge\UiProcessBundle\Twig\Extension\ProcessExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\PasswordHasher\Hasher\Pbkdf2PasswordHasher;

#[CoversClass(UserCrudController::class)]
#[UsesClass(ProcessDashboardController::class)]
#[UsesClass(User::class)]
#[UsesClass(HttpProcessExecutionAuthenticator::class)]
#[UsesClass(LogLevelExtension::class)]
#[UsesClass(MD5Extension::class)]
#[UsesClass(ProcessExecutionExtension::class)]
#[UsesClass(ProcessExtension::class)]
#[UsesClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(CheckDoctrineLazyGhostObjectsPass::class)]
#[UsesClass(UploadDirectoryParameterPass::class)]
#[UsesClass(LoginThrottlingPass::class)]
#[UsesClass(UploadedFileSubscriberPass::class)]
#[UsesClass(LogRecordCrudController::class)]
#[UsesClass(ProcessExecutionCrudController::class)]
#[UsesClass(ProcessScheduleCrudController::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(DeprecatedEasyAdminRouteAliasLoader::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProcessEventSubscriber::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
#[UsesClass(ProcessExecutionRepository::class)]
#[UsesClass(DoctrineProcessHandler::class)]
#[UsesClass(ProcessHandler::class)]
class UserCrudTest extends FunctionalTestCase
{
    public function testIndex(): void
    {
        $this->login(['ROLE_ADMIN']);
        $this->createUser('other@example.com');

        $crawler = $this->client->request('GET', '/process/user');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('other@example.com', $crawler->filter('table')->text());
    }

    public function testUsersAreReservedToAdmins(): void
    {
        $this->login();
        $other = $this->createUser('other@example.com');

        $crawler = $this->client->request('GET', '/process/user');
        // The users are filtered by the entity permission
        self::assertStringNotContainsString('other@example.com', $crawler->text());

        $this->client->request('GET', '/process/user/'.$other->getId().'/edit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testCreate(): void
    {
        $this->login(['ROLE_ADMIN']);

        $crawler = $this->client->request('GET', '/process/user/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Create')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();
        $values['User']['email'] = 'new@example.com';
        $values['User']['password'] = ['first' => 'new password', 'second' => 'new password'];
        $values['User']['firstname'] = 'Jane';
        $values['User']['roles'] = ['ROLE_ADMIN'];
        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseRedirects();
        $user = $this->getEntityManager()->getRepository(User::class)->findOneBy(['email' => 'new@example.com']);
        self::assertInstanceOf(User::class, $user);
        self::assertSame('Jane', $user->getFirstname());
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        // Hashed with the hasher of the test application (plaintext)
        self::assertSame('new password', $user->getPassword());
    }

    public function testPasswordsMustMatch(): void
    {
        $this->login(['ROLE_ADMIN']);

        $crawler = $this->client->request('GET', '/process/user/new');
        $form = $crawler->selectButton('Create')->form();
        /** @var array<string, array<string, mixed>> $values */
        $values = $form->getPhpValues();
        $values['User']['email'] = 'new@example.com';
        $values['User']['password'] = ['first' => 'password', 'second' => 'other'];
        $this->client->request($form->getMethod(), $form->getUri(), $values);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->getEntityManager()->getRepository(User::class)->findOneBy(['email' => 'new@example.com']));
    }

    public function testEntityFqcn(): void
    {
        self::assertSame(User::class, UserCrudController::getEntityFqcn());
    }

    public function testGenerateToken(): void
    {
        $admin = $this->login(['ROLE_ADMIN']);

        $crawler = $this->client->request('GET', '/process/user/'.$admin->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('a[href*="generate-token"]'));

        $this->client->request('GET', '/process/user/'.$admin->getId().'/generate-token');
        self::assertResponseRedirects();
        self::assertStringContainsString('/process/user/'.$admin->getId().'/edit', (string) $this->client->getResponse()->headers->get('Location'));
        $crawler = $this->client->followRedirect();
        $token = $this->getGeneratedToken($crawler->filter('.alert-success')->text());
        $this->getEntityManager()->clear();
        // Only the hash of the token is stored
        self::assertSame((new Pbkdf2PasswordHasher())->hash($token), $this->getEntityManager()->find(User::class, $admin->getId())?->getToken());
    }

    /**
     * The users are reserved to the admins: the custom action too (it was only protected because EasyAdmin does not
     * load the entity, a 500).
     */
    public function testGenerateTokenIsReservedToAdmins(): void
    {
        $user = $this->login();
        $other = $this->createUser('other@example.com');

        $this->client->request('GET', '/process/user/'.$other->getId().'/generate-token');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/process/user/'.$user->getId().'/generate-token');
        self::assertResponseStatusCodeSame(403);

        $this->getEntityManager()->clear();
        self::assertNull($this->getEntityManager()->find(User::class, $other->getId())?->getToken());
    }

    public function testGenerateTokenOfAnUnknownUser(): void
    {
        $this->login(['ROLE_ADMIN']);

        $this->client->request('GET', '/process/user/999999/generate-token');
        self::assertResponseStatusCodeSame(404);
    }
}
