<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Schema;

/**
 * Tests for database migrations
 *
 * Verifies that all migrations run correctly and create the expected tables
 * with proper columns, indexes, and foreign key constraints.
 *
 * @since 1.0.0
 */
describe( 'Forms package migrations', function (): void {

    describe( 'forms table', function (): void {

        it( 'creates the forms table with all columns', function (): void {
            expect( Schema::hasTable( 'forms' ) )->toBeTrue();
            expect( Schema::hasColumns( 'forms', [
                'id',
                'name',
                'slug',
                'description',
                'submit_button_text',
                'success_message',
                'redirect_url',
                'settings',
                'is_multi_step',
                'show_progress_bar',
                'allow_step_navigation',
                'is_active',
                'created_at',
                'updated_at',
            ] ) )->toBeTrue();
        } );

        it( 'enforces unique slug constraint', function (): void {
            $form1 = Illuminate\Support\Facades\DB::table( 'forms' )->insert( [
                'name'       => 'Test Form',
                'slug'       => 'test-form',
                'created_at' => now(),
                'updated_at' => now(),
            ] );

            expect( fn () => Illuminate\Support\Facades\DB::table( 'forms' )->insert( [
                'name'       => 'Test Form 2',
                'slug'       => 'test-form',
                'created_at' => now(),
                'updated_at' => now(),
            ] ) )->toThrow( Illuminate\Database\QueryException::class );
        } );

    } );

    describe( 'form_steps table', function (): void {

        it( 'creates the form_steps table with all columns', function (): void {
            expect( Schema::hasTable( 'form_steps' ) )->toBeTrue();
            expect( Schema::hasColumns( 'form_steps', [
                'id',
                'form_id',
                'title',
                'description',
                'sort_order',
                'next_button_text',
                'prev_button_text',
                'created_at',
                'updated_at',
            ] ) )->toBeTrue();
        } );

        it( 'cascades delete when form is deleted', function (): void {
            $formId = Illuminate\Support\Facades\DB::table( 'forms' )->insertGetId( [
                'name'       => 'Test Form',
                'slug'       => 'cascade-test',
                'created_at' => now(),
                'updated_at' => now(),
            ] );

            $stepId = Illuminate\Support\Facades\DB::table( 'form_steps' )->insertGetId( [
                'form_id'    => $formId,
                'title'      => 'Step 1',
                'created_at' => now(),
                'updated_at' => now(),
            ] );

            expect( Illuminate\Support\Facades\DB::table( 'form_steps' )->where( 'id', $stepId )->exists() )->toBeTrue();

            Illuminate\Support\Facades\DB::table( 'forms' )->where( 'id', $formId )->delete();

            expect( Illuminate\Support\Facades\DB::table( 'form_steps' )->where( 'id', $stepId )->exists() )->toBeFalse();
        } );

    } );

    describe( 'form_fields table', function (): void {

        it( 'creates the form_fields table with all columns', function (): void {
            expect( Schema::hasTable( 'form_fields' ) )->toBeTrue();
            expect( Schema::hasColumns( 'form_fields', [
                'id',
                'form_id',
                'step_id',
                'uuid',
                'name',
                'type',
                'label',
                'placeholder',
                'help_text',
                'is_required',
                'validation_rules',
                'field_config',
                'default_value',
                'conditional_logic',
                'width',
                'css_classes',
                'sort_order',
                'created_at',
                'updated_at',
            ] ) )->toBeTrue();
        } );

        it( 'enforces unique uuid constraint', function (): void {
            $formId = Illuminate\Support\Facades\DB::table( 'forms' )->insertGetId( [
                'name'       => 'UUID Test Form',
                'slug'       => 'uuid-test',
                'created_at' => now(),
                'updated_at' => now(),
            ] );

            $uuid = Illuminate\Support\Str::uuid()->toString();

            Illuminate\Support\Facades\DB::table( 'form_fields' )->insert( [
                'form_id'    => $formId,
                'uuid'       => $uuid,
                'name'       => 'field_1',
                'type'       => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ] );

            expect( fn () => Illuminate\Support\Facades\DB::table( 'form_fields' )->insert( [
                'form_id'    => $formId,
                'uuid'       => $uuid,
                'name'       => 'field_2',
                'type'       => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ] ) )->toThrow( Illuminate\Database\QueryException::class );
        } );

        it( 'cascades delete when form is deleted', function (): void {
            $formId = Illuminate\Support\Facades\DB::table( 'forms' )->insertGetId( [
                'name'       => 'Field Cascade Test',
                'slug'       => 'field-cascade',
                'created_at' => now(),
                'updated_at' => now(),
            ] );

            $fieldId = Illuminate\Support\Facades\DB::table( 'form_fields' )->insertGetId( [
                'form_id'    => $formId,
                'uuid'       => Illuminate\Support\Str::uuid()->toString(),
                'name'       => 'email',
                'type'       => 'email',
                'created_at' => now(),
                'updated_at' => now(),
            ] );

            expect( Illuminate\Support\Facades\DB::table( 'form_fields' )->where( 'id', $fieldId )->exists() )->toBeTrue();

            Illuminate\Support\Facades\DB::table( 'forms' )->where( 'id', $formId )->delete();

            expect( Illuminate\Support\Facades\DB::table( 'form_fields' )->where( 'id', $fieldId )->exists() )->toBeFalse();
        } );

        it( 'sets step_id to null when step is deleted', function (): void {
            $formId = Illuminate\Support\Facades\DB::table( 'forms' )->insertGetId( [
                'name'       => 'Step Null Test',
                'slug'       => 'step-null-test',
                'created_at' => now(),
                'updated_at' => now(),
            ] );

            $stepId = Illuminate\Support\Facades\DB::table( 'form_steps' )->insertGetId( [
                'form_id'    => $formId,
                'title'      => 'Step 1',
                'created_at' => now(),
                'updated_at' => now(),
            ] );

            $fieldId = Illuminate\Support\Facades\DB::table( 'form_fields' )->insertGetId( [
                'form_id'    => $formId,
                'step_id'    => $stepId,
                'uuid'       => Illuminate\Support\Str::uuid()->toString(),
                'name'       => 'name',
                'type'       => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ] );

            Illuminate\Support\Facades\DB::table( 'form_steps' )->where( 'id', $stepId )->delete();

            $field = Illuminate\Support\Facades\DB::table( 'form_fields' )->where( 'id', $fieldId )->first();
            expect( $field->step_id )->toBeNull();
        } );

    } );

    describe( 'form_submissions table', function (): void {

        it( 'creates the form_submissions table with all columns', function (): void {
            expect( Schema::hasTable( 'form_submissions' ) )->toBeTrue();
            expect( Schema::hasColumns( 'form_submissions', [
                'id',
                'form_id',
                'submission_number',
                'page_url',
                'referrer_url',
                'ip_address',
                'user_agent',
                'is_read',
                'is_spam',
                'is_starred',
                'admin_notes',
                'created_at',
                'updated_at',
            ] ) )->toBeTrue();
        } );

        it( 'cascades delete when form is deleted', function (): void {
            $formId = Illuminate\Support\Facades\DB::table( 'forms' )->insertGetId( [
                'name'       => 'Submission Cascade Test',
                'slug'       => 'submission-cascade',
                'created_at' => now(),
                'updated_at' => now(),
            ] );

            $submissionId = Illuminate\Support\Facades\DB::table( 'form_submissions' )->insertGetId( [
                'form_id'           => $formId,
                'submission_number' => 'FORM-2025-00001',
                'created_at'        => now(),
                'updated_at'        => now(),
            ] );

            expect( Illuminate\Support\Facades\DB::table( 'form_submissions' )->where( 'id', $submissionId )->exists() )->toBeTrue();

            Illuminate\Support\Facades\DB::table( 'forms' )->where( 'id', $formId )->delete();

            expect( Illuminate\Support\Facades\DB::table( 'form_submissions' )->where( 'id', $submissionId )->exists() )->toBeFalse();
        } );

    } );

    describe( 'form_uploads table', function (): void {

        it( 'creates the form_uploads table with all columns', function (): void {
            expect( Schema::hasTable( 'form_uploads' ) )->toBeTrue();
            expect( Schema::hasColumns( 'form_uploads', [
                'id',
                'submission_id',
                'field_id',
                'original_name',
                'stored_name',
                'disk',
                'path',
                'mime_type',
                'size',
                'created_at',
                'updated_at',
            ] ) )->toBeTrue();
        } );

        it( 'cascades delete when submission is deleted', function (): void {
            $formId = Illuminate\Support\Facades\DB::table( 'forms' )->insertGetId( [
                'name'       => 'Upload Cascade Test',
                'slug'       => 'upload-cascade',
                'created_at' => now(),
                'updated_at' => now(),
            ] );

            $submissionId = Illuminate\Support\Facades\DB::table( 'form_submissions' )->insertGetId( [
                'form_id'           => $formId,
                'submission_number' => 'FORM-2025-00002',
                'created_at'        => now(),
                'updated_at'        => now(),
            ] );

            $uploadId = Illuminate\Support\Facades\DB::table( 'form_uploads' )->insertGetId( [
                'submission_id' => $submissionId,
                'original_name' => 'test.pdf',
                'stored_name'   => 'abc123.pdf',
                'path'          => 'uploads/abc123.pdf',
                'size'          => 1024,
                'created_at'    => now(),
                'updated_at'    => now(),
            ] );

            expect( Illuminate\Support\Facades\DB::table( 'form_uploads' )->where( 'id', $uploadId )->exists() )->toBeTrue();

            Illuminate\Support\Facades\DB::table( 'form_submissions' )->where( 'id', $submissionId )->delete();

            expect( Illuminate\Support\Facades\DB::table( 'form_uploads' )->where( 'id', $uploadId )->exists() )->toBeFalse();
        } );

    } );

    describe( 'form_submission_values table', function (): void {

        it( 'creates the form_submission_values table with all columns', function (): void {
            expect( Schema::hasTable( 'form_submission_values' ) )->toBeTrue();
            expect( Schema::hasColumns( 'form_submission_values', [
                'id',
                'submission_id',
                'field_id',
                'field_name',
                'field_label',
                'field_type',
                'value',
                'value_array',
                'upload_id',
                'created_at',
            ] ) )->toBeTrue();
        } );

        it( 'cascades delete when submission is deleted', function (): void {
            $formId = Illuminate\Support\Facades\DB::table( 'forms' )->insertGetId( [
                'name'       => 'Value Cascade Test',
                'slug'       => 'value-cascade',
                'created_at' => now(),
                'updated_at' => now(),
            ] );

            $submissionId = Illuminate\Support\Facades\DB::table( 'form_submissions' )->insertGetId( [
                'form_id'           => $formId,
                'submission_number' => 'FORM-2025-00003',
                'created_at'        => now(),
                'updated_at'        => now(),
            ] );

            $valueId = Illuminate\Support\Facades\DB::table( 'form_submission_values' )->insertGetId( [
                'submission_id' => $submissionId,
                'field_name'    => 'email',
                'field_type'    => 'email',
                'value'         => 'test@example.com',
                'created_at'    => now(),
            ] );

            expect( Illuminate\Support\Facades\DB::table( 'form_submission_values' )->where( 'id', $valueId )->exists() )->toBeTrue();

            Illuminate\Support\Facades\DB::table( 'form_submissions' )->where( 'id', $submissionId )->delete();

            expect( Illuminate\Support\Facades\DB::table( 'form_submission_values' )->where( 'id', $valueId )->exists() )->toBeFalse();
        } );

        it( 'sets field_id to null when field is deleted', function (): void {
            $formId = Illuminate\Support\Facades\DB::table( 'forms' )->insertGetId( [
                'name'       => 'Field Null Test',
                'slug'       => 'field-null-test',
                'created_at' => now(),
                'updated_at' => now(),
            ] );

            $fieldId = Illuminate\Support\Facades\DB::table( 'form_fields' )->insertGetId( [
                'form_id'    => $formId,
                'uuid'       => Illuminate\Support\Str::uuid()->toString(),
                'name'       => 'email',
                'type'       => 'email',
                'created_at' => now(),
                'updated_at' => now(),
            ] );

            $submissionId = Illuminate\Support\Facades\DB::table( 'form_submissions' )->insertGetId( [
                'form_id'           => $formId,
                'submission_number' => 'FORM-2025-00004',
                'created_at'        => now(),
                'updated_at'        => now(),
            ] );

            $valueId = Illuminate\Support\Facades\DB::table( 'form_submission_values' )->insertGetId( [
                'submission_id' => $submissionId,
                'field_id'      => $fieldId,
                'field_name'    => 'email',
                'field_type'    => 'email',
                'value'         => 'test@example.com',
                'created_at'    => now(),
            ] );

            Illuminate\Support\Facades\DB::table( 'form_fields' )->where( 'id', $fieldId )->delete();

            $value = Illuminate\Support\Facades\DB::table( 'form_submission_values' )->where( 'id', $valueId )->first();
            expect( $value->field_id )->toBeNull();
        } );

    } );

    describe( 'form_notifications table', function (): void {

        it( 'creates the form_notifications table with all columns', function (): void {
            expect( Schema::hasTable( 'form_notifications' ) )->toBeTrue();
            expect( Schema::hasColumns( 'form_notifications', [
                'id',
                'form_id',
                'type',
                'name',
                'to_email',
                'to_field',
                'cc_emails',
                'bcc_emails',
                'reply_to_email',
                'reply_to_field',
                'from_name',
                'from_email',
                'subject',
                'message',
                'conditional_logic',
                'include_submission_data',
                'is_active',
                'sort_order',
                'created_at',
                'updated_at',
            ] ) )->toBeTrue();
        } );

        it( 'cascades delete when form is deleted', function (): void {
            $formId = Illuminate\Support\Facades\DB::table( 'forms' )->insertGetId( [
                'name'       => 'Notification Cascade Test',
                'slug'       => 'notification-cascade',
                'created_at' => now(),
                'updated_at' => now(),
            ] );

            $notificationId = Illuminate\Support\Facades\DB::table( 'form_notifications' )->insertGetId( [
                'form_id'    => $formId,
                'type'       => 'admin',
                'name'       => 'Admin Notification',
                'to_email'   => 'admin@example.com',
                'subject'    => 'New Form Submission',
                'message'    => 'You have a new submission.',
                'created_at' => now(),
                'updated_at' => now(),
            ] );

            expect( Illuminate\Support\Facades\DB::table( 'form_notifications' )->where( 'id', $notificationId )->exists() )->toBeTrue();

            Illuminate\Support\Facades\DB::table( 'forms' )->where( 'id', $formId )->delete();

            expect( Illuminate\Support\Facades\DB::table( 'form_notifications' )->where( 'id', $notificationId )->exists() )->toBeFalse();
        } );

    } );

} );

describe( 'Forms package configuration', function (): void {

    it( 'registers the form-uploads disk', function (): void {
        $diskConfig = config( 'filesystems.disks.form-uploads' );

        expect( $diskConfig )->not->toBeNull();
        expect( $diskConfig['driver'])->toBe( 'local');
        expect( $diskConfig['visibility'])->toBe( 'private');
    });

    it( 'loads package configuration under artisanpack.forms', function (): void {
        $config = config( 'artisanpack.forms');

        expect( $config)->not->toBeNull();
        expect( $config['uploads'])->not->toBeNull();
        expect( $config['submissions'])->not->toBeNull();
        expect( $config['spam_protection'])->not->toBeNull();
        expect( $config['notifications'])->not->toBeNull();
        expect( $config['display'])->not->toBeNull();
    });

});
