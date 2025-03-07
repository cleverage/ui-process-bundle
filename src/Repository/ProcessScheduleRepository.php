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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<ProcessSchedule>
 *
 * @method ProcessSchedule|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProcessSchedule|null findOneBy(mixed[] $criteria, string[] $orderBy = null)
 * @method ProcessSchedule[]    findAll()
 * @method ProcessSchedule[]    findBy(mixed[] $criteria, string[] $orderBy = null, $limit = null, $offset = null)
 */
class ProcessScheduleRepository extends EntityRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, $em->getClassMetadata(ProcessSchedule::class));
    }
}
