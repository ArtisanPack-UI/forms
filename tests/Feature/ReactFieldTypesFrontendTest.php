<?php

declare( strict_types=1 );

/**
 * Guards the React side of issues #89, #90, #91, #92 and #99. The surfaces
 * are validated by source content (the package ships no JS build or test
 * harness of its own), matching FieldSettingsFrontendTest.
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

describe( 'React schema-driven field settings (issue #91)', function () use ( $resource, $between ): void {
    beforeEach( function () use ( $resource ): void {
        $this->editor = $resource( 'react/components/admin/FieldEditor.tsx' );
    } );

    it( 'renders each tab from the field\'s settings schema', function () use ( $between ): void {
        $general = $between( $this->editor, 'function GeneralTabContent(', '// SettingsTabContent' );

        expect( $general )->toContain( "getSettingsForTab( field, 'general' ).map(" )
            ->and( $this->editor )->toContain( "getSettingsForTab( field, 'validation' )" )
            ->and( $this->editor )->toContain( "getSettingsForTab( field, 'advanced' )" )
            ->and( $this->editor )->toContain( 'if ( validationSettings.length > 0 ) {' );
    } );

    it( 'adds an Advanced tab only when the type has advanced settings (issue #99)', function (): void {
        expect( $this->editor )->toContain( 'if ( advancedSettings.length > 0 ) {' )
            ->toContain( "name: 'advanced'," )
            ->toContain( "label: t( 'Advanced' )," );
    } );

    it( 'resyncs a setting draft when the stored value moves away from it', function () use ( $between ): void {
        $control = $between( $this->editor, 'function SettingControl(', "\n}\n" );

        expect( $control )->toContain( 'setDraft( ( current ) => ( isDraftStored( field, setting, current ) ? current : storedText ) );' )
            ->and( $control )->toContain( '}, [storedText] );' );
    } );

    it( 'compares a draft with the stored value after parsing it', function () use ( $resource ): void {
        $settings = $resource( 'shared/fieldSettings.ts' );

        expect( $settings )->toContain( 'export function isDraftStored( field: FormField, setting: FieldSettingDefinition, draft: string ): boolean {' )
            ->and( $settings )->toContain( 'const drafted = { ...field, ...buildSettingUpdate( field, setting, draft ) } as FormField;' );
    } );

    it( 'maps each schema input to a control in one SettingControl component', function () use ( $between ): void {
        $control = $between( $this->editor, 'function SettingControl(', "\n}\n" );

        expect( $control )->toContain( "if ( setting.input === 'toggle' ) {" )
            ->and( $control )->toContain( 'buildSettingUpdate( field, setting, e.target.checked )' )
            ->and( $control )->toContain( "if ( setting.input === 'select' ) {" )
            ->and( $control )->toContain( 'options={setting.options ?? []}' )
            ->and( $control )->toContain( 'type={htmlInputType( setting.input )}' )
            ->and( $control )->toContain( 'placeholder={setting.placeholder}' )
            ->and( $control )->toContain( "className={setting.monospace ? 'font-mono' : undefined}" );
    } );

    it( 'keeps a draft of typed input so parsing does not rewrite it', function () use ( $between ): void {
        $control = $between( $this->editor, 'function SettingControl(', "\n}\n" );

        expect( $control )->toContain( "const [draft, setDraft] = useState<string>( typeof stored === 'string' ? stored : '' );" )
            ->and( $control )->toContain( '}, [field.id, setting.key] );' )
            ->and( $control )->toContain( 'value={draft}' )
            ->and( $control )->toContain( "setDraft( e.target.value );\n\t\t\t\tupdateField( buildSettingUpdate( field, setting, e.target.value ) );" );
    } );

    it( 'has no hard-coded type-specific setting branches left', function (): void {
        expect( $this->editor )->not->toContain( "field.type === 'textarea'" )
            ->not->toContain( "field.type === 'divider'" )
            ->not->toContain( "field.type === 'file'" )
            ->not->toContain( "field.type === 'time'" )
            ->not->toContain( "field.type === 'number'" )
            ->not->toContain( 'buildFileSettingUpdate(' )
            ->not->toContain( 'buildFieldConfigUpdate(' )
            ->not->toContain( 'ACCEPTANCE_FIELD_TYPES' );
    } );

    it( 'keeps other field_config keys when editing options', function (): void {
        expect( substr_count( $this->editor, 'field_config: { ...( field.field_config ?? {} ), options: newOptions }' ) )->toBe( 3 );
    } );
} );

describe( 'React rating field (issue #92)', function () use ( $resource, $between ): void {
    beforeEach( function () use ( $resource, $between ): void {
        $this->rating = $between( $resource( 'react/components/fields/AdvancedField.tsx' ), 'export function RatingField(', "\n}\n" );
    } );

    it( 'renders native radios in a labelled radiogroup', function (): void {
        expect( $this->rating )->toContain( 'role="radiogroup"' )
            ->toContain( 'aria-labelledby={labelId}' )
            ->toContain( 'aria-describedby={describedBy}' )
            ->toContain( "aria-required={field.is_required ? 'true' : undefined}" )
            ->toContain( 'type="radio"' );
    } );

    it( 'offers a hidden "no rating" radio and one radio per step', function (): void {
        expect( $this->rating )->toContain( 'value="0"' )
            ->toContain( 'className="rating-hidden"' )
            ->toContain( "aria-label={t( 'No rating' )}" )
            ->toContain( 'const steps = getRatingSteps( field );' )
            ->toContain( 'steps.map(' )
            ->toContain( "'mask-half-2' : 'mask-half-1'" )
            ->toContain( 'getRatingSizeClass( field )' )
            ->toContain( "allowHalf ? 'rating-half' : ''" );
    } );

    it( 'labels each star and compares the value numerically', function (): void {
        expect( $this->rating )->toContain( "t( ':count star', { count: step } )" )
            ->toContain( "t( ':count stars', { count: step } )" )
            ->toContain( 'const current = Number( value );' )
            ->toContain( 'checked={selected === step}' )
            ->toContain( 'onChange( String( step ) )' );
    } );

    it( 'shows a visible focus ring', function () use ( $resource ): void {
        expect( $resource( 'react/components/fields/AdvancedField.tsx' ) )
            ->toContain( "const RATING_FOCUS_CLASSES = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';" )
            ->and( $this->rating )->toContain( '${RATING_FOCUS_CLASSES}' )
            ->and( $this->rating )->toContain( '${RATING_GROUP_FOCUS_CLASSES}' )
            ->and( $resource( 'react/components/fields/AdvancedField.tsx' ) )->toContain( 'has-[.rating-hidden:focus-visible]:outline' );
    } );

    it( 'is registered in the field renderer and exported', function () use ( $resource ): void {
        expect( $resource( 'react/components/fields/FieldRenderer.tsx' ) )->toContain( "\trating: RatingField,\n" )
            ->and( $resource( 'react/index.ts' ) )->toContain( "\tRatingField,\n} from './components/fields/AdvancedField';" );
    } );

    it( 'is offered in the field palette', function () use ( $resource ): void {
        expect( $resource( 'react/components/admin/FieldPalette.tsx' ) )
            ->toContain( "{ type: 'rating', label: t( 'Rating' ), icon: 'rating', category: 'advanced' }" )
            ->toContain( "\trating: 'M8 1l2.1 4.3" );
    } );
} );

describe( 'React read-only fields (issue #99)', function () use ( $resource, $between ): void {
    it( 'keeps text-like inputs focusable with readOnly', function () use ( $resource ): void {
        expect( substr_count( $resource( 'react/components/fields/TextField.tsx' ), 'readOnly={isReadOnlyField( field )}' ) )->toBe( 7 );
    } );

    it( 'renders a read-only date as a plain readonly date input instead of the picker', function () use ( $resource, $between ): void {
        $dateField = $between( $resource( 'react/components/fields/AdvancedField.tsx' ), 'export function DateField', '<DatePicker' );

        expect( $dateField )
            ->toContain( 'if ( isReadOnlyField( field ) ) {' )
            ->toContain( 'type="date"' )
            ->toContain( 'readOnly' );
    } );

    it( 'seeds a read-only field from the value the server stores', function () use ( $resource ): void {
        expect( $resource( 'shared/fieldSettings.ts' ) )
            ->toContain( 'export function getInitialFieldValue( field: FormField, population: PopulationSource = {} ): unknown {' )
            ->toContain( 'isReadOnlyField( field ) && field.read_only_value !== undefined' )
            ->and( substr_count( $resource( 'react/hooks/useForm.ts' ), 'defaults[field.name] = getInitialFieldValue( field, { prefill: prefillRef.current } );' ) )->toBe( 2 )
            ->and( $resource( 'types/artisanpack-forms.d.ts' ) )->toContain( 'read_only_value?: unknown;' );
    } );

    it( 'marks choices aria-readonly and disables their inputs', function () use ( $resource, $between ): void {
        $choice = $resource( 'react/components/fields/ChoiceField.tsx' );
        $select = $between( $choice, 'export function SelectField(', "\n}\n" );
        $radio  = $between( $choice, 'export function RadioField(', "\n}\n" );
        $box    = $between( $choice, 'export function CheckboxField(', "\n}\n" );
        $group  = $between( $choice, 'export function CheckboxGroupField(', "\n}\n" );
        $multi  = $between( $choice, 'export function SelectMultipleField(', "\n}\n" );

        foreach ( [$select, $box, $multi] as $component ) {
            expect( $component )->toContain( 'disabled={readOnly}' )
                ->toContain( "aria-readonly={readOnly ? 'true' : undefined}" );
        }

        expect( $radio )->toContain( 'disabled={readOnly}' )
            ->and( $radio )->toContain( '<div role="group" aria-readonly="true">{radio}</div>' )
            ->and( $group )->toContain( "aria-readonly={readOnly ? 'true' : undefined}" )
            ->and( $group )->toContain( 'disabled={readOnly}' );
    } );

    it( 'disables a read-only rating', function () use ( $resource, $between ): void {
        $rating = $between( $resource( 'react/components/fields/AdvancedField.tsx' ), 'export function RatingField(', "\n}\n" );

        expect( $rating )->toContain( "aria-readonly={readOnly ? 'true' : undefined}" )
            ->and( substr_count( $rating, 'disabled={readOnly}' ) )->toBe( 2 );
    } );
} );

describe( 'React field widths (issue #89)', function () use ( $resource ): void {
    it( 'reads widths from the shared map', function () use ( $resource ): void {
        $renderer = $resource( 'react/components/fields/FieldRenderer.tsx' );

        expect( $renderer )->toContain( "import { getWidthClass, WIDTH_CLASSES } from '../../../shared/fieldSettings';" )
            ->toContain( ': getWidthClass( field.width );' )
            ->not->toContain( 'const WIDTH_CLASSES' )
            ->and( $resource( 'react/components/admin/FormBuilder.tsx' ) )->toContain( 'getWidthClass( field.width )' )
            ->not->toContain( "'half' === field.width ? 'w-1/2'" );
    } );
} );

describe( 'React admin translations (issue #90)', function () use ( $resource, $adminComponents ): void {
    it( 'imports t() in every admin component', function ( string $component ) use ( $resource ): void {
        expect( $resource( "react/components/admin/{$component}.tsx" ) )
            ->toContain( "import { t } from '../../../shared/i18n';" );
    } )->with( $adminComponents );

    it( 'passes no plain English literal to a label, placeholder, title, hint or aria-label', function ( string $component ) use ( $resource ): void {
        $source = $resource( "react/components/admin/{$component}.tsx" );

        preg_match_all( '/\b(label|placeholder|title|hint|aria-label)="([^"]*)"/', $source, $matches, PREG_SET_ORDER );

        // Example addresses are technical, not copy.
        $literals = array_filter(
            $matches,
            fn ( array $match ): bool => 1 === preg_match( '/[A-Za-z]/', $match[2] ) && ! str_contains( $match[2], '@' ),
        );

        expect( array_map( fn ( array $match ): string => $match[0], $literals ) )->toBe( [] );
    } )->with( $adminComponents );

    it( 'only passes single-quoted literals to t()', function ( string $component ) use ( $resource ): void {
        $source = $resource( "react/components/admin/{$component}.tsx" );

        expect( preg_match( '/\bt\(\s*[^\s\']/', $source ) )->toBe( 0 );
    } )->with( $adminComponents );

    it( 'exports the translation helpers from the React entry point', function () use ( $resource ): void {
        expect( $resource( 'react/index.ts' ) )->toContain( "export {\n\tt,\n\tsetTranslations,\n\tgetTranslations,\n\tclearTranslations,\n} from '../shared/i18n';" )
            ->toContain( "export type { TranslationReplacements } from '../shared/i18n';" );
    } );

    it( 'builds translated option lists at render rather than at import', function () use ( $resource ): void {
        expect( $resource( 'react/components/admin/ConditionalLogicEditor.tsx' ) )->toContain( 'function getOperatorOptions()' )
            ->not->toContain( 'OPERATOR_DEFINITIONS' )
            ->and( $resource( 'react/components/admin/NotificationEditor.tsx' ) )->toContain( 'function getNotificationTypes()' )
            ->and( $resource( 'react/components/admin/FieldPalette.tsx' ) )->toContain( '...getFieldPalette(),' )
            ->and( $resource( 'react/components/admin/FieldEditor.tsx' ) )->toContain( 'options={getWidthOptions()}' );
    } );
} );
