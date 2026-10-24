<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Support\DynamicPopulation;
use ArtisanPackUI\Forms\Support\MergeTags;

/**
 * Guards the React, Vue and shared JS side of issues #93, #94, #95, #96 and
 * #97. The surfaces are validated by source content (the package ships no JS
 * build or test harness of its own), matching FieldSettingsFrontendTest.
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

describe( 'Toggle field in React and Vue (issue #93)', function () use ( $resource, $between ): void {
    it( 'is a built-in field type in the TypeScript definitions', function () use ( $resource ): void {
        expect( $resource( 'types/artisanpack-forms.d.ts' ) )
            ->toContain( "    | 'toggle'\n" )
            ->toContain( 'export interface ToggleFieldConfig extends BaseFieldConfig {' );
    } );

    it( 'renders a React switch with its size, color and read-only state', function () use ( $resource, $between ): void {
        $toggle = $between( $resource( 'react/components/fields/ChoiceField.tsx' ), 'export function ToggleField(', "\n}\n" );

        expect( $toggle )->toContain( '<Toggle' )
            ->toContain( 'color={getToggleColor( field )}' )
            ->toContain( 'getToggleSizeClass( field )' )
            ->toContain( 'disabled={readOnly}' )
            ->toContain( "aria-readonly={readOnly ? 'true' : undefined}" )
            ->and( $resource( 'react/components/fields/FieldRenderer.tsx' ) )->toContain( 'toggle: ToggleField,' )
            ->and( $resource( 'react/index.ts' ) )->toContain( "\tToggleField,\n" );
    } );

    it( 'renders a Vue switch with its size, color and read-only state', function () use ( $resource, $between ): void {
        $choice = $resource( 'vue/components/fields/ChoiceField.vue' );
        $toggle = $between( $choice, 'v-else-if="field.type === \'toggle\'"', '/>' );

        expect( $choice )->toContain( "import { Checkbox, Select, Toggle } from '@artisanpack-ui/vue/form';" )
            ->and( $toggle )->toContain( ':color="getToggleColor( field )"' )
            ->toContain( 'getToggleSizeClass( field )' )
            ->toContain( ':disabled="readOnly"' )
            ->and( $resource( 'vue/components/fields/FieldRenderer.vue' ) )
            ->toContain( "const CHOICE_TYPES = new Set( ['select', 'radio', 'checkbox', 'toggle', 'checkbox_group', 'select_multiple'] );" );
    } );

    it( 'reads the size and color like the Blade partial', function () use ( $resource ): void {
        $settings = $resource( 'shared/fieldSettings.ts' );

        expect( $settings )->toContain( "export const TOGGLE_COLORS = ['primary', 'secondary', 'accent', 'success', 'warning', 'error', 'info'] as const;" )
            ->toContain( 'export function getToggleSizeClass( field: FormField ): string {' )
            ->toContain( "return ( TOGGLE_COLORS as readonly string[] ).includes( String( color ) ) ? color as ToggleColor : 'primary';" )
            ->toContain( "\tif ( ACCEPTANCE_FIELD_TYPES.has( field.type ) ) {\n\t\treturn source === true" );
    } );

    it( 'is offered in both palettes and builder previews', function () use ( $resource ): void {
        foreach ( ['react/components/admin/FieldPalette.tsx', 'vue/components/admin/FieldPalette.vue'] as $palette ) {
            expect( $resource( $palette ) )->toContain( "{ type: 'toggle', label: t( 'Toggle' ), icon: 'toggle', category: 'choice' }," )
                ->toContain( "\ttoggle: 'M5 4h6" );
        }

        expect( $resource( 'shared/fieldTypeLabels.ts' ) )->toContain( "toggle: t( 'Toggle' )," )
            ->and( $resource( 'react/components/admin/FormBuilder.tsx' ) )->toContain( '<input type="checkbox" role="switch" className="toggle" disabled />' )
            ->and( $resource( 'vue/components/admin/FormBuilder.vue' ) )->toContain( '<input type="checkbox" role="switch" class="toggle" disabled />' );
    } );
} );

describe( 'Autocomplete tokens in React and Vue (issue #94)', function () use ( $resource ): void {
    it( 'is part of the field payload type', function () use ( $resource ): void {
        expect( $resource( 'types/artisanpack-forms.d.ts' ) )->toContain( 'autocomplete?: string | null;' );
    } );

    it( 'is passed to every React text, select and date input', function () use ( $resource ): void {
        expect( substr_count( $resource( 'react/components/fields/TextField.tsx' ), 'autoComplete={field.autocomplete ?? undefined}' ) )->toBe( 7 )
            ->and( substr_count( $resource( 'react/components/fields/ChoiceField.tsx' ), 'autoComplete={field.autocomplete ?? undefined}' ) )->toBe( 1 )
            ->and( substr_count( $resource( 'react/components/fields/AdvancedField.tsx' ), 'autoComplete={field.autocomplete ?? undefined}' ) )->toBe( 2 );
    } );

    it( 'is set on the native Vue control through a directive', function () use ( $resource ): void {
        $directive = $resource( 'vue/components/fields/autocomplete.ts' );

        expect( $directive )->toContain( "control.setAttribute( 'autocomplete', token );" )
            ->toContain( "control.removeAttribute( 'autocomplete' );" )
            ->toContain( 'mounted: ( el, binding ) => apply( el, binding.value ),' )
            ->toContain( 'updated: ( el, binding ) => apply( el, binding.value ),' )
            ->and( substr_count( $resource( 'vue/components/fields/TextField.vue' ), 'v-autocomplete="field.autocomplete"' ) )->toBe( 6 )
            ->and( substr_count( $resource( 'vue/components/fields/ChoiceField.vue' ), 'v-autocomplete="field.autocomplete"' ) )->toBe( 1 )
            ->and( substr_count( $resource( 'vue/components/fields/AdvancedField.vue' ), 'v-autocomplete="field.autocomplete"' ) )->toBe( 2 );
    } );
} );

describe( 'Dynamic population in React and Vue (issue #95)', function () use ( $resource, $between ): void {
    beforeEach( function () use ( $resource ): void {
        $this->population = $resource( 'shared/dynamicPopulation.ts' );
    } );

    it( 'lists the same merge tags as the server', function (): void {
        preg_match( '/export const MERGE_TAGS = \[(.*?)\];/', $this->population, $matches );

        expect( str_getcsv( str_replace( "'", '"', $matches[1] ) ) )
            ->toBe( MergeTags::available() )
            ->and( $this->population )->toContain( 'export const MAX_POPULATED_LENGTH = ' . DynamicPopulation::MAX_LENGTH . ';' );
    } );

    it( 'prefers the prefill, then the query string, then the resolved default', function () use ( $between ): void {
        $populate = $between( $this->population, 'export function getPopulatedValue(', "\n}\n" );

        $prefill = strpos( $populate, 'source.prefill[parameter]' );
        $query   = strpos( $populate, 'page?.query.has( parameter )' );

        expect( $populate )->toContain( 'field.resolved_default_value !== undefined ? field.resolved_default_value : field.default_value' )
            ->toContain( 'base = resolvePageMergeTags( base, page );' )
            ->and( $prefill )->toBeLessThan( $query );
    } );

    it( 'sanitizes values and matches choice options', function () use ( $between ): void {
        expect( $between( $this->population, 'export function sanitizePopulatedValue(', "\n}\n" ) )
            ->toContain( ".replace( /<[^>]*>/g, '' )" )
            ->toContain( '.slice( 0, MAX_POPULATED_LENGTH );' )
            ->and( $between( $this->population, 'function coerce(', "\n}\n" ) )
            ->toContain( ".flatMap( ( item ) => item.split( ',' ) )" )
            ->toContain( 'return matchOption( field, value ) ?? fallback' )
            ->toContain( 'return values.length > 0 ? Array.from( new Set( values ) ) : fallback;' );
    } );

    it( 'keeps read-only fields on the value the server stores', function () use ( $resource ): void {
        expect( $resource( 'shared/fieldSettings.ts' ) )
            ->toContain( "\t\t? field.read_only_value\n\t\t: getPopulatedValue( field, population );" );
    } );

    it( 'takes a prefill in the React and Vue renderers', function () use ( $resource ): void {
        expect( $resource( 'react/hooks/useForm.ts' ) )->toContain( 'prefill?: Record<string, unknown>;' )
            ->toContain( 'prefillRef.current = prefill;' )
            ->and( $resource( 'react/components/FormRenderer.tsx' ) )->toContain( "\t\tprefill,\n\t} );" )
            ->and( $resource( 'vue/composables/useForm.ts' ) )->toContain( 'buildDefaults( data.data.fields, prefill )' )
            ->toContain( 'buildDefaults( form.value.fields, prefill )' )
            ->and( $resource( 'vue/components/FormRenderer.vue' ) )->toContain( 'prefill: props.prefill,' );
    } );

    it( 'shows the merge tags beside the Default Value input', function () use ( $resource ): void {
        expect( $resource( 'react/components/admin/FieldEditor.tsx' ) )->toContain( "hint={t( 'Merge tags: :tags', { tags: MERGE_TAGS.join( ', ' ) } )}" )
            ->and( $resource( 'vue/components/admin/FieldEditor.vue' ) )->toContain( ":hint=\"t( 'Merge tags: :tags', { tags: MERGE_TAGS.join( ', ' ) } )\"" );
    } );

    it( 'is part of the render payload type', function () use ( $resource ): void {
        expect( $resource( 'types/artisanpack-forms.d.ts' ) )->toContain( 'dynamic_parameter?: string | null;' )
            ->toContain( 'resolved_default_value?: string | boolean | string[] | Record<string, string> | null;' );
    } );
} );

describe( 'Custom validation messages in React and Vue (issue #96)', function () use ( $resource, $between ): void {
    it( 'replaces the client messages like the server does', function () use ( $resource, $between ): void {
        $validate = $between( $resource( 'shared/validation.ts' ), 'export function validateField(', "\n}\n" );

        expect( $validate )->toContain( 'return [field.required_message ?? `${field.label ?? field.name} is required.`];' )
            ->toContain( 'return errors.length > 0 && field.error_message ? [field.error_message] : errors;' );
    } );

    it( 'is part of the field payload type', function () use ( $resource ): void {
        expect( $resource( 'types/artisanpack-forms.d.ts' ) )->toContain( '    error_message?: string;' )
            ->toContain( '    required_message?: string;' );
    } );
} );

describe( 'Admin labels in React and Vue (issue #97)', function () use ( $resource ): void {
    it( 'labels submitted values by their admin label', function () use ( $resource ): void {
        expect( $resource( 'react/components/admin/SubmissionDetail.tsx' ) )->toContain( '{value.field_admin_label || value.field_label || value.field_name}' )
            ->and( $resource( 'vue/components/admin/SubmissionDetail.vue' ) )->toContain( '{{ value.field_admin_label || value.field_label || value.field_name }}' )
            ->and( $resource( 'types/artisanpack-forms.d.ts' ) )->toContain( 'field_admin_label: string | null;' );
    } );
} );
