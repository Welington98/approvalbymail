<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor', 'node_modules', 'lib', 'staging'])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2.0'                 => true,
        '@PER-CS2.0:risky'           => true,
        'array_syntax'               => ['syntax' => 'short'],
        'no_unused_imports'          => true,
        'ordered_imports'            => true,
        'single_quote'               => true,
        'trailing_comma_in_multiline' => true,
        'visibility_required'        => ['elements' => ['method', 'property']],
    ])
    ->setFinder($finder);
