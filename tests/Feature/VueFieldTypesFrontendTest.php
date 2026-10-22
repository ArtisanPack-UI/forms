<?php

declare( strict_types=1 );

/**
 * Guards the Vue side of issues #89, #90, #91, #92 and #99. The surfaces are
 * validated by source content (the package ships no JS build or test harness
 * of its own), matching the convention used by FieldSettingsFrontendTest.
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

$adminComponents = [
    'ConditionalLogicEditor',
    'FieldEditor',
    'FieldPalette',
    'FormBuilder',
    'FormsList',
    'NotificationEditor',
    'SubmissionDetail',
    'SubmissionsList',
];

describe( 'Vue schema-driven field settings (issue #91)', function () use ( $resource, $between ): void {
    beforeEach( function () use ( $resource ): void {
        $this->editor  = $resource( 'vue/components/admin/FieldEditor.vue' );
        $this->control = $resource( 'vue/components/admin/FieldSettingControl.vue' );
    } );

    it( 'renders each tab\'s settings from the field\'s schema', function (): void {
        expect( $this->editor )
            ->toContain( "getSettingsForTab( currentField.value, 'general' )" )
            ->toContain( "getSettingsForTab( currentField.value, 'validation' )" )
            ->toContain( "getSettingsForTab( currentField.value, 'advanced' )" )
            ->toContain( 'v-for="setting in generalSettings"' )
            ->toContain( 'v-for="setting in validationSettings"' )
            ->toContain( 'v-for="setting in advancedSettings"' );
    } );

    it( 'leaves no hard-coded per-type setting branches behind', function (): void {
        expect( $this->editor )
            ->not->toContain( "field.type === 'textarea'" )
            ->not->toContain( "field.type === 'divider'" )
            ->not->toContain( "field.type === 'number'" )
            ->not->toContain( "field.type === 'date'" )
            ->not->toContain( "field.type === 'time'" )
            ->not->toContain( "field.type === 'file'\" class" )
            ->not->toContain( 'buildFieldConfigUpdate(' )
            ->not->toContain( 'buildFileSettingUpdate(' )
            ->not->toContain( 'updateValidation(' )
            ->not->toContain( 'parseNumericInput' )
            ->not->toContain( 'ACCEPTANCE_FIELD_TYPES' );
    } );

    it( 'adds an Advanced tab only when the field has advanced settings', function () use ( $between ): void {
        $tabs = $between( $this->editor, 'const tabs = computed(', "\n} );\n" );

        expect( $tabs )->toContain( 'if ( advancedSettings.value.length > 0 ) {' )
            ->and( $tabs )->toContain( "{ name: 'advanced', label: t( 'Advanced' ) }" )
            ->and( $this->editor )->toContain( '<template #advanced>' );
    } );

    it( 'stores every setting through buildSettingUpdate', function (): void {
        expect( $this->control )
            ->toContain( "import { buildSettingUpdate, getSettingInput, isDraftStored } from '../../../shared/fieldSettings';" )
            ->toContain( "emit( 'update', buildSettingUpdate( props.field, props.setting, draft.value ) );" )
            ->toContain( "emit( 'update', buildSettingUpdate( props.field, props.setting, value ) );" );
    } );

    it( 'keeps a local draft for typed inputs so parsing does not rewrite them', function (): void {
        expect( $this->control )
            ->toContain( 'const draft = ref<string>( String( getSettingInput( props.field, props.setting ) ) );' )
            ->toContain( 'watch( [() => props.field.id, () => props.setting.key], () => {' )
            ->not->toContain( 'watch( () => [props.field.id' )
            ->toContain( ':model-value="draft"' );
    } );

    it( 'maps each input kind to a control', function (): void {
        expect( $this->control )
            ->toContain( "v-if=\"setting.input === 'toggle'\"" )
            ->toContain( "v-else-if=\"setting.input === 'select'\"" )
            ->toContain( ':options="setting.options ?? []"' )
            ->toContain( ':hint="setting.hint"' )
            ->toContain( ':placeholder="setting.placeholder"' )
            ->toContain( ':min="setting.min"' )
            ->toContain( ':max="setting.max"' )
            ->toContain( ":class=\"setting.monospace ? 'font-mono' : undefined\"" );
    } );
} );

describe( 'Vue rating field (issue #92)', function () use ( $resource ): void {
    beforeEach( function () use ( $resource ): void {
        $this->rating   = $resource( 'vue/components/fields/RatingField.vue' );
        $this->renderer = $resource( 'vue/components/fields/FieldRenderer.vue' );
    } );

    it( 'renders native radios in a labelled radiogroup', function (): void {
        expect( $this->rating )
            ->toContain( 'role="radiogroup"' )
            ->toContain( ':aria-labelledby="labelId"' )
            ->toContain( ':aria-describedby="describedBy"' )
            ->toContain( ":aria-required=\"field.is_required ? 'true' : undefined\"" )
            ->toContain( 'type="radio"' )
            ->toContain( 'v-for="value in steps"' );
    } );

    it( 'offers a clearing "No rating" choice and one star per step', function (): void {
        expect( $this->rating )
            ->toContain( 'const steps = computed( () => getRatingSteps( props.field ) );' )
            ->toContain( 'class="rating-hidden"' )
            ->toContain( ":aria-label=\"t( 'No rating' )\"" )
            ->toContain( "return value === 1 ? t( ':count star', { count: value } ) : t( ':count stars', { count: value } );" );
    } );

    it( 'styles half stars, sizes and a visible focus ring', function (): void {
        expect( $this->rating )
            ->toContain( "'mask-half-2' : 'mask-half-1'" )
            ->toContain( 'getRatingSizeClass( props.field )' )
            ->toContain( "allowHalf.value ? 'rating-half' : ''" )
            ->toContain( 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary' )
            ->toContain( 'has-[.rating-hidden:focus-visible]:outline' )
            ->toContain( 'GROUP_FOCUS_CLASSES,' );
    } );

    it( 'compares the stored string value numerically', function (): void {
        expect( $this->rating )
            ->toContain( 'const value = Number( props.modelValue );' )
            ->toContain( ':checked="currentRating === value"' )
            ->toContain( "emit( 'update:modelValue', String( value ) );" );
    } );

    it( 'is rendered by FieldRenderer and exported', function () use ( $resource ): void {
        expect( $this->renderer )
            ->toContain( "import RatingField from './RatingField.vue';" )
            ->toContain( "const RATING_TYPES = new Set( ['rating'] );" )
            ->toContain( "v-else-if=\"componentGroup === 'rating'\"" )
            ->and( $resource( 'vue/index.ts' ) )->toContain( "export { default as RatingField } from './components/fields/RatingField.vue';" );
    } );

    it( 'adds rating to the palette with a star icon', function () use ( $resource ): void {
        expect( $resource( 'vue/components/admin/FieldPalette.vue' ) )
            ->toContain( "{ type: 'rating', label: t( 'Rating' ), icon: 'rating', category: 'advanced' }" )
            ->toContain( "rating: 'M8 1l2.1 4.3" );
    } );
} );

describe( 'Vue read-only fields (issue #99)', function () use ( $resource, $between ): void {
    it( 'makes text-like inputs readonly', function () use ( $resource ): void {
        $text = $resource( 'vue/components/fields/TextField.vue' );

        expect( $text )->toContain( 'const readOnly = computed( () => isReadOnlyField( props.field ) );' )
            ->and( substr_count( $text, ':readonly="readOnly"' ) )->toBe( 7 )
            ->and( $resource( 'vue/components/fields/AdvancedField.vue' ) )
            ->toContain( "v-else-if=\"field.type === 'date' && readOnly\"" );
    } );

    it( 'marks choices aria-readonly and disables their inputs', function () use ( $resource ): void {
        $choice = $resource( 'vue/components/fields/ChoiceField.vue' );

        expect( $choice )->toContain( 'const readOnly = computed( () => isReadOnlyField( props.field ) );' )
            ->toContain( 'type="radio"' )
            ->toContain( ':disabled="readOnly"' )
            ->and( substr_count( $choice, ":aria-readonly=\"readOnly ? 'true' : undefined\"" ) )->toBe( 6 )
            ->and( $resource( 'vue/components/fields/RatingField.vue' ) )
            ->toContain( ":aria-readonly=\"readOnly ? 'true' : undefined\"" )
            ->toContain( ':disabled="readOnly"' );
    } );

    it( 'disables read-only radios and wraps them in an aria-readonly group, as in React', function () use ( $resource, $between ): void {
        $radio = $between( $resource( 'vue/components/fields/ChoiceField.vue' ), '<!-- Radio', '<!-- Checkbox' );

        expect( $radio )
            ->toContain( ":role=\"readOnly ? 'group' : undefined\"" )
            ->toContain( ':disabled="readOnly"' )
            ->toContain( "!readOnly && emit( 'update:modelValue', option.value )" );
    } );

    it( 'seeds a read-only field from the value the server stores', function () use ( $resource ): void {
        expect( $resource( 'vue/composables/useForm.ts' ) )
            ->toContain( "import { getInitialFieldValue, resolveOtherChoices } from '../../shared/fieldSettings';" )
            ->toContain( 'defaults[field.name] = getInitialFieldValue( field, { prefill } );' );
    } );
} );

describe( 'Vue builder review fixes', function () use ( $resource, $between ): void {
    beforeEach( function () use ( $resource ): void {
        $this->editor  = $resource( 'vue/components/admin/FieldEditor.vue' );
        $this->builder = $resource( 'vue/components/admin/FormBuilder.vue' );
    } );

    it( 'resyncs a setting draft when the stored value moves away from it', function () use ( $resource ): void {
        $control = $resource( 'vue/components/admin/FieldSettingControl.vue' );

        expect( $control )->toContain( 'watch( () => String( getSettingInput( props.field, props.setting ) ), ( stored ) => {' )
            ->and( $control )->toContain( 'if ( ! isDraftStored( props.field, props.setting, draft.value ) ) {' );
    } );

    it( 'keeps pending settings when options are edited', function (): void {
        // Every options edit (add, update, remove, defaults, bulk) goes through OptionsEditor's change event.
        expect( substr_count( $this->editor, 'field_config: { ...( currentField.value.field_config ?? {} ), options: newOptions }' ) )->toBe( 1 )
            ->and( $this->editor )->toContain( '@change="setOptions"' )
            ->and( $this->editor )->not->toContain( '...( props.field.field_config ?? {} )' );
    } );

    it( 'replaces store columns in the optimistic update so removed settings disappear', function () use ( $between ): void {
        $update = $between( $this->builder, 'async function updateField(', 'try {' );

        expect( $update )
            ->toContain( 'f.id === fieldId ? { ...f, ...data } as FormField : f,' )
            ->not->toContain( '...f.field_config, ...data.field_config' )
            ->not->toContain( '...f.validation_rules, ...data.validation_rules' );
    } );

    it( 'matches the React tab order and only shows tabs that have settings', function () use ( $between ): void {
        $tabs = $between( $this->editor, 'const tabs = computed(', 'return items;' );

        expect( $tabs )->toContain( 'if ( validationSettings.value.length > 0 ) {' )
            ->and( strpos( $tabs, "name: 'conditional'" ) )->toBeLessThan( strpos( $tabs, "name: 'advanced'" ) )
            ->and( $this->editor )->not->toContain( 'This field type has no validation settings.' );
    } );

    it( 'hides the placeholder for checkbox, toggle, radio and rating fields', function (): void {
        expect( $this->editor )
            ->toContain( "const NO_PLACEHOLDER_TYPES = new Set<FieldType>( ['checkbox', 'toggle', 'radio', 'rating'] );" )
            ->toContain( 'v-if="!isLayoutField && !isComposite && !NO_PLACEHOLDER_TYPES.has( field.type )"' );
    } );

    it( 'labels option inputs for screen readers', function () use ( $resource ): void {
        expect( $resource( 'vue/components/admin/OptionsEditor.vue' ) )
            ->toContain( ":aria-label=\"t( 'Option :number label', { number: index + 1 } )\"" )
            ->toContain( ":aria-label=\"t( 'Option :number value', { number: index + 1 } )\"" );
    } );

    it( 'uses the translated type label for the header and new fields', function () use ( $resource ): void {
        expect( $this->editor )->toContain( "t( 'Edit :type Field', { type: getFieldTypeLabel( field.type ) } )" )
            ->and( $this->builder )->toContain( 'label: getFieldTypeLabel( type ),' )
            ->and( $resource( 'react/components/admin/FieldEditor.tsx' ) )
            ->toContain( "t( 'Edit :type Field', { type: getFieldTypeLabel( field.type ) } )" );
    } );
} );

describe( 'Vue shared width classes (issue #89)', function () use ( $resource ): void {
    it( 'reads widths from the shared map', function () use ( $resource ): void {
        $renderer = $resource( 'vue/components/fields/FieldRenderer.vue' );

        expect( $renderer )->toContain( "import { WIDTH_CLASSES } from '../../../shared/fieldSettings';" )
            ->not->toContain( 'const WIDTH_CLASSES' )
            ->and( $resource( 'vue/components/admin/FormBuilder.vue' ) )
            ->toContain( 'classes.push( getWidthClass( field.width ) );' )
            ->not->toContain( "classes.push( 'w-1/2' );" );
    } );
} );

describe( 'Vue admin translations (issue #90)', function () use ( $resource, $adminComponents ): void {
    it( 'imports t in every admin component', function ( string $component ) use ( $resource ): void {
        expect( $resource( "vue/components/admin/{$component}.vue" ) )
            ->toContain( "import { t } from '../../../shared/i18n';" );
    } )->with( $adminComponents );

    it( 'leaves no static English attribute in admin templates', function ( string $component ) use ( $resource ): void {
        $source   = $resource( "vue/components/admin/{$component}.vue" );
        $template = substr( $source, (int) strpos( $source, '<template>' ) );

        preg_match_all( '/(?<![:\w-])(label|placeholder|aria-label|title|hint|value)="([A-Z][a-z][^"]*)"/', $template, $matches );

        expect( $matches[0] )->toBe( [] );
    } )->with( $adminComponents );

    it( 'passes only single-quoted literals to t()', function ( string $component ) use ( $resource ): void {
        preg_match_all( '/\bt\(\s*([^\s)])/', $resource( "vue/components/admin/{$component}.vue" ), $matches );

        expect( array_unique( $matches[1] ) )->toBe( ["'"] );
    } )->with( $adminComponents );

    it( 'exports the translation helpers', function () use ( $resource ): void {
        $index = $resource( 'vue/index.ts' );

        expect( $index )->toContain( "} from '../shared/i18n';" )
            ->toContain( "\tsetTranslations,\n" )
            ->toContain( "\tgetTranslations,\n" )
            ->toContain( "\tclearTranslations,\n" )
            ->toContain( "export type { TranslationReplacements } from '../shared/i18n';" );
    } );
} );
