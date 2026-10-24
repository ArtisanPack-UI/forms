<?php

declare( strict_types=1 );

/**
 * Guards the Vue side of issues #109 (name fields), #110 (address fields),
 * #111 (email confirmation), #112 (consent fields) and #113 (CAPTCHA
 * fields). The surfaces are validated by source content (the package ships
 * no JS build or test harness of its own), matching
 * ChoiceAndCompositeFrontendTest.
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

describe( 'Vue composite fields (name and address)', function () use ( $resource ): void {
    it( 'renders select sub-inputs with an empty first option', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/CompositeField.vue' ) )
            ->toContain( '<select' )
            ->toContain( "v-if=\"subInput.type === 'select'\"" )
            ->toContain( "'select select-bordered w-full'" )
            ->toContain( "<option value=\"\">{{ subInput.placeholder || t( 'Select…' ) }}</option>" )
            ->toContain( '<option v-for="option in subInput.options ?? []" :key="option.value" :value="option.value">' );
    } );

    it( 'spans wide sub-inputs across the row and marks the parts for autocomplete providers', function () use ( $resource ): void {
        $composite = $resource( 'vue/components/fields/CompositeField.vue' );

        expect( $composite )
            ->toContain( "subInput.wide && subInputs.length > 1 ? 'sm:col-span-2' : ''" )
            ->toContain( ':data-forms-composite="field.type"' )
            ->and( substr_count( $composite, ':data-forms-part="subInput.key"' ) )->toBe( 2 );
    } );

    it( 'routes name and address fields to the composite field', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/FieldRenderer.vue' ) )
            ->toContain( "const COMPOSITE_TYPES = new Set( ['name', 'address'] );" )
            ->toContain( "if ( COMPOSITE_TYPES.has( props.field.type ) ) {\n\t\treturn 'composite';" );
    } );
} );

describe( 'Vue email confirmation', function () use ( $resource, $between ): void {
    it( 'renders two email inputs in a labelled fieldset', function () use ( $resource ): void {
        $email = $resource( 'vue/components/fields/EmailConfirmationField.vue' );

        expect( $email )
            ->toContain( '<fieldset' )
            ->toContain( '<legend v-if="field.label"' )
            ->toContain( 'class="grid grid-cols-1 gap-3 sm:grid-cols-2"' )
            ->toContain( 'type="email"' )
            ->toContain( 'id: `field-${props.field.name}`,' )
            ->toContain( 'id: `field-${getConfirmationKey( props.field )}`,' )
            ->toContain( "label: props.field.email_confirmation?.email ?? ''," )
            ->toContain( "label: props.field.email_confirmation?.confirm ?? ''," )
            ->toContain( 'placeholder: props.field.email_confirmation?.confirm_placeholder ?? undefined,' )
            ->toContain( '<p v-if="error" :id="errorId" class="fieldset-label text-error" role="alert">' )
            ->toContain( "layout.description === 'above'" )
            ->not->toContain( '@paste' )
            ->not->toContain( 'onpaste' );
    } );

    it( 'emits the confirmation separately from the email', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/EmailConfirmationField.vue' ) )
            ->toContain( 'confirmValue?: string;' )
            ->toContain( "'update:confirmValue': [text: string];" )
            ->toContain( "emit( 'update:confirmValue', text );" )
            ->and( $resource( 'vue/components/fields/FieldRenderer.vue' ) )
            ->toContain( 'confirmValue?: string;' )
            ->toContain( "if ( requiresEmailConfirmation( props.field ) ) {\n\t\treturn 'email_confirmation';" )
            ->toContain( ':confirm-value="confirmValue"' )
            ->toContain( "@update:confirm-value=\"emit( 'update:confirmValue', \$event )\"" );
    } );

    it( 'passes the confirmation from the form renderer', function () use ( $resource ): void {
        $renderer = $resource( 'vue/components/FormRenderer.vue' );

        expect( $renderer )
            ->toContain( "return requiresEmailConfirmation( field ) ? String( values.value[getConfirmationKey( field )] ?? '' ) : undefined;" )
            ->toContain( 'setValue( getConfirmationKey( field ), text );' )
            ->and( substr_count( $renderer, ':confirm-value="getConfirmValue( field )"' ) )->toBe( 2 )
            ->and( substr_count( $renderer, '@update:confirm-value="setConfirmValue( field, $event )"' ) )->toBe( 2 );
    } );

    it( 'seeds the confirmation and clears the email error when it changes', function () use ( $resource, $between ): void {
        $form = $resource( 'vue/composables/useForm.ts' );

        expect( $between( $form, 'function buildDefaults(', "\n}\n" ) )
            ->toContain( 'if ( requiresEmailConfirmation( field ) ) {' )
            ->toContain( "defaults[getConfirmationKey( field )] = typeof email === 'string' ? email : '';" )
            ->and( $between( $form, 'function setValue(', "\n\t}\n" ) )
            ->toContain( 'const confirmedField = confirmationFields.value[name];' )
            ->toContain( 'delete next[confirmedField];' );
    } );

    it( 'leaves the confirmation out with its hidden email field', function () use ( $resource, $between ): void {
        expect( $between( $resource( 'vue/composables/useForm.ts' ), 'async function submit(', "\n\t}\n" ) )
            ->toContain( 'const confirmedField = confirmationFields.value[key];' )
            ->toContain( 'if ( confirmedField && hiddenFields.value[confirmedField] ) {' );
    } );
} );

describe( 'Vue consent field', function () use ( $resource ): void {
    it( 'renders the label as a heading and the checkbox with its consent text', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/ConsentField.vue' ) )
            ->toContain( 'const labelId = computed( () => `label-${props.field.name}` );' )
            ->toContain( "<p v-if=\"field.label\" :id=\"labelId\" :class=\"['label-text font-medium', hiddenLabel ? 'sr-only' : '']\">" )
            ->toContain( 'type="checkbox"' )
            ->toContain( ':checked="isChecked"' )
            ->toContain( "@change=\"emit( 'update:modelValue', ( \$event.target as HTMLInputElement ).checked )\"" )
            ->toContain( "const consentText = computed( () => props.field.consent?.text ?? '' );" )
            ->toContain( '<span class="label-text whitespace-normal">{{ consentText }}</span>' );
    } );

    it( 'renders the sanitized description and describes the checkbox by it', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/ConsentField.vue' ) )
            ->toContain( "const consentDescription = computed( () => props.field.consent?.description ?? '' );" )
            ->toContain( 'v-html="consentDescription"' )
            ->toContain( '`consent-description-${props.field.name}`' )
            ->toContain( "descriptionId.value,\n\tlayout.value.custom ? getLayoutDescribedBy( props.field, layout.value ) : hintId.value,\n\terrorId.value," )
            ->toContain( ':aria-describedby="describedBy"' )
            ->toContain( 'role="alert"' );
    } );

    it( 'is registered with the field renderer', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/FieldRenderer.vue' ) )
            ->toContain( "import ConsentField from './ConsentField.vue';" )
            ->toContain( "const CONSENT_TYPES = new Set( ['consent'] );" )
            ->toContain( "v-else-if=\"componentGroup === 'consent'\"" );
    } );
} );

describe( 'Vue CAPTCHA field', function () use ( $resource, $between ): void {
    it( 'renders nothing without a widget and a labelled group otherwise', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/CaptchaField.vue' ) )
            ->toContain( 'const hasWidget = computed( () => hasCaptchaWidget( props.field ) );' )
            ->toContain( '<FieldLayout v-if="hasWidget"' )
            ->toContain( 'role="group"' )
            ->toContain( ':aria-labelledby="labelId"' )
            ->toContain( "props.field.captcha.size === 'invisible'" )
            ->toContain( '<div ref="widgetElement" />' )
            ->toContain( "{{ t( 'The verification check could not be loaded. Please reload the page and try again.' ) }}" );
    } );

    it( 'mounts the widget once and stores each token', function () use ( $resource, $between ): void {
        $mounted = $between( $resource( 'vue/components/fields/CaptchaField.vue' ), 'onMounted( () => {', "\n} );\n" );

        expect( $mounted )
            ->toContain( "const captcha = createCaptchaController( widget, ( token ) => emit( 'update:modelValue', token ) );" )
            ->toContain( 'captcha.mount( widgetElement.value ).catch( () => {' )
            ->toContain( 'failed.value = true;' );
    } );

    it( 'registers submit hooks that fetch and reset the token', function () use ( $resource, $between ): void {
        $captcha = $resource( 'vue/components/fields/CaptchaField.vue' );
        $mounted = $between( $captcha, 'onMounted( () => {', "\n} );\n" );

        expect( $mounted )
            ->toContain( 'unregister = submitHooks?.register( field.name, {' )
            ->toContain( 'beforeSubmit: runsOnSubmit( widget )' )
            ->toContain( 'async () => ( { [field.name]: await captcha.execute() } )' )
            ->toContain( 'if ( !ok && ( errors[field.name] || Object.keys( errors ).length === 0 ) ) {' )
            ->toContain( 'captcha.reset();' )
            ->and( $between( $captcha, 'onBeforeUnmount( () => {', "\n} );\n" ) )
            ->toContain( 'unregister?.();' );
    } );

    it( 'is registered with the field renderer', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/FieldRenderer.vue' ) )
            ->toContain( "import CaptchaField from './CaptchaField.vue';" )
            ->toContain( "const CAPTCHA_TYPES = new Set( ['captcha'] );" )
            ->toContain( "v-else-if=\"componentGroup === 'captcha'\"" );
    } );
} );

describe( 'Vue submit hooks', function () use ( $resource, $between ): void {
    it( 'provides one registry per form through an injection key', function () use ( $resource ): void {
        expect( $resource( 'vue/composables/submitHooks.ts' ) )
            ->toContain( 'export const SUBMIT_HOOKS_KEY: InjectionKey<SubmitHooks> = Symbol(' )
            ->toContain( 'export function useSubmitHooks(): SubmitHooks | null {' )
            ->toContain( 'return inject( SUBMIT_HOOKS_KEY, null );' )
            ->and( $resource( 'vue/composables/useForm.ts' ) )
            ->toContain( 'const submitHooks: SubmitHooks = createSubmitHooks();' )
            ->toContain( "\t\tsubmitHooks,\n" )
            ->toContain( '@since 1.6.0' )
            ->and( $resource( 'vue/components/FormRenderer.vue' ) )
            ->toContain( 'provide( SUBMIT_HOOKS_KEY, submitHooks );' );
    } );

    it( 'collects hook values after validation and stops when a hook fails', function () use ( $resource, $between ): void {
        $submit = $between( $resource( 'vue/composables/useForm.ts' ), 'async function submit(', "\n\t}\n" );

        expect( strpos( $submit, 'validateCurrentFieldSet( allVisibleFields )' ) )
            ->toBeLessThan( strpos( $submit, 'await submitHooks.collect()' ) )
            ->and( $submit )
            ->toContain( 'if ( hookError instanceof SubmitHookError ) {' )
            ->toContain( "errors.value = { [hookError.field]: [t( 'The verification check failed. Please try again.' )] };" )
            ->toContain( '...collected,' )
            ->toContain( 'formDataObj.append( `data[${key}]`, String( value ) );' );
    } );

    it( 'settles the hooks for every outcome of the request', function () use ( $resource, $between ): void {
        $submit = $between( $resource( 'vue/composables/useForm.ts' ), 'async function submit(', "\n\t}\n" );

        expect( $submit )
            ->toContain( 'outcome = { ok: false, errors: {} };' )
            ->toContain( 'outcome = { ok: false, errors: serverErrors };' )
            ->toContain( 'outcome = { ok: true, errors: {} };' )
            ->toContain( "if ( outcome ) {\n\t\t\t\tsubmitHooks.settle( outcome );" );
    } );
} );

describe( 'Vue builder', function () use ( $resource ): void {
    it( 'renders textarea settings', function () use ( $resource ): void {
        expect( $resource( 'vue/components/admin/FieldSettingControl.vue' ) )
            ->toContain( "v-else-if=\"setting.input === 'textarea'\"" )
            ->toContain( 'class="textarea textarea-sm textarea-bordered w-full"' )
            ->toContain( 'rows="4"' )
            ->toContain( ':maxlength="setting.max_length"' )
            ->toContain( '<label :for="textareaId"' )
            ->toContain( '@input="handleDraftInput( ( $event.target as HTMLTextAreaElement ).value )"' );
    } );

    it( 'edits sub-input defaults and population parameters', function () use ( $resource ): void {
        $editor = $resource( 'vue/components/admin/SubInputsEditor.vue' );

        expect( $editor )
            ->toContain( "<th scope=\"col\">{{ t( 'Placeholder' ) }}</th>\n\t\t\t\t\t\t<th scope=\"col\">{{ t( 'Default' ) }}</th>\n\t\t\t\t\t\t<th scope=\"col\">{{ t( 'Parameter' ) }}</th>\n\t\t\t\t\t\t<th scope=\"col\">{{ t( 'Required' ) }}</th>" )
            ->toContain( "<option value=\"\">{{ t( 'None' ) }}</option>" )
            ->toContain( ":aria-label=\"t( ':input default value', { input: subInput.default_label } )\"" )
            ->toContain( ":aria-label=\"t( ':input population parameter', { input: subInput.default_label } )\"" )
            ->toContain( "change( subInput.key, 'default'," )
            ->toContain( "change( subInput.key, 'parameter'," )
            ->toContain( 'class="input input-xs input-bordered w-full font-mono"' )
            ->toContain( 'maxlength="100"' )
            ->toContain( "{{ t( 'A parameter fills the input from the renderer prefill or the query string.' ) }}" );
    } );

    it( 'offers the new field types in the palette', function () use ( $resource ): void {
        $palette = $resource( 'vue/components/admin/FieldPalette.vue' );

        foreach ( ['name' => 'Name', 'address' => 'Address', 'consent' => 'Consent', 'captcha' => 'CAPTCHA'] as $type => $label ) {
            expect( $palette )
                ->toContain( "{ type: '{$type}', label: t( '{$label}' ), icon: '{$type}', category: 'advanced' }," )
                ->toMatch( '/\n\t' . $type . ": 'M[^']+',\n/" );
        }
    } );

    it( 'hides the placeholder and default value for consent and CAPTCHA fields', function () use ( $resource ): void {
        expect( $resource( 'vue/components/admin/FieldEditor.vue' ) )
            ->toContain( "const NO_VALUE_SETTING_TYPES = new Set<FieldType>( ['consent', 'captcha'] );" )
            ->toContain( '<template v-if="!NO_VALUE_SETTING_TYPES.has( field.type )">' )
            ->toContain( "v-if=\"!isLayoutField && !isComposite && field.type !== 'file' && !NO_VALUE_SETTING_TYPES.has( field.type )\"" );
    } );

    it( 'shows consent records and composite values in the submission detail', function () use ( $resource ): void {
        expect( $resource( 'vue/components/admin/SubmissionDetail.vue' ) )
            ->toContain( "<template v-else-if=\"value.field_type === 'consent'\">" )
            ->toContain( "return getConsentValue( value ).consented === '1';" )
            ->toContain( '<details v-if="isConsented( value )" class="mt-2">' )
            ->toContain( "{{ t( 'View consent text' ) }}" )
            ->toContain( 'v-html="getConsentValue( value ).description"' )
            ->toContain( "{{ t( 'The consent text was not stored.' ) }}" )
            ->toContain( "t( 'Consented at :time', { time: getConsentValue( value ).consented_at ?? '—' } )" )
            ->toContain( "t( 'Revision :revision', { revision: String( getConsentValue( value ).revision ?? '' ).slice( 0, 12 ) } )" )
            ->toContain( "return stored !== null && typeof stored === 'object' && !Array.isArray( stored );" )
            ->toContain( '<div v-else-if="isKeyedValue( value )" class="whitespace-pre-line">{{ value.display_value }}</div>' );
    } );
} );

describe( 'Vue exports', function () use ( $resource ): void {
    it( 'exports the new components, the submit hook key and the shared helpers', function () use ( $resource ): void {
        expect( $resource( 'vue/index.ts' ) )
            ->toContain( "export { default as EmailConfirmationField } from './components/fields/EmailConfirmationField.vue';" )
            ->toContain( "export { default as ConsentField } from './components/fields/ConsentField.vue';" )
            ->toContain( "export { default as CaptchaField } from './components/fields/CaptchaField.vue';" )
            ->toContain( "export { SUBMIT_HOOKS_KEY, useSubmitHooks } from './composables/submitHooks';" )
            ->toContain( "export { getConfirmationKey, requiresEmailConfirmation } from '../shared/fieldSettings';" )
            ->toContain( 'createCaptchaController,' )
            ->toContain( 'createSubmitHooks,' )
            ->toContain( 'hasCaptchaWidget,' )
            ->toContain( 'runsOnSubmit,' )
            ->toContain( "} from '../shared/captcha';" );
    } );
} );
