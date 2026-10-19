<?php

declare( strict_types=1 );

/**
 * Guards the React and Vue side of issues #76, #79, #81 and #82. The surfaces
 * are validated by source content (the package ships no JS build or test
 * harness of its own), matching the convention used by FormRendererLayoutTest
 * and ReactFormRendererTest.
 */

$resource = static fn ( string $path ): string => file_get_contents( __DIR__ . '/../../resources/js/' . $path );

/**
 * Returns the part of `$haystack` between the first `$start` and the next
 * `$end` after it, so an assertion can be scoped to one block of code.
 */
$between = static function ( string $haystack, string $start, string $end ): string {
    $from = strpos( $haystack, $start );
    expect( $from )->not->toBeFalse();

    $to = strpos( $haystack, $end, $from + strlen( $start ) );
    expect( $to )->not->toBeFalse();

    return substr( $haystack, $from, $to - $from );
};

describe( 'React custom settings placement (issue #76)', function () use ( $resource, $between ): void {
    beforeEach( function () use ( $resource ): void {
        $this->registry = $resource( 'react/components/fields/registry.ts' );
        $this->editor   = $resource( 'react/components/admin/FieldEditor.tsx' );
    } );

    it( 'accepts a position option that defaults to after-basics', function () use ( $between ): void {
        $register = $between( $this->registry, 'export function registerFieldSettings(', "\n}\n" );

        expect( $this->registry )->toContain( "export type FieldSettingsPosition = 'before-basics' | 'after-basics';" )
            ->and( $register )->toContain( 'options: FieldSettingsOptions = {},' )
            ->and( $register )->toContain( 'normalizeFieldSettingsPosition( options.position )' )
            ->and( $this->registry )->toContain( "return position === 'before-basics' ? 'before-basics' : 'after-basics';" );
    } );

    it( 'keeps getRegisteredFieldSettings returning the component', function () use ( $between ): void {
        $getter = $between( $this->registry, 'export function getRegisteredFieldSettings(', "\n}\n" );

        expect( $getter )->toContain( 'return fieldSettingsRegistry.get( type )?.component;' );
    } );

    it( 'lets the customSettings prop pass a component or { component, position }', function () use ( $between ): void {
        $resolve = $between( $this->registry, 'export function resolveFieldSettings(', "\n}\n" );

        expect( $this->registry )->toContain( '| ( FieldSettingsOptions & { component: CustomFieldSettingsComponent } );' )
            ->and( $resolve )->toContain( 'const entry = ownEntry( overlay, type );' )
            ->and( $resolve )->toContain( "'component' in entry" )
            ->and( $resolve )->toContain( "position: 'after-basics'" )
            ->and( $this->editor )->toContain( 'customSettings?: Record<string, CustomFieldSettingsEntry>;' );
    } );

    it( 'renders a before-basics panel above Width and an after-basics panel last', function () use ( $between ): void {
        $general = $between( $this->editor, 'function GeneralTabContent(', '// ValidationTabContent' );

        $beforeBasics = strpos( $general, '{rendersBeforeBasics && customSettingsPanel && (' );
        $width        = strpos( $general, '{/* Width */}' );
        $required     = strpos( $general, '{/* Required toggle */}' );
        $afterBasics  = strpos( $general, '{!rendersBeforeBasics && customSettingsPanel}' );
        $fileConfig   = strpos( $general, '{/* File field config */}' );

        expect( $beforeBasics )->toBeGreaterThan( $required )
            ->and( $beforeBasics )->toBeLessThan( $width )
            ->and( $afterBasics )->toBeGreaterThan( $fileConfig );
    } );

    it( 'exports the new registry API from the React entry point', function () use ( $resource ): void {
        expect( $resource( 'react/index.ts' ) )
            ->toContain( 'resolveFieldSettings,' )
            ->toContain( 'getRegisteredFieldSettingsRegistration,' )
            ->toContain( 'FieldSettingsPosition,' )
            ->toContain( 'CustomFieldSettingsEntry,' );
    } );
} );

describe( 'Shared field settings contract (issues #79, #82)', function () use ( $resource ): void {
    beforeEach( function () use ( $resource ): void {
        $this->settings = $resource( 'shared/fieldSettings.ts' );
    } );

    it( 'stores file settings in field_config and drops the legacy validation_rules copy', function (): void {
        expect( $this->settings )->toContain( 'export function buildFileSettingUpdate(' )
            ->toContain( 'fieldConfig[key] = value;' )
            ->toContain( 'delete validationRules[key];' );
    } );

    it( 'stores heading text in label and paragraph/html content in field_config.content', function (): void {
        expect( $this->settings )->toContain( 'return { label: content, default_value: null };' )
            ->toContain( 'field_config: { ...( field.field_config ?? {} ), content } as FormField[\'field_config\'],' );
    } );

    it( 'reads layout content with the same fallbacks as FormField::getLayoutContent()', function (): void {
        expect( $this->settings )->toContain( "return legacy ?? field.label ?? '';" )
            ->toContain( "return legacy ?? content ?? nonBlank( field.help_text ) ?? field.label ?? '';" )
            ->toContain( "return legacy ?? content ?? '';" );
    } );
} );

describe( 'Builders and renderers use the shared contract (issues #79, #82)', function () use ( $resource ): void {
    it( 'writes file settings and layout content through the shared helpers', function ( string $path ) use ( $resource ): void {
        $editor = $resource( $path );

        expect( $editor )->toContain( 'buildFileSettingUpdate(' )
            ->toContain( "'max_size'" )
            ->toContain( "'allowed_types'" )
            ->toContain( 'buildLayoutContentUpdate(' )
            ->not->toContain( 'validationRules.max_size' )
            ->not->toContain( 'validationRules.allowed_types' )
            ->not->toContain( 'default_value: e.target.value } )' )
            ->not->toContain( 'default_value: ($event as string) } )' );
    } )->with( [
        'React' => 'react/components/admin/FieldEditor.tsx',
        'Vue'   => 'vue/components/admin/FieldEditor.vue',
    ] );

    it( 'derives the file accept attribute from the shared helper', function ( string $path ) use ( $resource ): void {
        expect( $resource( $path ) )->toContain( 'getFileAccept(' )
            ->not->toContain( 'validation_rules?.allowed_types' );
    } )->with( [
        'React' => 'react/components/fields/AdvancedField.tsx',
        'Vue'   => 'vue/components/fields/AdvancedField.vue',
    ] );

    it( 'renders layout content through the shared helper', function ( string $path ) use ( $resource ): void {
        expect( $resource( $path ) )->toContain( 'getLayoutContent(' )
            ->not->toContain( 'field.default_value ??' );
    } )->with( [
        'React renderer' => 'react/components/fields/LayoutField.tsx',
        'Vue renderer'   => 'vue/components/fields/LayoutField.vue',
        'React preview'  => 'react/components/admin/FormBuilder.tsx',
        'Vue preview'    => 'vue/components/admin/FormBuilder.vue',
    ] );
} );

describe( 'Client-side required acceptance (issue #81)', function () use ( $resource, $between ): void {
    it( 'treats checkbox and toggle as acceptance fields', function () use ( $resource, $between ): void {
        $validation = $resource( 'shared/validation.ts' );
        $required   = $between( $validation, '// Required check', '// Skip further validation' );

        expect( $resource( 'shared/fieldSettings.ts' ) )->toContain( "export const ACCEPTANCE_FIELD_TYPES = new Set( ['checkbox', 'toggle'] );" )
            ->and( $required )->toContain( 'ACCEPTANCE_FIELD_TYPES.has( field.type )' );
    } );
} );
