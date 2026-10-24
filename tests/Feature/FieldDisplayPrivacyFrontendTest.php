<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\FormField;

/**
 * Guards the React, Vue and shared JS side of issues #98, #100, #101, #102
 * and #103. The surfaces are validated by source content (the package ships
 * no JS build or test harness of its own), matching FieldSettingsBatchFrontendTest.
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

describe( 'TypeScript definitions', function () use ( $resource ): void {
    it( 'describe the new settings and payload keys', function () use ( $resource ): void {
        expect( $resource( 'types/artisanpack-forms.d.ts' ) )
            ->toContain( "export type LabelPlacement = 'top' | 'left' | 'hidden';" )
            ->toContain( "export type DescriptionPlacement = 'above' | 'below';" )
            ->toContain( "export type FieldVisibility = 'visible' | 'administrative';" )
            ->toContain( 'label_placement?: LabelPlacement | null;' )
            ->toContain( 'description_placement?: DescriptionPlacement | null;' )
            ->toContain( 'other_choice_label?: string;' )
            ->toContain( 'is_administrative?: boolean;' )
            ->toContain( 'no_duplicates?: boolean;' )
            ->toContain( 'do_not_store?: boolean;' )
            ->toContain( 'mask_in_admin?: boolean;' )
            ->toContain( 'redact_after_days?: number;' )
            ->toContain( 'enable_other?: boolean;' )
            ->toContain( 'is_masked: boolean;' )
            ->toContain( 'is_redacted: boolean;' )
            ->toContain( 'administrative_fields?: FormField[];' )
            ->toContain( 'admin_values?: Record<string, unknown>;' );
    } );
} );

describe( 'shared helpers', function () use ( $resource, $between ): void {
    it( 'mirror the server\'s "Other" choice placeholder and resolution', function () use ( $resource ): void {
        $settings = $resource( 'shared/fieldSettings.ts' );

        expect( $settings )->toContain( "export const OTHER_CHOICE_VALUE = '" . FormField::OTHER_CHOICE_VALUE . "';" )
            ->toContain( 'export const OTHER_CHOICE_MAX_LENGTH = ' . FormField::OTHER_CHOICE_MAX_LENGTH . ';' )
            ->toContain( 'export function resolveOtherChoices(' )
            ->toContain( 'export function getConditionOptions( field: FormField )' )
            ->toContain( 'export function getAdministrativeFormState(' );
    } );

    it( 'resolve a field\'s layout from the render payload, falling back to the display config', function () use ( $resource, $between ): void {
        $layout = $between( $resource( 'shared/fieldSettings.ts' ), 'export function getFieldLayout(', "\n}\n" );

        expect( $layout )->toContain( "const label = field.label_placement ?? LEGACY_LABEL_PLACEMENTS[displayConfig?.label_position ?? 'above'] ?? 'top';" )
            ->toContain( 'custom: Boolean( field.label_placement || field.description_placement ),' )
            ->toContain( "description: field.description_placement ?? 'below'," );
    } );

    it( 'reject a blank or overlong "Other" choice in client validation', function () use ( $resource ): void {
        expect( $resource( 'shared/validation.ts' ) )
            ->toContain( 'if ( allowsOtherChoice( field ) ) {' )
            ->toContain( 'item === OTHER_CHOICE_VALUE || String( item ).length > OTHER_CHOICE_MAX_LENGTH' );
    } );

    it( 'list every new admin string for translation', function () use ( $resource ): void {
        expect( json_decode( $resource( 'shared/translation-keys.json' ), true ) )
            ->toContain( 'Administrative Fields', 'Masked', 'Label Placement', 'Description Placement', ':label, please specify', 'Please specify' );
    } );
} );

describe( 'React renderer', function () use ( $resource, $between ): void {
    it( 'wraps inputs in the field layout and hands them the IDs it references', function () use ( $resource ): void {
        $layout = $resource( 'react/components/fields/FieldLayout.tsx' );

        expect( $layout )->toContain( 'export function FieldLayout( { field, layout, renderLabel = true, group = false, children }: FieldLayoutProps ) {' )
            ->toContain( "layout.label === 'hidden' ? 'sr-only' : ''" )
            ->toContain( '<label id={layout.labelId} htmlFor={layout.inputId} className={labelClass}>' )
            ->toContain( "{layout.description === 'above' && description}" )
            // Text, phone, number and textarea add their notes' IDs through getNotedLabelProps() (#115, #116, #118).
            ->and( substr_count( $resource( 'react/components/fields/TextField.tsx' ), '{...getInputLabelProps( field, layout, displayConfig, error )}' ) )->toBe( 3 )
            ->and( substr_count( $resource( 'react/components/fields/TextField.tsx' ), '{...getNotedLabelProps( field, layout, displayConfig, error )}' ) )->toBe( 4 )
            ->and( $resource( 'shared/fieldSettings.ts' ) )->toContain( "'aria-describedby': getLayoutDescribedBy( field, layout, error )," );
    } );

    it( 'renders native radios with an "Other" choice and its text input', function () use ( $resource, $between ): void {
        $radio = $between( $resource( 'react/components/fields/ChoiceField.tsx' ), 'export function RadioField(', "\n}\n" );

        expect( $radio )->toContain( 'type="radio"' )
            ->toContain( 'checked={selected === option.value}' )
            ->toContain( '{ value: OTHER_CHOICE_VALUE, label: getOtherChoiceLabel( field ) }' )
            ->toContain( '<OtherChoiceInput field={field} value={otherValue} error={error} readOnly={readOnly} onChange={onOtherChange} />' )
            ->toContain( '<div role="group" aria-readonly="true">{radio}</div>' );
    } );

    it( 'labels the "Other" text input for screen readers', function () use ( $resource, $between ): void {
        $input = $between( $resource( 'react/components/fields/ChoiceField.tsx' ), 'function OtherChoiceInput(', "\n}\n" );

        expect( $input )->toContain( "{t( ':label, please specify', { label: getOtherChoiceLabel( field ) } )}" )
            ->toContain( 'className="sr-only"' )
            ->toContain( 'maxLength={OTHER_CHOICE_MAX_LENGTH}' );
    } );

    it( 'keeps "Other" text in the form state and resolves it to validate and submit', function () use ( $resource ): void {
        $useForm = $resource( 'react/hooks/useForm.ts' );

        expect( $useForm )->toContain( 'const [otherValues, setOtherValues] = useState<Record<string, string>>( {} );' )
            ->toContain( 'resolveOtherChoices( fieldsToValidate, values, otherValues ),' )
            ->toContain( 'for ( const [key, value] of Object.entries( resolveOtherChoices( allFields, values, otherValues ) ) ) {' )
            ->and( $resource( 'react/components/FormRenderer.tsx' ) )
            ->toContain( "otherValue={otherValues[field.name] ?? ''}" )
            ->toContain( 'onOtherChange={( text ) => setOtherValue( field.name, text )}' );
    } );
} );

describe( 'Vue renderer', function () use ( $resource ): void {
    it( 'wraps inputs in the field layout component', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/FieldLayout.vue' ) )
            ->toContain( '<slot v-if="!layout.custom" />' )
            ->toContain( '<label v-else :id="layout.labelId" :for="layout.inputId" :class="labelClass">' )
            ->and( $resource( 'vue/components/fields/TextField.vue' ) )
            ->toContain( '<FieldLayout :field="field" :layout="layout">' )
            ->toContain( 'v-described-by="describedBy"' )
            ->and( $resource( 'vue/components/fields/describedBy.ts' ) )
            ->toContain( "control.setAttribute( 'aria-describedby', ids );" );
    } );

    it( 'adds the "Other" choice and emits its text', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/ChoiceField.vue' ) )
            ->toContain( '? [...options.value, { value: OTHER_CHOICE_VALUE, label: getOtherChoiceLabel( props.field ) }]' )
            ->toContain( "'update:otherValue': [text: string];" )
            ->toContain( "{{ t( ':label, please specify', { label: getOtherChoiceLabel( field ) } ) }}" )
            ->and( $resource( 'vue/components/fields/FieldRenderer.vue' ) )
            ->toContain( '@update:other-value="emit( \'update:otherValue\', $event )"' )
            ->and( $resource( 'vue/composables/useForm.ts' ) )
            ->toContain( 'resolveOtherChoices( form.value.fields, values.value, otherValues.value )' );
    } );
} );

describe( 'React and Vue admin', function () use ( $resource ): void {
    it( 'set the form-level placement defaults', function () use ( $resource ): void {
        expect( $resource( 'react/components/admin/FormBuilder.tsx' ) )
            ->toContain( 'updateFormSetting( { label_placement: ( e.target.value || null ) as LabelPlacement | null } )' )
            ->toContain( 'updateFormSetting( { description_placement: ( e.target.value || null ) as DescriptionPlacement | null } )' )
            ->and( $resource( 'vue/components/admin/FormBuilder.vue' ) )
            ->toContain( 'updateFormSetting( { label_placement: ( $event || null ) as LabelPlacement | null } )' );
    } );

    it( 'show masked and redacted values and edit administrative fields', function ( string $path ): void {
        expect( file_get_contents( __DIR__ . '/../../resources/js/' . $path ) )
            ->toContain( 'is_masked' )
            ->toContain( 'is_redacted' )
            ->toContain( 'getAdministrativeFormState( loaded.administrative_fields ?? [], loaded.values ?? [] )' )
            ->toContain( '{ admin_values: values },' )
            ->toContain( "replace( /^admin_values\\./, '' )" );
    } )->with( ['react/components/admin/SubmissionDetail.tsx', 'vue/components/admin/SubmissionDetail.vue'] );

    it( 'offer "Other" in the conditional logic value list', function ( string $path ): void {
        expect( file_get_contents( __DIR__ . '/../../resources/js/' . $path ) )
            ->toContain( 'getConditionOptions( field )' );
    } )->with( ['react/components/admin/ConditionalLogicEditor.tsx', 'vue/components/admin/ConditionalLogicEditor.vue'] );
} );
