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

use CleverAge\ProcessBundle\Configuration\ProcessConfiguration;
use CleverAge\ProcessBundle\Configuration\TaskConfiguration;
use CleverAge\UiProcessBundle\Form\Type\ProcessUploadFileType;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/process/upload-and-execute',
    name: 'process_upload_and_execute',
    requirements: ['process' => '\w+'],
    methods: ['POST', 'GET']
)]
#[IsGranted('ROLE_USER')]
class UploadAndExecuteAction extends AbstractController
{
    public function __invoke(
        RequestStack $requestStack,
        MessageBusInterface $messageBus,
        string $uploadDirectory,
        #[ValueResolver('process')] ProcessConfiguration $processConfiguration,
    ): Response {
        if (!$processConfiguration->getEntryPoint() instanceof TaskConfiguration) {
            throw new \RuntimeException('You must set an entry_point.');
        }
        $form = $this->createForm(
            ProcessUploadFileType::class,
            null,
            ['process_code' => $requestStack->getMainRequest()?->get('process')]
        );
        $form->handleRequest($requestStack->getMainRequest());
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->getData();
            $savedFilepath = \sprintf('%s/%s.%s', $uploadDirectory, Uuid::v4(), $file->getClientOriginalExtension());
            (new Filesystem())->dumpFile($savedFilepath, $file->getContent());
            $messageBus->dispatch(
                new ProcessExecuteMessage(
                    $form->getConfig()->getOption('process_code'),
                    $savedFilepath
                )
            );
            $this->addFlash(
                'success',
                'Process has been added to queue. It will start as soon as possible'
            );

            return $this->redirectToRoute('process', ['routeName' => 'process_list']);
        }

        return $this->render(
            '@CleverAgeUiProcess/admin/process/upload_and_execute.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }
}
