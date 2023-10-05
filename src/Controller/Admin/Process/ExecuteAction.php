<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Controller\Admin\Process;

use CleverAge\ProcessUiBundle\Message\ProcessExecuteMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/process/execute', name: 'process_execute', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
class ExecuteAction extends AbstractController
{
    public function __invoke(Request $request, MessageBusInterface $bus): Response
    {
        $process = $request->get('process');
        if (null === $process) {
            $this->createNotFoundException('Process is missing');
        }
        $bus->dispatch(new ProcessExecuteMessage($process, null));
        $this->addFlash(
            'success',
            'Process has been added to queue. It will start as soon as possible'
        );

        return $this->redirectToRoute('process', ['routeName' => 'process_list']);
    }
}
