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

use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\ProcessUiBundle\Entity\Process;
use CleverAge\ProcessUiBundle\Manager\ProcessUiConfigurationManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Process>
 */
class ProcessRepository extends EntityRepository
{
    public function __construct(
        EntityManagerInterface $em,
        private readonly ProcessUiConfigurationManager $processUiConfigurationManager,
        private readonly ProcessConfigurationRegistry $processConfigurationRegistry,
    ) {
        parent::__construct($em, $em->getClassMetadata(Process::class));
    }

    public function sync(): void
    {
        // Create missing process into database
        $codes = [];
        foreach ($this->processConfigurationRegistry->getProcessConfigurations() as $configuration) {
            $process = $this->findOneBy(['processCode' => $configuration->getCode()]);
            $codes[] = $configuration->getCode();
            if (null === $process) {
                $process = new Process(
                    $configuration->getCode(),
                    $this->processUiConfigurationManager->getSource($configuration->getCode()),
                    $this->processUiConfigurationManager->getTarget($configuration->getCode()),
                );
                $this->getEntityManager()->persist($process);
            }
        }
        $this->getEntityManager()->flush();

        // Delete process in database if not into configuration registry
        $qb = $this->createQueryBuilder('p');
        $qb->delete();
        $qb->where($qb->expr()->notIn('p.processCode', $codes));
        $qb->getQuery()->execute();
    }
}
