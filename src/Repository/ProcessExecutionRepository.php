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

use CleverAge\UiProcessBundle\Entity\ProcessExecutionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * @template T of ProcessExecutionInterface
 *
 * @template-extends EntityRepository<ProcessExecutionInterface>
 */
class ProcessExecutionRepository extends EntityRepository implements ProcessExecutionRepositoryInterface
{
    /**
     * @param class-string<ProcessExecutionInterface> $className
     */
    public function __construct(EntityManagerInterface $em, string $className, private readonly string $logRecordClassName)
    {
        parent::__construct($em, $em->getClassMetadata($className));
    }

    public function save(ProcessExecutionInterface $processExecution): void
    {
        $this->getEntityManager()->persist($processExecution);
        $this->getEntityManager()->flush();
    }

    public function getLastProcessExecution(string $code): ?ProcessExecutionInterface
    {
        $qb = $this->createQueryBuilder('pe');

        return $qb->select('pe')
            ->where($qb->expr()->eq('pe.code', $qb->expr()->literal($code)))
            ->orderBy('pe.startDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasLogs(ProcessExecutionInterface $processExecution): bool
    {
        $qb = $this->createQueryBuilder('pe')
            ->select('count(lr.id)')
            ->join($this->logRecordClassName, 'lr', 'WITH', 'lr.processExecution = pe')
            ->where('pe.id = :id')
            ->setParameter('id', $processExecution->getId()
            );

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
