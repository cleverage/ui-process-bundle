<?php

/*
 * This file is part of the CleverAge/UiProcessBundle package.
 *
 * Copyright (c) Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\UiProcessBundle\Entity;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface as CoreUserInterface;

interface UserInterface extends CoreUserInterface, PasswordAuthenticatedUserInterface
{
    public function getId(): ?int;

    public function getEmail(): ?string;

    public function setEmail(string $email): static;

    public function getUsername(): string;

    public function getFirstname(): ?string;

    public function setFirstname(?string $firstname): static;

    public function getLastname(): ?string;

    public function setLastname(?string $lastname): static;

    public function getRoles(): array;

    /**
     * @param array<int, string> $roles
     */
    public function setRoles(array $roles): static;

    public function getPassword(): ?string;

    public function setPassword(string $password): static;

    public function getTimezone(): ?string;

    public function setTimezone(?string $timezone): static;

    public function getLocale(): ?string;

    public function setLocale(?string $locale): static;

    public function getToken(): ?string;

    public function setToken(?string $token): static;

    public function eraseCredentials(): void;

    public function getUserIdentifier(): string;
}
