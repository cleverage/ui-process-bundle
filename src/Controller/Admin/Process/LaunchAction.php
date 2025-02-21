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
use CleverAge\UiProcessBundle\Entity\UserInterface;
use CleverAge\UiProcessBundle\Form\Type\LaunchType;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use EasyCorp\Bundle\EasyAdminBundle\Config\Asset;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function __invoke(
        RequestStack $requestStack,
        string $uploadDirectory,
        ProcessConfigurationsManager $processConfigurationsManager,
        AdminContext $context,
    ): Response {
        $processCode = $requestStack->getMainRequest()?->get('process');
        if (null === $processCode) {
            throw new MissingProcessException();
        }
        $uiOptions = $processConfigurationsManager->getUiOptions($processCode);
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
                'constraints' => $uiOptions['constraints'],
                'process_code' => $processCode,
            ]
        );
        if (false === $form->isSubmitted()) {
            $default = $uiOptions['default'];
            if (false === $form->get('input')->getConfig()->getType()->getInnerType() instanceof TextType
                && isset($default['input'])
            ) {
                unset($default['input']);
            }
            $form->setData($default);
        }
        $form->handleRequest($requestStack->getMainRequest());
        if ($form->isSubmitted() && $form->isValid()) {
            $input = $form->get('input')->getData();
            if ($input instanceof UploadedFile) {
                $filename = \sprintf('%s/%s.%s', $uploadDirectory, Uuid::v4(), $input->getClientOriginalExtension());
                (new Filesystem())->dumpFile($filename, $input->getContent());
                $input = $filename;
            }
            $this->dispatch(
                $form->getConfig()->getOption('process_code'),
                $input,
                $form->get('context')->getData()
            );
            $this->addFlash(
                'success',
                'Process has been added to queue. It will start as soon as possible'
            );

            return $this->redirectToRoute('process', ['routeName' => 'process_list']);
        }
        $context->getAssets()->addJsAsset(Asset::fromEasyAdminAssetPackage('field-collection.js')->getAsDto());

        return $this->render(
            '@CleverAgeUiProcess/admin/process/launch.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * @param mixed[] $context
     */
    protected function dispatch(string $processCode, mixed $input = null, array $context = []): void
    {
        $message = new ProcessExecuteMessage(
            $processCode,
            $input,
            array_merge(
                ['execution_user' => $this->getUser()?->getEmail()],
                $context
            )
        );
        $this->messageBus->dispatch($message);
    }

    protected function getUser(): ?UserInterface
    {
        /** @var UserInterface $user */
        $user = parent::getUser();

        return $user;
    }
}
