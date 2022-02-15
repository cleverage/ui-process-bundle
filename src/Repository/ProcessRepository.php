<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Repository;

use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\ProcessUiBundle\Entity\Process;
use CleverAge\ProcessUiBundle\Manager\ProcessUiConfigurationManager;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Process>
 */
class ProcessRepository extends ServiceEntityRepository
{
    private ProcessUiConfigurationManager $processUiConfigurationManager;
    private ProcessConfigurationRegistry $processConfigurationRegistry;

    /**
     * @required
     */
    public function setProcessUiConfigurationManager(ProcessUiConfigurationManager $processUiConfigurationManager): void
    {
        $this->processUiConfigurationManager = $processUiConfigurationManager;
    }

    /**
     * @required
     */
    public function setProcessConfigurationRegistry(ProcessConfigurationRegistry $processConfigurationRegistry): void
    {
        $this->processConfigurationRegistry = $processConfigurationRegistry;
    }

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Process::class);
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
