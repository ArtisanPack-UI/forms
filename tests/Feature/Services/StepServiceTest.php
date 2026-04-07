<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use ArtisanPackUI\Forms\Services\StepService;

describe( 'StepService', function (): void {
    beforeEach( function (): void {
        $this->service = app( StepService::class );
        $this->form    = Form::factory()->multiStep()->create();
    } );

    describe( 'create', function (): void {
        it( 'creates a step with default values', function (): void {
            $step = $this->service->create( $this->form );

            expect( $step )->toBeInstanceOf( FormStep::class )
                ->and( $step->form_id )->toBe( $this->form->id )
                ->and( $step->title )->toBe( 'Step 1' )
                ->and( $step->next_button_text )->toBe( 'Next' )
                ->and( $step->prev_button_text )->toBe( 'Previous' )
                ->and( $step->sort_order )->toBe( 1 );
        } );

        it( 'creates a step with custom data', function (): void {
            $step = $this->service->create( $this->form, [
                'title'       => 'Personal Info',
                'description' => 'Enter your details',
            ] );

            expect( $step->title )->toBe( 'Personal Info' )
                ->and( $step->description )->toBe( 'Enter your details' );
        } );

        it( 'assigns incrementing sort order', function (): void {
            $step1 = $this->service->create( $this->form );
            $step2 = $this->service->create( $this->form );
            $step3 = $this->service->create( $this->form );

            expect( $step1->sort_order )->toBe( 1 )
                ->and( $step2->sort_order )->toBe( 2 )
                ->and( $step3->sort_order )->toBe( 3 );
        } );

        it( 'increments step number in default title', function (): void {
            $step1 = $this->service->create( $this->form );
            $step2 = $this->service->create( $this->form );

            expect( $step1->title )->toBe( 'Step 1' )
                ->and( $step2->title )->toBe( 'Step 2' );
        } );
    } );

    describe( 'update', function (): void {
        it( 'updates a step with valid data', function (): void {
            $step = FormStep::factory()->for( $this->form )->create( ['title' => 'Original'] );

            $updated = $this->service->update( $step, [
                'title'       => 'Updated Title',
                'description' => 'New description',
            ] );

            expect( $updated->title )->toBe( 'Updated Title' )
                ->and( $updated->description )->toBe( 'New description' );
        } );

        it( 'preserves unchanged fields', function (): void {
            $step = FormStep::factory()->for( $this->form )->create( [
                'title'            => 'Test',
                'next_button_text' => 'Continue',
            ] );

            $updated = $this->service->update( $step, ['title' => 'New Title'] );

            expect( $updated->title )->toBe( 'New Title' )
                ->and( $updated->next_button_text )->toBe( 'Continue' );
        } );
    } );

    describe( 'delete', function (): void {
        it( 'deletes a step', function (): void {
            $step   = FormStep::factory()->for( $this->form )->create();
            $stepId = $step->id;

            // Create another step so delete doesn't auto-create one
            FormStep::factory()->for( $this->form )->create();

            $result = $this->service->delete( $step );

            expect( $result )->toBeTrue()
                ->and( FormStep::find( $stepId ) )->toBeNull();
        } );

        it( 'reassigns fields to previous step when deleting', function (): void {
            $step1 = FormStep::factory()->for( $this->form )->create( ['sort_order' => 1] );
            $step2 = FormStep::factory()->for( $this->form )->create( ['sort_order' => 2] );
            $field = FormField::factory()->for( $this->form )->create( ['step_id' => $step2->id] );

            $this->service->delete( $step2 );

            expect( $field->fresh()->step_id )->toBe( $step1->id );
        } );

        it( 'reassigns fields to next step when deleting first step', function (): void {
            $step1 = FormStep::factory()->for( $this->form )->create( ['sort_order' => 1] );
            $step2 = FormStep::factory()->for( $this->form )->create( ['sort_order' => 2] );
            $field = FormField::factory()->for( $this->form )->create( ['step_id' => $step1->id] );

            $this->service->delete( $step1 );

            expect( $field->fresh()->step_id )->toBe( $step2->id );
        } );

        it( 'creates a default step when deleting the last step of a multi-step form', function (): void {
            $step = FormStep::factory()->for( $this->form )->create();

            $this->service->delete( $step );

            expect( $this->form->steps()->count() )->toBe( 1 )
                ->and( $this->form->steps()->first()->title )->toBe( 'Step 1' );
        } );
    } );

    describe( 'reorder', function (): void {
        it( 'reorders steps based on ID array', function (): void {
            $step1 = FormStep::factory()->for( $this->form )->create( ['sort_order' => 1] );
            $step2 = FormStep::factory()->for( $this->form )->create( ['sort_order' => 2] );
            $step3 = FormStep::factory()->for( $this->form )->create( ['sort_order' => 3] );

            // Reverse the order
            $this->service->reorder( $this->form, [$step3->id, $step2->id, $step1->id] );

            expect( $step1->fresh()->sort_order )->toBe( 3 )
                ->and( $step2->fresh()->sort_order )->toBe( 2 )
                ->and( $step3->fresh()->sort_order )->toBe( 1 );
        } );
    } );

    describe( 'getById', function (): void {
        it( 'finds a step by ID', function (): void {
            $step = FormStep::factory()->for( $this->form )->create();

            $found = $this->service->getById( $this->form, $step->id );

            expect( $found )->toBeInstanceOf( FormStep::class )
                ->and( $found->id )->toBe( $step->id );
        } );

        it( 'returns null for non-existent ID', function (): void {
            $found = $this->service->getById( $this->form, 99999 );

            expect( $found )->toBeNull();
        } );
    } );

    describe( 'getSteps', function (): void {
        it( 'returns all steps for a form with fields loaded', function (): void {
            $step1 = FormStep::factory()->for( $this->form )->create( ['sort_order' => 2] );
            $step2 = FormStep::factory()->for( $this->form )->create( ['sort_order' => 1] );
            FormField::factory()->count( 2 )->for( $this->form )->create( ['step_id' => $step1->id] );

            $steps = $this->service->getSteps( $this->form );

            expect( $steps )->toHaveCount( 2 )
                ->and( $steps[0]->id )->toBe( $step2->id ) // Ordered by sort_order
                ->and( $steps[1]->id )->toBe( $step1->id )
                ->and( $steps[1]->relationLoaded( 'fields' ) )->toBeTrue()
                ->and( $steps[1]->fields )->toHaveCount( 2 );
        } );
    } );

    describe( 'initializeMultiStep', function (): void {
        it( 'creates a default step for a form with no steps', function (): void {
            $step = $this->service->initializeMultiStep( $this->form );

            expect( $step )->toBeInstanceOf( FormStep::class )
                ->and( $step->title )->toBe( 'Step 1' );
        } );

        it( 'returns null if form already has steps', function (): void {
            FormStep::factory()->for( $this->form )->create();

            $result = $this->service->initializeMultiStep( $this->form );

            expect( $result )->toBeNull();
        } );
    } );

    describe( 'convertToMultiStep', function (): void {
        it( 'creates a step and assigns existing fields to it', function (): void {
            $field1 = FormField::factory()->for( $this->form )->create( ['step_id' => null] );
            $field2 = FormField::factory()->for( $this->form )->create( ['step_id' => null] );

            $step = $this->service->convertToMultiStep( $this->form );

            expect( $step )->toBeInstanceOf( FormStep::class )
                ->and( $field1->fresh()->step_id )->toBe( $step->id )
                ->and( $field2->fresh()->step_id )->toBe( $step->id );
        } );
    } );

    describe( 'convertToSingleStep', function (): void {
        it( 'removes all steps and unassigns fields', function (): void {
            $step1 = FormStep::factory()->for( $this->form )->create();
            $step2 = FormStep::factory()->for( $this->form )->create();
            $field = FormField::factory()->for( $this->form )->create( ['step_id' => $step1->id] );

            $this->service->convertToSingleStep( $this->form );

            expect( $this->form->steps()->count() )->toBe( 0 )
                ->and( $field->fresh()->step_id )->toBeNull();
        } );
    } );

    describe( 'getFirstStep', function (): void {
        it( 'returns the first step by sort order', function (): void {
            FormStep::factory()->for( $this->form )->create( ['sort_order' => 3] );
            $first = FormStep::factory()->for( $this->form )->create( ['sort_order' => 1] );
            FormStep::factory()->for( $this->form )->create( ['sort_order' => 2] );

            $result = $this->service->getFirstStep( $this->form );

            expect( $result->id )->toBe( $first->id );
        } );

        it( 'returns null if no steps exist', function (): void {
            $result = $this->service->getFirstStep( $this->form );

            expect( $result )->toBeNull();
        } );
    } );

    describe( 'getLastStep', function (): void {
        it( 'returns the last step by sort order', function (): void {
            // Use a fresh form without any existing steps
            $form = Form::factory()->create( ['is_multi_step' => true] );

            FormStep::factory()->for( $form )->create( ['sort_order' => 1, 'title' => 'First'] );
            FormStep::factory()->for( $form )->create( ['sort_order' => 2, 'title' => 'Middle'] );
            FormStep::factory()->for( $form )->create( ['sort_order' => 3, 'title' => 'Last'] );

            $result = $this->service->getLastStep( $form );

            expect( $result->sort_order )->toBe( 3 )
                ->and( $result->title )->toBe( 'Last' );
        } );

        it( 'returns null if no steps exist', function (): void {
            $result = $this->service->getLastStep( $this->form );

            expect( $result )->toBeNull();
        });
    });
});
