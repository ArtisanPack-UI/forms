<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Services\SubmissionService;
use Livewire\Livewire;

/**
 * Guards issue #153: choice fields only accept their own option values, and
 * a multiple-choice field's values must each be a string.
 */
beforeEach( function (): void {
    $this->form    = Form::factory()->create( ['is_active' => true] );
    $this->choices = [['value' => 'a', 'label' => 'A'], ['value' => 'b,c', 'label' => 'B and C']];
} );

$submit = function ( array $data ) {
    return $this->postJson( route( 'api.forms.submit', $this->form ), [
        'data'            => $data,
        '_form_loaded_at' => time() - 10,
    ] );
};

describe( 'single-choice fields', function () use ( $submit ): void {
    beforeEach( function (): void {
        $this->field = FormField::factory()->for( $this->form )->create( [
            'type'         => 'select',
            'name'         => 'pick',
            'label'        => 'Pick',
            'field_config' => ['options' => $this->choices],
        ] );
    } );

    it( 'accept one of the options, including a value with a comma', function ( string $type, string $value ) use ( $submit ): void {
        $this->field->update( ['type' => $type] );

        $submit->call( $this, ['pick' => $value] )->assertCreated();
    } )->with( ['select', 'radio'] )->with( ['a', 'b,c'] );

    it( 'reject a value that is not one of the options', function ( string $type, mixed $value ) use ( $submit ): void {
        $this->field->update( ['type' => $type] );

        $submit->call( $this, ['pick' => $value] )->assertUnprocessable()->assertJsonValidationErrors( 'data.pick' );

        expect( FormSubmission::count() )->toBe( 0 );
    } )->with( ['select', 'radio'] )->with( ['x', 'b', 'A', FormField::OTHER_CHOICE_VALUE] );

    it( 'reject a made-up value set through Livewire', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->afterSpamDelay()
            ->set( 'formData.pick', 'x' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.pick' => 'in'] )
            ->assertSee( 'Please choose one of the available options for Pick.' );
    } );

    it( 'still accept a blank optional value', function () use ( $submit ): void {
        $submit->call( $this, ['pick' => ''] )->assertCreated();
    } );

    it( 'accept any text when the field offers an "Other" choice', function () use ( $submit ): void {
        $this->field->update( ['type' => 'radio', 'field_config' => ['options' => $this->choices, 'enable_other' => true]] );

        expect( collect( $this->field->fresh()->buildValidationRules() )->filter( fn ( mixed $rule ): bool => is_string( $rule ) && str_starts_with( $rule, 'in:' ) ) )->toBeEmpty();
    } );

    it( 'reject any value when the field has no options', function () use ( $submit ): void {
        $this->field->update( ['field_config' => ['options' => []]] );

        $submit->call( $this, ['pick' => 'x'] )->assertUnprocessable()->assertJsonValidationErrors( 'data.pick' );
    } );
} );

describe( 'multiple-choice fields', function () use ( $submit ): void {
    beforeEach( function (): void {
        $this->field = FormField::factory()->for( $this->form )->create( [
            'type'         => 'checkbox_group',
            'name'         => 'picks',
            'label'        => 'Picks',
            'field_config' => ['options' => $this->choices],
        ] );
    } );

    it( 'check each value against the options', function ( string $type ): void {
        $this->field->update( ['type' => $type] );

        $rules = app( SubmissionService::class )->buildValidationRules( $this->form->fields()->get() );

        expect( $rules['formData.picks.*'] )->toBe( ['string', 'in:"a","b,c"'] );
    } )->with( ['checkbox_group', 'select_multiple'] );

    it( 'accept a list of options', function ( string $type ) use ( $submit ): void {
        $this->field->update( ['type' => $type] );

        $submit->call( $this, ['picks' => ['a', 'b,c']] )->assertCreated();
    } )->with( ['checkbox_group', 'select_multiple'] );

    it( 'reject made-up and nested values', function ( string $type, array $value, string $error ) use ( $submit ): void {
        $this->field->update( ['type' => $type] );

        $submit->call( $this, ['picks' => $value] )->assertUnprocessable()->assertJsonValidationErrors( $error );

        expect( FormSubmission::count() )->toBe( 0 );
    } )->with( ['checkbox_group', 'select_multiple'] )->with( [
        'made-up value' => [['a', 'zzz'], 'data.picks.1'],
        'nested array'  => [['a', ['nested']], 'data.picks.1'],
        'integer'       => [['a', 1], 'data.picks.1'],
    ] );

    it( 'reject a made-up value set through Livewire', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->afterSpamDelay()
            ->set( 'formData.picks', ['a', 'zzz'] )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.picks.1' => 'in'] );
    } );

    it( 'allow "Other" text but still reject nested values', function () use ( $submit ): void {
        $this->field->update( ['field_config' => ['options' => $this->choices, 'enable_other' => true]] );

        $rules = app( SubmissionService::class )->buildValidationRules( $this->form->fields()->get() );

        expect( $rules['formData.picks.*'] )->toBe( ['string'] );

        $submit->call( $this, ['picks' => ['a', ['nested']]] )->assertUnprocessable()->assertJsonValidationErrors( 'data.picks.1' );
    } );

    it( 'use the available-options message for nested values', function (): void {
        $messages = app( SubmissionService::class )->buildValidationMessages( $this->form->fields()->get() );

        expect( $messages['formData.picks.*.string'] )->toBe( 'Please choose one of the available options for Picks.' )
            ->and( $messages['formData.picks.*.in'] )->toBe( 'Please choose one of the available options for Picks.' );
    } );
} );

describe( 'administrative choice fields', function (): void {
    it( 'are checked against their options on the submission detail', function (): void {
        $field = FormField::factory()->for( $this->form )->create( [
            'type'         => 'select',
            'name'         => 'status',
            'field_config' => ['options' => $this->choices, 'visibility' => 'administrative'],
        ] );

        $submission = FormSubmission::factory()->for( $this->form )->create();
        $rules      = app( SubmissionService::class )->buildAdministrativeValidationRules( [$field], $submission );

        expect( $rules['formData.status'] )->toContain( 'in:"a","b,c"' );
    } );
} );
