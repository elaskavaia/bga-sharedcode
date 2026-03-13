<?php

return (new PhpCsFixer\Config())
    ->setRules([
        'braces_position' => [
            'functions_opening_brace' => 'same_line',
            'classes_opening_brace' => 'same_line',
            'anonymous_functions_opening_brace' => 'same_line',
            'control_structures_opening_brace' => 'same_line',
        ],
        'single_blank_line_at_eof' => true,
        'no_trailing_whitespace' => true,
        'no_extra_blank_lines' => true,
        'indentation_type' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setIndent("    ")
    ->setLineEnding("\n")
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__)
            ->name('*.php')
    );
