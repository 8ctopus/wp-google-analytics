<?php

require_once __DIR__ . '/vendor/autoload.php';

$finder = PhpCsFixer\Finder::create()
    ->exclude('node_modules')
    ->exclude('vendor')
    ->in( __DIR__ );

$config = new PhpCsFixer\Config();
$config
    ->registerCustomFixers([
        new WeDevs\Fixer\SpaceInsideParenthesisFixer(),
        new WeDevs\Fixer\BlankLineAfterClassOpeningFixer()
    ])
    ->setRiskyAllowed(true)
    ->setUsingCache(false)
    ->setRules( WeDevs\Fixer\Fixer::rules() )
    ->setFinder( $finder )
;

return $config;
