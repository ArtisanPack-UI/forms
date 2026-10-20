<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Models\FormUpload;

describe( 'FormSubmissionValue Model', function (): void {
    describe( 'factory', function (): void {
        it( 'creates a valid submission value', function (): void {
            $value = FormSubmissionValue::factory()->create();

            expect( $value )->toBeInstanceOf( FormSubmissionValue::class )
                ->and( $value->field_name )->not->toBeEmpty()
                ->and( $value->value )->not->toBeEmpty();
        } );

        it( 'creates an email value', function (): void {
            $value = FormSubmissionValue::factory()->email()->create();

            expect( $value->field_type )->toBe( 'email' )
                ->and( $value->value )->toContain( '@' );
        } );

        it( 'creates an array value', function (): void {
            $value = FormSubmissionValue::factory()->array()->create();

            expect( $value->field_type )->toBe( 'checkbox_group' )
                ->and( $value->value_array )->toBeArray()
                ->and( $value->value )->toBeNull();
        } );
    } );

    describe( 'timestamps', function (): void {
        it( 'does not use timestamps', function (): void {
            $value = new FormSubmissionValue;

            expect( $value->timestamps )->toBeFalse();
        } );
    } );

    describe( 'relationships', function (): void {
        it( 'belongs to a submission', function (): void {
            $submission = FormSubmission::factory()->create();
            $value      = FormSubmissionValue::factory()->for( $submission, 'submission' )->create();

            expect( $value->submission->id )->toBe( $submission->id );
        } );

        it( 'belongs to a field', function (): void {
            $field = FormField::factory()->create();
            $value = FormSubmissionValue::factory()->state( ['field_id' => $field->id] )->create();

            expect( $value->field->id )->toBe( $field->id );
        } );

        it( 'belongs to an upload', function (): void {
            $submission = FormSubmission::factory()->create();
            $upload     = FormUpload::factory()->for( $submission, 'submission' )->create();
            $value      = FormSubmissionValue::factory()->for( $submission, 'submission' )->state( [
                'upload_id'  => $upload->id,
                'field_type' => 'file',
            ] )->create();

            expect( $value->upload->id )->toBe( $upload->id );
        } );
    } );

    describe( 'accessors', function (): void {
        it( 'gets display value from value', function (): void {
            $value = FormSubmissionValue::factory()->create( [
                'value'       => 'Test Value',
                'value_array' => null,
            ] );

            expect( $value->display_value )->toBe( 'Test Value' );
        } );

        it( 'gets display value from array', function (): void {
            $value = FormSubmissionValue::factory()->create( [
                'value'       => null,
                'value_array' => ['Option A', 'Option B'],
            ] );

            expect( $value->display_value )->toBe( 'Option A, Option B' );
        } );

        it( 'gets display value from upload', function (): void {
            $submission = FormSubmission::factory()->create();
            $upload     = FormUpload::factory()->for( $submission, 'submission' )->create( [
                'original_name' => 'document.pdf',
            ] );
            $value = FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'value'       => null,
                'value_array' => null,
                'upload_id'   => $upload->id,
            ] );

            expect( $value->display_value )->toBe( 'document.pdf' );
        } );

        it( 'returns empty string for null value', function (): void {
            $value = FormSubmissionValue::factory()->create( [
                'value'       => null,
                'value_array' => null,
            ] );

            expect( $value->display_value )->toBe( '' );
        } );

        it( 'detects array value', function (): void {
            $arrayValue = FormSubmissionValue::factory()->array()->create();
            $textValue  = FormSubmissionValue::factory()->create( ['value_array' => null] );

            expect( $arrayValue->is_array_value )->toBeTrue()
                ->and( $textValue->is_array_value )->toBeFalse();
        } );

        it( 'detects file value', function (): void {
            $submission = FormSubmission::factory()->create();
            $upload     = FormUpload::factory()->for( $submission, 'submission' )->create();

            $fileValue = FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'field_type' => 'file',
                'upload_id'  => $upload->id,
            ] );
            $textValue = FormSubmissionValue::factory()->create( [
                'field_type' => 'text',
                'upload_id'  => null,
            ] );

            expect( $fileValue->is_file )->toBeTrue()
                ->and( $textValue->is_file )->toBeFalse();
        } );
    } );
} );

describe( 'FormSubmissionValue display labels (issue #85)', function (): void {
    beforeEach( function (): void {
        $this->submission = FormSubmission::factory()->create();
        $this->radio      = FormField::factory()->for( $this->submission->form )->create( [
            'type'         => 'radio',
            'name'         => 'state',
            'field_config' => ['options' => [
                ['value' => 'ca', 'label' => 'California'],
                ['value' => 'ny', 'label' => 'New York'],
            ]],
        ] );
    } );

    it( 'shows the chosen option label for a choice field', function (): void {
        $value = FormSubmissionValue::factory()->for( $this->submission, 'submission' )->create( [
            'field_id'   => $this->radio->id,
            'field_name' => 'state',
            'field_type' => 'radio',
            'value'      => 'ca',
        ] );

        expect( $value->display_value )->toBe( 'California' )
            ->and( $value->getRawValue() )->toBe( 'ca' );
    } );

    it( 'shows one label per item for an array value', function (): void {
        $field = FormField::factory()->for( $this->submission->form )->selectMultiple()->create();
        $value = FormSubmissionValue::factory()->for( $this->submission, 'submission' )->create( [
            'field_id'    => $field->id,
            'field_type'  => 'select_multiple',
            'value'       => null,
            'value_array' => ['option1', 'option3', 'removed'],
        ] );

        expect( $value->display_values )->toBe( ['Option 1', 'Option 3', 'removed'] )
            ->and( $value->display_value )->toBe( 'Option 1, Option 3, removed' )
            ->and( $value->getRawValue() )->toBe( 'option1, option3, removed' );
    } );

    it( 'falls back to the raw value when the field was deleted', function (): void {
        $value = FormSubmissionValue::factory()->for( $this->submission, 'submission' )->create( [
            'field_id'   => null,
            'field_type' => 'radio',
            'value'      => 'ca',
        ] );

        expect( $value->display_value )->toBe( 'ca' );
    } );

    it( 'does not map values of non-choice fields', function (): void {
        $value = FormSubmissionValue::factory()->for( $this->submission, 'submission' )->create( [
            'field_id'   => $this->radio->id,
            'field_type' => 'text',
            'value'      => 'ca',
        ] );

        expect( $value->display_value )->toBe( 'ca' );
    } );

    it( 'shows checked / unchecked text for a checkbox', function ( ?string $stored, array $config, string $expected ): void {
        $field = FormField::factory()->for( $this->submission->form )->create( [
            'type'         => 'checkbox',
            'field_config' => $config,
        ] );
        $value = FormSubmissionValue::factory()->for( $this->submission, 'submission' )->create( [
            'field_id'   => $field->id,
            'field_type' => 'checkbox',
            'value'      => $stored,
        ] );

        expect( $value->display_value )->toBe( $expected );
    } )->with( [
        'checked, default'    => ['1', [], 'Yes'],
        'unchecked, default'  => ['', [], 'No'],
        'null, default'       => [null, [], 'No'],
        'checked, custom'     => ['1', ['checked_label' => 'Subscribed'], 'Subscribed'],
        'unchecked, custom'   => ['0', ['unchecked_label' => 'Not subscribed'], 'Not subscribed'],
    ] );

    it( 'shows Yes / No for a checkbox whose field was deleted', function (): void {
        $value = FormSubmissionValue::factory()->for( $this->submission, 'submission' )->create( [
            'field_id'   => null,
            'field_type' => 'checkbox',
            'value'      => '1',
        ] );

        expect( $value->display_value )->toBe( 'Yes' );
    } );

    it( 'keeps raw values in submission data for conditions and webhooks', function (): void {
        FormSubmissionValue::factory()->for( $this->submission, 'submission' )->create( [
            'field_id'   => $this->radio->id,
            'field_name' => 'state',
            'field_type' => 'radio',
            'value'      => 'ny',
        ] );

        $submission = $this->submission->fresh( ['values.field'] );

        expect( $submission->data_array )->toBe( ['state' => 'ny'] )
            ->and( $submission->getValue( 'state' ) )->toBe( 'New York' );
    } );
} );
