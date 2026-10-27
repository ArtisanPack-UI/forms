<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormBuilder;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use Livewire\Livewire;
use Tests\Models\User;

/**
 * Guards SEC-01: a user who may edit a form but may not reveal masked values
 * cannot weaken a masked field's privacy, and a calculation may not read a
 * masked field.
 */
beforeEach( function (): void {
    $this->editor = User::factory()->create( ['is_admin' => false] );
    $this->admin  = User::factory()->create( ['is_admin' => true] );
    $this->form   = Form::factory()->create();
    $this->field  = FormField::factory()->for( $this->form )->create( [
        'type'         => 'text',
        'name'         => 'ssn',
        'label'        => 'SSN',
        'field_config' => ['mask_in_admin' => true],
    ] );
} );

describe( 'the Livewire builder', function (): void {
    it( 'forbids an editor who may not reveal masked values to turn the mask off', function (): void {
        Livewire::actingAs( $this->editor )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $this->field->uuid )
            ->call( 'updateSetting', 'mask_in_admin', false )
            ->assertForbidden();

        expect( $this->field->fresh()->isMaskedInAdmin() )->toBeTrue();
    } );

    it( 'forbids an editor to rename a masked field', function (): void {
        Livewire::actingAs( $this->editor )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $this->field->uuid )
            ->call( 'updateField', ['name' => 'tax_id'] )
            ->assertForbidden();

        expect( $this->field->fresh()->name )->toBe( 'ssn' );
    } );

    it( 'still lets an editor change other settings of a masked field', function (): void {
        Livewire::actingAs( $this->editor )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $this->field->uuid )
            ->call( 'updateField', ['help_text' => 'Nine digits'] )
            ->assertSuccessful();

        expect( $this->field->fresh()->help_text )->toBe( 'Nine digits' )
            ->and( $this->field->fresh()->isMaskedInAdmin() )->toBeTrue();
    } );

    it( 'forbids an editor to delete a masked field that has stored values', function (): void {
        $submission = FormSubmission::factory()->for( $this->form )->create();
        FormSubmissionValue::factory()->create( [
            'submission_id' => $submission->id,
            'field_id'      => $this->field->id,
            'field_name'    => 'ssn',
            'value'         => '123-45-6789',
        ] );

        Livewire::actingAs( $this->editor )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'deleteField', $this->field->uuid )
            ->assertForbidden();

        expect( $this->field->fresh() )->not->toBeNull();
    } );

    it( 'lets an editor delete a masked field with no stored values', function (): void {
        Livewire::actingAs( $this->editor )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'deleteField', $this->field->uuid )
            ->assertSuccessful();

        expect( $this->field->fresh() )->toBeNull();
    } );

    it( 'lets a user who may reveal masked values turn the mask off and delete the field', function (): void {
        $submission = FormSubmission::factory()->for( $this->form )->create();
        FormSubmissionValue::factory()->create( [
            'submission_id' => $submission->id,
            'field_id'      => $this->field->id,
            'field_name'    => 'ssn',
        ] );

        Livewire::actingAs( $this->admin )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $this->field->uuid )
            ->call( 'updateSetting', 'mask_in_admin', false )
            ->assertSuccessful();

        expect( $this->field->fresh()->isMaskedInAdmin() )->toBeFalse();

        Livewire::actingAs( $this->admin )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'deleteField', $this->field->uuid )
            ->assertSuccessful();

        expect( $this->field->fresh() )->toBeNull();
    } );
} );

describe( 'the field API', function (): void {
    it( 'forbids changing a masked field to a type that cannot be masked', function (): void {
        $this->actingAs( $this->editor )
            ->putJson( route( 'api.forms.fields.update', [$this->form, $this->field] ), ['type' => 'signature'] )
            ->assertForbidden();

        expect( $this->field->fresh()->type )->toBe( 'text' );
    } );

    it( 'forbids replacing the field config without the mask', function (): void {
        $this->actingAs( $this->editor )
            ->putJson( route( 'api.forms.fields.update', [$this->form, $this->field] ), ['field_config' => ['placeholder' => 'x']] )
            ->assertForbidden();

        expect( $this->field->fresh()->isMaskedInAdmin() )->toBeTrue();
    } );

    it( 'allows the same change for a user who may reveal masked values', function (): void {
        $this->actingAs( $this->admin )
            ->putJson( route( 'api.forms.fields.update', [$this->form, $this->field] ), ['type' => 'signature'] )
            ->assertSuccessful();
    } );
} );

describe( 'calculations', function (): void {
    it( 'rejects a formula that reads a masked field', function (): void {
        $total = FormField::factory()->for( $this->form )->create( [
            'type'         => 'calculation',
            'name'         => 'total',
            'field_config' => ['formula' => '', 'decimals' => 2],
        ] );

        $this->actingAs( $this->admin )
            ->putJson( route( 'api.forms.fields.update', [$this->form, $total] ), ['field_config' => ['formula' => '{ssn} * 1', 'decimals' => 2]] )
            ->assertUnprocessable()
            ->assertJsonValidationErrors( 'field_config.formula' );

        expect( $total->fresh()->getConfig( 'formula' ) )->toBe( '' );
    } );

    it( 'accepts a formula that reads only unmasked fields', function (): void {
        FormField::factory()->for( $this->form )->create( ['type' => 'number', 'name' => 'qty'] );
        $total = FormField::factory()->for( $this->form )->create( [
            'type'         => 'calculation',
            'name'         => 'total',
            'field_config' => ['formula' => '', 'decimals' => 2],
        ] );

        $this->actingAs( $this->editor )
            ->putJson( route( 'api.forms.fields.update', [$this->form, $total] ), ['field_config' => ['formula' => '{qty} * 2', 'decimals' => 2]] )
            ->assertSuccessful();
    } );
} );
