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
            ]);

            expect( $fileValue->is_file)->toBeTrue()
                ->and( $textValue->is_file)->toBeFalse();
        });
    });
});
