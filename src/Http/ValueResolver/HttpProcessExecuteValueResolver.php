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

#[AsTargetedValueResolver('http_process_execution')]
readonly class HttpProcessExecuteValueResolver implements ValueResolverInterface
{
    public function __construct(private string $storageDir)
    {
    }

    /**
     * @return iterable<HttpProcessExecution>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $input = $request->get('input', $request->files->get('input'));
        if ($input instanceof UploadedFile) {
            $uploadFileName = $this->storageDir.\DIRECTORY_SEPARATOR.date('YmdHis').'_'.uniqid().'_'.$input->getClientOriginalName();
            (new Filesystem())->dumpFile($uploadFileName, $input->getContent());
            $input = $uploadFileName;
        }

        return [new HttpProcessExecution($request->get('code'), $input, $request->get('context', []))];
    }
}
