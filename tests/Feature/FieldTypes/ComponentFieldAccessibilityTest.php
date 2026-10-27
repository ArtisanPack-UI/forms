<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use Livewire\Livewire;

/**
 * Guards LW-04 (textarea and select controls are named by the layout's label,
 * since the component labels its own fieldset legend) and LW-05 (fields built
 * on the input/textarea/select components render the error the input's
 * `aria-describedby` references, and are marked invalid).
 */
beforeEach( function (): void {
    $this->form = Form::factory()->create( ['is_active' => true] );
} );

/**
 * Gets the ID the renderer gives one of a field's elements (see FormField::domId()).
 */
function componentFieldDomId( string $part, string $name, Form $form ): string
{
    return "{$part}-f{$form->id}-{$name}";
}

/**
 * Finds the opening tag of the control bound to a field.
 */
function boundControl( string $html, string $name ): string
{
    preg_match( '/<(?:input|textarea|select)\b[^>]*wire:model\.live="formData\.' . preg_quote( $name, '/' ) . '"[^>]*>/s', $html, $matches );

    return $matches[0] ?? '';
}

it( 'labels a :dataset control with the layout label in the default placement (LW-04)', function ( string $type ): void {
    FormField::factory()->for( $this->form )->create( [
        'type'         => $type,
        'name'         => 'pick',
        'label'        => 'Pick one',
        'field_config' => ['options' => [['value' => 'a', 'label' => 'A']]],
    ] );

    $html    = Livewire::test( FormRenderer::class, ['form' => $this->form] )->html();
    $control = boundControl( $html, 'pick' );

    expect( $html )->toMatch( '/<label\s+id="' . componentFieldDomId( 'label', 'pick', $this->form ) . '"/' )
        ->and( $html )->not->toContain( 'fieldset-legend">Pick one' )
        ->and( $control )->toContain( 'aria-labelledby="' . componentFieldDomId( 'label', 'pick', $this->form ) . '"' );
} )->with( ['textarea', 'select', 'select_multiple'] );

it( 'names a :dataset control without a label after the field name (LW-04)', function ( string $type ): void {
    FormField::factory()->for( $this->form )->create( [
        'type'         => $type,
        'name'         => 'favourite_colour',
        'label'        => '',
        'field_config' => ['options' => [['value' => 'a', 'label' => 'A']]],
    ] );

    $control = boundControl( Livewire::test( FormRenderer::class, ['form' => $this->form] )->html(), 'favourite_colour' );

    expect( $control )->toContain( 'aria-label="Favourite Colour"' )
        ->not->toContain( 'aria-labelledby' );
} )->with( ['textarea', 'select'] );

it( 'renders the referenced error and marks a :dataset control invalid (LW-05)', function ( string $type ): void {
    FormField::factory()->for( $this->form )->required()->create( [
        'type'         => $type,
        'name'         => 'answer',
        'label'        => 'Answer',
        'help_text'    => null,
        'field_config' => ['options' => [['value' => 'a', 'label' => 'A']]],
    ] );

    $html    = Livewire::test( FormRenderer::class, ['form' => $this->form] )
        ->afterSpamDelay()
        ->call( 'submit' )
        ->assertHasErrors( 'formData.answer' )
        ->html();
    $control = boundControl( $html, 'answer' );
    $errorId = componentFieldDomId( 'error', 'answer', $this->form );

    expect( $html )->toContain( 'id="' . $errorId . '"' )
        ->and( $control )->toContain( 'aria-invalid="true"' )
        ->and( $control )->toContain( 'aria-describedby="' . $errorId )
        ->and( $control )->not->toMatch( '/\serror="/' )
        ->and( substr_count( $html, 'field is required' ) )->toBe( 1 );
} )->with( ['text', 'email', 'url', 'phone', 'number', 'textarea', 'select', 'select_multiple'] );

it( 'leaves a valid control without aria-invalid (LW-05)', function (): void {
    FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'answer', 'label' => 'Answer'] );

    expect( boundControl( Livewire::test( FormRenderer::class, ['form' => $this->form] )->html(), 'answer' ) )
        ->not->toContain( 'aria-invalid' );
} );
