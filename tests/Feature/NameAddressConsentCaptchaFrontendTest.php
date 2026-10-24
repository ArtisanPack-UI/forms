<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\FieldTypes\CompositeFieldType;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\FieldSettings;

/**
 * Guards the shared JS and TypeScript side of issues #109 (Name), #110
 * (Address), #111 (email confirmation), #112 (Consent) and #113 (CAPTCHA).
 * The React and Vue components have their own source tests. The surfaces
 * are validated by source content (the package ships no JS build or test
 * harness of its own), matching ChoiceAndCompositeFrontendTest.
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
    it( 'list the new field types and their payload keys', function () use ( $resource ): void {
        expect( $resource( 'types/artisanpack-forms.d.ts' ) )
            ->toContain( "    | 'name'\n    | 'address'\n    | 'consent'\n    | 'captcha'" )
            ->toContain( "export type SubInputType = 'text' | 'email' | 'tel' | 'url' | 'number' | 'select';" )
            ->toContain( "export type NameFormat = 'simple' | 'normal' | 'extended';" )
            ->toContain( "export type AddressType = 'international' | 'us' | 'canada';" )
            ->toContain( "export type CaptchaSize = 'normal' | 'compact' | 'invisible';" )
            ->toContain( 'export interface CaptchaWidget {' )
            ->toContain( 'export interface ConsentValue {' )
            ->toContain( 'options?: Array<{ value: string; label: string }>;' )
            ->toContain( 'parameter?: string | null;' )
            ->toContain( 'email_confirmation?: { email: string; confirm: string; confirm_placeholder: string | null };' )
            ->toContain( 'consent?: { text: string; description: string };' )
            ->toContain( 'captcha?: CaptchaWidget | null;' );
    } );

    it( 'mirror the settings schema inputs', function () use ( $resource ): void {
        expect( $resource( 'types/artisanpack-forms.d.ts' ) )
            ->toContain( "export type FieldSettingInput = '" . implode( "' | '", FieldSettings::INPUTS ) . "';" );
    } );
} );

describe( 'composite helpers', function () use ( $resource, $between ): void {
    it( 'mirror the sub-input types, settings and parameter cleaning', function () use ( $resource, $between ): void {
        $composite = $resource( 'shared/composite.ts' );

        expect( $composite )
            ->toContain( "export type SubInputSetting = '" . implode( "' | '", CompositeFieldType::SUB_INPUT_SETTINGS ) . "';" )
            ->toContain( 'export const SUB_INPUT_PARAMETER_PATTERN = /[^A-Za-z0-9_.\-]/g;' )
            ->toContain( 'export function matchSubInputOption( subInput: SubInputDefinition, value: string ): string | undefined' )
            ->and( $between( $composite, 'export function buildSubInputUpdate(', "\n}\n" ) )
            ->toContain( "if ( setting === 'parameter' ) {" )
            ->toContain( 'text = sanitizeSubInputParameter( text );' );

        expect( FormField::SUB_INPUT_TYPES )->toContain( 'select' );
    } );

    it( 'populate a composite field\'s parts from their parameters', function () use ( $resource, $between ): void {
        $initial = $between( $resource( 'shared/fieldSettings.ts' ), 'export function getInitialFieldValue(', "\n}\n" );

        expect( $initial )->toContain( 'const parameter = subInput.parameter;' )
            ->toContain( 'population.prefill[parameter]' )
            ->toContain( 'page.query.get( parameter )' )
            ->toContain( 'matchSubInputOption( subInput, value ) ?? base' );
    } );
} );

describe( 'validation', function () use ( $resource, $between ): void {
    it( 'treats consent as an acceptance type', function () use ( $resource ): void {
        expect( $resource( 'shared/fieldSettings.ts' ) )->toContain( "export const ACCEPTANCE_FIELD_TYPES = new Set( ['" . implode( "', '", FormField::ACCEPTANCE_TYPES ) . "'] );" );
    } );

    it( 'checks select sub-inputs against their options', function () use ( $resource, $between ): void {
        expect( $between( $resource( 'shared/validation.ts' ), 'export function validateCompositeField(', "\n}\n" ) )
            ->toContain( "subInput.type === 'select'" )
            ->toContain( ':field: please choose a valid :part.' );
    } );

    it( 'checks the email confirmation like the confirmed rule', function () use ( $resource, $between ): void {
        $validation = $resource( 'shared/validation.ts' );

        expect( $between( $validation, 'export function validateEmailConfirmation(', "\n}\n" ) )
            ->toContain( "email === String( confirmation ?? '' )" )
            ->toContain( 'The email addresses entered for :field do not match.' )
            ->and( $between( $validation, 'export function validateFields(', "\n}\n" ) )
            ->toContain( 'formData[getConfirmationKey( field )]' );

        expect( $resource( 'shared/fieldSettings.ts' ) )->toContain( 'return `${field.name}_confirmation`;' );
    } );

    it( 'requires a CAPTCHA token unless the widget fetches it on submit', function () use ( $resource, $between ): void {
        expect( $between( $resource( 'shared/validation.ts' ), 'export function validateCaptchaField(', "\n}\n" ) )
            ->toContain( '!hasCaptchaWidget( field ) || runsOnSubmit( field.captcha )' )
            ->toContain( 'Please complete the verification check.' );
    } );
} );

describe( 'CAPTCHA helpers', function () use ( $resource, $between ): void {
    it( 'drive each built-in provider\'s API', function () use ( $resource ): void {
        expect( $resource( 'shared/captcha.ts' ) )
            ->toContain( 'g.turnstile.render( element, { ...options, theme: widget.theme } )' )
            ->toContain( 'g.hcaptcha.execute( widgetId as string );' )
            ->toContain( 'g.grecaptcha.execute( widgetId as number );' )
            ->toContain( "g.grecaptcha?.execute( widget.site_key, { action: widget.action ?? 'forms_submit' } )" )
            ->toContain( "'expired-callback': expire," );
    } );

    it( 'load each script once', function () use ( $resource, $between ): void {
        expect( $between( $resource( 'shared/captcha.ts' ), 'export function loadCaptchaScript(', "\n}\n" ) )
            ->toContain( 'const existing = scripts.get( widget.script );' )
            ->toContain( 'scripts.set( widget.script, promise );' );
    } );

    it( 'run invisible widgets on submit and name the field whose hook failed', function () use ( $resource, $between ): void {
        $captcha = $resource( 'shared/captcha.ts' );

        expect( $captcha )->toContain( "return widget.size === 'invisible';" )
            ->and( $between( $captcha, 'export function createSubmitHooks(', "\n}\n" ) )
            ->toContain( 'throw new SubmitHookError( name );' )
            ->toContain( 'hook.afterSubmit?.( result );' );
    } );
} );

describe( 'field type labels', function () use ( $resource ): void {
    it( 'name the new types', function () use ( $resource ): void {
        expect( $resource( 'shared/fieldTypeLabels.ts' ) )
            ->toContain( "name: t( 'Name' )," )
            ->toContain( "address: t( 'Address' )," )
            ->toContain( "consent: t( 'Consent' )," )
            ->toContain( "captcha: t( 'CAPTCHA' )," );
    } );
} );
