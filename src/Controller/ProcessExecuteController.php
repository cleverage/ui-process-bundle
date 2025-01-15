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

namespace CleverAge\UiProcessBundle\Controller;

use CleverAge\UiProcessBundle\Http\Model\HttpProcessExecution;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
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
        MessageBusInterface $bus,
    ): JsonResponse {
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
                $httpProcessExecution->code ?? '',
                $httpProcessExecution->input,
                is_string($httpProcessExecution->context)
                    ? json_decode($httpProcessExecution->context, true)
                    : $httpProcessExecution->context
            )
        );

        return new JsonResponse('Process has been added to queue. It will start as soon as possible.');
    }
}
