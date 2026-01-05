<?php

declare(strict_types=1);

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Models\FormStep;
use ArtisanPackUI\Forms\Services\FormService;

describe('FormService', function (): void {
    beforeEach(function (): void {
        $this->service = app(FormService::class);
    });

    describe('create', function (): void {
        it('creates a form with valid data', function (): void {
            $form = $this->service->create([
                'name' => 'Contact Form',
                'description' => 'A simple contact form',
            ]);

            expect($form)->toBeInstanceOf(Form::class)
                ->and($form->name)->toBe('Contact Form')
                ->and($form->description)->toBe('A simple contact form')
                ->and($form->slug)->toBe('contact-form')
                ->and($form->fresh()->is_active)->toBeTrue(); // Default is true from migration
        });

        it('auto-generates unique slugs', function (): void {
            $form1 = $this->service->create(['name' => 'Test Form']);
            $form2 = $this->service->create(['name' => 'Test Form']);

            expect($form1->slug)->toBe('test-form')
                ->and($form2->slug)->toBe('test-form-1');
        });
    });

    describe('update', function (): void {
        it('updates a form with valid data', function (): void {
            $form = Form::factory()->create(['name' => 'Original Name']);

            $updated = $this->service->update($form, [
                'name' => 'Updated Name',
                'description' => 'New description',
            ]);

            expect($updated->name)->toBe('Updated Name')
                ->and($updated->description)->toBe('New description');
        });

        it('preserves unchanged fields', function (): void {
            $form = Form::factory()->create([
                'name' => 'Test',
                'description' => 'Original description',
            ]);

            $updated = $this->service->update($form, ['name' => 'New Name']);

            expect($updated->name)->toBe('New Name')
                ->and($updated->description)->toBe('Original description');
        });
    });

    describe('delete', function (): void {
        it('deletes a form', function (): void {
            $form = Form::factory()->create();
            $formId = $form->id;

            $result = $this->service->delete($form);

            expect($result)->toBeTrue()
                ->and(Form::find($formId))->toBeNull();
        });

        it('cascades delete to related fields', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->count(3)->for($form)->create();

            $this->service->delete($form);

            expect(FormField::where('form_id', $form->id)->count())->toBe(0);
        });
    });

    describe('duplicate', function (): void {
        it('duplicates a form', function (): void {
            $form = Form::factory()->create(['name' => 'Original Form']);

            $clone = $this->service->duplicate($form);

            expect($clone->name)->toBe('Original Form (Copy)')
                ->and($clone->slug)->not->toBe($form->slug)
                ->and($clone->is_active)->toBeFalse()
                ->and($clone->id)->not->toBe($form->id);
        });

        it('duplicates form with fields', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->count(3)->for($form)->create();

            $clone = $this->service->duplicate($form);

            expect($clone->fields()->count())->toBe(3);
        });

        it('duplicates form with steps and their fields', function (): void {
            $form = Form::factory()->multiStep()->create();
            $step1 = FormStep::factory()->for($form)->create(['sort_order' => 1]);
            $step2 = FormStep::factory()->for($form)->create(['sort_order' => 2]);
            FormField::factory()->count(2)->for($form)->for($step1, 'step')->create();
            FormField::factory()->count(3)->for($form)->for($step2, 'step')->create();

            $clone = $this->service->duplicate($form);

            expect($clone->steps()->count())->toBe(2)
                ->and($clone->fields()->count())->toBe(5);
        });

        it('duplicates form with notifications', function (): void {
            $form = Form::factory()->create();
            FormNotification::factory()->count(2)->for($form)->create();

            $clone = $this->service->duplicate($form);

            expect($clone->notifications()->count())->toBe(2);
        });
    });

    describe('publish', function (): void {
        it('publishes an inactive form', function (): void {
            $form = Form::factory()->inactive()->create();

            $this->service->publish($form);

            expect($form->fresh()->is_active)->toBeTrue();
        });
    });

    describe('unpublish', function (): void {
        it('unpublishes an active form', function (): void {
            $form = Form::factory()->create(['is_active' => true]);

            $this->service->unpublish($form);

            expect($form->fresh()->is_active)->toBeFalse();
        });
    });

    describe('togglePublish', function (): void {
        it('toggles inactive to active', function (): void {
            $form = Form::factory()->inactive()->create();

            $this->service->togglePublish($form);

            expect($form->fresh()->is_active)->toBeTrue();
        });

        it('toggles active to inactive', function (): void {
            $form = Form::factory()->create(['is_active' => true]);

            $this->service->togglePublish($form);

            expect($form->fresh()->is_active)->toBeFalse();
        });
    });

    describe('getBySlug', function (): void {
        it('finds form by slug', function (): void {
            $form = Form::factory()->create(['slug' => 'my-form']);

            $found = $this->service->getBySlug('my-form');

            expect($found)->toBeInstanceOf(Form::class)
                ->and($found->id)->toBe($form->id);
        });

        it('returns null for non-existent slug', function (): void {
            $found = $this->service->getBySlug('non-existent');

            expect($found)->toBeNull();
        });
    });

    describe('getById', function (): void {
        it('finds form by id', function (): void {
            $form = Form::factory()->create();

            $found = $this->service->getById($form->id);

            expect($found)->toBeInstanceOf(Form::class)
                ->and($found->id)->toBe($form->id);
        });

        it('returns null for non-existent id', function (): void {
            $found = $this->service->getById(99999);

            expect($found)->toBeNull();
        });
    });
});
