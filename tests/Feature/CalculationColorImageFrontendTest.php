<?php

declare( strict_types=1 );

/**
 * Guards the React, Vue and shared JS side of issues #134 (image choices),
 * #135 (calculation) and #136 (color). The surfaces are validated by source
 * content (the package ships no JS build or test harness of its own),
 * matching SurveyFieldsFrontendTest; CalculationColorImageParityTest runs
 * the shared helpers themselves.
 */

$resource = static fn ( string $path ): string => file_get_contents( __DIR__ . '/../../resources/js/' . $path );

describe( 'shared helpers', function () use ( $resource ): void {
    it( 'validates colors and works calculations out alongside conditional logic', function () use ( $resource ): void {
        expect( $resource( 'shared/validation.ts' ) )->toContain( "\tcolor: validateColor,\n};" )
            ->and( $resource( 'shared/calculation.ts' ) )
            ->toContain( 'export function resolveCalculatedValues(' )
            ->toContain( 'export function applyCalculations(' )
            ->toContain( 'export function getFormulaFields(' )
            ->not->toContain( 'eval(' )
            ->not->toContain( 'new Function' );
    } );

    it( 'labels the new types', function () use ( $resource ): void {
        expect( $resource( 'shared/fieldTypeLabels.ts' ) )->toContain( "color: t( 'Color' )," )
            ->toContain( "calculation: t( 'Calculation' )," );
    } );
} );

describe( 'TypeScript definitions', function () use ( $resource ): void {
    it( 'lists the new types, settings and payload keys', function () use ( $resource ): void {
        expect( $resource( 'types/artisanpack-forms.d.ts' ) )
            ->toContain( "    | 'slider'\n    | 'color'\n    | 'calculation'\n" )
            ->toContain( 'export interface CalculationFieldConfig extends CommonFieldConfig {' )
            ->toContain( 'calculation?: CalculationSettings;' )
            ->toContain( "display?: 'list' | 'images';" )
            ->toContain( 'image?: string;' )
            ->toContain( 'image_alt?: string;' )
            ->toContain( "| 'date_expression' | 'formula';" );
    } );
} );

describe( 'React', function () use ( $resource ): void {
    it( 'renders the new types', function () use ( $resource ): void {
        expect( $resource( 'react/components/fields/FieldRenderer.tsx' ) )
            ->toContain( "\tcolor: ColorField,\n" )
            ->toContain( "\tcalculation: CalculationField,\n" )
            ->and( $resource( 'react/components/fields/ColorField.tsx' ) )
            ->toContain( 'type="color"' )
            ->toContain( "aria-label={t( ':field color picker', { field: field.label || field.name } )}" )
            ->toContain( 'onBlur={( e ) => onChange( normalizeColor( e.target.value ) )}' )
            ->and( $resource( 'react/components/fields/CalculationField.tsx' ) )
            ->toContain( 'readOnly' )
            ->toContain( 'aria-readonly="true"' )
            ->and( $resource( 'react/hooks/useForm.ts' ) )
            ->toContain( 'return resolveCalculatedValues( form.fields, enteredValues );' );
    } );

    it( 'names each image choice by its label', function () use ( $resource ): void {
        $choices = $resource( 'react/components/fields/ChoiceField.tsx' );

        expect( $choices )->toContain( 'function ImageChoice(' )
            ->toContain( 'aria-labelledby={asImage ? `${optionId}-label` : undefined}' )
            ->toContain( 'aria-labelledby={`field-${field.name}-${index}-label`}' )
            ->toContain( 'alt={getChoiceImageAlt( option )}' );
    } );

    it( 'edits formulas and option images in the builder', function () use ( $resource ): void {
        expect( $resource( 'react/components/admin/FieldEditor.tsx' ) )
            ->toContain( "if ( setting.input === 'formula' ) {" )
            ->toContain( 'onChange( insertFormulaTag( input.current, e.target.value ) );' )
            ->and( $resource( 'react/components/admin/OptionsEditor.tsx' ) )
            ->toContain( "aria-label={t( 'Image alt text: :label', { label: option.label || option.value } )}" )
            ->toContain( 'accept="image/jpeg,image/png,image/gif,image/webp"' )
            ->and( $resource( 'react/components/admin/FormBuilder.tsx' ) )
            ->toContain( "formPath( '/fields/choice-images' )" )
            ->and( $resource( 'react/hooks/useApi.ts' ) )
            ->toContain( 'headers: buildHeaders( !isFormData ),' )
            ->and( $resource( 'react/components/admin/FieldPalette.tsx' ) )
            ->toContain( "{ type: 'color', label: t( 'Color' ), icon: 'color', category: 'advanced' }," )
            ->toContain( "{ type: 'calculation', label: t( 'Calculation' ), icon: 'calculation', category: 'advanced' }," );
    } );

    it( 'exports the new components and helpers', function () use ( $resource ): void {
        expect( $resource( 'react/index.ts' ) )
            ->toContain( "export { ColorField } from './components/fields/ColorField';" )
            ->toContain( "export { CalculationField } from './components/fields/CalculationField';" )
            ->toContain( "from '../shared/calculation';" )
            ->toContain( "from '../shared/color';" );
    } );
} );

describe( 'Vue', function () use ( $resource ): void {
    it( 'renders the new types', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/FieldRenderer.vue' ) )
            ->toContain( "v-else-if=\"componentGroup === 'color'\"" )
            ->toContain( "v-else-if=\"componentGroup === 'calculation'\"" )
            ->and( $resource( 'vue/components/fields/ColorField.vue' ) )
            ->toContain( 'type="color"' )
            ->toContain( ':aria-label="pickerLabel"' )
            ->and( $resource( 'vue/composables/useForm.ts' ) )
            ->toContain( 'return resolveCalculatedValues( form.value.fields, values.value );' );
    } );

    it( 'names each image choice by its label', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/ChoiceField.vue' ) )
            ->toContain( '<ImageChoice' )
            ->toContain( ':aria-labelledby="`field-${field.name}-${index}-label`"' )
            ->and( $resource( 'vue/components/fields/ImageChoice.vue' ) )
            ->toContain( '<img v-if="image" :src="image" :alt="alt"' );
    } );

    it( 'edits formulas and option images in the builder', function () use ( $resource ): void {
        expect( $resource( 'vue/components/admin/FieldSettingControl.vue' ) )
            ->toContain( "v-else-if=\"setting.input === 'formula'\"" )
            ->toContain( '<option v-for="entry in formulaFields" :key="entry.name" :value="entry.tag">' )
            ->and( $resource( 'vue/components/admin/OptionsEditor.vue' ) )
            ->toContain( '@change="handleUpload( index, $event )"' )
            ->and( $resource( 'vue/components/admin/FormBuilder.vue' ) )
            ->toContain( ':upload-choice-image="uploadChoiceImage"' )
            ->and( $resource( 'vue/composables/useApi.ts' ) )
            ->toContain( 'headers: buildHeaders( !isFormData ),' );
    } );

    it( 'exports the new components and helpers', function () use ( $resource ): void {
        expect( $resource( 'vue/index.ts' ) )
            ->toContain( "export { default as ColorField } from './components/fields/ColorField.vue';" )
            ->toContain( "export { default as CalculationField } from './components/fields/CalculationField.vue';" );
    } );
} );
