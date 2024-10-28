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

namespace CleverAge\ProcessUiBundle\EventSubscriber\Crud;

use CleverAge\ProcessUiBundle\Entity\Process;
use CleverAge\ProcessUiBundle\Repository\ProcessRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProcessCrudListener implements EventSubscriberInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
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
