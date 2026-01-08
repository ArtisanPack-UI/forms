<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;

describe( 'FormField Model', function (): void {
    describe( 'factory', function (): void {
        it( 'creates a valid form field', function (): void {
            $field = FormField::factory()->create();

            expect( $field )->toBeInstanceOf( FormField::class )
                ->and( $field->name )->not->toBeEmpty()
                ->and( $field->uuid )->not->toBeEmpty()
                ->and( $field->form )->toBeInstanceOf( Form::class );
        } );

        it( 'creates an email field', function (): void {
            $field = FormField::factory()->email()->create();

            expect( $field->type )->toBe( 'email' )
                ->and( $field->name )->toBe( 'email' );
        } );

        it( 'creates a required field', function (): void {
            $field = FormField::factory()->required()->create();

            expect( $field->is_required )->toBeTrue();
        } );

        it( 'creates a field with conditions', function (): void {
            $field = FormField::factory()->withConditions()->create();

            expect( $field->conditional_logic )->not->toBeEmpty()
                ->and( $field->has_conditional_logic )->toBeTrue();
        } );
    } );

    describe( 'boot', function (): void {
        it( 'auto-generates UUID', function (): void {
            $field = FormField::factory()->create( ['uuid' => null] );

            expect( $field->uuid )->not->toBeEmpty()
                ->and( strlen( $field->uuid ) )->toBe( 36 );
        } );
    } );

    describe( 'relationships', function (): void {
        it( 'belongs to a form', function (): void {
            $form  = Form::factory()->create();
            $field = FormField::factory()->for( $form )->create();

            expect( $field->form->id )->toBe( $form->id );
        } );

        it( 'belongs to a step', function (): void {
            $step  = FormStep::factory()->create();
            $field = FormField::factory()->for( $step->form )->state( ['step_id' => $step->id] )->create();

            expect( $field->step->id )->toBe( $step->id );
        } );

        it( 'has many submission values', function (): void {
            $field = FormField::factory()->create();
            FormSubmissionValue::factory()->count( 2 )->state( ['field_id' => $field->id] )->create();

            expect( $field->submissionValues )->toHaveCount( 2 );
        } );
    } );

    describe( 'scopes', function (): void {
        it( 'filters required fields', function (): void {
            FormField::factory()->create( ['is_required' => true] );
            FormField::factory()->create( ['is_required' => false] );

            expect( FormField::required()->count() )->toBe( 1 );
        } );

        it( 'filters by type', function (): void {
            FormField::factory()->create( ['type' => 'text'] );
            FormField::factory()->create( ['type' => 'email'] );

            expect( FormField::ofType( 'email' )->count() )->toBe( 1 );
        } );

        it( 'filters fields without step', function (): void {
            $step = FormStep::factory()->create();
            FormField::factory()->for( $step->form )->create( ['step_id' => null] );
            FormField::factory()->for( $step->form )->state( ['step_id' => $step->id] )->create();

            expect( FormField::withoutStep()->count() )->toBe( 1 );
        } );

        it( 'filters fields in step', function (): void {
            $step = FormStep::factory()->create();
            FormField::factory()->for( $step->form )->create( ['step_id' => null] );
            FormField::factory()->for( $step->form )->state( ['step_id' => $step->id] )->create();

            expect( FormField::inStep( $step->id )->count() )->toBe( 1 );
        } );
    } );

    describe( 'accessors', function (): void {
        it( 'detects conditional logic', function (): void {
            $fieldWith    = FormField::factory()->withConditions()->create();
            $fieldWithout = FormField::factory()->create( ['conditional_logic' => null] );

            expect( $fieldWith->has_conditional_logic )->toBeTrue()
                ->and( $fieldWithout->has_conditional_logic )->toBeFalse();
        } );

        it( 'detects file field', function (): void {
            $fileField = FormField::factory()->file()->create();
            $textField = FormField::factory()->text()->create();

            expect( $fileField->is_file_field )->toBeTrue()
                ->and( $textField->is_file_field )->toBeFalse();
        } );

        it( 'gets options from config', function (): void {
            $field = FormField::factory()->select()->create();

            expect( $field->options )->toBeArray()
                ->and( $field->options )->toHaveCount( 3 );
        } );

        it( 'returns width class', function (): void {
            expect( FormField::factory()->create( ['width' => 'full'] )->width_class )->toBe( 'w-full' )
                ->and( FormField::factory()->create( ['width' => 'half'] )->width_class )->toBe( 'w-full md:w-1/2' )
                ->and( FormField::factory()->create( ['width' => 'third'] )->width_class )->toBe( 'w-full md:w-1/3' )
                ->and( FormField::factory()->create( ['width' => 'two-thirds'] )->width_class )->toBe( 'w-full md:w-2/3' );
        } );
    } );

    describe( 'methods', function (): void {
        it( 'gets config value', function (): void {
            $field = FormField::factory()->create( [
                'field_config' => ['option' => 'value', 'nested' => ['key' => 'nested_value']],
            ] );

            expect( $field->getConfig( 'option' ) )->toBe( 'value' )
                ->and( $field->getConfig( 'nested.key' ) )->toBe( 'nested_value' )
                ->and( $field->getConfig( 'missing', 'default' ) )->toBe( 'default' );
        } );

        it( 'gets validation rule', function (): void {
            $field = FormField::factory()->create( [
                'validation_rules' => ['min' => 5, 'max' => 100],
            ] );

            expect( $field->getValidationRule( 'min' ) )->toBe( 5 )
                ->and( $field->getValidationRule( 'missing', 10 ) )->toBe( 10 );
        } );

        it( 'builds validation rules', function (): void {
            $field = FormField::factory()->required()->email()->create( [
                'validation_rules' => ['max' => 255],
            ] );

            $rules = $field->buildValidationRules();

            expect( $rules )->toContain( 'required' )
                ->and( $rules )->toContain( 'email' )
                ->and( $rules )->toContain( 'max:255' );
        } );

        it( 'builds validation rules for file field', function (): void {
            $field = FormField::factory()->required()->file()->create();

            $rules = $field->buildValidationRules();

            expect( $rules )->toContain( 'required' )
                ->and( $rules )->toContain( 'file' )
                ->and( $rules )->toContain( 'mimes:pdf,doc,docx' )
                ->and( $rules )->toContain( 'max:5120' );
        } );

        it( 'builds validation rules for time field with min and max time', function (): void {
            $field = FormField::factory()->required()->time()->create( [
                'validation_rules' => [
                    'min_time' => '09:00',
                    'max_time' => '17:00',
                ],
            ]);

            $rules = $field->buildValidationRules();

            expect( $rules)->toContain( 'required')
                ->and( $rules)->toContain( 'date_format:H:i')
                ->and( $rules)->toContain( 'after_or_equal:09:00')
                ->and( $rules)->toContain( 'before_or_equal:17:00');
        });
    });
});
