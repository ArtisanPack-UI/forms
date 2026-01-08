<?php

use ArtisanPackUI\CodeStylePint\Fixers\SpacesInsideBracketsFixer;
use ArtisanPackUI\CodeStylePint\Fixers\SpacesInsideParenthesisFixer;
use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/config',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
    ->exclude([
        'vendor',
        'node_modules',
    ]);

$config = new Config();
$config
    ->setRiskyAllowed(true)
    ->registerCustomFixers([
        new SpacesInsideParenthesisFixer(),
        new SpacesInsideBracketsFixer(),
    ])
    ->setRules([
        // Array formatting
        'array_syntax' => ['syntax' => 'short'],
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters'],
        ],

        // Binary operators
        'binary_operator_spaces' => [
            'default' => 'single_space',
            'operators' => [
                '=' => 'align_single_space',
                '=>' => 'align_single_space',
            ],
        ],
        'concat_space' => ['spacing' => 'one'],

        // Blank lines
        'blank_line_after_opening_tag' => true,
        'no_extra_blank_lines' => [
            'tokens' => ['extra', 'throw', 'use'],
        ],

        // Braces
        'braces_position' => [
            'functions_opening_brace' => 'next_line_unless_newline_at_signature_end',
            'control_structures_opening_brace' => 'same_line',
        ],
        'control_structure_braces' => true,
        'control_structure_continuation_position' => ['position' => 'same_line'],

        // Class structure
        'class_attributes_separation' => [
            'elements' => [
                'method' => 'one',
                'property' => 'one',
                'trait_import' => 'none',
            ],
        ],
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'magic',
                'phpunit',
                'method_public',
                'method_protected',
                'method_private',
            ],
        ],
        'ordered_traits' => true,
        'single_class_element_per_statement' => true,
        'visibility_required' => [
            'elements' => ['property', 'method', 'const'],
        ],

        // Function declaration
        'function_declaration' => ['closure_function_spacing' => 'one'],

        // Imports
        'fully_qualified_strict_types' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'no_unused_imports' => true,
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],

        // PHPDoc
        'phpdoc_order' => true,
        'phpdoc_separation' => true,
        'phpdoc_types_order' => ['null_adjustment' => 'always_last'],

        // Return type
        'return_type_declaration' => ['space_before' => 'none'],
        'void_return' => true,

        // Quotes
        'single_quote' => true,

        // Semicolons
        'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line'],

        // Other
        'single_trait_insert_per_statement' => true,
        'yoda_style' => [
            'equal' => true,
            'identical' => true,
            'less_and_greater' => false,
        ],

        // WordPress-style spacing (custom fixers)
        'ArtisanPackUI/spaces_inside_parenthesis' => true,
        'ArtisanPackUI/spaces_inside_brackets' => true,

        // NOTE: declare_strict_types is intentionally NOT enabled for this package
        // to maintain consistency with forms handling varied user input types
    ])
    ->setFinder($finder);

return $config;
