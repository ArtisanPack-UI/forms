<?php

declare( strict_types=1 );

/**
 * Guards the React, Vue and shared JS side of issues #124 (file limits note),
 * #125 (sections), #126 (paragraph formatting, live merge tags and heading
 * alignment), #127 (list fields) and #128 (date range and date and time
 * fields). The surfaces are validated by source content (the package ships
 * no JS build or test harness of its own), matching
 * FieldValidationModesFrontendTest.
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

describe( 'shared helpers', function () use ( $resource, $between ): void {
    it( 'groups fields into sections like Sections', function () use ( $resource, $between ): void {
        $sections = $resource( 'shared/sections.ts' );

        expect( $between( $sections, 'export function getFieldSegments(', "\n}\n" ) )
            ->toContain( 'if ( isSectionField( field ) ) {' )
            ->toContain( 'current = { section: field, fields: [] };' )
            ->and( $between( $sections, 'export function getSectionMembers(', "\n}\n" ) )
            ->toContain( "const step = String( field.step_id ?? '' );" )
            ->toContain( 'a.sort_order - b.sort_order || a.id - b.id' )
            ->and( $between( $sections, 'export function applySectionVisibility(', "\n}\n" ) )
            ->toContain( "if ( types[name] !== 'captcha' ) {" );
    } );

    it( 'hides a hidden section\'s fields in the conditional logic engine', function () use ( $resource ): void {
        expect( $resource( 'shared/conditionalLogic.ts' ) )->toContain( 'return applySectionVisibility( fields, hidden );' )
            ->toContain( 'const values = normalizeConditionValues( fields, formData );' )
            ->toContain( 'values[field.name] = rows.length > 0 ? rows : null;' )
            ->toContain( "values[field.name] = value.replace( 'T', ' ' );" );
    } );

    it( 'fills merge tags in text only, escaped, like LiveMergeTags', function () use ( $resource, $between ): void {
        $tags = $resource( 'shared/liveMergeTags.ts' );

        expect( $tags )->toContain( 'export const LIVE_MERGE_TAG_PATTERN = /\{([A-Za-z0-9_-]+)(?::([A-Za-z0-9_]+))?\}/g;' )
            ->toContain( '.split( /(<[^>]*>)/ )' )
            ->toContain( "segment.startsWith( '<' )" )
            ->toContain( "const UNTAGGABLE_TYPES = new Set( ['section', 'heading', 'paragraph', 'divider', 'html', 'file', 'captcha'] );" )
            ->and( $between( $tags, 'function escapeHtml(', "\n}\n" ) )
            ->toContain( ".replace( /</g, '&lt;' )" )
            ->toContain( ".replace( /\"/g, '&quot;' )" )
            ->and( $tags )->toContain( 'if ( state.hiddenFields?.[name] ) {' )
            ->toContain( 'const labels: Record<string, string> = Object.create( null );' );
    } );

    it( 'validates lists like ListFieldType', function () use ( $resource, $between ): void {
        $list = $resource( 'shared/list.ts' );

        expect( $list )->toContain( 'export const LIST_CELL_MAX_LENGTH = 255;' )
            ->toContain( 'export const LIST_MAX_ROWS = 100;' )
            ->and( $between( $list, 'export function validateListField(', "\n}\n" ) )
            ->toContain( "t( 'Please fill in at least one row of :field.', { field: label } )" )
            ->toContain( 'filled.length > max' )
            ->toContain( 'filled.length < min' )
            ->toContain( 'errors[`${field.name}.${index}.${column.key}`]' );
    } );

    it( 'validates date ranges and date and time values', function () use ( $resource, $between ): void {
        $validation = $resource( 'shared/validation.ts' );

        expect( $validation )->toContain( "const LAYOUT_FIELDS = new Set( ['section', 'heading', 'paragraph', 'divider', 'html'] );" )
            ->toContain( 'const DATE_TIME_PATTERN = /^(\d{4}-\d{2}-\d{2})[T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?$/;' )
            ->toContain( "} else if ( subInput.type === 'date' ) {" )
            ->toContain( "if ( field.type === 'date_range' && enabledKeys.includes( 'start' ) && enabledKeys.includes( 'end' ) && Object.keys( errors ).length === 0 && !isDateRange( parts ) ) {" )
            ->toContain( 'Object.assign( errors, validateListField( field, formData[field.name] ) );' )
            ->and( $between( $validation, 'export function isDateRange(', "\n}\n" ) )
            ->toContain( "if ( start === '' || end === '' ) {" )
            ->toContain( 'end >= start' )
            ->and( $resource( 'shared/dateConstraints.ts' ) )->toContain( "const DATE_LIMIT_TYPES = new Set( ['date', 'datetime', 'date_range'] );" );
    } );

    it( 'shows the allowed types in the file note like FormField::getFileLimitsHint()', function () use ( $resource, $between ): void {
        expect( $between( $resource( 'shared/fieldSettings.ts' ), 'export function getFileLimitsHint(', "\n}\n" ) )
            ->toContain( "t( 'Allowed file types: :types', { types: types.map( ( type ) => type.toUpperCase() ).join( ', ' ) } )" )
            ->toContain( "notes.join( ' · ' )" );
    } );

    it( 'starts lists with empty rows and converts date and time values for the input', function () use ( $resource, $between ): void {
        expect( $between( $resource( 'shared/fieldSettings.ts' ), 'export function getInitialFieldValue(', "\n}\n" ) )
            ->toContain( 'return getInitialListRows( field );' )
            ->toContain( "source.trim().replace( /^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})(:\d{2})?$/, '\$1T\$2' )" );
    } );

    it( 'lets sections take conditional logic', function () use ( $resource ): void {
        expect( $resource( 'shared/fieldSettings.ts' ) )->toContain( "return !LAYOUT_FIELD_TYPES.has( type ) || type === 'section';" );
    } );

    it( 'sends list rows as nested form data from both renderers', function () use ( $resource ): void {
        foreach ( ['react/hooks/useForm.ts', 'vue/composables/useForm.ts'] as $path ) {
            expect( $resource( $path ) )->toContain( 'formDataObj.append( `data[${key}][${index}][${column}]`, String( cell ?? \'\' ) );' )
                ->toContain( "const LAYOUT_FIELDS = new Set( ['section', 'heading', 'paragraph', 'divider', 'html'] );" );
        }
    } );
} );

describe( 'React', function () use ( $resource, $between ): void {
    it( 'groups a section\'s fields in its fieldset', function () use ( $resource ): void {
        $renderer = $resource( 'react/components/FormRenderer.tsx' );
        $section  = $resource( 'react/components/fields/SectionField.tsx' );

        expect( $renderer )->toContain( 'getFieldSegments( currentFields ).map(' )
            ->toContain( '<SectionGroup' )
            ->toContain( "holdsCaptcha={segment.fields.some( ( field ) => field.type === 'captcha' )}" )
            ->toContain( '<FormValuesContext.Provider value={{ fields: form.fields, values, hiddenFields, otherValues }}>' )
            ->and( $section )->toContain( '<fieldset' )
            ->toContain( '<legend className="fieldset-legend text-lg font-semibold">' )
            ->toContain( 'aria-controls={contentId}' )
            ->toContain( 'aria-expanded={open}' )
            ->toContain( 'if ( hidden && !holdsCaptcha ) {' )
            ->toContain( "collapsible && !open && !hidden ? 'hidden' : 'flex'" )
            ->toContain( 'if ( hasErrors ) {' );
    } );

    it( 'renders the new types', function () use ( $resource ): void {
        expect( $resource( 'react/components/fields/FieldRenderer.tsx' ) )
            ->toContain( 'datetime: DateTimeField,' )
            ->toContain( 'date_range: CompositeField,' )
            ->toContain( 'list: ListField,' )
            ->toContain( 'section: SectionField,' );
    } );

    it( 'renders an accessible list', function () use ( $resource ): void {
        expect( $resource( 'react/components/fields/ListField.tsx' ) )
            ->toContain( 'role="group"' )
            ->toContain( 'aria-labelledby={`${rowId}-label`}' )
            ->toContain( "aria-label={t( 'Remove :row :number', { row: rowLabel, number: index + 1 } )}" )
            ->toContain( "{t( 'Add :row', { row: rowLabel } )}" )
            ->toContain( 'setFocusTarget( { row: rows.length } );' )
            ->toContain( "setFocusTarget( 'add' );" )
            ->toContain( 'aria-live="polite"' );
    } );

    it( 'aligns headings, formats paragraphs and fills merge tags', function () use ( $resource ): void {
        expect( $resource( 'react/components/fields/LayoutField.tsx' ) )
            ->toContain( 'getHeadingAlignClass( field )' )
            ->toContain( 'dangerouslySetInnerHTML={{ __html: field.content_html }}' )
            ->toContain( 'applyLiveMergeTags( content, form.fields, form )' );
    } );

    it( 'shows the file limits note and renders a date and time input', function () use ( $resource, $between ): void {
        $advanced = $resource( 'react/components/fields/AdvancedField.tsx' );

        expect( $between( $advanced, 'function withLimitsNote(', "\n}\n" ) )
            ->toContain( 'withInputNotes( props, field, error, [id] )' )
            ->and( substr_count( $advanced, '{limits.note}' ) )->toBe( 2 )
            ->and( $between( $advanced, 'export function DateTimeField(', "\n}\n" ) )
            ->toContain( 'type="datetime-local"' )
            ->toContain( 'min={limits.min ? `${limits.min}T00:00` : undefined}' );
    } );

    it( 'offers the new types in the builder', function () use ( $resource ): void {
        expect( $resource( 'react/components/admin/FieldPalette.tsx' ) )
            ->toContain( "{ type: 'section', label: t( 'Section' ), icon: 'section', category: 'layout' }," )
            ->toContain( "{ type: 'list', label: t( 'List' ), icon: 'list', category: 'advanced' }," )
            ->toContain( "{ type: 'date_range', label: t( 'Date Range' ), icon: 'date_range', category: 'advanced' }," )
            ->toContain( "{ type: 'datetime', label: t( 'Date & Time' ), icon: 'datetime', category: 'advanced' }," )
            ->and( $resource( 'react/components/admin/FieldEditor.tsx' ) )
            ->toContain( 'if ( supportsConditionalLogic( field.type ) ) {' )
            ->toContain( "t( 'Section Title' )" );
    } );
} );

describe( 'Vue', function () use ( $resource ): void {
    it( 'groups a section\'s fields in its fieldset', function () use ( $resource ): void {
        $renderer = $resource( 'vue/components/FormRenderer.vue' );
        $section  = $resource( 'vue/components/fields/SectionField.vue' );

        expect( $renderer )->toContain( 'const segments = computed( () => getFieldSegments( currentFields.value ) );' )
            ->toContain( '<component :is="segment.section ? SectionField : FieldGroup" v-bind="sectionProps( segment )">' )
            ->toContain( 'provide( FORM_VALUES_KEY, computed(' )
            ->and( substr_count( $renderer, 'v-for="( segment, index ) in segments"' ) )->toBe( 2 )
            ->and( $section )->toContain( 'v-if="!hidden || holdsCaptcha"' )
            ->toContain( "collapsible && !open && !hidden ? 'hidden' : 'flex'" )
            ->toContain( ':aria-controls="contentId"' )
            ->toContain( ":aria-expanded=\"open ? 'true' : 'false'\"" );
    } );

    it( 'renders the new types', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/FieldRenderer.vue' ) )
            ->toContain( "const ADVANCED_TYPES = new Set( ['file', 'date', 'datetime'] );" )
            ->toContain( "if ( props.field.type === 'list' ) {" )
            ->toContain( '<ListField' )
            ->toContain( '<SectionField' );
    } );

    it( 'renders an accessible list', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/ListField.vue' ) )
            ->toContain( 'role="group"' )
            ->toContain( ":aria-label=\"t( 'Remove :row :number', { row: rowLabel, number: index + 1 } )\"" )
            ->toContain( "{{ t( 'Add :row', { row: rowLabel } ) }}" )
            ->toContain( 'addButton.value?.focus();' )
            ->toContain( 'aria-live="polite"' );
    } );

    it( 'aligns headings, formats paragraphs and fills merge tags', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/LayoutField.vue' ) )
            ->toContain( 'getHeadingAlignClass( field )' )
            ->toContain( 'v-html="field.content_html"' )
            ->toContain( 'applyLiveMergeTags( layoutContent.value, form.value.fields, form.value )' );
    } );

    it( 'shows the file limits note and renders a date and time input', function () use ( $resource ): void {
        $advanced = $resource( 'vue/components/fields/AdvancedField.vue' );

        expect( substr_count( $advanced, '<p v-if="limitsHint" :id="limitsId" class="fieldset-label mt-1">{{ limitsHint }}</p>' ) )->toBe( 2 )
            ->and( $advanced )->toContain( 'type="datetime-local"' )
            ->toContain( ':min="dateTimeBounds.min"' );
    } );

    it( 'offers the new types in the builder', function () use ( $resource ): void {
        expect( $resource( 'vue/components/admin/FieldPalette.vue' ) )
            ->toContain( "{ type: 'section', label: t( 'Section' ), icon: 'section', category: 'layout' }," )
            ->toContain( "{ type: 'list', label: t( 'List' ), icon: 'list', category: 'advanced' }," )
            ->and( $resource( 'vue/components/admin/FieldEditor.vue' ) )
            ->toContain( 'if ( supportsConditionalLogic( props.field.type ) ) {' );
    } );
} );

describe( 'TypeScript definitions', function () use ( $resource ): void {
    it( 'list the new types, configs and payload keys', function () use ( $resource ): void {
        expect( $resource( 'types/artisanpack-forms.d.ts' ) )
            ->toContain( "    | 'datetime'\n    | 'date_range'" )
            ->toContain( "    | 'section'\n    | 'heading'" )
            ->toContain( 'export interface SectionFieldConfig extends BaseFieldConfig {' )
            ->toContain( 'export interface ListFieldConfig extends BaseFieldConfig {' )
            ->toContain( 'export interface ListColumn {' )
            ->toContain( 'export interface DateTimeFieldConfig extends CommonFieldConfig {' )
            ->toContain( 'export interface DateRangeFieldConfig extends CompositeFieldConfig {' )
            ->toContain( "storage?: 'forms' | 'media_library';" )
            ->toContain( "align?: 'left' | 'center' | 'right';" )
            ->toContain( 'content_html?: string;' )
            ->toContain( 'list?: { columns: ListColumn[]; min_rows: number | null; max_rows: number; row_label: string };' );
    } );
} );
