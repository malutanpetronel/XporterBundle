<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->exclude('web')
    ->exclude('var')
    ->exclude('bin')
    ->exclude('tests')
    ->exclude('DoctrineMigrations')
    ->in(__DIR__)
;

return PhpCsFixer\Config::create()
    ->setRules(array(
        '@Symfony' => true,
        'array_syntax' => array('syntax' => 'short'),
        'ordered_imports' => true,

    ))
    ->setFinder($finder)
;
