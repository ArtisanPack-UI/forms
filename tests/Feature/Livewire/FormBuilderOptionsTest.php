<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormBuilder;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use Livewire\Livewire;
use Tests\Models\User;

/**
 * Guards LW-03: the options editor saves only the options, so settings
 * changed beside it are kept, and server-side option changes reach it.
 */
beforeEach( function (): void {
    $this->form  = Form::factory()->create();
    $this->field = FormField::factory()->for( $this->form )->create( [
        'type'         => 'radio',
        'name'         => 'color',
        'label'        => 'Color',
        'field_config' => ['options' => [['value' => 'red', 'label' => 'Red']]],
    ] );

    $this->builder = fn () => Livewire::actingAs( User::factory()->create() )
        ->test( FormBuilder::class, ['form' => $this->form] )
        ->call( 'selectField', $this->field->uuid );
} );

it( 'keeps other settings when the options are saved', function (): void {
    ( $this->builder )()
        ->call( 'updateSetting', 'layout', 'inline' )
        ->call( 'updateOptions', [['value' => 'red', 'label' => 'Red'], ['value' => 'blue', 'label' => 'Blue', 'default' => true]] )
        ->assertHasNoErrors()
        ->assertDispatched( 'forms-options-updated' );

    $field = $this->field->fresh();

    expect( $field->getConfig( 'layout' ) )->toBe( 'inline' )
        ->and( $field->getConfig( 'options' ) )->toBe( [
            ['value' => 'red', 'label' => 'Red'],
            ['value' => 'blue', 'label' => 'Blue', 'default' => true],
        ] );
} );

it( 'drops unknown keys and unsafe images from the options', function (): void {
    ( $this->builder )()
        ->call( 'updateOptions', [['value' => 'red', 'label' => 'Red', 'image' => 'javascript:alert(1)', 'onclick' => 'x']] )
        ->assertHasNoErrors();

    expect( $this->field->fresh()->getConfig( 'options' ) )->toBe( [['value' => 'red', 'label' => 'Red']] );
} );

it( 'rejects options that are not valid', function (): void {
    ( $this->builder )()
        ->call( 'updateOptions', [['value' => ['nested'], 'label' => 'Red']] )
        ->assertHasErrors( 'options.0.value' );

    expect( $this->field->fresh()->getConfig( 'options' ) )->toBe( [['value' => 'red', 'label' => 'Red']] );
} );

it( 'sends bulk choices back to the options editor', function (): void {
    ( $this->builder )()
        ->call( 'applyBulkChoices', "a\nb", 'replace' )
        ->assertDispatched( 'forms-options-updated', fn ( string $name, array $params ): bool => $this->field->uuid === $params['field'] && ['a', 'b'] === array_column( $params['options'], 'value' ) );
} );

it( 'renders an editor that saves through updateOptions', function (): void {
    ( $this->builder )()
        ->assertSeeHtml( '$wire.updateOptions(cleanOptions)' )
        ->assertSeeHtml( 'x-on:forms-options-updated.window' )
        ->assertDontSeeHtml( 'updateField({ field_config: fieldConfig })' );
} );
