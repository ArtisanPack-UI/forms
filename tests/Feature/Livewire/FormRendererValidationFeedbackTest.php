<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use Livewire\Livewire;

/**
 * Guards LW-09: a failed submission or step is announced and moves focus to
 * the first invalid control, with one announcement instead of one alert per
 * field.
 */
it( 'tells the script when a submission fails validation', function (): void {
    $form = Form::factory()->create( ['is_active' => true] );
    FormField::factory()->for( $form )->required()->create( ['type' => 'text', 'name' => 'name'] );
    FormField::factory()->for( $form )->required()->create( ['type' => 'date', 'name' => 'starts', 'sort_order' => 2] );

    $html = Livewire::test( FormRenderer::class, ['form' => $form] )
        ->afterSpamDelay()
        ->call( 'submit' )
        ->assertHasErrors( ['formData.name', 'formData.starts'] )
        ->assertDispatched( 'forms-validation-failed', formId: $form->id, count: 2 )
        ->html();

    expect( $html )->toContain( 'x-on:forms-validation-failed.window="onValidationFailed($event)"' )
        ->and( $html )->not->toMatch( '/id="error-f' . $form->id . '-starts"[^>]*role="alert"/' );
} );

it( 'tells the script when a step fails validation', function (): void {
    $form  = Form::factory()->create( ['is_active' => true, 'is_multi_step' => true] );
    $first = FormStep::factory()->for( $form )->create( ['sort_order' => 0] );
    FormStep::factory()->for( $form )->create( ['sort_order' => 1] );
    FormField::factory()->for( $form )->required()->create( ['type' => 'text', 'name' => 'name', 'step_id' => $first->id] );

    Livewire::test( FormRenderer::class, ['form' => $form->fresh()] )
        ->call( 'nextStep' )
        ->assertHasErrors( 'formData.name' )
        ->assertSet( 'currentStepIndex', 0 )
        ->assertDispatched( 'forms-validation-failed', formId: $form->id, count: 1 );
} );

it( 'does not tell the script when validation passes', function (): void {
    $form = Form::factory()->create( ['is_active' => true] );
    FormField::factory()->for( $form )->create( ['type' => 'text', 'name' => 'name'] );

    Livewire::test( FormRenderer::class, ['form' => $form] )
        ->afterSpamDelay()
        ->call( 'submit' )
        ->assertNotDispatched( 'forms-validation-failed' );
} );

it( 'leaves role="alert" off the per-field error paragraphs', function ( string $partial ): void {
    $view = file_get_contents( __DIR__ . '/../../../resources/views/components/fields/' . $partial . '.blade.php' );

    expect( $view )->not->toMatch( '/id="\{\{ \$field->domId\(\'error\'\) \}\}" role="alert"/' );
} )->with( ['date', 'time', 'composite', 'matrix', 'list', 'ranking', 'email'] );

it( 'announces the errors and focuses the first visible invalid control', function (): void {
    $view = file_get_contents( __DIR__ . '/../../../resources/views/livewire/form-renderer.blade.php' );

    expect( $view )->toContain( 'onValidationFailed(event) {' )
        ->and( $view )->toContain( 'String(detail.formId) !== String(this.formId)' )
        ->and( $view )->toContain( 'this.$nextTick(() => this.focusFirstInvalid());' )
        ->and( $view )->toContain( "!el.closest('[inert], [aria-hidden=\"true\"]')" )
        ->and( $view )->toContain( "validationFailedMany: @js(__( 'There are :count errors in the form. Please fix them and try again.' ))" );
} );
