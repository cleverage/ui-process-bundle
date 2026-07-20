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

namespace CleverAge\UiProcessBundle\Http\ValueResolver;

use CleverAge\UiProcessBundle\Http\Model\HttpProcessExecution;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsTargetedValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\SerializerInterface;

#[AsTargetedValueResolver('http_process_execution')]
readonly class HttpProcessExecuteValueResolver implements ValueResolverInterface
{
    public function __construct(private string $storageDir, private SerializerInterface $serializer)
    {
    }

    /**
     * @return iterable<HttpProcessExecution>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        try {
            $hasRequestData = $request->request->count() > 0 || $request->files->count() > 0;

            if (!$hasRequestData) {
                $content = $request->getContent();
                if (empty($content)) {
                    return [new HttpProcessExecution()];
                }

                $httpProcessExecution = $this->serializer->deserialize(
                    $content,
                    HttpProcessExecution::class,
                    'json'
                );
            } else {
                $input = $request->request->get('input') ?? $request->query->get('input');

                if (null === $input) {
                    $input = $request->files->get('input');
                }

                if ($input instanceof UploadedFile) {
                    $uploadFileName = $this->storageDir.\DIRECTORY_SEPARATOR.date('YmdHis').'_'.uniqid().'_'.$input->getClientOriginalName();
                    (new Filesystem())->dumpFile($uploadFileName, $input->getContent());
                    $input = $uploadFileName;
                }

                $code = $request->request->get('code') ?? $request->query->get('code');
                $context = $request->request->all('context');
                if ([] === $context) {
                    $context = $request->query->all('context');
                }

                $queue = $request->request->getBoolean('queue', true);

                $httpProcessExecution = new HttpProcessExecution(
                    (string) $code,
                    $input,
                    $context,
                    $queue
                );
            }

            return [$httpProcessExecution];
        } catch (\Throwable) {
            return [new HttpProcessExecution()];
        }
    }
}
