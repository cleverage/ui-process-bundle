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
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends EntityRepository<ProcessExecution>
 *
 * @method ProcessExecution|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProcessExecution|null findOneBy(mixed[] $criteria, string[] $orderBy = null)
 * @method ProcessExecution[]    findAll()
 * @method ProcessExecution[]    findBy(mixed[] $criteria, string[] $orderBy = null, $limit = null, $offset = null)
 *
 * @final since 3.3
 */
class ProcessExecutionRepository extends EntityRepository
{
    public function __construct(EntityManagerInterface $em, private readonly ?ManagerRegistry $registry = null)
    {
        parent::__construct($em, $em->getClassMetadata(ProcessExecution::class));
    }

    public function save(ProcessExecution $processExecution): void
    {
        $this->getEntityManager()->persist($processExecution);
        $this->getEntityManager()->flush();
    }

    /**
     * The process execution managed by the entity manager: the given one, or the persisted one, updated with the state
     * of the given one, when it has been detached (e.g. the entity manager, shared with the process tasks, has been
     * cleared). Persisting a detached process execution would insert it again.
     *
     * A closed entity manager (e.g. closed by Doctrine after an error during a flush of a process task) is reset
     * first: the process execution could not be saved anymore.
     */
    public function getManaged(ProcessExecution $processExecution): ProcessExecution
    {
        if (!$this->getEntityManager()->isOpen()) {
            // Reset in place (lazy service): the services holding the entity manager get an open one
            $this->registry?->resetManager();
        }
        $id = $processExecution->getId();
        if (null === $id || $this->getEntityManager()->contains($processExecution)) {
            return $processExecution;
        }
        $managed = $this->find($id);
        if (!$managed instanceof ProcessExecution) {
            return $processExecution;
        }
        $managed->status = $processExecution->status;
        $managed->endDate = $processExecution->endDate;
        foreach ($processExecution->getReport() as $key => $value) {
            $managed->addReport($key, $value);
        }
        $managed->setContext($processExecution->getContext() ?? []);

        return $managed;
    }

    public function getLastProcessExecution(string $code): ?ProcessExecution
    {
        $qb = $this->createQueryBuilder('pe');
        /** @var ProcessExecution|null $processExecution */
        $processExecution = $qb->select('pe')
            ->where($qb->expr()->eq('pe.code', $qb->expr()->literal($code)))
            ->orderBy('pe.startDate', $this->descending())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $processExecution;
    }

    public function hasLogs(ProcessExecution $processExecution): bool
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('count(lr.id)')
            ->from(LogRecord::class, 'lr')
            ->where('lr.processExecution = :processExecution')
            ->setParameter('processExecution', $processExecution->getId());

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * doctrine/orm 3.7 deprecates the string sort directions in favor of the \SortDirection enum (PHP 8.6, polyfilled),
     * which the previous versions do not accept (the enum may exist with them, e.g. on PHP 8.6).
     */
    private function descending(): \SortDirection|string
    {
        static $acceptsSortDirection = null;
        $acceptsSortDirection ??= str_contains((string) (new \ReflectionMethod(QueryBuilder::class, 'orderBy'))->getParameters()[1]->getType(), 'SortDirection');

        return $acceptsSortDirection ? \SortDirection::Descending : 'DESC';
    }
}
