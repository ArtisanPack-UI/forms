<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use Livewire\Livewire;

/**
 * Guards that an inactive form refuses submissions in the Livewire renderer
 * (as the submit API already does) and that the redirect URL is followed,
 * but only for http(s) URLs and site paths.
 */
beforeEach( function (): void {
    $this->form = Form::factory()->create( ['is_active' => true] );
    FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'name'] );
} );

it( 'shows a notice instead of the form when the form is inactive', function (): void {
    $this->form->update( ['is_active' => false] );

    Livewire::test( FormRenderer::class, ['form' => $this->form] )
        ->assertSee( 'This form is not accepting submissions.' )
        ->assertDontSeeHtml( 'wire:submit="submit"' );
} );

it( 'refuses a submission to an inactive form', function (): void {
    $component = Livewire::test( FormRenderer::class, ['form' => $this->form] );

    $this->form->update( ['is_active' => false] );

    $component->afterSpamDelay()
        ->set( 'formData.name', 'Ada' )
        ->call( 'submit' )
        ->assertSet( 'isSubmitted', false )
        ->assertSet( 'errorMessage', 'This form is not accepting submissions.' );

    expect( $this->form->submissions()->count() )->toBe( 0 );
} );

it( 'redirects to a safe redirect URL after submitting', function ( string $url ): void {
    $this->form->update( ['redirect_url' => $url] );

    Livewire::test( FormRenderer::class, ['form' => $this->form] )
        ->afterSpamDelay()
        ->set( 'formData.name', 'Ada' )
        ->call( 'submit' )
        ->assertDispatched( 'redirect-to', url: $url )
        ->assertRedirect( $url );
} )->with( [
    'https URL' => 'https://example.com/thanks',
    'site path' => '/thanks',
] );

it( 'does not follow an unsafe redirect URL', function ( string $url ): void {
    $this->form->forceFill( ['redirect_url' => $url] )->save();

    Livewire::test( FormRenderer::class, ['form' => $this->form] )
        ->afterSpamDelay()
        ->set( 'formData.name', 'Ada' )
        ->call( 'submit' )
        ->assertSet( 'isSubmitted', true )
        ->assertNotDispatched( 'redirect-to' )
        ->assertNoRedirect();
} )->with( [
    'javascript'        => 'javascript:alert(1)',
    'protocol-relative' => '//evil.example/',
] );
