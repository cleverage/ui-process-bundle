<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\EventListener;

use CleverAge\ProcessUiBundle\Entity\ProcessSchedule;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsEntityListener(event: Events::postUpdate, method: 'restartWorkers', entity: ProcessSchedule::class)]
#[AsEntityListener(event: Events::postPersist, method: 'restartWorkers', entity: ProcessSchedule::class)]
#[AsEntityListener(event: Events::postRemove, method: 'restartWorkers', entity: ProcessSchedule::class)]
readonly class SchedulerStopWorkers
{
    public function __construct(private KernelInterface $kernel)
    {
    }

    public function restartWorkers(ProcessSchedule $schedule, LifecycleEventArgs $eventArgs): void
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $input = new ArrayInput(['command' => 'messenger:stop-workers']);
        $output = new NullOutput();
        $application->run($input, $output);
    }
}
