<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\FieldTypes\CompositeFieldType;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\PredefinedChoices;

/**
 * Guards the React, Vue and shared JS side of issues #104 (bulk and
 * predefined choices), #105 (choice field options), #106 (searchable
 * select), #107 (dynamic choices) and #108 (composite fields). The surfaces
 * are validated by source content (the package ships no JS build or test
 * harness of its own), matching FieldDisplayPrivacyFrontendTest.
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
    it( 'describe the choice and composite settings and payload keys', function () use ( $resource ): void {
        expect( $resource( 'types/artisanpack-forms.d.ts' ) )
            ->toContain( "export type ChoiceLayout = 'stacked' | 'inline' | '2-col' | '3-col';" )
            ->toContain( "export type SubLabelPlacement = 'below' | 'above' | 'hidden';" )
            ->toContain( "export type CompositeExportFormat = 'columns' | 'single';" )
            ->toContain( 'default?: boolean;' )
            ->toContain( 'choices_source?: string;' )
            ->toContain( 'layout?: ChoiceLayout;' )
            ->toContain( 'randomize?: boolean;' )
            ->toContain( 'select_all?: boolean;' )
            ->toContain( 'searchable?: boolean;' )
            ->toContain( 'export interface SubInputDefinition {' )
            ->toContain( 'export interface PredefinedChoiceList {' )
            ->toContain( 'sub_inputs?: SubInputDefinition[];' )
            ->toContain( 'required_sub_inputs?: string[];' )
            ->toContain( 'sub_label_placement?: SubLabelPlacement;' );
    } );
} );

describe( 'shared helpers', function () use ( $resource, $between ): void {
    it( 'mirror the server\'s choice layouts and their classes', function () use ( $resource, $between ): void {
        $choices = $resource( 'shared/choices.ts' );
        $classes = $between( $choices, 'export function getChoiceLayoutClasses(', "\n}\n" );

        expect( $choices )->toContain( "export const CHOICE_LAYOUTS: readonly ChoiceLayout[] = ['" . implode( "', '", FormField::CHOICE_LAYOUTS ) . "'];" );

        foreach ( FormField::CHOICE_LAYOUTS as $layout ) {
            expect( $classes )->toContain( FormField::factory()->make( ['type' => 'radio', 'field_config' => ['layout' => $layout]] )->getChoiceLayoutClasses() );
        }
    } );

    it( 'mirror the bulk choice syntax', function () use ( $resource, $between ): void {
        $choices = $resource( 'shared/choices.ts' );

        expect( $choices )->toContain( 'export const MAX_BULK_CHOICES = ' . PredefinedChoices::MAX_CHOICES . ';' )
            ->toContain( "export const BULK_SEPARATOR = '" . PredefinedChoices::SEPARATOR . "';" )
            ->toContain( 'export function parseBulkChoices( text: string ): FieldOption[]' )
            ->toContain( 'export function toBulkChoices( choices: unknown ): string' )
            ->toContain( "export function applyBulkChoices( existing: unknown, choices: FieldOption[], mode: 'replace' | 'append' = 'replace' ): FieldOption[]" )
            ->and( $between( $choices, 'export function parseBulkChoices(', "\n}\n" ) )
            ->toContain( 'value = value === \'\' ? label : value;' )
            ->toContain( 'if ( value === \'\' || seen.has( value ) ) {' );
    } );

    it( 'shuffle randomized choices once per field object with a seeded PRNG', function () use ( $resource ): void {
        expect( $resource( 'shared/choices.ts' ) )
            ->toContain( 'const choiceSeeds = new WeakMap<object, number>();' )
            ->toContain( 'export function shuffleWithSeed<T>( items: readonly T[], seed: number ): T[]' )
            ->toContain( 'return randomizesChoices( field ) && options.length > 1 ? shuffleWithSeed( options, getChoiceSeed( field ) ) : options;' );
    } );

    it( 'mirror the composite sub-input rules and settings', function () use ( $resource, $between ): void {
        $composite = $resource( 'shared/composite.ts' );

        expect( $composite )->toContain( 'export const SUB_INPUT_MAX_LENGTH = ' . CompositeFieldType::SUB_INPUT_MAX_LENGTH . ';' )
            ->toContain( "export type SubInputSetting = '" . implode( "' | '", CompositeFieldType::SUB_INPUT_SETTINGS ) . "';" )
            ->toContain( 'export function getConditionTargets( field: FormField ): ConditionTarget[]' )
            ->and( $between( $composite, 'export function buildSubInputUpdate(', "\n}\n" ) )
            ->toContain( "if ( setting === 'enabled' && !flag && enabled.length === 1 && enabled[0] === key ) {" )
            ->toContain( '.slice( 0, 255 )' );
    } );

    it( 'test a composite field\'s sub-input in conditional logic', function () use ( $resource ): void {
        expect( $resource( 'shared/conditionalLogic.ts' ) )
            ->toContain( 'const [fieldName, subKey] = resolveFieldReference( fieldRef, uuidToName, formData );' )
            ->toContain( '( fieldValue as Record<string, unknown> )[subKey] ?? null' );
    } );

    it( 'clear only the errors of the composite parts that changed', function () use ( $resource, $between ): void {
        $clear = $between( $resource( 'shared/composite.ts' ), 'export function clearChangedFieldErrors(', "\n}\n" );

        expect( $clear )->toContain( 'if ( !isPartsMap( value ) || !isPartsMap( previous ) || value[part] !== previous[part] ) {' )
            ->and( $resource( 'react/hooks/useForm.ts' ) )
            ->toContain( 'setErrors( ( prev ) => clearChangedFieldErrors( prev, name, previous, value ) );' )
            ->and( $resource( 'vue/composables/useForm.ts' ) )
            ->toContain( 'errors.value = clearChangedFieldErrors( errors.value, name, previous, value );' );
    } );

    it( 'compare a rule on a whole composite field against its formatted value', function () use ( $resource ): void {
        expect( $resource( 'shared/conditionalLogic.ts' ) )->toContain( 'fieldValue = formatCompositeForCondition( fieldValue );' )
            ->and( file_get_contents( __DIR__ . '/../../resources/views/livewire/form-renderer.blade.php' ) )
            ->toContain( '// A rule on a whole composite field compares its filled parts, joined' );
    } );

    it( 'validate composite fields one sub-input at a time', function () use ( $resource ): void {
        expect( $resource( 'shared/validation.ts' ) )
            ->toContain( 'export function validateCompositeField( field: FormField, value: unknown ): Record<string, string[]>' )
            ->toContain( 'errors[`${field.name}.${subInput.key}`]' )
            ->toContain( 'Object.assign( errors, validateCompositeField( field, formData[field.name] ) );' );
    } );

    it( 'start a composite field with a blank string per enabled sub-input', function () use ( $resource ): void {
        expect( $resource( 'shared/fieldSettings.ts' ) )->toContain( 'if ( isCompositeField( field ) ) {' );
    } );

    it( 'resolve a sub-input reference in the Livewire renderer\'s conditional logic', function (): void {
        expect( file_get_contents( __DIR__ . '/../../resources/views/livewire/form-renderer.blade.php' ) )
            ->toContain( "// Check if it's a composite field's sub-input" )
            ->toContain( 'value[fieldRef.slice(dot + 1)]' );
    } );
} );

describe( 'React renderer', function () use ( $resource, $between ): void {
    it( 'lays out, shuffles and selects all choices', function () use ( $resource, $between ): void {
        $choice = $resource( 'react/components/fields/ChoiceField.tsx' );
        $group  = $between( $choice, 'export function CheckboxGroupField(', "\n}\n" );

        expect( $between( $choice, 'export function RadioField(', "\n}\n" ) )
            ->toContain( 'const options = getRenderOptions( field );' )
            ->toContain( 'className={`${getChoiceLayoutClasses( field )} ${field.css_classes ?? \'\'}`}' )
            ->and( $group )->toContain( 'const showSelectAll = offersSelectAll( field ) && optionValues.length > 1;' )
            ->toContain( 'selectAllRef.current.indeterminate = selectedCount > 0 && selectedCount < optionValues.length;' )
            ->toContain( "aria-controls={optionValues.map( ( _, index ) => `field-\${field.name}-\${index}` ).join( ' ' )}" )
            ->toContain( "{t( 'Select All' )}" );
    } );

    it( 'renders searchable selects as an ARIA combobox', function () use ( $resource ): void {
        $combobox = $resource( 'react/components/fields/Combobox.tsx' );

        expect( $resource( 'react/components/fields/ChoiceField.tsx' ) )
            ->toContain( 'return <SearchableSelect {...props} multiple={false} />;' )
            ->toContain( 'return <SearchableSelect {...props} multiple />;' )
            ->and( $combobox )->toContain( 'role="combobox"' )
            ->toContain( 'aria-autocomplete="list"' )
            ->toContain( 'aria-controls={listboxId}' )
            ->toContain( 'aria-expanded={open}' )
            ->toContain( 'aria-activedescendant={activeOption ? optionId( activeOption ) : undefined}' )
            ->toContain( 'role="listbox"' )
            ->toContain( "aria-multiselectable={multiple ? 'true' : undefined}" )
            ->toContain( 'role="option"' )
            ->toContain( 'aria-selected={selected.includes( option.value )}' )
            ->toContain( 'role="status" aria-live="polite"' )
            ->toContain( "t( ':count results available.', { count: filtered.length } )" );
    } );

    it( 'renders composite fields in a fieldset with labelled, autocompleted inputs', function () use ( $resource ): void {
        $composite = $resource( 'react/components/fields/CompositeField.tsx' );

        expect( $resource( 'react/components/fields/FieldRenderer.tsx' ) )
            ->toContain( '( isCompositeField( field ) ? CompositeField : undefined );' )
            ->toContain( 'subErrors={subErrors}' )
            ->and( $composite )->toContain( '<fieldset' )
            ->toContain( '<legend' )
            ->toContain( 'autoComplete={subInput.autocomplete ?? undefined}' )
            ->toContain( 'htmlFor={inputId}' )
            ->toContain( 'aria-invalid={subError ? true : undefined}' )
            ->toContain( "t( 'Please fix :count highlighted parts of :field.'" )
            ->and( $resource( 'react/components/FormRenderer.tsx' ) )
            ->toContain( 'subErrors={getSubInputErrors( errors, field.name )}' );
    } );

    it( 'submits a composite value per sub-input and routes its errors to the right step', function () use ( $resource ): void {
        expect( $resource( 'react/hooks/useForm.ts' ) )
            ->toContain( 'formDataObj.append( `data[${key}][${subKey}]`, String( part ?? \'\' ) );' )
            ->toContain( 'new Set( Object.keys( validationErrors ).map( getErrorFieldName ) )' )
            ->toContain( 'new Set( Object.keys( serverErrors ).map( getErrorFieldName ) )' );
    } );
} );

describe( 'React builder', function () use ( $resource ): void {
    it( 'edits option defaults and bulk choices with the predefined lists', function () use ( $resource ): void {
        $editor = $resource( 'react/components/admin/OptionsEditor.tsx' );

        expect( $editor )->toContain( 'onChange( setOptionDefault( options, index, e.target.checked, multiple ) )' )
            ->toContain( 'onChange( applyBulkChoices( options, parseBulkChoices( bulkText ), mode ) );' )
            ->toContain( 'setBulkText( toBulkChoices( list.choices ) );' )
            ->toContain( "{t( 'Bulk Add / Predefined Choices' )}" )
            ->toContain( 'if ( source ) {' )
            ->toContain( 'const match = /^option_(\\d+)$/.exec( option.value );' )
            ->and( $resource( 'react/components/admin/FieldEditor.tsx' ) )
            ->toContain( "<OptionsEditor\n\t\t\t\t\tkey={field.id}" )
            ->and( $resource( 'react/components/admin/SubInputsEditor.tsx' ) )
            ->toContain( '<tr key={`${field.id}-${subInput.key}`}>' )
            ->and( $resource( 'react/components/admin/FormBuilder.tsx' ) )
            ->toContain( '`/${encodeURIComponent( formSlug )}/fields/predefined-choices`' )
            ->toContain( '}, [get, form?.id, formSlug] );' )
            ->toContain( 'predefinedChoices={predefinedChoices}' );
    } );

    it( 'edits a composite field\'s sub-inputs', function () use ( $resource ): void {
        expect( $resource( 'react/components/admin/SubInputsEditor.tsx' ) )
            ->toContain( 'const update = buildSubInputUpdate( field, key, setting, value );' )
            ->toContain( 'disabled={subInput.enabled && enabledCount === 1}' )
            ->and( $resource( 'react/components/admin/FieldEditor.tsx' ) )
            ->toContain( '{isComposite && (' )
            ->toContain( '{!isLayoutField && !isComposite && field.type !== \'file\' && (' );
    } );

    it( 'lists each sub-input as a condition target', function () use ( $resource ): void {
        expect( $resource( 'react/components/admin/ConditionalLogicEditor.tsx' ) )
            ->toContain( 'const availableTargets = useMemo( () => availableFields.flatMap( getConditionTargets ), [availableFields] );' )
            ->toContain( 'field: firstTarget.ref,' );
    } );

    it( 'exports the new components and helpers', function () use ( $resource ): void {
        expect( $resource( 'react/index.ts' ) )
            ->toContain( "export { Combobox } from './components/fields/Combobox';" )
            ->toContain( "export { CompositeField } from './components/fields/CompositeField';" )
            ->toContain( 'parseBulkChoices,' )
            ->toContain( 'isCompositeField,' )
            ->toContain( "\tOptionsEditor,\n" );
    } );
} );

describe( 'Vue renderer', function () use ( $resource, $between ): void {
    it( 'lays out, shuffles and selects all choices', function () use ( $resource, $between ): void {
        $choice = $resource( 'vue/components/fields/ChoiceField.vue' );

        expect( $choice )->toContain( '? getRenderOptions( toRaw( props.field ) )' )
            ->toContain( 'const layoutClasses = computed( () => getChoiceLayoutClasses( props.field ) );' )
            ->toContain( 'selectAllInput.value.indeterminate = selectedOptionCount.value > 0 && selectedOptionCount.value < optionValues.value.length;' )
            ->toContain( "{{ t( 'Select All' ) }}" )
            ->and( $between( $choice, '<!-- Radio', '<!-- Checkbox' ) )
            ->toContain( 'role="radiogroup"' )
            ->toContain( ':class="[layoutClasses, field.css_classes ?? \'\']"' );
    } );

    it( 'renders searchable selects as an ARIA combobox', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/ChoiceField.vue' ) )
            ->toContain( '<FieldLayout v-if="searchable" :field="field" :layout="layout">' )
            ->and( $resource( 'vue/components/fields/Combobox.vue' ) )
            ->toContain( 'role="combobox"' )
            ->toContain( ':aria-activedescendant="activeOption ? optionId( activeOption ) : undefined"' )
            ->toContain( 'role="listbox"' )
            ->toContain( 'role="option"' )
            ->toContain( 'role="status" aria-live="polite"' );
    } );

    it( 'renders composite fields and passes their sub-input errors', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/FieldRenderer.vue' ) )
            ->toContain( "if ( isCompositeField( props.field ) ) {\n\t\treturn 'composite';" )
            ->toContain( ':sub-errors="subErrors"' )
            ->and( $resource( 'vue/components/fields/CompositeField.vue' ) )
            ->toContain( '<fieldset' )
            ->toContain( '<legend' )
            ->toContain( ':autocomplete="subInput.autocomplete ?? undefined"' )
            ->and( substr_count( $resource( 'vue/components/FormRenderer.vue' ), ':sub-errors="getSubInputErrors( errors, field.name )"' ) )->toBe( 2 )
            ->and( $resource( 'vue/composables/useForm.ts' ) )
            ->toContain( 'formDataObj.append( `data[${key}][${subKey}]`, String( part ?? \'\' ) );' );
    } );
} );

describe( 'Vue builder', function () use ( $resource ): void {
    it( 'edits option defaults, bulk choices and sub-inputs', function () use ( $resource ): void {
        expect( $resource( 'vue/components/admin/OptionsEditor.vue' ) )
            ->toContain( "emit( 'change', applyBulkChoices( props.options, parseBulkChoices( bulkText.value ), mode ) );" )
            ->toContain( 'setOptionDefault( options, index' )
            ->and( $resource( 'vue/components/admin/FieldEditor.vue' ) )
            ->toContain( "v-if=\"hasOptions\"\n\t\t\t\t\t\t:key=\"field.id\"" )
            ->and( $resource( 'vue/components/admin/SubInputsEditor.vue' ) )
            ->toContain( 'const update = buildSubInputUpdate( props.field, key, setting, value );' )
            ->and( $resource( 'vue/components/admin/FormBuilder.vue' ) )
            ->toContain( '/fields/predefined-choices' )
            ->toContain( ':predefined-choices="predefinedChoices"' )
            ->and( $resource( 'vue/components/admin/ConditionalLogicEditor.vue' ) )
            ->toContain( 'const availableTargets = computed( () => availableFields.value.flatMap( getConditionTargets ) );' );
    } );
} );

describe( 'translations', function () use ( $resource ): void {
    it( 'list the new admin and renderer strings', function () use ( $resource ): void {
        expect( json_decode( $resource( 'shared/translation-keys.json' ), true ) )
            ->toContain( 'Bulk Add / Predefined Choices', 'Select All', 'Show options', ':count results available.', 'Sub-Label', ':field: :part is required.' );
    } );
} );
