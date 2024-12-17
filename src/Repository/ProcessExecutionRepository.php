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

use CleverAge\UiProcessBundle\Entity\LogRecord;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<ProcessExecution>
 *
 * @method ProcessExecution|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProcessExecution|null findOneBy(mixed[] $criteria, string[] $orderBy = null)
 * @method ProcessExecution[]    findAll()
 * @method ProcessExecution[]    findBy(mixed[] $criteria, string[] $orderBy = null, $limit = null, $offset = null)
 */
class ProcessExecutionRepository extends EntityRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, $em->getClassMetadata(ProcessExecution::class));
    }

    public function save(ProcessExecution $processExecution): void
    {
        $this->getEntityManager()->persist($processExecution);
        $this->getEntityManager()->flush();
    }

    public function getLastProcessExecution(string $code): ?ProcessExecution
    {
        $qb = $this->createQueryBuilder('pe');

        return $qb->select('pe')
            ->where($qb->expr()->eq('pe.code', $qb->expr()->literal($code)))
            ->orderBy('pe.startDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasLogs(ProcessExecution $processExecution): bool
    {
        $qb = $this->createQueryBuilder('pe')
            ->select('count(lr.id)')
            ->join(LogRecord::class, 'lr', 'WITH', 'lr.processExecution = pe')
            ->where('pe.id = :id')
            ->setParameter('id', $processExecution->getId()
            );

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
