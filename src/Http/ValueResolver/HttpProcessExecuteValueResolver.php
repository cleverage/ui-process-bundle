<?php

namespace CleverAge\ProcessUiBundle\Http\ValueResolver;

use CleverAge\ProcessUiBundle\Http\Model\HttpProcessExecution;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsTargetedValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

#[AsTargetedValueResolver('http_process_execution')]
readonly class HttpProcessExecuteValueResolver implements ValueResolverInterface
{
    public function __construct(#[Autowire(param: 'upload_directory')] private string $storageDir)
    {

    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $input = $request->get('input', $request->files->get('input'));
        if ($input instanceof UploadedFile) {
            $uploadFileName = $this->storageDir . DIRECTORY_SEPARATOR . date('YmdHis') . '_' . uniqid() . '_' . $input->getClientOriginalName();
            (new Filesystem())->dumpFile($uploadFileName, $input->getContent());
            $input = $uploadFileName;

        }

        return [new HttpProcessExecution($request->get('code'), $input, $request->get('context', []))];
    }
}