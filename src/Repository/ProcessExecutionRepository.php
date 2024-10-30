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

namespace CleverAge\ProcessUiBundle\Repository;

use CleverAge\ProcessUiBundle\Entity\ProcessExecution;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<ProcessExecution>
 */
class ProcessExecutionRepository extends EntityRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, $em->getClassMetadata(ProcessExecution::class));
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
