<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__.'/src')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
    ->exclude(['build', 'vendor'])
    ->files()
    ->name('*.php')
;

$config = new PhpCsFixer\Config();

return $config
    ->setUsingCache(true)
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setRules([
        '@PHP80Migration' => true,
        '@PHP80Migration:risky' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'fopen_flags' => false,
        'ordered_imports' => true,
        'protected_to_private' => false,
        'single_line_throw' => false,
        // this must be disabled because the output of some tests include NBSP characters
        'non_printable_character' => false,
    ])
    ;
