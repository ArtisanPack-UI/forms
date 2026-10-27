<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

/**
 * Guards LW-08: files already uploaded stay listed (with a remove control)
 * after a step change or a conditional show/hide re-creates the field.
 */
beforeEach( function (): void {
    $this->form = Form::factory()->create( ['is_active' => true, 'is_multi_step' => true] );
    $this->one  = FormStep::factory()->for( $this->form )->create( ['sort_order' => 0] );
    $this->two  = FormStep::factory()->for( $this->form )->create( ['sort_order' => 1] );

    FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'toggle', 'step_id' => $this->one->id, 'sort_order' => 1] );
    FormField::factory()->for( $this->form )->create( [
        'type'         => 'file',
        'name'         => 'docs',
        'label'        => 'Documents',
        'step_id'      => $this->one->id,
        'sort_order'   => 2,
        'field_config' => ['multiple' => true, 'max_files' => 5],
    ] );
    FormField::factory()->for( $this->form )->required()->create( [
        'type'       => 'file',
        'name'       => 'resume',
        'label'      => 'Resume',
        'step_id'    => $this->one->id,
        'sort_order' => 3,
    ] );
    FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'comment', 'step_id' => $this->two->id, 'sort_order' => 4] );
} );

it( 'lists the uploaded files again after moving away and back', function (): void {
    $html = Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
        ->set( 'files.docs.a', UploadedFile::fake()->create( 'first.pdf', 10, 'application/pdf' ) )
        ->set( 'files.docs.b', UploadedFile::fake()->create( 'second.pdf', 10, 'application/pdf' ) )
        ->set( 'files.resume', UploadedFile::fake()->create( 'cv.pdf', 10, 'application/pdf' ) )
        ->call( 'nextStep' )
        ->assertSet( 'currentStepIndex', 1 )
        ->call( 'previousStep' )
        ->html();

    expect( $html )->toContain( 'first.pdf' )
        ->and( $html )->toContain( 'second.pdf' )
        ->and( $html )->toContain( '\\u0022key\\u0022:\\u0022a\\u0022' )
        ->and( $html )->toContain( '\\u0022status\\u0022:\\u0022done\\u0022' )
        ->and( $html )->toContain( 'data-forms-uploaded-file' )
        ->and( $html )->toContain( 'cv.pdf' )
        ->and( $html )->toContain( 'wire:click="$set(\'files.resume\', null)"' );
} );

it( 'does not mark the single-file input required once a file is uploaded', function (): void {
    $component = Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] );

    expect( $component->html() )->toMatch( '/<input\s+type="file"\s+wire:model="files\.resume"[^>]*\srequired\s/' );

    $html = $component->set( 'files.resume', UploadedFile::fake()->create( 'cv.pdf', 10, 'application/pdf' ) )->html();

    expect( $html )->not->toMatch( '/<input\s+type="file"\s+wire:model="files\.resume"[^>]*\srequired\s/' );
} );

it( 'keeps the field wrapper key when conditional logic hides the field', function (): void {
    FormField::query()->where( 'name', 'docs' )->update( ['conditional_logic' => ['action' => 'hide', 'logic' => 'all', 'rules' => [['field' => 'toggle', 'operator' => 'equals', 'value' => 'hide']]]] );

    $field = FormField::query()->where( 'name', 'docs' )->first();

    $component = Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
        ->assertSeeHtml( 'wire:key="field-' . $field->uuid . '"' );

    $component->set( 'formData.toggle', 'hide' )
        ->assertSet( 'hiddenFields.docs', true )
        ->assertSeeHtml( 'wire:key="field-' . $field->uuid . '"' );
} );
