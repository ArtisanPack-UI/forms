<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormBuilder;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use Livewire\Livewire;

describe( 'FormBuilder Component', function (): void {
    beforeEach( function (): void {
        $this->form = Form::factory()->create();
    } );

    describe( 'rendering', function (): void {
        it( 'renders the form builder component', function (): void {
            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->assertStatus( 200 )
                ->assertSee( 'Add Fields' )
                ->assertSee( 'Form Fields' );
        } );

        it( 'shows field categories in the palette', function (): void {
            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->assertSee( 'Basic Fields' )
                ->assertSee( 'Choice Fields' )
                ->assertSee( 'Advanced Fields' );
        } );

        it( 'shows existing fields on load', function (): void {
            $field = FormField::factory()->for( $this->form )->create( ['label' => 'Test Field'] );

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->assertSee( 'Test Field' );
        } );
    } );

    describe( 'adding fields', function (): void {
        it( 'adds a text field when clicking the button', function (): void {
            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'addField', 'text' )
                ->assertDispatched( 'field-added' );

            expect( $this->form->fields()->count() )->toBe( 1 )
                ->and( $this->form->fields()->first()->type )->toBe( 'text' );
        } );

        it( 'adds an email field with correct defaults', function (): void {
            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'addField', 'email' );

            $field = $this->form->fields()->first();

            expect( $field->type )->toBe( 'email' )
                ->and( $field->label )->toBe( 'Email Address' );
        } );

        it( 'selects the newly added field', function (): void {
            $component = Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'addField', 'text' );

            $field = $this->form->fields()->first();

            $component->assertSet( 'selectedFieldId', $field->uuid );
        } );

        it( 'marks form as dirty when adding a field', function (): void {
            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->assertSet( 'isDirty', false )
                ->call( 'addField', 'text' )
                ->assertSet( 'isDirty', true );
        } );
    } );

    describe( 'selecting fields', function (): void {
        it( 'selects a field when clicked', function (): void {
            $field = FormField::factory()->for( $this->form )->create();

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'selectField', $field->uuid )
                ->assertSet( 'selectedFieldId', $field->uuid );
        } );

        it( 'deselects the current field', function (): void {
            $field = FormField::factory()->for( $this->form )->create();

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'selectField', $field->uuid )
                ->assertSet( 'selectedFieldId', $field->uuid )
                ->call( 'deselectField' )
                ->assertSet( 'selectedFieldId', null );
        } );
    } );

    describe( 'updating fields', function (): void {
        it( 'updates the selected field', function (): void {
            $field = FormField::factory()->for( $this->form )->create( ['label' => 'Original'] );

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'selectField', $field->uuid )
                ->call( 'updateField', ['label' => 'Updated Label'] );

            expect( $field->fresh()->label )->toBe( 'Updated Label' );
        } );

        it( 'marks form as dirty when updating a field', function (): void {
            $field = FormField::factory()->for( $this->form )->create();

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'selectField', $field->uuid )
                ->assertSet( 'isDirty', false )
                ->call( 'updateField', ['label' => 'New Label'] )
                ->assertSet( 'isDirty', true );
        } );
    } );

    describe( 'deleting fields', function (): void {
        it( 'deletes a field', function (): void {
            $field = FormField::factory()->for( $this->form )->create();

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'deleteField', $field->uuid )
                ->assertDispatched( 'field-deleted' );

            expect( $this->form->fields()->count() )->toBe( 0 );
        } );

        it( 'clears selection when deleting the selected field', function (): void {
            $field = FormField::factory()->for( $this->form )->create();

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'selectField', $field->uuid )
                ->call( 'deleteField', $field->uuid )
                ->assertSet( 'selectedFieldId', null );
        } );
    } );

    describe( 'duplicating fields', function (): void {
        it( 'duplicates a field', function (): void {
            $field = FormField::factory()->for( $this->form )->create( [
                'label' => 'Original',
                'type'  => 'text',
            ] );

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'duplicateField', $field->uuid )
                ->assertDispatched( 'field-duplicated' );

            expect( $this->form->fields()->count() )->toBe( 2 );

            $duplicate = $this->form->fields()->where( 'id', '!=', $field->id )->first();
            expect( $duplicate->label )->toBe( 'Original (Copy)' )
                ->and( $duplicate->type )->toBe( 'text' );
        } );

        it( 'selects the duplicated field', function (): void {
            $field = FormField::factory()->for( $this->form )->create();

            $component = Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'duplicateField', $field->uuid );

            $duplicate = $this->form->fields()->where( 'id', '!=', $field->id )->first();
            $component->assertSet( 'selectedFieldId', $duplicate->uuid );
        } );
    } );

    describe( 'reordering fields', function (): void {
        it( 'reorders fields based on UUID array', function (): void {
            $field1 = FormField::factory()->for( $this->form )->create( ['sort_order' => 1] );
            $field2 = FormField::factory()->for( $this->form )->create( ['sort_order' => 2] );
            $field3 = FormField::factory()->for( $this->form )->create( ['sort_order' => 3] );

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->dispatch( 'fields-reordered', orderedUuids: [$field3->uuid, $field2->uuid, $field1->uuid] );

            expect( $field1->fresh()->sort_order )->toBe( 3 )
                ->and( $field2->fresh()->sort_order )->toBe( 2 )
                ->and( $field3->fresh()->sort_order )->toBe( 1 );
        } );
    } );

    describe( 'saving', function (): void {
        it( 'clears dirty state after saving', function (): void {
            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'addField', 'text' )
                ->assertSet( 'isDirty', true )
                ->call( 'saveForm' )
                ->assertSet( 'isDirty', false )
                ->assertDispatched( 'form-saved' );
        } );

        it( 'does not save when not dirty', function (): void {
            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->assertSet( 'isDirty', false )
                ->call( 'saveForm' )
                ->assertNotDispatched( 'form-saved' );
        } );
    } );
} );

describe( 'FormBuilder Multi-Step', function (): void {
    beforeEach( function (): void {
        $this->form = Form::factory()->multiStep()->create();
        $this->step = FormStep::factory()->for( $this->form )->create();
    } );

    it( 'shows step manager for multi-step forms', function (): void {
        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->assertSee( $this->step->title )
            ->assertSee( 'Add Step' );
    } );

    it( 'sets active step on mount', function (): void {
        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->assertSet( 'activeStepId', $this->step->id );
    } );

    it( 'adds a new step', function (): void {
        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'addStep' )
            ->assertDispatched( 'step-added' );

        expect( $this->form->steps()->count() )->toBe( 2 );
    } );

    it( 'selects a step', function (): void {
        $step2 = FormStep::factory()->for( $this->form )->create();

        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectStep', $step2->id )
            ->assertSet( 'activeStepId', $step2->id );
    } );

    it( 'updates a step', function (): void {
        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'updateStep', $this->step->id, ['title' => 'Updated Title'] );

        expect( $this->step->fresh()->title )->toBe( 'Updated Title' );
    } );

    it( 'deletes a step when more than one exists', function (): void {
        $step2 = FormStep::factory()->for( $this->form )->create();

        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'deleteStep', $step2->id )
            ->assertDispatched( 'step-deleted' );

        expect( $this->form->steps()->count() )->toBe( 1 );
    } );

    it( 'adds fields to the active step', function (): void {
        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'addField', 'text' );

        $field = $this->form->fields()->first();

        expect( $field->step_id )->toBe( $this->step->id );
    } );

    it( 'reorders steps based on ID array', function (): void {
        $step2 = FormStep::factory()->for( $this->form )->create( ['sort_order' => 2] );
        $step3 = FormStep::factory()->for( $this->form )->create( ['sort_order' => 3] );

        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->dispatch( 'steps-reordered', orderedIds: [$step3->id, $step2->id, $this->step->id] );

        expect( $this->step->fresh()->sort_order )->toBe( 3 )
            ->and( $step2->fresh()->sort_order )->toBe( 2 )
            ->and( $step3->fresh()->sort_order )->toBe( 1 );
    } );
} );

describe( 'FormBuilder Enable/Disable Multi-Step', function (): void {
    it( 'enables multi-step mode on a single-step form', function (): void {
        $form  = Form::factory()->create( ['is_multi_step' => false] );
        $field = FormField::factory()->for( $form )->create( ['step_id' => null] );

        Livewire::test( FormBuilder::class, ['form' => $form] )
            ->call( 'enableMultiStep' )
            ->assertDispatched( 'multi-step-enabled' );

        $form->refresh();
        $field->refresh();

        expect( $form->is_multi_step )->toBeTrue()
            ->and( $form->steps()->count() )->toBe( 1 )
            ->and( $field->step_id )->not->toBeNull();
    } );

    it( 'does nothing when enabling multi-step on already multi-step form', function (): void {
        $form = Form::factory()->multiStep()->create();
        FormStep::factory()->for( $form )->create();

        Livewire::test( FormBuilder::class, ['form' => $form] )
            ->call( 'enableMultiStep' )
            ->assertNotDispatched( 'multi-step-enabled' );

        expect( $form->steps()->count() )->toBe( 1 );
    } );

    it( 'disables multi-step mode', function (): void {
        $form  = Form::factory()->multiStep()->create();
        $step  = FormStep::factory()->for( $form )->create();
        $field = FormField::factory()->for( $form )->create( ['step_id' => $step->id] );

        Livewire::test( FormBuilder::class, ['form' => $form] )
            ->call( 'disableMultiStep' )
            ->assertDispatched( 'multi-step-disabled' )
            ->assertSet( 'activeStepId', null );

        $form->refresh();
        $field->refresh();

        expect( $form->is_multi_step )->toBeFalse()
            ->and( $form->steps()->count() )->toBe( 0 )
            ->and( $field->step_id )->toBeNull();
    } );

    it( 'does nothing when disabling multi-step on single-step form', function (): void {
        $form = Form::factory()->create( ['is_multi_step' => false] );

        Livewire::test( FormBuilder::class, ['form' => $form] )
            ->call( 'disableMultiStep' )
            ->assertNotDispatched( 'multi-step-disabled' );
    } );

    it( 'sets active step when enabling multi-step', function (): void {
        $form = Form::factory()->create( ['is_multi_step' => false] );

        $component = Livewire::test( FormBuilder::class, ['form' => $form] )
            ->call( 'enableMultiStep' );

        $form->refresh();
        $step = $form->steps()->first();

        $component->assertSet( 'activeStepId', $step->id );
    } );
} );
