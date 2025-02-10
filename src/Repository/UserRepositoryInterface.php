<?php

/*
 * This file is part of the CleverAge/UiProcessBundle package.
 *
 * Copyright (c) Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\UiProcessBundle\Repository;

use CleverAge\UiProcessBundle\Entity\UserInterface;
use Doctrine\Persistence\ObjectRepository;

/**
 * @extends ObjectRepository<UserInterface>
 */
interface UserRepositoryInterface extends ObjectRepository
{
}
