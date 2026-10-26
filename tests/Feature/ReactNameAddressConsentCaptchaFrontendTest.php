<?php

declare( strict_types=1 );

/**
 * Guards the React side of issues #109 (Name), #110 (Address), #111 (email
 * confirmation), #112 (Consent) and #113 (CAPTCHA). The surfaces are
 * validated by source content (the package ships no JS build or test
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

describe( 'React field registry', function () use ( $resource, $between ): void {
    it( 'maps the new built-in types to their components', function () use ( $resource, $between ): void {
        $renderer = $resource( 'react/components/fields/FieldRenderer.tsx' );
        $map      = $between( $renderer, 'const FIELD_COMPONENTS: Record<BuiltInFieldType, FieldComponent> = {', '};' );

        expect( $map )->toContain( 'name: CompositeField,' )
            ->toContain( 'address: CompositeField,' )
            ->toContain( 'consent: ConsentField,' )
            ->toContain( 'captcha: CaptchaField,' )
            ->and( $renderer )->toContain( "import { CaptchaField } from './CaptchaField';" )
            ->toContain( "import { ConsentField } from './ConsentField';" );
    } );

    it( 'passes the email confirmation props through', function () use ( $resource ): void {
        expect( $resource( 'react/components/fields/types.ts' ) )
            ->toContain( 'confirmValue?: string;' )
            ->toContain( 'onConfirmChange?: ( text: string ) => void;' )
            ->and( $resource( 'react/components/fields/FieldRenderer.tsx' ) )
            ->toContain( 'confirmValue?: string;' )
            ->toContain( 'onConfirmChange?: ( text: string ) => void;' )
            ->toContain( 'confirmValue={confirmValue}' )
            ->toContain( 'onConfirmChange={onConfirmChange}' );
    } );
} );

describe( 'React composite field (Name and Address)', function () use ( $resource ): void {
    it( 'renders select sub-inputs with an empty first option', function () use ( $resource ): void {
        expect( $resource( 'react/components/fields/CompositeField.tsx' ) )
            ->toContain( "{subInput.type === 'select' ? (" )
            ->toContain( '<select' )
            ->toContain( 'className={`select select-bordered w-full ${subError ? \'select-error\' : \'\'}`}' )
            ->toContain( "<option value=\"\">{subInput.placeholder || t( 'Select…' )}</option>" )
            ->toContain( '{( subInput.options ?? [] ).map( ( option ) => (' )
            ->toContain( '<option key={option.value} value={option.value}>{option.label}</option>' );
    } );

    it( 'spans wide sub-inputs and marks the parts for autocomplete providers', function () use ( $resource ): void {
        $composite = $resource( 'react/components/fields/CompositeField.tsx' );

        expect( $composite )
            ->toContain( 'const isWide = Boolean( subInput.wide ) && subInputs.length > 1;' )
            ->toContain( "className={`flex flex-col gap-1 \${isWide ? 'sm:col-span-2' : ''}`}" )
            ->toContain( 'data-forms-composite={field.type}' )
            ->and( substr_count( $composite, 'data-forms-part={subInput.key}' ) )->toBe( 2 )
            ->and( substr_count( $composite, 'autoComplete={subInput.autocomplete ?? undefined}' ) )->toBe( 2 );
    } );
} );

describe( 'React email confirmation', function () use ( $resource, $between ): void {
    it( 'renders the email and confirm inputs in a fieldset', function () use ( $resource, $between ): void {
        $text      = $resource( 'react/components/fields/TextField.tsx' );
        $confirmed = $between( $text, 'function ConfirmedEmailField(', "\n}\n" );

        expect( $between( $text, 'export function EmailField(', "\n}\n" ) )
            ->toContain( 'if ( requiresEmailConfirmation( field ) ) {' )
            ->toContain( 'return <ConfirmedEmailField {...props} />;' )
            ->and( $confirmed )->toContain( '<fieldset id={`fieldset-${field.name}`}' )
            ->toContain( '<legend className={`fieldset-legend ${hiddenLabel ? \'sr-only\' : \'\'}`}>' )
            ->toContain( '<div className="grid grid-cols-1 gap-3 sm:grid-cols-2">' )
            ->toContain( 'id: `field-${field.name}`,' )
            ->toContain( 'id: `field-${getConfirmationKey( field )}`,' )
            ->toContain( 'label: labels?.email ?? \'\',' )
            ->toContain( 'label: labels?.confirm ?? \'\',' )
            ->toContain( 'placeholder: labels?.confirm_placeholder ?? null,' )
            ->toContain( 'type="email"' )
            ->toContain( 'htmlFor={input.id}' )
            ->toContain( "{layout.description === 'above' && hint}" )
            ->toContain( '<p id={errorId} className="fieldset-label text-error" role="alert">{error}</p>' )
            ->toContain( 'change: ( text: string ) => onConfirmChange?.( text ),' );
    } );

    it( 'never blocks pasting into the confirm input', function () use ( $resource ): void {
        expect( $resource( 'react/components/fields/TextField.tsx' ) )
            ->not->toContain( 'onPaste' );
    } );

    it( 'seeds, clears and submits the confirmation', function () use ( $resource ): void {
        $hook = $resource( 'react/hooks/useForm.ts' );

        expect( substr_count( $hook, 'defaults[getConfirmationKey( field )] = defaults[field.name];' ) )->toBe( 2 )
            ->and( substr_count( $hook, "if ( requiresEmailConfirmation( field ) && typeof defaults[field.name] === 'string' ) {" ) )->toBe( 2 )
            ->and( $hook )->toContain( 'map[getConfirmationKey( field )] = field.name;' )
            ->toContain( 'delete next[confirmedField];' )
            ->toContain( 'if ( confirmedField !== undefined && hiddenFields[confirmedField] ) {' );
    } );

    it( 'wires the confirm value through the renderer', function () use ( $resource ): void {
        expect( $resource( 'react/components/FormRenderer.tsx' ) )
            ->toContain( 'const confirms = requiresEmailConfirmation( field );' )
            ->toContain( "confirmValue={confirms ? String( values[getConfirmationKey( field )] ?? '' ) : undefined}" )
            ->toContain( 'onConfirmChange={confirms ? ( text ) => setValue( getConfirmationKey( field ), text ) : undefined}' );
    } );
} );

describe( 'React consent field', function () use ( $resource ): void {
    it( 'renders the label, checkbox, text and sanitized description', function () use ( $resource ): void {
        expect( $resource( 'react/components/fields/ConsentField.tsx' ) )
            ->toContain( '<FieldLayout field={field} layout={layout} renderLabel={false}>' )
            ->toContain( 'aria-labelledby={field.label ? `label-${field.name} consent-text-${field.name}` : undefined}' )
            ->toContain( '<span id={`consent-text-${field.name}`} className="label-text whitespace-normal">{text}</span>' )
            ->toContain( '<p id={`label-${field.name}`} className={`label-text font-medium ${hiddenLabel ? \'sr-only\' : \'\'}`}>' )
            ->toContain( 'type="checkbox"' )
            ->toContain( 'checked={checked}' )
            ->toContain( 'onChange={( e ) => onChange( e.target.checked )}' )
            ->toContain( "const text = field.consent?.text ?? '';" )
            ->toContain( "const descriptionId = description !== '' ? `consent-description-\${field.name}` : undefined;" )
            ->toContain( 'dangerouslySetInnerHTML={{ __html: description }}' )
            ->toContain( 'const describedBy = [descriptionId, hintId, errorId].filter( Boolean ).join( \' \' ) || undefined;' )
            ->toContain( 'aria-describedby={describedBy}' );
    } );
} );

describe( 'React CAPTCHA field', function () use ( $resource ): void {
    it( 'renders nothing without a provider and a labelled group otherwise', function () use ( $resource ): void {
        expect( $resource( 'react/components/fields/CaptchaField.tsx' ) )
            ->toContain( 'const widget = hasCaptchaWidget( field ) ? field.captcha : null;' )
            ->toContain( "if ( !widget ) {\n\t\treturn null;\n\t}" )
            ->toContain( 'role="group"' )
            ->toContain( 'aria-labelledby={labelId}' )
            ->toContain( 'const hiddenLabel = runsOnSubmit( widget ) || layout.label === \'hidden\'' )
            ->toContain( '<div ref={widgetRef} />' )
            ->toContain( 'data-forms-captcha={widget.provider}' );
    } );

    it( 'mounts the shared controller once and shows a load failure', function () use ( $resource ): void {
        expect( $resource( 'react/components/fields/CaptchaField.tsx' ) )
            ->toContain( 'createCaptchaController( widget, ( token ) => onChangeRef.current( token ) )' )
            ->toContain( 'if ( !controller || !element || mountedOn.current === element ) {' )
            ->toContain( 'controller.mount( element ).catch( () => setFailed( true ) );' )
            ->toContain( "{t( 'The verification check could not be loaded. Please reload the page and try again.' )}" );
    } );

    it( 'registers submit hooks that fetch and reset the token', function () use ( $resource ): void {
        expect( $resource( 'react/components/fields/CaptchaField.tsx' ) )
            ->toContain( 'const submitHooks = useSubmitHooks();' )
            ->toContain( 'return submitHooks.register( field.name, {' )
            ->toContain( 'beforeSubmit: runsOnSubmit( widget )' )
            ->toContain( '? async () => ( { [field.name]: await controller.execute() } )' )
            ->toContain( 'if ( !ok && ( errors[field.name] || Object.keys( errors ).length === 0 ) ) {' )
            ->toContain( 'controller.reset();' );
    } );
} );

describe( 'React submit hooks', function () use ( $resource ): void {
    it( 'provides the registry through a context', function () use ( $resource ): void {
        expect( $resource( 'react/components/fields/SubmitHooksContext.ts' ) )
            ->toContain( 'export const SubmitHooksContext = createContext<SubmitHooks | null>( null );' )
            ->toContain( 'export function useSubmitHooks(): SubmitHooks | null {' )
            ->and( $resource( 'react/components/FormRenderer.tsx' ) )
            ->toContain( '<SubmitHooksContext.Provider value={submitHooks}>' )
            ->toContain( '</SubmitHooksContext.Provider>' );
    } );

    it( 'creates one registry and exposes it from useForm', function () use ( $resource ): void {
        expect( $resource( 'react/hooks/useForm.ts' ) )
            ->toContain( 'submitHooks: SubmitHooks;' )
            ->toContain( 'submitHooksRef.current = createSubmitHooks();' )
            ->toContain( "\t\tsubmitHooks,\n\t};" );
    } );

    it( 'collects hook values before sending and settles every outcome', function () use ( $resource ): void {
        $hook = $resource( 'react/hooks/useForm.ts' );

        expect( $hook )
            ->toContain( 'collected = await submitHooks.collect();' )
            ->toContain( 'if ( err instanceof SubmitHookError ) {' )
            ->toContain( "setErrors( { [err.field]: [t( 'The verification check failed. Please try again.' )] } );" )
            ->toContain( 'formDataObj.append( `data[${key}]`, String( value ) );' )
            ->toContain( 'submitHooks.settle( { ok: false, errors: serverErrors } );' )
            ->toContain( 'submitHooks.settle( { ok: true, errors: {} } );' )
            ->and( substr_count( $hook, 'submitHooks.settle( { ok: false, errors: {} } );' ) )->toBe( 2 );
    } );
} );

describe( 'React builder', function () use ( $resource, $between ): void {
    it( 'edits textarea settings', function () use ( $resource, $between ): void {
        $control = $between( $resource( 'react/components/admin/FieldEditor.tsx' ), 'function SettingControl(', "\n}\n" );

        expect( $control )->toContain( "if ( setting.input === 'textarea' ) {" )
            ->toContain( '<Textarea' )
            ->toContain( 'rows={4}' )
            ->toContain( 'maxLength={setting.max_length}' )
            ->toContain( 'className="textarea textarea-sm textarea-bordered w-full"' );
    } );

    it( 'hides the placeholder and default value for consent and CAPTCHA fields', function () use ( $resource ): void {
        expect( $resource( 'react/components/admin/FieldEditor.tsx' ) )
            ->toContain( "const NO_PLACEHOLDER_TYPES: Set<FieldType> = new Set( ['checkbox', 'toggle', 'radio', 'rating', 'consent', 'captcha', 'datetime', 'list', 'slider', 'nps', 'matrix', 'ranking', 'signature'] );" )
            ->toContain( "const NO_DEFAULT_VALUE_TYPES: Set<FieldType> = new Set( ['consent', 'captcha', 'list', 'matrix', 'ranking', 'signature'] );" )
            ->toContain( '!NO_DEFAULT_VALUE_TYPES.has( field.type )' );
    } );

    it( 'edits a sub-input\'s default value and population parameter', function () use ( $resource ): void {
        expect( $resource( 'react/components/admin/SubInputsEditor.tsx' ) )
            ->toContain( "<th scope=\"col\">{t( 'Default' )}</th>" )
            ->toContain( "<th scope=\"col\">{t( 'Parameter' )}</th>" )
            ->toContain( "aria-label={t( ':input default value', { input: subInput.default_label } )}" )
            ->toContain( "aria-label={t( ':input population parameter', { input: subInput.default_label } )}" )
            ->toContain( "<option value=\"\">{t( 'None' )}</option>" )
            ->toContain( "change( subInput.key, 'default', e.target.value )" )
            ->toContain( "change( subInput.key, 'parameter', e.target.value );" )
            ->toContain( 'className="input input-xs input-bordered w-full font-mono"' )
            ->toContain( 'maxLength={100}' )
            ->toContain( "{t( 'A parameter fills the input from the renderer prefill or the query string.' )}" );
    } );

    it( 'adds the new types to the advanced palette', function () use ( $resource, $between ): void {
        $palette  = $resource( 'react/components/admin/FieldPalette.tsx' );
        $advanced = $between( $palette, "label: t( 'Advanced Fields' ),", "label: t( 'Layout' )," );

        expect( $advanced )->toContain( "{ type: 'name', label: t( 'Name' ), icon: 'name', category: 'advanced' }," )
            ->toContain( "{ type: 'address', label: t( 'Address' ), icon: 'address', category: 'advanced' }," )
            ->toContain( "{ type: 'consent', label: t( 'Consent' ), icon: 'consent', category: 'advanced' }," )
            ->toContain( "{ type: 'captcha', label: t( 'CAPTCHA' ), icon: 'captcha', category: 'advanced' }," );

        foreach ( ['name', 'address', 'consent', 'captcha'] as $icon ) {
            expect( $palette )->toMatch( "/\\n\\t{$icon}: 'M[^']+',\\n/" );
        }
    } );

    it( 'shows consent records and composite values in the submission detail', function () use ( $resource, $between ): void {
        $detail  = $resource( 'react/components/admin/SubmissionDetail.tsx' );
        $consent = $between( $detail, 'function ConsentValueDetails(', "\n}\n" );

        expect( $detail )->toContain( ") : value.field_type === 'consent' ? (" )
            ->toContain( '<ConsentValueDetails value={value} />' )
            ->toContain( ') : isCompositeValue( value.value_array ) ? (' )
            ->toContain( '<div className="whitespace-pre-line">{value.display_value}</div>' )
            ->toContain( "return valueArray !== null && typeof valueArray === 'object' && !Array.isArray( valueArray );" )
            ->and( $consent )->toContain( "const consented = consent.consented === '1';" )
            ->toContain( '<details className="mt-2">' )
            ->toContain( "{t( 'View consent text' )}" )
            ->toContain( 'dangerouslySetInnerHTML={{ __html: consent.description }}' )
            ->toContain( "{t( 'The consent text was not stored.' )}" )
            ->toContain( "t( 'Consented at :time', { time: consent.consented_at ?? '—' } )" )
            ->toContain( "t( 'Revision :revision', { revision: String( consent.revision ?? '' ).slice( 0, 12 ) } )" );
    } );
} );

describe( 'React exports', function () use ( $resource ): void {
    it( 'exports the new components, context and helpers', function () use ( $resource ): void {
        expect( $resource( 'react/index.ts' ) )
            ->toContain( "export { ConsentField } from './components/fields/ConsentField';" )
            ->toContain( "export { CaptchaField } from './components/fields/CaptchaField';" )
            ->toContain( "export { SubmitHooksContext, useSubmitHooks } from './components/fields/SubmitHooksContext';" )
            ->toContain( 'createCaptchaController,' )
            ->toContain( 'createSubmitHooks,' )
            ->toContain( 'hasCaptchaWidget,' )
            ->toContain( 'runsOnSubmit,' )
            ->toContain( 'SubmitHookError,' )
            ->toContain( "} from '../shared/captcha';" )
            ->toContain( "export type { CaptchaController, SubmitHook, SubmitHooks } from '../shared/captcha';" )
            ->toContain( 'requiresEmailConfirmation,' )
            ->toContain( 'getConfirmationKey,' );
    } );
} );
