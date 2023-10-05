<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle;

use CleverAge\ProcessUiBundle\DependencyInjection\Compiler\RegisterLogHandler;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class CleverAgeProcessUiBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new RegisterLogHandler());
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
