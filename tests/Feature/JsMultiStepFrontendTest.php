<?php

declare( strict_types=1 );

/**
 * Guards the React and Vue multi-step forms: invisible CAPTCHAs on earlier
 * steps (RX2), Enter on a non-final step (RX5) and step-change focus and
 * announcements (RX4, VU-08).
 */

use Tests\Support\SharedTypeScript;

/**
 * Reads a JS source file.
 */
function jsMultiStepSource( string $path ): string
{
    return (string) file_get_contents( __DIR__ . '/../../resources/js/' . $path );
}

describe( 'invisible CAPTCHAs on other steps (RX2)', function (): void {
    it( 'finds the invisible CAPTCHAs that are not on the current step', function (): void {
        $names = SharedTypeScript::run( <<<'JS'
            const { getOffStepSubmitCaptchas } = await load( 'captcha' );
            const captcha = ( name, step_id, size ) => ( { name, type: 'captcha', step_id, captcha: { provider: 'hcaptcha', site_key: 'k', size } } );
            const fields = [
                captcha( 'invisible_1', 1, 'invisible' ),
                captcha( 'invisible_2', 2, 'invisible' ),
                captcha( 'visible_1', 1, 'normal' ),
                captcha( 'hidden_1', 1, 'invisible' ),
                { name: 'email', type: 'email', step_id: 1 },
                { name: 'unconfigured', type: 'captcha', step_id: 1, captcha: null },
            ];
            return getOffStepSubmitCaptchas( fields, 2, { hidden_1: true } ).map( ( field ) => field.name );
            JS );

        if ( null === $names ) {
            $this->markTestSkipped( 'Node 22.7+ is needed to run the shared helpers.' );
        }

        expect( $names )->toBe( ['invisible_1'] );
    } );

    it( 'keeps them mounted out of sight in React', function (): void {
        expect( jsMultiStepSource( 'react/components/FormRenderer.tsx' ) )
            ->toContain( 'getOffStepSubmitCaptchas( form.fields, currentStepData.id, hiddenFields )' )
            ->toContain( '{offStepCaptchas.map( renderField )}' );
    } );

    it( 'keeps them mounted out of sight in Vue', function (): void {
        expect( jsMultiStepSource( 'vue/components/FormRenderer.vue' ) )
            ->toContain( 'getOffStepSubmitCaptchas( form.value.fields, currentStepData.value.id, hiddenFields.value )' )
            ->toContain( '<div v-if="offStepCaptchas.length > 0" class="sr-only">' )
            ->toContain( 'v-for="field in offStepCaptchas"' );
    } );
} );
