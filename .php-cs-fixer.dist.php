<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS3.0' => true,
        '@PER-CS3.0:risky' => true,
        '@PHP8x3Migration' => true,
        '@PHP8x3Migration:risky' => true,
        '@PHPUnit10x0Migration:risky' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true
    ])
    ->setFinder(
        (new Finder())
            ->in(__DIR__)
            ->exclude(['.Build'])
            ->notPath('ext_emconf.php')
    );
