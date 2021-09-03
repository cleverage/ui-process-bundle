<?php
namespace CleverAge\ProcessUiBundle\Repository;

use CleverAge\ProcessUiBundle\Entity\ProcessExecution;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use function Doctrine\ORM\QueryBuilder;

class ProcessExecutionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProcessExecution::class);
    }

    public function getProcessCodeChoices(): array
    {
        $choices = [];
        $qb = $this->createQueryBuilder('pe');
        $qb->distinct(true);
        $qb->select('pe.processCode');
        foreach ($qb->getQuery()->getArrayResult() as $result) {
            $choices[$result['processCode']] = $result['processCode'];
        }

        return $choices;
    }

    public function getSourceChoices(): array
    {
        $choices = [];
        $qb = $this->createQueryBuilder('pe');
        $qb->distinct(true);
        $qb->select('pe.source');
        foreach ($qb->getQuery()->getArrayResult() as $result) {
            $choices[$result['source']] = $result['source'];
        }

        return $choices;
    }

    public function getTargetChoices(): array
    {
        $choices = [];
        $qb = $this->createQueryBuilder('pe');
        $qb->distinct(true);
        $qb->select('pe.target');
        foreach ($qb->getQuery()->getArrayResult() as $result) {
            $choices[$result['target']] = $result['target'];
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