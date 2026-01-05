<?php

declare(strict_types=1);

use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormUpload;

describe('FormUpload Model', function (): void {
    describe('factory', function (): void {
        it('creates a valid upload', function (): void {
            $upload = FormUpload::factory()->create();

            expect($upload)->toBeInstanceOf(FormUpload::class)
                ->and($upload->original_name)->not->toBeEmpty()
                ->and($upload->path)->not->toBeEmpty()
                ->and($upload->submission)->toBeInstanceOf(FormSubmission::class);
        });

        it('creates an image upload', function (): void {
            $upload = FormUpload::factory()->image()->create();

            expect($upload->mime_type)->toBe('image/jpeg')
                ->and($upload->original_name)->toEndWith('.jpg');
        });

        it('creates a PDF upload', function (): void {
            $upload = FormUpload::factory()->pdf()->create();

            expect($upload->mime_type)->toBe('application/pdf')
                ->and($upload->original_name)->toEndWith('.pdf');
        });

        it('creates a document upload', function (): void {
            $upload = FormUpload::factory()->document()->create();

            expect($upload->mime_type)->toContain('wordprocessingml')
                ->and($upload->original_name)->toEndWith('.docx');
        });

        it('creates an upload with specific size', function (): void {
            $upload = FormUpload::factory()->size(2048)->create();

            expect($upload->size)->toBe(2048);
        });
    });

    describe('relationships', function (): void {
        it('belongs to a submission', function (): void {
            $submission = FormSubmission::factory()->create();
            $upload = FormUpload::factory()->for($submission, 'submission')->create();

            expect($upload->submission->id)->toBe($submission->id);
        });

        it('belongs to a field', function (): void {
            $field = FormField::factory()->file()->create();
            $upload = FormUpload::factory()->state(['field_id' => $field->id])->create();

            expect($upload->field->id)->toBe($field->id);
        });
    });

    describe('accessors', function (): void {
        it('returns human-readable size in bytes', function (): void {
            $upload = FormUpload::factory()->size(500)->create();

            expect($upload->human_size)->toBe('500 B');
        });

        it('returns human-readable size in KB', function (): void {
            $upload = FormUpload::factory()->size(2048)->create();

            expect($upload->human_size)->toBe('2 KB');
        });

        it('returns human-readable size in MB', function (): void {
            $upload = FormUpload::factory()->size(2097152)->create();

            expect($upload->human_size)->toBe('2 MB');
        });

        it('returns file extension', function (): void {
            $upload = FormUpload::factory()->create(['original_name' => 'document.pdf']);

            expect($upload->extension)->toBe('pdf');
        });

        it('detects image files', function (): void {
            $image = FormUpload::factory()->image()->create();
            $pdf = FormUpload::factory()->pdf()->create();

            expect($image->is_image)->toBeTrue()
                ->and($pdf->is_image)->toBeFalse();
        });

        it('handles null mime type', function (): void {
            $upload = FormUpload::factory()->create(['mime_type' => null]);

            expect($upload->is_image)->toBeFalse();
        });
    });
});
