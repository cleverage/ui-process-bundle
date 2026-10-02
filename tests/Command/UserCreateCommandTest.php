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

namespace CleverAge\UiProcessBundle\Tests\Command;

use CleverAge\UiProcessBundle\Command\UserCreateCommand;
use CleverAge\UiProcessBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validation;

#[CoversClass(UserCreateCommand::class)]
#[UsesClass(User::class)]
class UserCreateCommandTest extends TestCase
{
    private UserPasswordHasherInterface $passwordHasher;

    /** @var User[] */
    private array $persisted = [];

    protected function setUp(): void
    {
        $this->passwordHasher = new UserPasswordHasher(
            new PasswordHasherFactory([User::class => ['algorithm' => 'bcrypt', 'cost' => 4]])
        );
        $this->persisted = [];
    }

    public function testDefinition(): void
    {
        $command = $this->createCommand($this->createStub(EntityManagerInterface::class));

        self::assertSame('cleverage:ui-process:user-create', $command->getName());
        self::assertSame('Command to create a new admin into database for ui process.', $command->getDescription());
        self::assertFalse($command->getDefinition()->getArgument('email')->isRequired());
        self::assertFalse($command->getDefinition()->getArgument('password')->isRequired());
    }

    public function testCreateUserFromArguments(): void
    {
        $tester = new CommandTester($this->createCommand($this->createEntityManager()));

        $exitCode = $tester->execute(['email' => 'admin@example.com', 'password' => 'my-password']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('User created.', $tester->getDisplay());
        self::assertStringNotContainsString('Please enter', $tester->getDisplay());
        $this->assertUserCreated('admin@example.com', 'my-password');
    }

    public function testCreateUserInteractively(): void
    {
        $tester = new CommandTester($this->createCommand($this->createEntityManager()));
        $tester->setInputs(['admin@example.com', 'my-password']);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Please enter the email.', $display);
        self::assertStringContainsString('Please enter the user password.', $display);
        self::assertStringNotContainsString('my-password', $display);
        self::assertStringContainsString('User created.', $display);
        $this->assertUserCreated('admin@example.com', 'my-password');
    }

    public function testInvalidAnswersAreAskedAgain(): void
    {
        $tester = new CommandTester($this->createCommand($this->createEntityManager()));
        $tester->setInputs(['not-an-email', 'admin@example.com', '', 'short', 'long-enough-password']);

        $exitCode = $tester->execute([], ['decorated' => false]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        self::assertSame(2, substr_count($display, 'Please enter the email.'));
        self::assertSame(3, substr_count($display, 'Please enter the user password.'));
        self::assertStringContainsString('This value is not a valid email address.', $display);
        self::assertStringContainsString('This value should not be blank.', $display);
        self::assertStringContainsString('This value is too short. It should have 8 characters or more.', $display);
        $this->assertUserCreated('admin@example.com', 'long-enough-password');
    }

    public function testOnlyMissingArgumentsAreAsked(): void
    {
        $tester = new CommandTester($this->createCommand($this->createEntityManager()));
        $tester->setInputs(['my-password']);

        $exitCode = $tester->execute(['email' => 'admin@example.com']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringNotContainsString('Please enter the email.', $tester->getDisplay());
        self::assertStringContainsString('Please enter the user password.', $tester->getDisplay());
        $this->assertUserCreated('admin@example.com', 'my-password');
    }

    public function testArgumentsAreNotValidated(): void
    {
        // Validation constraints only apply to the answers of the interactive questions
        $tester = new CommandTester($this->createCommand($this->createEntityManager()));

        $exitCode = $tester->execute(['email' => 'not-an-email', 'password' => 'short']);

        self::assertSame(Command::SUCCESS, $exitCode);
        $this->assertUserCreated('not-an-email', 'short');
    }

    private function createCommand(EntityManagerInterface $entityManager): Command
    {
        $application = new Application();
        $application->addCommands([new UserCreateCommand(
            Validation::createValidator(),
            $this->passwordHasher,
            $entityManager,
        )]);

        return $application->find('cleverage:ui-process:user-create');
    }

    private function createEntityManager(): EntityManagerInterface
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')
            ->with(self::isInstanceOf(User::class))
            ->willReturnCallback(function (object $user): void {
                \assert($user instanceof User);
                $this->persisted[] = $user;
            });
        $entityManager->expects(self::once())->method('flush');

        return $entityManager;
    }

    private function assertUserCreated(string $email, string $plainPassword): void
    {
        self::assertCount(1, $this->persisted);
        $user = $this->persisted[0];
        self::assertSame($email, $user->getEmail());
        // The command stores ROLE_USER and ROLE_ADMIN, User::getRoles() always adds ROLE_USER
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], array_values(array_unique($user->getRoles())));
        self::assertNotSame($plainPassword, $user->getPassword());
        self::assertTrue($this->passwordHasher->isPasswordValid($user, $plainPassword));
    }
}
