<?php

declare(strict_types=1);

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Services\SubmissionService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->service = app(SubmissionService::class);
    Storage::fake('local');
});

describe('SubmissionService', function (): void {
    describe('create submission', function (): void {
        it('creates a submission with form data', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for($form)->create([
                'name' => 'name',
                'label' => 'Name',
                'type' => 'text',
            ]);

            $submission = $this->service->create($form, [
                'name' => 'John Doe',
            ]);

            expect($submission)->toBeInstanceOf(FormSubmission::class)
                ->and($submission->form_id)->toBe($form->id)
                ->and($submission->getValue('name'))->toBe('John Doe');
        });

        it('creates submission values for each field', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for($form)->create(['name' => 'name', 'label' => 'Name', 'type' => 'text']);
            FormField::factory()->for($form)->create(['name' => 'email', 'label' => 'Email', 'type' => 'email']);

            $submission = $this->service->create($form, [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ]);

            expect($submission->values)->toHaveCount(2)
                ->and($submission->getValue('name'))->toBe('John Doe')
                ->and($submission->getValue('email'))->toBe('john@example.com');
        });

        it('stores metadata with submission', function (): void {
            $form = Form::factory()->create();

            $submission = $this->service->create($form, [], [], [
                'page_url' => 'https://example.com/contact',
                'referrer_url' => 'https://google.com',
                'ip_address' => '192.168.1.100',
                'user_agent' => 'Mozilla/5.0',
            ]);

            expect($submission->page_url)->toBe('https://example.com/contact')
                ->and($submission->referrer_url)->toBe('https://google.com')
                ->and($submission->ip_address)->toBe('192.168.1.100')
                ->and($submission->user_agent)->toBe('Mozilla/5.0');
        });

        it('handles array values for checkbox groups', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for($form)->create([
                'name' => 'interests',
                'label' => 'Interests',
                'type' => 'checkbox_group',
            ]);

            $submission = $this->service->create($form, [
                'interests' => ['sports', 'music', 'reading'],
            ]);

            $value = FormSubmissionValue::where('submission_id', $submission->id)
                ->where('field_name', 'interests')
                ->first();

            expect($value->value_array)->toBe(['sports', 'music', 'reading']);
        });

        it('ignores fields not in form', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for($form)->create(['name' => 'name', 'type' => 'text']);

            $submission = $this->service->create($form, [
                'name' => 'John Doe',
                'unknown_field' => 'should be ignored',
            ]);

            expect($submission->values)->toHaveCount(1);
        });
    });

    describe('file uploads', function (): void {
        it('handles file upload', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for($form)->file()->create(['name' => 'document']);

            $file = UploadedFile::fake()->create('test.pdf', 100);

            $submission = $this->service->create($form, [], [
                'document' => $file,
            ]);

            expect($submission->uploads)->toHaveCount(1);

            $upload = $submission->uploads->first();
            expect($upload->original_name)->toBe('test.pdf')
                ->and($upload->mime_type)->toBe('application/pdf');
        });

        it('stores file on configured disk', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for($form)->file()->create(['name' => 'document']);

            $file = UploadedFile::fake()->create('test.pdf', 100);

            $submission = $this->service->create($form, [], [
                'document' => $file,
            ]);

            $upload = $submission->uploads->first();
            expect(Storage::disk($upload->disk)->exists($upload->path))->toBeTrue();
        });

        it('creates submission value linking to upload', function (): void {
            $form = Form::factory()->create();
            $field = FormField::factory()->for($form)->file()->create(['name' => 'document']);

            $file = UploadedFile::fake()->create('test.pdf', 100);

            $submission = $this->service->create($form, [], [
                'document' => $file,
            ]);

            $value = FormSubmissionValue::where('submission_id', $submission->id)
                ->where('field_id', $field->id)
                ->first();

            expect($value->upload_id)->not->toBeNull();
        });

        it('validates file extension against allowlist', function (): void {
            config(['artisanpack.forms.uploads.allowed_extensions' => ['pdf', 'doc']]);

            $form = Form::factory()->create();
            FormField::factory()->for($form)->file()->create(['name' => 'document']);

            $file = UploadedFile::fake()->create('test.exe', 100);

            expect(fn () => $this->service->create($form, [], ['document' => $file]))
                ->toThrow(InvalidArgumentException::class);
        });

        it('allows file when extension is in allowlist', function (): void {
            config(['artisanpack.forms.uploads.allowed_extensions' => ['pdf', 'doc']]);

            $form = Form::factory()->create();
            FormField::factory()->for($form)->file()->create(['name' => 'document']);

            $file = UploadedFile::fake()->create('test.pdf', 100);

            $submission = $this->service->create($form, [], ['document' => $file]);

            expect($submission->uploads)->toHaveCount(1);
        });

        it('skips non-file field types for file uploads', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for($form)->text()->create(['name' => 'name']);

            $file = UploadedFile::fake()->create('test.pdf', 100);

            $submission = $this->service->create($form, ['name' => 'Test'], [
                'name' => $file, // Passing file to non-file field
            ]);

            expect($submission->uploads)->toHaveCount(0);
        });
    });

    describe('spam detection', function (): void {
        it('detects spam when honeypot is filled', function (): void {
            expect($this->service->isSpam('spam-value', null))->toBeTrue()
                ->and($this->service->isSpam('', null))->toBeFalse()
                ->and($this->service->isSpam(null, null))->toBeFalse();
        });

        it('detects spam when submission is too fast', function (): void {
            $now = time();

            // 1 second ago - too fast
            expect($this->service->isSpam('', $now - 1))->toBeTrue();

            // 2 seconds ago - still too fast (< 3 seconds)
            expect($this->service->isSpam('', $now - 2))->toBeTrue();

            // 3 seconds ago - borderline, should pass (>= 3 seconds passes)
            expect($this->service->isSpam('', $now - 3))->toBeFalse();

            // 10 seconds ago - definitely ok
            expect($this->service->isSpam('', $now - 10))->toBeFalse();
        });

        it('allows submission when both checks pass', function (): void {
            $now = time();

            expect($this->service->isSpam('', $now - 10))->toBeFalse();
        });

        it('detects spam when both honeypot and time fail', function (): void {
            $now = time();

            expect($this->service->isSpam('bot-filled', $now))->toBeTrue();
        });

        it('handles null formLoadedAt', function (): void {
            // When formLoadedAt is null, only honeypot is checked
            expect($this->service->isSpam('', null))->toBeFalse()
                ->and($this->service->isSpam('spam', null))->toBeTrue();
        });
    });

    describe('rate limiting', function (): void {
        beforeEach(function (): void {
            RateLimiter::clear('form-submission:1:192.168.1.1');
        });

        it('is not rate limited initially', function (): void {
            expect($this->service->isRateLimited('192.168.1.1', 1))->toBeFalse();
        });

        it('records submission attempts', function (): void {
            $this->service->recordAttempt('192.168.1.1', 1);
            $this->service->recordAttempt('192.168.1.1', 1);

            // Still under limit
            expect($this->service->isRateLimited('192.168.1.1', 1))->toBeFalse();
        });

        it('rate limits after max attempts', function (): void {
            // Record 5 attempts (the limit)
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordAttempt('192.168.1.1', 1);
            }

            // 6th attempt should be rate limited
            expect($this->service->isRateLimited('192.168.1.1', 1))->toBeTrue();
        });

        it('rate limits per IP and form', function (): void {
            // Max out one IP/form combo
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordAttempt('192.168.1.1', 1);
            }

            expect($this->service->isRateLimited('192.168.1.1', 1))->toBeTrue();

            // Different IP should not be limited
            expect($this->service->isRateLimited('192.168.1.2', 1))->toBeFalse();

            // Different form should not be limited
            expect($this->service->isRateLimited('192.168.1.1', 2))->toBeFalse();
        });
    });

    describe('validation rules', function (): void {
        it('builds validation rules for required fields', function (): void {
            $form = Form::factory()->create();
            $field = FormField::factory()->for($form)->required()->create([
                'name' => 'name',
                'type' => 'text',
            ]);

            $rules = $this->service->buildValidationRules($form->fields);

            expect($rules)->toHaveKey('formData.name')
                ->and($rules['formData.name'])->toContain('required');
        });

        it('excludes hidden fields from validation', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for($form)->required()->create(['name' => 'visible', 'type' => 'text']);
            FormField::factory()->for($form)->required()->create(['name' => 'hidden', 'type' => 'text']);

            $hiddenFields = ['hidden' => true];

            $rules = $this->service->buildValidationRules($form->fields, $hiddenFields);

            expect($rules)->toHaveKey('formData.visible')
                ->and($rules)->not->toHaveKey('formData.hidden');
        });

        it('excludes layout fields from validation', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for($form)->create(['name' => 'text_field', 'type' => 'text']);
            FormField::factory()->for($form)->create(['name' => 'heading', 'type' => 'heading']);
            FormField::factory()->for($form)->create(['name' => 'paragraph', 'type' => 'paragraph']);
            FormField::factory()->for($form)->create(['name' => 'divider', 'type' => 'divider']);

            $rules = $this->service->buildValidationRules($form->fields);

            expect($rules)->toHaveKey('formData.text_field')
                ->and($rules)->not->toHaveKey('formData.heading')
                ->and($rules)->not->toHaveKey('formData.paragraph')
                ->and($rules)->not->toHaveKey('formData.divider');
        });

        it('includes type-specific validation rules', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for($form)->email()->create();

            $rules = $this->service->buildValidationRules($form->fields);

            expect($rules['formData.email'])->toContain('email');
        });
    });

    describe('validation messages', function (): void {
        it('builds custom messages for required fields', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for($form)->required()->create([
                'name' => 'name',
                'label' => 'Full Name',
                'type' => 'text',
            ]);

            $messages = $this->service->buildValidationMessages($form->fields);

            expect($messages)->toHaveKey('formData.name.required')
                ->and($messages['formData.name.required'])->toContain('Full Name');
        });

        it('builds custom messages for email fields', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for($form)->email()->create();

            $messages = $this->service->buildValidationMessages($form->fields);

            expect($messages)->toHaveKey('formData.email.email');
        });

        it('builds custom messages for file fields', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for($form)->file()->create(['name' => 'resume', 'label' => 'Resume']);

            $messages = $this->service->buildValidationMessages($form->fields);

            expect($messages)->toHaveKey('formData.resume.file')
                ->and($messages)->toHaveKey('formData.resume.mimes')
                ->and($messages)->toHaveKey('formData.resume.max');
        });

        it('excludes layout fields from messages', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for($form)->create(['name' => 'heading', 'type' => 'heading']);

            $messages = $this->service->buildValidationMessages($form->fields);

            expect($messages)->toBeEmpty();
        });
    });

    describe('request metadata', function (): void {
        it('extracts metadata from request', function (): void {
            $request = Request::create('/contact', 'POST', [], [], [], [
                'REMOTE_ADDR' => '192.168.1.100',
                'HTTP_USER_AGENT' => 'TestBrowser/1.0',
                'HTTP_REFERER' => 'https://google.com',
            ]);

            $metadata = $this->service->getRequestMetadata($request);

            expect($metadata)->toHaveKey('page_url')
                ->and($metadata)->toHaveKey('referrer_url')
                ->and($metadata)->toHaveKey('ip_address')
                ->and($metadata)->toHaveKey('user_agent')
                ->and($metadata['referrer_url'])->toBe('https://google.com')
                ->and($metadata['user_agent'])->toBe('TestBrowser/1.0');
        });
    });

    describe('filter hooks', function (): void {
        it('applies ap.forms.submissionData filter', function (): void {
            if (! function_exists('addFilter')) {
                $this->markTestSkipped('Hook system not available');
            }

            addFilter('ap.forms.submissionData', function (array $data, Form $form): array {
                $data['injected_field'] = 'hook_value';

                return $data;
            });

            $form = Form::factory()->create();
            FormField::factory()->for($form)->create(['name' => 'name', 'type' => 'text']);
            FormField::factory()->for($form)->create(['name' => 'injected_field', 'type' => 'text']);

            $submission = $this->service->create($form, ['name' => 'John']);

            expect($submission->getValue('injected_field'))->toBe('hook_value');
        });
    });
});

afterEach(function (): void {
    // Clean up rate limiter
    RateLimiter::clear('form-submission:1:192.168.1.1');
    RateLimiter::clear('form-submission:1:192.168.1.2');
    RateLimiter::clear('form-submission:2:192.168.1.1');

    if (function_exists('removeAllFilters')) {
        removeAllFilters('ap.forms.submissionData');
    }
});
