<?php

declare( strict_types=1 );

/**
 * Guards the React, Vue and shared JS side of issues #114 (multi-file
 * upload), #115 (input masks), #116 (counters), #117 (phone formats) and
 * #118 (number options). The surfaces are validated by source content (the
 * package ships no JS build or test harness of its own), matching
 * ReactNameAddressConsentCaptchaFrontendTest.
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
    it( 'mirrors the mask tokens and Alpine\'s deletion behaviour', function () use ( $resource ): void {
        expect( $resource( 'shared/mask.ts' ) )
            ->toContain( "'9': /[0-9]/," )
            ->toContain( 'a: /[a-zA-Z]/,' )
            ->toContain( "'*': /[a-zA-Z0-9]/," )
            ->toContain( 'if ( previous !== undefined && value.length === previous.length - 1 ) {' )
            ->toContain( 'return buildUp( mask, stripDown( mask, value ) );' )
            ->toContain( "return character.replace( /[.*+?^\${}()|[\\]\\\\]/g, '\\\\$&' );" )
            ->toContain( "return new RegExp( `^\${pattern}$`, 'u' );" );
    } );

    it( 'mirrors the number formats', function () use ( $resource ): void {
        expect( $resource( 'shared/numberFormat.ts' ) )
            ->toContain( "export const NUMBER_FORMATS: readonly NumberFormat[] = ['decimal_dot', 'decimal_comma', 'currency'];" )
            ->toContain( "const [thousands, decimal] = format === 'decimal_comma' ? ['.', ','] : [',', '.'];" )
            ->toContain( 'return roundDecimal( plain, 2 );' );
    } );

    it( 'counts words like the forms_max_words rule and throttles announcements', function () use ( $resource ): void {
        expect( $resource( 'shared/counter.ts' ) )
            ->toContain( 'export const COUNTER_ANNOUNCE_DELAY = 1000;' )
            ->toContain( "return trimmed === '' ? 0 : trimmed.split( /\\s+/u ).length;" )
            ->toContain( "t( ':count of :max words', { count, max: limit ?? '' } )" )
            ->toContain( "t( ':count of :max characters', { count, max: limit } )" );
    } );

    it( 'composes phone numbers with the selected calling code', function () use ( $resource ): void {
        expect( $resource( 'shared/phone.ts' ) )
            ->toContain( "if ( !country || trimmed === '' || trimmed.startsWith( '+' ) ) {" )
            ->toContain( 'return `+${country.dial_code} ${national}`;' );
    } );

    it( 'validates masks, word limits, phone and number options, and file lists on the client', function () use ( $resource, $between ): void {
        $validation = $resource( 'shared/validation.ts' );

        expect( $validation )
            ->toContain( 'if ( mask && !maskToRegExp( mask ).test( value ) ) {' )
            ->toContain( "if ( field.type === 'phone' && !isPlausiblePhoneNumber( value, getPhoneFormat( field ) ) ) {" )
            ->toContain( 'const plain = format ? normalizeFormattedNumber( String( value ), format ) : String( value );' )
            ->toContain( 'if ( isIntegerOnly( field ) && !Number.isInteger( num ) ) {' )
            ->toContain( 'if ( typeof text === \'string\' && maxWords !== undefined && countWords( text ) > maxWords ) {' )
            ->and( $between( $validation, 'function validateFileListRules(', "\n}\n" ) )
            ->toContain( 'if ( maxFiles !== undefined && fileList.length > maxFiles ) {' )
            ->toContain( 'fileList.reduce( ( total, file ) => total + file.size, 0 ) > maxTotalSize * 1024' );
    } );

    it( 'adds note IDs to the input description without losing its hint and error', function () use ( $resource, $between ): void {
        $settings = $resource( 'shared/fieldSettings.ts' );

        expect( $between( $settings, 'export function getInputNoteIds(', "\n}\n" ) )
            ->toContain( '`field-${field.name}-mask`' )
            ->toContain( '`field-${field.name}-counter`' )
            ->toContain( '`field-${field.name}-affix`' )
            ->and( $between( $settings, 'export function withInputNotes(', "\n}\n" ) )
            ->toContain( 'props.hint && !error ? `${id}-hint` : undefined,' )
            ->toContain( 'error ? `${id}-error` : undefined,' );
    } );
} );

describe( 'React', function () use ( $resource, $between ): void {
    it( 'masks text and phone input and renders the notes', function () use ( $resource, $between ): void {
        $text = $resource( 'react/components/fields/TextField.tsx' );

        expect( $between( $text, 'export function TextField(', "\n}\n" ) )
            ->toContain( 'onChange={( e ) => onChange( mask ? applyMask( e.target.value, mask, text ) : e.target.value )}' )
            ->toContain( "maxLength={getLengthLimit( field, 'max' )}" )
            ->toContain( '<InputNotes field={field} value={text} />' )
            ->and( $between( $text, 'export function TextareaField(', "\n}\n" ) )
            ->toContain( "maxLength={getLengthLimit( field, 'max' )}" )
            ->toContain( '<InputNotes field={field} value={text} />' );
    } );

    it( 'renders the phone country selector', function () use ( $resource, $between ): void {
        expect( $between( $resource( 'react/components/fields/TextField.tsx' ), 'export function PhoneField(', "\n}\n" ) )
            ->toContain( 'id={`field-${field.name}-country`}' )
            ->toContain( 'autoComplete="country"' )
            ->toContain( "aria-label={t( ':field country', { field: field.label ?? field.name } )}" )
            ->toContain( 'onChange( composePhoneNumber( national, next ) );' );
    } );

    it( 'renders number formats, prefix and suffix', function () use ( $resource, $between ): void {
        expect( $between( $resource( 'react/components/fields/TextField.tsx' ), 'export function NumberField(', "\n}\n" ) )
            ->toContain( "type={format ? 'text' : 'number'}" )
            ->toContain( "inputMode={format ? ( integerOnly ? 'numeric' : 'decimal' ) : undefined}" )
            ->toContain( 'prefix={prefix}' )
            ->toContain( 'suffix={suffix}' );
    } );

    it( 'announces the counter politely after typing pauses', function () use ( $resource ): void {
        expect( $resource( 'react/components/fields/InputNotes.tsx' ) )
            ->toContain( 'setTimeout( () => setAnnouncement( counter.text ), COUNTER_ANNOUNCE_DELAY )' )
            ->toContain( '<span className="sr-only" aria-live="polite" aria-atomic="true">{announcement}</span>' )
            ->toContain( 'id={`field-${field.name}-counter`}' )
            ->toContain( 'id={`field-${field.name}-affix`} className="sr-only"' );
    } );

    it( 'lists multiple files with remove buttons and a live region', function () use ( $resource, $between ): void {
        $multi = $between( $resource( 'react/components/fields/AdvancedField.tsx' ), 'function MultiFileField(', "\n}\n" );

        expect( $multi )
            ->toContain( 'multiple' )
            ->toContain( 'withDragDrop' )
            ->toContain( "aria-label={t( 'Remove :name', { name: file.name } )}" )
            ->toContain( '<div className="sr-only" aria-live="polite" aria-atomic="true">{announcement}</div>' )
            ->toContain( 'onFileChange?.( next );' );
    } );

    it( 'shows every file of a value in the submission detail', function () use ( $resource ): void {
        expect( $resource( 'react/components/admin/SubmissionDetail.tsx' ) )
            ->toContain( 'const ids = value.upload_ids && value.upload_ids.length > 0 ? value.upload_ids : [value.upload_id];' )
            ->toContain( '{valueUploads.map( ( upload ) => (' );
    } );
} );

describe( 'Vue', function () use ( $resource, $between ): void {
    it( 'sets length and keypad attributes on the native input', function () use ( $resource ): void {
        $text = $resource( 'vue/components/fields/TextField.vue' );

        expect( $text )
            ->toContain( "import { vInputAttrs } from './inputAttrs';" )
            ->toContain( 'v-input-attrs="lengthAttrs"' )
            ->toContain( 'v-input-attrs="numberAttrs"' )
            ->toContain( "maxlength: getLengthLimit( props.field, 'max' )," )
            ->and( $resource( 'vue/components/fields/inputAttrs.ts' ) )
            ->toContain( 'control.setAttribute( name, String( value ) );' );
    } );

    it( 'masks text input, composes phone numbers and renders affixes', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/TextField.vue' ) )
            ->toContain( "@update:model-value=\"emit( 'update:modelValue', masked( String( \$event ?? '' ), stringValue ) )\"" )
            ->toContain( "emit( 'update:modelValue', composePhoneNumber( masked( text, phoneNational.value ), phoneCountry.value ) );" )
            ->toContain( ':id="`field-${field.name}-country`"' )
            ->toContain( '<template v-if="affixes.prefix" #prefix>{{ affixes.prefix }}</template>' )
            ->toContain( '<template v-if="affixes.suffix" #suffix>{{ affixes.suffix }}</template>' )
            ->toContain( 'v-if="NOTED_TYPES.has( field.type )"' );
    } );

    it( 'announces the counter politely after typing pauses', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/InputNotes.vue' ) )
            ->toContain( '}, COUNTER_ANNOUNCE_DELAY );' )
            ->toContain( '<span class="sr-only" aria-live="polite" aria-atomic="true">{{ announcement }}</span>' );
    } );

    it( 'lists multiple files with remove buttons and a live region', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/AdvancedField.vue' ) )
            ->toContain( '<div v-if="field.type === \'file\' && multiple">' )
            ->toContain( '@files-selected="addFiles"' )
            ->toContain( ":aria-label=\"t( 'Remove :name', { name: file.name } )\"" )
            ->toContain( '<div class="sr-only" aria-live="polite" aria-atomic="true">{{ announcement }}</div>' );
    } );

    it( 'shows every file of a value in the submission detail', function () use ( $resource ): void {
        expect( $resource( 'vue/components/admin/SubmissionDetail.vue' ) )
            ->toContain( '<li v-for="upload in findUploads( value )" :key="upload.id"' );
    } );
} );

describe( 'types', function () use ( $resource ): void {
    it( 'describes the new configs and payload entries', function () use ( $resource ): void {
        expect( $resource( 'types/artisanpack-forms.d.ts' ) )
            ->toContain( "export type InputMaskPreset = 'us_phone' | 'zip' | 'zip_plus4' | 'ssn' | 'date' | 'custom';" )
            ->toContain( "export type PhoneFormat = 'any' | 'us' | 'international';" )
            ->toContain( "export type NumberFormat = 'decimal_dot' | 'decimal_comma' | 'currency';" )
            ->toContain( 'input_mask?: { mask: string; hint: string };' )
            ->toContain( 'phone?: { format: PhoneFormat; default_country: string; countries: PhoneCountry[] };' )
            ->toContain( 'upload_ids: number[];' )
            ->toContain( 'max_words?: number;' )
            ->toContain( 'integer_only?: boolean;' );
    } );
} );
