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

use CleverAge\UiProcessBundle\Entity\ProcessSchedule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProcessSchedule>
 *
 * @method ProcessSchedule|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProcessSchedule|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProcessSchedule[]    findAll()
 * @method ProcessSchedule[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProcessScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProcessSchedule::class);
    }
}
