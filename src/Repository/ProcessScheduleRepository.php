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

namespace CleverAge\UiProcessBundle\Repository;

use CleverAge\UiProcessBundle\Entity\ProcessScheduleInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * @template T of ProcessScheduleInterface
 *
 * @template-extends EntityRepository<ProcessScheduleInterface>
 */
class ProcessScheduleRepository extends EntityRepository implements ProcessScheduleRepositoryInterface
{
    /**
     * @param class-string<ProcessScheduleInterface> $className
     */
    public function __construct(EntityManagerInterface $em, string $className)
    {
        parent::__construct($em, $em->getClassMetadata($className));
    }
}
