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

/**
 * PHP 8.2 : Replace by readonly class.
 */
#[AsTargetedValueResolver('http_process_execution')]
class HttpProcessExecuteValueResolver implements ValueResolverInterface
{
    public function __construct(private readonly string $storageDir, private readonly SerializerInterface $serializer)
    {
    }

    /**
     * @return iterable<HttpProcessExecution>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $all = $request->request->all();
        try {
            if ([] === $all) {
                $httpProcessExecution = $this->serializer->deserialize(
                    $request->getContent(),
                    HttpProcessExecution::class,
                    'json'
                );
            } else {
                $input = $request->get('input', $request->files->get('input'));
                if ($input instanceof UploadedFile) {
                    $uploadFileName = $this->storageDir.\DIRECTORY_SEPARATOR.date('YmdHis').'_'.uniqid().'_'.$input->getClientOriginalName();
                    (new Filesystem())->dumpFile($uploadFileName, $input->getContent());
                    $input = $uploadFileName;
                }
                $httpProcessExecution = new HttpProcessExecution(
                    $request->get('code'),
                    $input,
                    $request->get('context', []),
                    $request->request->getBoolean('queue', true),
                );
            }

            return [$httpProcessExecution];
        } catch (\Throwable) {
            return [new HttpProcessExecution()];
        }
    }
}
