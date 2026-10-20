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
        $general = $between( $this->editor, 'function GeneralTabContent(', '// SettingsTabContent' );

        $beforeBasics = strpos( $general, '{rendersBeforeBasics && customSettingsPanel && (' );
        $width        = strpos( $general, '{/* Width */}' );
        $required     = strpos( $general, '{/* Required toggle */}' );
        $afterBasics  = strpos( $general, '{!rendersBeforeBasics && customSettingsPanel}' );
        $typeSettings = strpos( $general, "{/* Type-specific settings from the field type's settings schema */}" );

        expect( $beforeBasics )->toBeGreaterThan( $required )
            ->and( $beforeBasics )->toBeLessThan( $width )
            ->and( $afterBasics )->toBeGreaterThan( $typeSettings );
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
    it( 'writes React file settings through the schema and layout content through the shared helpers', function () use ( $resource ): void {
        $editor = $resource( 'react/components/admin/FieldEditor.tsx' );

        expect( $editor )->toContain( 'buildLayoutContentUpdate(' )
            ->toContain( "getSettingsForTab( field, 'validation' )" )
            ->toContain( 'updateField( buildSettingUpdate( field, setting, e.target.value ) );' )
            ->not->toContain( 'validationRules.max_size' )
            ->not->toContain( 'validationRules.allowed_types' )
            ->not->toContain( 'default_value: e.target.value } )' );
    } );

    it( 'writes Vue file settings through the schema and layout content through the shared helpers', function () use ( $resource ): void {
        $editor  = $resource( 'vue/components/admin/FieldEditor.vue' );
        $control = $resource( 'vue/components/admin/FieldSettingControl.vue' );

        expect( $editor )->toContain( 'buildLayoutContentUpdate(' )
            ->toContain( "getSettingsForTab( currentField.value, 'validation' )" )
            ->not->toContain( 'validationRules.max_size' )
            ->not->toContain( 'validationRules.allowed_types' )
            ->not->toContain( 'default_value: ($event as string) } )' )
            ->and( $control )->toContain( 'buildSettingUpdate( props.field, props.setting, draft.value )' );
    } );

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

describe( 'React and Vue display settings (issue #83)', function () use ( $resource, $between ): void {
    beforeEach( function () use ( $resource ): void {
        $this->settings    = $resource( 'shared/fieldSettings.ts' );
        $this->reactEditor = $resource( 'react/components/admin/FieldEditor.tsx' );
        $this->vueEditor   = $resource( 'vue/components/admin/FieldEditor.vue' );
    } );

    it( 'mirrors the server-side allowed values', function (): void {
        expect( $this->settings )
            ->toContain( "export const HEADING_LEVELS = ['h2', 'h3', 'h4', 'h5', 'h6'] as const;" )
            ->toContain( "export const DIVIDER_STYLES = ['solid', 'dashed', 'dotted'] as const;" )
            ->toContain( "export const DIVIDER_SPACINGS = ['small', 'normal', 'large'] as const;" );
    } );

    it( 'reads stored h-tags and bare numbers as heading levels, defaulting to h3', function () use ( $between ): void {
        $getter = $between( $this->settings, 'export function getHeadingLevel(', "\n}\n" );

        expect( $getter )->toContain( '? `h${raw}`' )
            ->and( $getter )->toContain( ": 'h3';" );
    } );

    it( 'accepts only whole numbers from 1 to the maximum for rows and step', function () use ( $between ): void {
        $parser = $between( $this->settings, 'export function parsePositiveInteger(', "\n}\n" );

        expect( $parser )->toContain( 'if ( !/^\\d+$/.test( value.trim() ) ) {' )
            ->and( $parser )->toContain( 'return num >= 1 && num <= max ? num : undefined;' );
    } );

    it( 'removes a cleared field_config entry instead of storing a blank', function () use ( $between ): void {
        $builder = $between( $this->settings, 'export function buildFieldConfigUpdate(', "\n}\n" );

        expect( $builder )->toContain( "if ( value === undefined || value === '' ) {" )
            ->and( $builder )->toContain( 'delete fieldConfig[key];' );
    } );

    it( 'renders the React editor controls from the settings schema', function () use ( $between ): void {
        $general  = $between( $this->reactEditor, 'function GeneralTabContent(', '// SettingsTabContent' );
        $settings = $between( $this->reactEditor, 'function SettingsTabContent(', "\n}\n" );

        expect( $general )->toContain( "getSettingsForTab( field, 'general' ).map(" )
            ->and( $settings )->toContain( '<SettingControl' )
            ->and( $this->reactEditor )->not->toContain( 'buildFieldConfigUpdate(' )
            ->and( $this->reactEditor )->not->toContain( 'HEADING_LEVEL_OPTIONS' )
            ->and( $this->reactEditor )->not->toContain( 'function ValidationTabContent(' );
    } );

    it( 'renders the Vue editor controls from the settings schema', function (): void {
        expect( $this->vueEditor )
            ->toContain( "getSettingsForTab( currentField.value, 'general' )" )
            ->toContain( "getSettingsForTab( currentField.value, 'validation' )" )
            ->toContain( '<FieldSettingControl' )
            ->not->toContain( 'buildFieldConfigUpdate(' )
            ->not->toContain( 'HEADING_LEVEL_OPTIONS' )
            ->not->toContain( 'DIVIDER_STYLE_OPTIONS' );
    } );

    it( 'applies the settings in the React and Vue renderers', function () use ( $resource ): void {
        $reactText   = $resource( 'react/components/fields/TextField.tsx' );
        $reactLayout = $resource( 'react/components/fields/LayoutField.tsx' );
        $vueText     = $resource( 'vue/components/fields/TextField.vue' );
        $vueLayout   = $resource( 'vue/components/fields/LayoutField.vue' );

        expect( $reactText )->toContain( 'rows={getTextareaRows( field )}' )
            ->toContain( 'step={getTimeStep( field )}' )
            ->and( $reactLayout )->toContain( 'const Tag = getHeadingLevel( field );' )
            ->toContain( '${getDividerClasses( field )}' )
            ->and( $vueText )->toContain( ':rows="getTextareaRows( field )"' )
            ->toContain( ':step="getTimeStep( field )"' )
            ->and( $vueLayout )->toContain( 'getHeadingLevel( props.field )' )
            ->toContain( 'getDividerClasses( field )' );
    } );
} );

describe( 'Client validation limits of zero (issue #86)', function () use ( $resource, $between ): void {
    beforeEach( function () use ( $resource ): void {
        $this->validation = $resource( 'shared/validation.ts' );
    } );

    it( 'treats 0 as a set limit and null or blank as unset', function () use ( $between ): void {
        $limit = $between( $this->validation, 'function numericLimit(', "\n}\n" );

        expect( $limit )->toContain( "if ( value === null || value === undefined || value === '' ) {" )
            ->and( $limit )->toContain( 'Number.isFinite( num ) ? num : undefined' );
    } );

    it( 'reads every limit through the helpers', function (): void {
        expect( $this->validation )
            ->toContain( 'const minLength = numericLimit( rules.min );' )
            ->toContain( 'const min = numericLimit( rules.min );' )
            ->toContain( 'const step = numericLimit( rules.step );' )
            ->toContain( 'const minDate = stringLimit( rules.min_date );' )
            ->toContain( 'const maxTime = stringLimit( rules.max_time );' )
            ->not->toContain( 'rules.min !== undefined' )
            ->not->toContain( 'if ( rules.min_date )' );
    } );
} );

describe( 'React and Vue submission labels (issue #85)', function () use ( $resource ): void {
    it( 'lists option labels for array values', function () use ( $resource ): void {
        expect( $resource( 'types/artisanpack-forms.d.ts' ) )->toContain( 'display_values: string[];' )
            ->and( $resource( 'react/components/admin/SubmissionDetail.tsx' ) )->toContain( '( value.display_values ?? value.value_array ).map(' )
            ->and( $resource( 'vue/components/admin/SubmissionDetail.vue' ) )->toContain( 'in ( value.display_values ?? value.value_array )' );
    } );
} );
