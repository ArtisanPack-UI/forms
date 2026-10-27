<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * Guards RX2 (Livewire side): an invisible CAPTCHA on an earlier step of a
 * multi-step form fetches its token on submit, so leaving its step does not
 * need a token and the widget is rendered again on the last step.
 */
beforeEach( function (): void {
    config()->set( 'artisanpack.forms.spam_protection.captcha.drivers', [
        'hcaptcha' => ['site_key' => 'hc-site', 'secret_key' => 'hc-secret'],
    ] );

    $this->form = Form::factory()->create( ['is_active' => true, 'is_multi_step' => true] );
    $first      = FormStep::factory()->for( $this->form )->create( ['sort_order' => 0] );
    $second     = FormStep::factory()->for( $this->form )->create( ['sort_order' => 1] );

    $this->captcha = FormField::factory()->for( $this->form )->create( [
        'type'         => 'captcha',
        'name'         => 'verify',
        'label'        => 'Verification',
        'step_id'      => $first->id,
        'sort_order'   => 1,
        'field_config' => ['captcha_provider' => 'hcaptcha', 'captcha_size' => 'invisible'],
    ] );
    FormField::factory()->for( $this->form )->required()->create( ['type' => 'email', 'name' => 'email', 'label' => 'Email', 'step_id' => $second->id, 'sort_order' => 2] );
} );

it( 'leaves the step without a token and renders the widget on the last step', function (): void {
    Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
        ->call( 'nextStep' )
        ->assertHasNoErrors()
        ->assertSet( 'currentStepIndex', 1 )
        ->assertSeeHtml( 'data-forms-captcha="hcaptcha"' );
} );

it( 'verifies the token fetched on submit from the last step', function (): void {
    Http::fake( ['*' => Http::response( ['success' => true] )] );

    Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
        ->afterSpamDelay()
        ->call( 'nextStep' )
        ->set( 'formData.email', 'jane@example.com' )
        ->set( 'formData.verify', 'token' )
        ->call( 'submit' )
        ->assertHasNoErrors()
        ->assertSet( 'isSubmitted', true );

    Http::assertSentCount( 1 );
} );

it( 'still requires the token on submit and stays on the last step', function (): void {
    Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
        ->afterSpamDelay()
        ->call( 'nextStep' )
        ->set( 'formData.email', 'jane@example.com' )
        ->call( 'submit' )
        ->assertHasErrors( 'formData.verify' )
        ->assertSet( 'isSubmitted', false );
} );

it( 'keeps a visible CAPTCHA required when leaving its step', function (): void {
    $this->captcha->update( ['field_config' => ['captcha_provider' => 'hcaptcha', 'captcha_size' => 'normal']] );

    Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
        ->call( 'nextStep' )
        ->assertHasErrors( 'formData.verify' )
        ->assertSet( 'currentStepIndex', 0 );
} );
