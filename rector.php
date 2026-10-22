<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedConstructorParamRector;
use Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector;
use Rector\Symfony\Set\SymfonySetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withPhpSets()
    // here we can define, what prepared sets of rules will be applied
    ->withComposerBased(doctrine: true, phpunit: true, symfony: true)
    ->withPreparedSets(deadCode: true, codeQuality: true, doctrineCodeQuality: true, symfonyCodeQuality: true)
    ->withAttributesSets(symfony: true, doctrine: true)
    ->withSets([
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
    ])
    ->withSkip([
        // deprecated $translator argument kept for BC, to be removed in 4.0
        RemoveUnusedConstructorParamRector::class => [
            __DIR__.'/src/Controller/Admin/ProcessExecutionCrudController.php',
        ],
        // Symfony version checks of the test kernel, always true with the installed version only
        RemoveAlwaysTrueIfConditionRector::class => [
            __DIR__.'/tests/App/TestKernel.php',
        ],
    ])
;
