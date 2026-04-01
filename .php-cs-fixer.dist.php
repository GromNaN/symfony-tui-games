<?php

$dirs = array_filter(
    [__DIR__.'/src', __DIR__.'/tests'],
    is_dir(...)
);

$finder = [] !== $dirs
    ? PhpCsFixer\Finder::create()->in(array_values($dirs))
    : PhpCsFixer\Finder::create()->in(__DIR__)->depth(0); // no PHP files at root

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
    ])
    ->setFinder($finder)
;
