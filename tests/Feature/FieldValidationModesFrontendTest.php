<?php

declare( strict_types=1 );

/**
 * Guards the React, Vue and shared JS side of issues #119 (email domains),
 * #120 (URL schemes), #121 (rich text textareas), #122 (date constraints)
 * and #123 (date and time input modes). The surfaces are validated by source
 * content (the package ships no JS build or test harness of its own),
 * matching FieldInputOptionsFrontendTest.
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
    it( 'matches email domains and their subdomains like EmailDomains', function () use ( $resource ): void {
        expect( $resource( 'shared/emailDomains.ts' ) )
            ->toContain( 'return domains.some( ( listed ) => domain === listed || domain.endsWith( `.${listed}` ) );' )
            ->toContain( "const domain = entry.trim().replace( /^@+/, '' ).toLowerCase()" );
    } );

    it( 'adds a missing URL scheme like UrlSchemes', function () use ( $resource ): void {
        expect( $resource( 'shared/url.ts' ) )
            ->toContain( "export const DEFAULT_URL_SCHEMES: readonly string[] = ['http', 'https'];" )
            ->toContain( 'return /^[a-z][a-z0-9+.-]*:(?!\d)/i.test( value );' )
            ->toContain( "return trimmed.startsWith( '//' ) ? `\${scheme}:\${trimmed}` : `\${scheme}://\${trimmed}`;" )
            ->toContain( "return allowed.includes( url.protocol.replace( /:$/, '' ).toLowerCase() );" );
    } );

    it( 'converts rich text to plain text like RichText::toPlainText()', function () use ( $resource, $between ): void {
        expect( $between( $resource( 'shared/richText.ts' ), 'export function richTextToPlainText(', "\n}\n" ) )
            ->toContain( ".replace( /<br\\s*\\/?>/gi, '\\n' )" )
            ->toContain( ".replace( /<\\/(p|div|li|blockquote|h[1-6])\\s*>/gi, '\\n' )" )
            ->toContain( ".replace( /\\n{3,}/g, '\\n\\n' )" );
    } );

    it( 'offers a keyboard operable, labelled toolbar', function () use ( $resource, $between ): void {
        $richText = $resource( 'shared/richText.ts' );

        expect( $between( $richText, 'export function getToolbarFocusIndex(', "\n}\n" ) )
            ->toContain( "case 'ArrowRight':" )
            ->toContain( "case 'Home':" )
            ->toContain( "case 'End':" )
            ->and( $richText )->toContain( "{ command: 'bold', label: t( 'Bold' ), text: 'B', className: 'font-bold' }," );
    } );

    it( 'resolves relative dates without overflowing months like DateConstraints', function () use ( $resource ): void {
        expect( $resource( 'shared/dateConstraints.ts' ) )
            ->toContain( 'const RELATIVE_PATTERN = /^(?:today|([+-])\s*(\d{1,4})\s*(day|week|month|year)s?)$/i;' )
            ->toContain( 'return { year, month, day: Math.min( parts.day, daysInMonth( year, month ) ) };' )
            ->toContain( "[resolveDateExpression( rules?.min_date ), range === 'no_past' ? now : undefined]," )
            ->toContain( 'return formatIsoDate( addDays( addMonths( from, -( age + 1 ) * 12 ), 1 ) );' );
    } );

    it( 'combines date and time parts into YYYY-MM-DD and HH:MM', function () use ( $resource ): void {
        expect( $resource( 'shared/dateTime.ts' ) )
            ->toContain( "mdy: ['month', 'day', 'year']," )
            ->toContain( "hour = ( hour % 12 ) + ( parts.period === 'pm' ? 12 : 0 );" )
            ->toContain( 'const interval = step !== undefined && step >= 60 && step % 60 === 0 ? step / 60 : 1;' );
    } );

    it( 'parses date expression settings like FieldSettings', function () use ( $resource ): void {
        expect( $resource( 'shared/fieldSettings.ts' ) )->toContain( "case 'date_expression':\n\t\t\treturn parseDateExpression( value );" )
            ->and( $resource( 'types/artisanpack-forms.d.ts' ) )->toContain( "| 'list' | 'date_expression';" );
    } );

    it( 'validates the new options on the client', function () use ( $resource, $between ): void {
        $validation = $resource( 'shared/validation.ts' );

        expect( $validation )
            ->toContain( '} else if ( !isAcceptedEmailDomain( field, value ) ) {' )
            ->toContain( 'if ( !isAllowedUrl( withUrlScheme( value, schemes ), schemes ) ) {' )
            ->toContain( "const text = typeof value === 'string' && usesRichText( field ) ? richTextToPlainText( value ) : value;" )
            ->toContain( "return richTextToPlainText( value ) === '';" )
            ->and( $between( $validation, 'function validateDateField(', "\n}\n" ) )
            ->toContain( 'if ( !parseIsoDate( value ) ) {' )
            ->toContain( 'if ( !isAllowedDay( field, value ) ) {' )
            ->toContain( 'if ( minAge !== undefined && value > latestBirthdate( minAge ) ) {' )
            ->toContain( 'if ( maxAge !== undefined && value < earliestBirthdate( maxAge ) ) {' );
    } );
} );

describe( 'React renderer', function () use ( $resource, $between ): void {
    it( 'adds the URL scheme on blur', function () use ( $resource, $between ): void {
        expect( $between( $resource( 'react/components/fields/TextField.tsx' ), 'export function UrlField(', "\n}\n" ) )
            ->toContain( 'const withScheme = withUrlScheme( e.target.value, getAllowedUrlSchemes( field ) );' );
    } );

    it( 'renders the rich text editor as a labelled toolbar and textbox', function () use ( $resource ): void {
        $editor = $resource( 'react/components/fields/RichTextEditor.tsx' );

        expect( $editor )
            ->toContain( 'role="toolbar"' )
            ->toContain( "aria-label={t( 'Formatting for :field', { field: fieldName } )}" )
            ->toContain( 'aria-controls={editorId}' )
            ->toContain( "aria-pressed={pressed[command.command] ? 'true' : 'false'}" )
            ->toContain( 'tabIndex={current === index ? 0 : -1}' )
            ->toContain( 'role="textbox"' )
            ->toContain( 'aria-multiline="true"' )
            ->toContain( 'contentEditable={!readOnly}' )
            ->and( $resource( 'react/components/fields/TextField.tsx' ) )
            ->toContain( 'if ( usesRichText( field ) ) {' )
            ->toContain( '<InputNotes field={field} value={richTextToPlainText( html )} />' );
    } );

    it( 'renders date and time parts in a fieldset with a legend', function () use ( $resource ): void {
        $parts = $resource( 'react/components/fields/DateTimeParts.tsx' );

        expect( $parts )
            ->toContain( '<fieldset id={`field-${field.name}`}' )
            ->toContain( '<legend className=' )
            ->toContain( 'DATE_PART_ORDER[getDateFormat( field )].map(' )
            ->toContain( 'onChange( combineDate( next ) );' )
            ->toContain( 'onChange( combineTime( next, twelveHour ) );' )
            ->and( $resource( 'react/components/fields/AdvancedField.tsx' ) )
            ->toContain( "if ( getDateInputMode( field ) !== 'picker' ) {" )
            ->toContain( 'min={bounds.min}' )
            ->and( $resource( 'react/components/fields/TextField.tsx' ) )
            ->toContain( "if ( getTimeInputMode( field ) === 'dropdowns' ) {" );
    } );

    it( 'shows sanitized rich text in the submission view', function () use ( $resource ): void {
        expect( $resource( 'react/components/admin/SubmissionDetail.tsx' ) )->toContain( 'dangerouslySetInnerHTML={{ __html: value.display_html }}' );
    } );
} );

describe( 'Vue renderer', function () use ( $resource ): void {
    it( 'routes rich text and date and time parts to their components', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/FieldRenderer.vue' ) )
            ->toContain( "return 'rich_text';" )
            ->toContain( "return 'date_time_parts';" )
            ->toContain( '<RichTextField' )
            ->toContain( '<DateTimePartsField' );
    } );

    it( 'adds the URL scheme when the input loses focus', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/TextField.vue' ) )
            ->toContain( '@focusout="addUrlScheme"' )
            ->toContain( 'const withScheme = withUrlScheme( typed, getAllowedUrlSchemes( props.field ) );' );
    } );

    it( 'renders the rich text editor as a labelled toolbar and textbox', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/RichTextField.vue' ) )
            ->toContain( 'role="toolbar"' )
            ->toContain( ":aria-label=\"t( 'Formatting for :field', { field: fieldName } )\"" )
            ->toContain( ":aria-pressed=\"pressed[command.command] ? 'true' : 'false'\"" )
            ->toContain( ':tabindex="current === index ? 0 : -1"' )
            ->toContain( 'role="textbox"' )
            ->toContain( '@keydown="onToolbarKeydown"' );
    } );

    it( 'renders date and time parts in a fieldset with a legend', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/DateTimePartsField.vue' ) )
            ->toContain( '<fieldset :id="`field-${field.name}`"' )
            ->toContain( '<legend v-if="field.label"' )
            ->toContain( "emit( 'update:modelValue', combineDate( dateParts.value ) );" )
            ->toContain( "emit( 'update:modelValue', combineTime( timeParts.value, twelveHour.value ) );" )
            ->and( $resource( 'vue/components/fields/AdvancedField.vue' ) )
            ->toContain( ':min="dateBounds.min"' );
    } );

    it( 'shows sanitized rich text in the submission view', function () use ( $resource ): void {
        expect( $resource( 'vue/components/admin/SubmissionDetail.vue' ) )->toContain( 'v-html="value.display_html"' );
    } );
} );
