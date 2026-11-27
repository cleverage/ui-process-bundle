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
use CleverAge\UiProcessBundle\Admin\Field\ContextField;
use CleverAge\UiProcessBundle\Admin\Field\EnumField;
use CleverAge\UiProcessBundle\Entity\Enum\ProcessScheduleType;
use CleverAge\UiProcessBundle\Entity\ProcessSchedule;
use CleverAge\UiProcessBundle\Form\Type\ContextFormType;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Asset;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Process\Process;
use Symfony\Component\Scheduler\Trigger\CronExpressionTrigger;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<ProcessSchedule>
 *
 * @internal
 */
#[IsGranted('ROLE_USER')]
class ProcessScheduleCrudController extends AbstractCrudController
{
    /**
     * Id of the row of the context field: replaced by htmx (see context-form.js) when the process changes.
     */
    public const CONTEXT_ROW_ID = 'context-tab-content';

    /**
     * @var array<string, true> the processes whose invalid launch form has already been reported in this request
     */
    private array $reportedInvalidLaunchForms = [];

    public function __construct(private readonly ProcessConfigurationsManager $processConfigurationsManager)
    {
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setPageTitle('index', 'Scheduler')
            ->showEntityActionsInlined();
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $action) => $action->setIcon('fa fa-plus')
                ->setLabel(false)
                ->addCssClass(''))->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $action) => $action->setIcon('fa fa-edit')
                ->setLabel(false)
                ->addCssClass('text-warning'))->update(Crud::PAGE_INDEX, Action::DELETE, static fn (Action $action) => $action->setIcon('fa fa-trash-o')
                ->setLabel(false)
                ->addCssClass(''))->update(Crud::PAGE_INDEX, Action::BATCH_DELETE, static fn (Action $action) => $action->setLabel('Delete')
                ->addCssClass(''));
    }

    public static function getEntityFqcn(): string
    {
        return ProcessSchedule::class;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        $choices = array_map(static fn (ProcessConfiguration $configuration) => [$configuration->getCode()], $this->processConfigurationsManager->getPublicProcesses());

        return [
            FormField::addTab('General'),
            TextField::new('process')
                ->setFormType(ChoiceType::class)
                ->setFormTypeOption('choices', array_combine(array_keys($choices), array_keys($choices)))
                // htmx: when the process changes, the page is requested again and only the row of the context field
                // is swapped (the context form depends on the process, see createNewFormBuilder())
                ->setFormTypeOption('attr', [
                    'hx-get' => $this->getContext()?->getRequest()->getRequestUri() ?? '',
                    'hx-trigger' => 'change',
                    'hx-target' => '#'.self::CONTEXT_ROW_ID,
                    'hx-select' => '#'.self::CONTEXT_ROW_ID,
                    'hx-swap' => 'outerHTML',
                    'hx-push-url' => 'false',
                    // Read by context-form.js: asked before the values entered in the context are replaced
                    'data-context-confirm' => 'The values entered in the context will be replaced by the default values of the process. Continue?',
                ]),
            EnumField::new('type')
                ->setFormType(EnumType::class)
                ->setFormTypeOption('class', ProcessScheduleType::class),
            TextField::new('expression'),
            BooleanField::new('enabled')
                ->setHelp('A disabled schedule is not run.'),
            DateTimeField::new('nextExecution')
                ->setFormTypeOption('mapped', false)
                ->setVirtual(true)
                ->hideOnForm()
                ->hideOnDetail()
                ->formatValue(static fn ($value, ProcessSchedule $entity) => $entity->isEnabled() && ProcessScheduleType::CRON === $entity->getType()
                    ? CronExpressionTrigger::fromSpec($entity->getExpression() ?? '')
                        ->getNextRunDate(new \DateTimeImmutable())
                        ?->format('c')
                    : null),
            FormField::addTab('Input'),
            TextField::new('input'),
            FormField::addTab('Context'),
            // The fields of the form depend on the process: see createNewFormBuilder()
            // Not a generic field: EasyAdmin would guess an ArrayField (JSON column) and its collection options
            ContextField::new('context')
                ->hideOnIndex()
                ->setFormType(ContextFormType::class)
                ->setFormTypeOptions([
                    'as_rows' => true,
                    'process' => null,
                    'label' => false,
                    'required' => false,
                    'row_attr' => ['id' => self::CONTEXT_ROW_ID],
                ])
                ->addJsFiles(Asset::fromEasyAdminAssetPackage('field-collection.js')->onlyOnForms())
                ->setDefaultColumns('col-md-7 col-xxl-6'),
        ];
    }

    /**
     * The scripts are served by AssetMapper (logical paths of the "assets" directory of the bundle). htmx is a classic
     * script (not an ES module), so it is not an importmap entry.
     */
    #[\Override]
    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addJsFile(Asset::new('cleverage/ui-process-bundle/js/htmx.min.js')->onlyOnForms())
            ->addJsFile(Asset::new('cleverage/ui-process-bundle/js/context-form.js')->onlyOnForms());
    }

    /**
     * @param EntityDto<ProcessSchedule>    $entityDto
     * @param AdminContext<ProcessSchedule> $context
     *
     * @return FormBuilderInterface<ProcessSchedule>
     */
    #[\Override]
    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        return $this->addContextListeners(parent::createNewFormBuilder($entityDto, $formOptions, $context), $context->getRequest());
    }

    /**
     * @param EntityDto<ProcessSchedule>    $entityDto
     * @param AdminContext<ProcessSchedule> $context
     *
     * @return FormBuilderInterface<ProcessSchedule>
     */
    #[\Override]
    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        return $this->addContextListeners(parent::createEditFormBuilder($entityDto, $formOptions, $context), $context->getRequest());
    }

    #[\Override]
    public function index(AdminContext $context): KeyValueStore|RedirectResponse|Response
    {
        if (false === $this->schedulerIsRunning()) {
            $this->addFlash('warning', 'To run scheduler, ensure "bin/console messenger:consume scheduler_cron" console is alive. See https://symfony.com/doc/current/messenger.html#supervisor-configuration.');
        }

        return parent::index($context);
    }

    /**
     * The "context" field depends on the process, with the native events of the form: its fields are the ones of the
     * launch form of the process of the schedule (PRE_SET_DATA), or of the submitted one (PRE_SUBMIT). The page
     * requested by htmx when the process is changed (GET request, "HX-Request" header) displays the context of the
     * requested process, with its default values.
     *
     * @param FormBuilderInterface<ProcessSchedule> $builder
     *
     * @return FormBuilderInterface<ProcessSchedule>
     */
    private function addContextListeners(FormBuilderInterface $builder, Request $request): FormBuilderInterface
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($request): void {
            $schedule = $event->getData();
            if (!$schedule instanceof ProcessSchedule || !$event->getForm()->has('context')) {
                return;
            }
            $process = $this->getDisplayedProcess($schedule, $request);
            // Reports an invalid launch form of the process
            $launchForm = $this->getLaunchFormOptions($process);
            if ($request->isMethod('GET') && !$this->usesDefaultContext($schedule, $request) && null !== $schedule->getId() && $process === $schedule->getProcess()) {
                $this->warnAboutUndisplayedContext($schedule, $launchForm);
            }
            $this->replaceContextField($event->getForm(), $process);
        });
        // After the data mapper: the default values are displayed by the form only, the managed schedule is not changed
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($request): void {
            $schedule = $event->getData();
            if (!$schedule instanceof ProcessSchedule || !$event->getForm()->has('context') || !$this->usesDefaultContext($schedule, $request)) {
                return;
            }
            // The default values of the launch form of the process, not normalized: they are read by the fields
            $event->getForm()->get('context')->setData($this->getDefaultContextRows($this->getDisplayedProcess($schedule, $request)));
        });
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            $process = \is_array($data) ? ($data['process'] ?? null) : null;
            if (\is_string($process) && $event->getForm()->has('context')) {
                $this->replaceContextField($event->getForm(), $process);
            }
        });

        return $builder;
    }

    /**
     * The process whose launch form gives the fields of the context: the requested one (htmx), the one of the schedule,
     * or the first choice for a new form.
     */
    private function getDisplayedProcess(ProcessSchedule $schedule, Request $request): ?string
    {
        return $this->getRequestedProcess($request) ?? $schedule->getProcess() ?? $this->getFirstProcessCode();
    }

    /**
     * The process requested by htmx when the process of the form is changed (GET request, "HX-Request" header).
     */
    private function getRequestedProcess(Request $request): ?string
    {
        if (!$request->isMethod('GET') || !$request->headers->has('HX-Request')) {
            return null;
        }
        // Not query->all('ProcessSchedule'): it throws a BadRequestException when the parameter is not an array
        $schedule = $request->query->all()['ProcessSchedule'] ?? null;
        $requested = \is_array($schedule) ? ($schedule['process'] ?? null) : null;

        return \is_string($requested) && '' !== $requested ? $requested : null;
    }

    /**
     * Whether the context displays the default values of the launch form of the process (instead of the stored context):
     * another process is requested by htmx, or a new form has no context yet.
     */
    private function usesDefaultContext(ProcessSchedule $schedule, Request $request): bool
    {
        if (!$request->isMethod('GET')) {
            return false;
        }
        $requested = $this->getRequestedProcess($request);

        return null !== $requested && $requested !== $schedule->getProcess() || null === $schedule->getId() && [] === $schedule->getContext();
    }

    /**
     * The launch form of the process, null when it has none or when it is invalid (then reported once as a warning, and
     * the context is entered as free key/value pairs).
     *
     * @return array{context: array<string, mixed>, allow_extra_context: bool, data?: array{context?: array<string, mixed>}}|null
     */
    private function getLaunchFormOptions(?string $process): ?array
    {
        if (null === $process || 'form' !== ($this->processConfigurationsManager->getUiOptions($process)['ui_launch_mode'] ?? null)) {
            return null;
        }
        try {
            /** @var array{context: array<string, mixed>, allow_extra_context: bool, data?: array{context?: array<string, mixed>}}|null $options */
            $options = $this->processConfigurationsManager->getLaunchFormOptions($process);

            return $options;
        } catch (InvalidOptionsException $e) {
            if (!isset($this->reportedInvalidLaunchForms[$process])) {
                $this->reportedInvalidLaunchForms[$process] = true;
                $this->addFlash('warning', \sprintf('The launch form of the process "%s" is invalid, its context is entered as free key/value pairs: %s', $process, $e->getMessage()));
            }

            return null;
        }
    }

    /**
     * The stored rows that the launch form of the process does not accept are not displayed, and they are removed when
     * the schedule is saved: warn about them.
     *
     * @param array{context: array<string, mixed>, allow_extra_context: bool}|null $launchForm
     */
    private function warnAboutUndisplayedContext(ProcessSchedule $schedule, ?array $launchForm): void
    {
        if (null === $launchForm || $launchForm['allow_extra_context']) {
            return;
        }
        $keys = [];
        foreach ($schedule->getContext() as $row) {
            if (\is_array($row) && isset($row['key']) && \is_scalar($row['key'])) {
                $keys[] = (string) $row['key'];
            }
        }
        $undisplayed = array_values(array_unique(array_diff($keys, array_map(strval(...), array_keys($launchForm['context'])))));
        if ([] !== $undisplayed) {
            $this->addFlash('warning', \sprintf('The context keys "%s" of this schedule are not accepted by the launch form of its process: they are not displayed, and they are removed when the schedule is saved.', implode('", "', $undisplayed)));
        }
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function replaceContextField(FormInterface $form, ?string $process): void
    {
        $config = $form->get('context')->getConfig();
        /** @var \EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto $field */
        $field = $config->getAttribute('ea_field');
        $builder = $form->getConfig()->getFormFactory()->createNamedBuilder('context', ContextFormType::class, null, ['process' => $process, 'auto_initialize' => false] + $field->getFormTypeOptions());
        // Read by EasyAdmin to lay out the form
        $builder->setAttribute('ea_entity', $config->getAttribute('ea_entity'));
        $builder->setAttribute('ea_field', $field);
        $form->add($builder->getForm());
    }

    /**
     * The process displayed by a new form: the first choice.
     */
    private function getFirstProcessCode(): ?string
    {
        $code = array_key_first($this->processConfigurationsManager->getPublicProcesses());

        return null === $code ? null : (string) $code;
    }

    /**
     * @return list<array{key: string, value: mixed}>
     */
    private function getDefaultContextRows(?string $process): array
    {
        $rows = [];
        foreach ($this->getLaunchFormOptions($process)['data']['context'] ?? [] as $key => $value) {
            $rows[] = ['key' => (string) $key, 'value' => $value];
        }

        return $rows;
    }

    /**
     * Only detects a worker running on the same host as the web server (it looks for it in the local processes): with
     * workers on another host or container, the warning is displayed although the scheduler runs (see
     * docs/reference/05-scheduler.md).
     */
    private function schedulerIsRunning(): bool
    {
        $process = Process::fromShellCommandline('ps -faux');
        $process->run();
        $out = $process->getOutput();

        return str_contains($out, 'scheduler_cron');
    }
}
