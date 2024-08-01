<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Controller;

use CleverAge\ProcessUiBundle\Http\Model\HttpProcessExecution;
use CleverAge\ProcessUiBundle\Message\ProcessExecuteMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route(path: '/http/process/execute', name: 'http_process_execute', methods: ['POST'])]
class ProcessExecuteController extends AbstractController
{
    public function __invoke(
        #[ValueResolver('http_process_execution')] HttpProcessExecution $httpProcessExecution,
        ValidatorInterface $validator,
        MessageBusInterface $bus
    ): JsonResponse
    {
        $violations = $validator->validate($httpProcessExecution);
        if ($violations->count() > 0) {
            $violationsMessages = [];
            foreach ($violations as $violation) {
                $violationsMessages[] = $violation->getMessage();
            }
            throw new UnprocessableEntityHttpException(implode('. ', $violationsMessages));
        }
        $bus->dispatch(
            new ProcessExecuteMessage(
                $httpProcessExecution->code,
                $httpProcessExecution->input,
                $httpProcessExecution->context
            )
        );

        return new JsonResponse('Process has been added to queue. It will start as soon as possible.');
    }
}
