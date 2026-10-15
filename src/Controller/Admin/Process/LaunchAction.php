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

namespace CleverAge\UiProcessBundle\Controller\Admin\Process;

use CleverAge\ProcessBundle\Exception\MissingProcessException;
use CleverAge\UiProcessBundle\Entity\User;
use CleverAge\UiProcessBundle\Form\ContextValueNormalizer;
use CleverAge\UiProcessBundle\Form\Type\LaunchType;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use EasyCorp\Bundle\EasyAdminBundle\Config\Asset;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/process/launch',
    name: 'process_launch',
    requirements: ['process' => '\w+'],
    methods: ['POST', 'GET']
)]
#[IsGranted('ROLE_USER')]
class LaunchAction extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly RequestStack $requestStack,
        private readonly ProcessConfigurationsManager $processConfigurationsManager,
        private readonly AdminContextProvider $adminContextProvider,
    ) {
    }

    public function __invoke(
        string $uploadDirectory,
    ): Response {
        // Accessed directly, not through the dashboard: the template needs the EasyAdmin context
        if (!$this->adminContextProvider->getContext() instanceof AdminContextInterface) {
            return $this->redirectToRoute('process', ['routeName' => 'process_launch'] + ($this->requestStack->getMainRequest()?->query->all() ?? []));
        }
        $processCode = (string) $this->requestStack->getMainRequest()?->query->get('process');
        if ('' === $processCode) {
            throw new MissingProcessException();
        }
        $uiOptions = $this->processConfigurationsManager->getUiOptions($processCode);
        if (null === $uiOptions) {
            throw new \InvalidArgumentException('Missing UI Options');
        }
        if (null === $uiOptions['ui_launch_mode'] || 'modal' === $uiOptions['ui_launch_mode']) {
            $this->dispatch($processCode);
            $this->addFlash(
                'success',
                'Process has been added to queue. It will start as soon as possible'
            );

            return $this->redirectToRoute('process', ['routeName' => 'process_list']);
        }
        $form = $this->createForm(
            LaunchType::class,
            null,
            [
                'constraints' => $uiOptions['form']['constraints'],
                'process_code' => $processCode,
                // Not resolved again by the form type
                'ui_options' => $uiOptions,
            ]
        );
        if (false === $form->isSubmitted()) {
            $form->setData($this->processConfigurationsManager->buildLaunchFormDefaults($uiOptions));
        }
        $form->handleRequest($this->requestStack->getMainRequest());
        if ($form->isSubmitted() && $form->isValid()) {
            // Normalized after the validation: the constraints apply to the typed values
            $normalizer = new ContextValueNormalizer();
            $input = $form->get('input')->getData();
            if ($input instanceof UploadedFile) {
                $filename = \sprintf('%s/%s.%s', $uploadDirectory, Uuid::v4(), $input->getClientOriginalExtension());
                (new Filesystem())->dumpFile($filename, $input->getContent());
                $input = $filename;
            } else {
                $input = $normalizer->normalize($input, 'input');
            }
            /** @var string $processCode */
            $processCode = $form->getConfig()->getOption('process_code');
            /** @var array<int|string, mixed> $data */
            $data = $form->get('context')->getData() ?? [];
            $context = [];
            foreach ($data as $key => $value) {
                $context[(string) $key] = $normalizer->normalize($value, (string) $key);
            }
            $this->dispatch($processCode, $input, $context);
            $this->addFlash(
                'success',
                'Process has been added to queue. It will start as soon as possible'
            );

            return $this->redirectToRoute('process', ['routeName' => 'process_list']);
        }
        $this->adminContextProvider->getContext()->getAssets()->addJsAsset(Asset::fromEasyAdminAssetPackage('field-collection.js')->getAsDto());

        return $this->render(
            '@CleverAgeUiProcess/admin/process/launch.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function dispatch(string $processCode, mixed $input = null, array $context = []): void
    {
        $message = new ProcessExecuteMessage(
            $processCode,
            $input,
            // The connected user always wins over a context value
            [ProcessExecuteMessage::EXECUTION_USER_KEY => $this->getUser()?->getEmail()] + $context
        );
        $this->messageBus->dispatch($message);
    }

    #[\Override]
    protected function getUser(): ?User
    {
        /** @var User $user */
        $user = parent::getUser();

        return $user;
    }
}
