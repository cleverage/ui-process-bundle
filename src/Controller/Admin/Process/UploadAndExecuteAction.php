<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Controller\Admin\Process;

use CleverAge\ProcessBundle\Configuration\ProcessConfiguration;
use CleverAge\ProcessUiBundle\Form\Type\ProcessUploadFileType;
use CleverAge\ProcessUiBundle\Message\ProcessExecuteMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
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
        #[Autowire(param: 'upload_directory')] string $uploadDirectory,
        #[ValueResolver('process')] ProcessConfiguration $processConfiguration
    ): Response {
        if (null === $processConfiguration->getEntryPoint()) {
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
            $savedFilepath = sprintf('%s/%s.%s', $uploadDirectory, Uuid::v4(), $file->getClientOriginalExtension());
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
            '@CleverAgeProcessUi/admin/process/upload_and_execute.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }
}
