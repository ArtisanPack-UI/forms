<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use ArtisanPackUI\Forms\Services\FieldService;

describe( 'FieldService', function (): void {
    beforeEach( function (): void {
        $this->service = app( FieldService::class );
        $this->form    = Form::factory()->create();
    } );

    describe( 'create', function (): void {
        it( 'creates a field with defaults from FieldTypes config', function (): void {
            $field = $this->service->create( $this->form, 'text' );

            expect( $field )->toBeInstanceOf( FormField::class )
                ->and( $field->type )->toBe( 'text' )
                ->and( $field->label )->toBe( 'Text Field' )
                ->and( $field->form_id )->toBe( $this->form->id )
                ->and( $field->uuid )->not->toBeNull()
                ->and( $field->is_required )->toBeFalse()
                ->and( $field->width )->toBe( 'full' )
                ->and( $field->sort_order )->toBe( 1 );
        } );

        it( 'creates a field with custom data overriding defaults', function (): void {
            $field = $this->service->create( $this->form, 'email', null, [
                'label'       => 'Custom Email',
                'is_required' => true,
            ] );

            expect( $field->label )->toBe( 'Custom Email' )
                ->and( $field->type )->toBe( 'email' )
                ->and( $field->is_required )->toBeTrue();
        } );

        it( 'assigns incrementing sort order', function (): void {
            $field1 = $this->service->create( $this->form, 'text' );
            $field2 = $this->service->create( $this->form, 'email' );
            $field3 = $this->service->create( $this->form, 'number' );

            expect( $field1->sort_order )->toBe( 1 )
                ->and( $field2->sort_order )->toBe( 2 )
                ->and( $field3->sort_order )->toBe( 3 );
        } );

        it( 'creates a field assigned to a step', function (): void {
            $step = FormStep::factory()->for( $this->form )->create();

            $field = $this->service->create( $this->form, 'text', $step->id );

            expect( $field->step_id )->toBe( $step->id );
        } );

        it( 'throws exception for invalid field type', function (): void {
            expect( fn () => $this->service->create( $this->form, 'invalid_type' ) )
                ->toThrow( InvalidArgumentException::class, 'Invalid field type: invalid_type' );
        } );

        it( 'generates unique field names based on label', function (): void {
            $field1 = $this->service->create( $this->form, 'text', null, ['label' => 'Full Name'] );
            $field2 = $this->service->create( $this->form, 'text', null, ['label' => 'Full Name'] );

            expect( $field1->name )->toBe( 'full_name' )
                ->and( $field2->name )->toBe( 'full_name_1' );
        } );

        it( 'creates select field with options config', function (): void {
            $field = $this->service->create( $this->form, 'select' );

            expect( $field->type )->toBe( 'select' )
                ->and( FieldTypes::hasOptions( 'select' ) )->toBeTrue()
                ->and( $field->field_config )->toHaveKey( 'options' );
        } );
    } );

    describe( 'update', function (): void {
        it( 'updates a field with valid data', function (): void {
            $field = FormField::factory()->for( $this->form )->create( ['label' => 'Original'] );

            $updated = $this->service->update( $field, [
                'label'     => 'Updated Label',
                'help_text' => 'Some help text',
            ] );

            expect( $updated->label )->toBe( 'Updated Label' )
                ->and( $updated->help_text )->toBe( 'Some help text' );
        } );

        it( 'regenerates name when label changes', function (): void {
            $field = FormField::factory()->for( $this->form )->create( [
                'name'  => 'old_name',
                'label' => 'Old Name',
            ] );

            $updated = $this->service->update( $field, ['label' => 'New Label'] );

            expect( $updated->name )->toBe( 'new_label' );
        } );

        it( 'preserves name when explicitly provided', function (): void {
            $field = FormField::factory()->for( $this->form )->create( ['name' => 'keep_this'] );

            $updated = $this->service->update( $field, [
                'label' => 'Changed Label',
                'name'  => 'keep_this',
            ] );

            expect( $updated->name )->toBe( 'keep_this' );
        } );
    } );

    describe( 'delete', function (): void {
        it( 'deletes a field', function (): void {
            $field   = FormField::factory()->for( $this->form )->create();
            $fieldId = $field->id;

            $result = $this->service->delete( $field );

            expect( $result )->toBeTrue()
                ->and( FormField::find( $fieldId ) )->toBeNull();
        } );
    } );

    describe( 'duplicate', function (): void {
        it( 'duplicates a field within the same form', function (): void {
            $field = FormField::factory()->for( $this->form )->create( [
                'label'       => 'Original Field',
                'type'        => 'text',
                'is_required' => true,
            ] );

            $clone = $this->service->duplicate( $field );

            expect( $clone->label )->toBe( 'Original Field (Copy)' )
                ->and( $clone->type )->toBe( 'text' )
                ->and( $clone->is_required )->toBeTrue()
                ->and( $clone->uuid )->not->toBe( $field->uuid )
                ->and( $clone->id )->not->toBe( $field->id )
                ->and( $clone->sort_order )->toBeGreaterThan( $field->sort_order );
        } );

        it( 'creates a unique name for duplicated field', function (): void {
            $field = FormField::factory()->for( $this->form )->create( ['name' => 'email_address'] );

            $clone = $this->service->duplicate( $field );

            expect( $clone->name )->not->toBe( $field->name );
        } );
    } );

    describe( 'reorder', function (): void {
        it( 'reorders fields based on UUID array', function (): void {
            $field1 = FormField::factory()->for( $this->form )->create( ['sort_order' => 1] );
            $field2 = FormField::factory()->for( $this->form )->create( ['sort_order' => 2] );
            $field3 = FormField::factory()->for( $this->form )->create( ['sort_order' => 3] );

            // Reverse the order
            $this->service->reorder( $this->form, [
                $field3->uuid,
                $field2->uuid,
                $field1->uuid,
            ] );

            expect( $field1->fresh()->sort_order )->toBe( 3 )
                ->and( $field2->fresh()->sort_order )->toBe( 2 )
                ->and( $field3->fresh()->sort_order )->toBe( 1 );
        } );

        it( 'only reorders fields within specified step', function (): void {
            $step        = FormStep::factory()->for( $this->form )->create();
            $stepField1  = FormField::factory()->for( $this->form )->create( ['step_id' => $step->id, 'sort_order' => 1] );
            $stepField2  = FormField::factory()->for( $this->form )->create( ['step_id' => $step->id, 'sort_order' => 2] );
            $noStepField = FormField::factory()->for( $this->form )->create( ['step_id' => null, 'sort_order' => 1] );

            $this->service->reorder( $this->form, [$stepField2->uuid, $stepField1->uuid], $step->id );

            expect( $stepField1->fresh()->sort_order )->toBe( 2 )
                ->and( $stepField2->fresh()->sort_order )->toBe( 1 )
                ->and( $noStepField->fresh()->sort_order )->toBe( 1 ); // Unchanged
        } );
    } );

    describe( 'moveToStep', function (): void {
        it( 'moves a field to a different step', function (): void {
            $step1 = FormStep::factory()->for( $this->form )->create();
            $step2 = FormStep::factory()->for( $this->form )->create();
            $field = FormField::factory()->for( $this->form )->create( ['step_id' => $step1->id] );

            $moved = $this->service->moveToStep( $field, $step2->id );

            expect( $moved->step_id )->toBe( $step2->id );
        } );

        it( 'moves a field to no step (null)', function (): void {
            $step  = FormStep::factory()->for( $this->form )->create();
            $field = FormField::factory()->for( $this->form )->create( ['step_id' => $step->id] );

            $moved = $this->service->moveToStep( $field, null );

            expect( $moved->step_id )->toBeNull();
        } );
    } );

    describe( 'getByUuid', function (): void {
        it( 'finds a field by UUID', function (): void {
            $field = FormField::factory()->for( $this->form )->create();

            $found = $this->service->getByUuid( $this->form, $field->uuid );

            expect( $found )->toBeInstanceOf( FormField::class )
                ->and( $found->id )->toBe( $field->id );
        } );

        it( 'returns null for non-existent UUID', function (): void {
            $found = $this->service->getByUuid( $this->form, 'non-existent-uuid' );

            expect( $found )->toBeNull();
        } );
    } );

    describe( 'getFields', function (): void {
        it( 'returns all fields for a form ordered by sort_order', function (): void {
            FormField::factory()->for( $this->form )->create( ['sort_order' => 3] );
            FormField::factory()->for( $this->form )->create( ['sort_order' => 1] );
            FormField::factory()->for( $this->form )->create( ['sort_order' => 2] );

            $fields = $this->service->getFields( $this->form );

            expect( $fields )->toHaveCount( 3 )
                ->and( $fields[0]->sort_order )->toBe( 1 )
                ->and( $fields[1]->sort_order )->toBe( 2 )
                ->and( $fields[2]->sort_order )->toBe( 3 );
        } );

        it( 'filters fields by step when step_id provided', function (): void {
            $step = FormStep::factory()->for( $this->form )->create();
            FormField::factory()->count( 2 )->for( $this->form )->create( ['step_id' => $step->id] );
            FormField::factory()->for( $this->form )->create( ['step_id' => null] );

            $stepFields = $this->service->getFields( $this->form, $step->id );

            expect( $stepFields )->toHaveCount( 2 );
        } );
    } );
} );

describe( 'FieldTypes Config', function (): void {
    it( 'has all expected field types', function (): void {
        $types = FieldTypes::getTypes();

        expect( $types )->toHaveKeys( [
            'text', 'email', 'number', 'url', 'textarea', 'hidden',
            'select', 'radio', 'checkbox', 'checkbox_group', 'select_multiple',
            'file', 'date',
        ] );
    } );

    it( 'categorizes fields correctly', function (): void {
        $categories = FieldTypes::getCategories();

        expect( $categories['basic']['fields'] )->toContain( 'text', 'email', 'number' )
            ->and( $categories['choice']['fields'] )->toContain( 'select', 'radio', 'checkbox' )
            ->and( $categories['advanced']['fields'] )->toContain( 'file', 'date' );
    } );

    it( 'identifies fields with options', function (): void {
        expect( FieldTypes::hasOptions( 'select' ) )->toBeTrue()
            ->and( FieldTypes::hasOptions( 'radio' ) )->toBeTrue()
            ->and( FieldTypes::hasOptions( 'checkbox_group' ) )->toBeTrue()
            ->and( FieldTypes::hasOptions( 'text' ) )->toBeFalse()
            ->and( FieldTypes::hasOptions( 'email' ) )->toBeFalse();
    } );

    it( 'returns validation options for field types', function (): void {
        expect( FieldTypes::getValidationOptions( 'text' ) )->toContain( 'min', 'max', 'pattern' )
            ->and( FieldTypes::getValidationOptions( 'file' ) )->toContain( 'max_size', 'allowed_types' );
    } );

    it( 'validates field type existence', function (): void {
        expect( FieldTypes::typeExists( 'text' ) )->toBeTrue()
            ->and( FieldTypes::typeExists( 'invalid' ) )->toBeFalse();
    } );
});
