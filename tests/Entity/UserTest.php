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

namespace CleverAge\UiProcessBundle\Tests\Entity;

use CleverAge\UiProcessBundle\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(User::class)]
class UserTest extends TestCase
{
    public function testDefaults(): void
    {
        $user = new User();

        self::assertNull($user->getId());
        self::assertNull($user->getFirstname());
        self::assertNull($user->getLastname());
        self::assertNull($user->getPassword());
        self::assertNull($user->getTimezone());
        self::assertNull($user->getLocale());
        self::assertNull($user->getToken());
        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testGettersAndSetters(): void
    {
        $user = new User();

        self::assertSame($user, $user->setEmail('admin@example.com'));
        self::assertSame($user, $user->setFirstname('John'));
        self::assertSame($user, $user->setLastname('Doe'));
        self::assertSame($user, $user->setPassword('hashed'));
        self::assertSame($user, $user->setTimezone('Europe/Paris'));
        self::assertSame($user, $user->setLocale('fr'));
        self::assertSame($user, $user->setToken('token'));
        self::assertSame($user, $user->setRoles(['ROLE_ADMIN']));

        self::assertSame('admin@example.com', $user->getEmail());
        self::assertSame('admin@example.com', $user->getUserIdentifier());
        self::assertSame('admin@example.com', $user->getUsername());
        self::assertSame('John', $user->getFirstname());
        self::assertSame('Doe', $user->getLastname());
        self::assertSame('hashed', $user->getPassword());
        self::assertSame('Europe/Paris', $user->getTimezone());
        self::assertSame('fr', $user->getLocale());
        self::assertSame('token', $user->getToken());
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $user->getRoles());

        $user->eraseCredentials();
        self::assertSame('hashed', $user->getPassword());

        $user->setFirstname(null)->setLastname(null)->setTimezone(null)->setLocale(null)->setToken(null);
        self::assertNull($user->getFirstname());
        self::assertNull($user->getLastname());
        self::assertNull($user->getTimezone());
        self::assertNull($user->getLocale());
        self::assertNull($user->getToken());
    }

    public function testRoleUserIsNotDuplicated(): void
    {
        // As stored by UserCreateCommand
        $user = (new User())->setRoles(['ROLE_USER', 'ROLE_ADMIN']);

        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $user->getRoles());
    }

    public function testUserIdentifierRequiresAnEmail(): void
    {
        $user = (new User())->setEmail('');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The User class must have an email.');

        $user->getUserIdentifier();
    }
}
