<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Repository;

use CleverAge\ProcessUiBundle\Entity\ProcessSchedule;
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
