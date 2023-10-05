<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Repository;

use CleverAge\ProcessUiBundle\Entity\ProcessExecution;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProcessExecution>
 *
 * @method ProcessExecution|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProcessExecution|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProcessExecution[]    findAll()
 * @method ProcessExecution[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProcessExecutionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProcessExecution::class);
    }

    public function save(ProcessExecution $processExecution): void
    {
        $this->_em->persist($processExecution);
        $this->_em->flush();
    }

    public function getLastProcessExecution(string $code): ?ProcessExecution
    {
        $qb = $this->_em->createQueryBuilder();

        return $qb->select('pe')
            ->from(ProcessExecution::class, 'pe')
            ->where($qb->expr()->eq('pe.code', $qb->expr()->literal($code)))
            ->orderBy('pe.startDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
