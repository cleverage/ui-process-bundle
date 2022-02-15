<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\EventSubscriber\Crud;

use CleverAge\ProcessUiBundle\Entity\Process;
use CleverAge\ProcessUiBundle\Repository\ProcessRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProcessCrudListener implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @return array <string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [BeforeCrudActionEvent::class => 'syncProcessIntoDatabase'];
    }

    public function syncProcessIntoDatabase(BeforeCrudActionEvent $event): void
    {
        if (Process::class === $event->getAdminContext()?->getEntity()->getFqcn()) {
            /** @var ProcessRepository $repository */
            $repository = $this->entityManager->getRepository(Process::class);
            $repository->sync();
        }
    }
}
