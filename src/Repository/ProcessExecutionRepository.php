<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Repository;

use CleverAge\ProcessUiBundle\Entity\ProcessExecution;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProcessExecution>
 */
class ProcessExecutionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProcessExecution::class);
    }

    /**
     * @return array <string, string>
     */
    public function getProcessCodeChoices(): array
    {
        $choices = [];
        $qb = $this->createQueryBuilder('pe');
        $qb->distinct(true);
        $qb->select('pe.processCode');
        foreach ($qb->getQuery()->getArrayResult() as $result) {
            $choices[(string) $result['processCode']] = (string) $result['processCode'];
        }

        return $choices;
    }

    /**
     * @return array <string, string>
     */
    public function getSourceChoices(): array
    {
        $choices = [];
        $qb = $this->createQueryBuilder('pe');
        $qb->distinct(true);
        $qb->select('pe.source');
        foreach ($qb->getQuery()->getArrayResult() as $result) {
            $choices[(string) $result['source']] = (string) $result['source'];
        }

        return $choices;
    }

    /**
     * @return array <string, string>
     */
    public function getTargetChoices(): array
    {
        $choices = [];
        $qb = $this->createQueryBuilder('pe');
        $qb->distinct(true);
        $qb->select('pe.target');
        foreach ($qb->getQuery()->getArrayResult() as $result) {
            $choices[(string) $result['target']] = (string) $result['target'];
        }

        return $choices;
    }

    public function deleteBefore(\DateTime $dateTime): void
    {
        $qb = $this->createQueryBuilder('pe');
        $qb->delete();
        $qb->where('pe.startDate < :date');
        $qb->setParameter('date', $dateTime);

        $qb->getQuery()->execute();
    }
}
