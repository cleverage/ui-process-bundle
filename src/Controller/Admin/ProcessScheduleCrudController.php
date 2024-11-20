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

namespace CleverAge\UiProcessBundle\Controller\Admin;

use CleverAge\ProcessBundle\Configuration\ProcessConfiguration;
use CleverAge\UiProcessBundle\Admin\Field\EnumField;
use CleverAge\UiProcessBundle\Entity\ProcessSchedule;
use CleverAge\UiProcessBundle\Entity\ProcessScheduleType;
use CleverAge\UiProcessBundle\Form\Type\ProcessContextType;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Scheduler\Trigger\CronExpressionTrigger;

class ProcessScheduleCrudController extends AbstractCrudController
{
    public function __construct(private readonly ProcessConfigurationsManager $processConfigurationsManager)
    {
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setPageTitle('index', 'Scheduler')
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $action) => $action->setIcon('fa fa-plus')
                ->setLabel(false)
                ->addCssClass(''))->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => $action->setIcon('fa fa-edit')
                ->setLabel(false)
                ->addCssClass('text-warning'))->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => $action->setIcon('fa fa-trash-o')
                ->setLabel(false)
                ->addCssClass(''))->update(Crud::PAGE_INDEX, Action::BATCH_DELETE, fn (Action $action) => $action->setLabel('Delete')
                ->addCssClass(''));
    }

    public static function getEntityFqcn(): string
    {
        return ProcessSchedule::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $choices = array_map(fn (ProcessConfiguration $configuration) => [$configuration->getCode()], $this->processConfigurationsManager->getPublicProcesses());

        return [
            FormField::addTab('General'),
            TextField::new('process')
                ->setFormType(ChoiceType::class)
                ->setFormTypeOption('choices', array_combine(array_keys($choices), array_keys($choices))),
            EnumField::new('type')
                ->setFormType(EnumType::class)
                ->setFormTypeOption('class', ProcessScheduleType::class),
            TextField::new('expression'),
            DateTimeField::new('nextExecution')
                ->setFormTypeOption('mapped', false)
                ->setVirtual(true)
                ->hideOnForm()
                ->hideOnDetail()
                ->formatValue(fn ($value, ProcessSchedule $entity) => ProcessScheduleType::CRON === $entity->getType()
                    ? CronExpressionTrigger::fromSpec($entity->getExpression() ?? '')
                        ->getNextRunDate(new \DateTimeImmutable())
                        ?->format('c')
                    : null),
            FormField::addTab('Input'),
            TextField::new('input'),
            FormField::addTab('Context'),
            ArrayField::new('context')
                ->setFormTypeOption('entry_type', ProcessContextType::class)
                ->hideOnIndex()
                ->setFormTypeOption('entry_options.label', 'Context (key/value)')
                ->setFormTypeOption('label', '')
                ->setFormTypeOption('required', false),
        ];
    }

    public function index(AdminContext $context): KeyValueStore|RedirectResponse|Response
    {
        if (false === $this->schedulerIsRunning()) {
            $this->addFlash('warning', 'To run scheduler, ensure "bin/console messenger:consume scheduler_cron" console is alive. See https://symfony.com/doc/current/messenger.html#supervisor-configuration.');
        }

        return parent::index($context);
    }

    private function schedulerIsRunning(): bool
    {
        $process = Process::fromShellCommandline('ps -faux');
        $process->run();
        $out = $process->getOutput();

        return str_contains($out, 'scheduler_cron');
    }
}
