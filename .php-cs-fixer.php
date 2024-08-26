<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$header = <<<'EOF'
This file is part of Temporal package.

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOF;

$finder = Finder::create()
    ->in(__DIR__ . '/src')
    ->exclude('tests')
;

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12'                            => true,
        '@PHP80Migration:risky'             => true,
        'list_syntax'                       => ['syntax' => 'short'],
        'no_unused_imports'                 => true,
        // TODO change to true
        'declare_strict_types'              => false,
        'void_return'                       => true,
        // TODO change this to true
        'ordered_class_elements'            => false,
        'linebreak_after_opening_tag'       => true,
        'single_quote'                      => true,
        'no_blank_lines_after_phpdoc'       => true,
        'unary_operator_spaces'             => true,
        'no_useless_else'                   => true,
        'no_useless_return'                 => true,
        'trailing_comma_in_multiline'       => true,
        // TODO remove the remaining rules
        'blank_line_after_opening_tag'      => false,
        'braces_position'                   => false,

    ])
    ->setFinder($finder)
;

