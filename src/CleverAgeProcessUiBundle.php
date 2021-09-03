<?php
namespace CleverAge\ProcessUiBundle;

use CleverAge\ProcessUiBundle\DependencyInjection\Compiler\RegisterLogHandlerCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class CleverAgeProcessUiBundle extends Bundle
{
    public const ICON_NEW = 'fa fa-plus';
    public const ICON_EDIT = 'far fa-edit';
    public const ICON_DELETE = 'fa fa-trash-o';
    public const LABEL_NEW = false;
    public const LABEL_EDIT = false;
    public const LABEL_DELETE = false;
    public const CLASS_NEW = '';
    public const CLASS_EDIT = 'text-warning';
    public const CLASS_DELETE = '';


    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new RegisterLogHandlerCompilerPass());
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}