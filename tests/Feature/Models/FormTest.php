<?php

declare(strict_types=1);

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Models\FormStep;
use ArtisanPackUI\Forms\Models\FormSubmission;

describe('Form Model', function (): void {
    describe('factory', function (): void {
        it('creates a valid form', function (): void {
            $form = Form::factory()->create();

            expect($form)->toBeInstanceOf(Form::class)
                ->and($form->name)->not->toBeEmpty()
                ->and($form->slug)->not->toBeEmpty()
                ->and($form->is_active)->toBeTrue();
        });

        it('creates an inactive form', function (): void {
            $form = Form::factory()->inactive()->create();

            expect($form->is_active)->toBeFalse();
        });

        it('creates a multi-step form', function (): void {
            $form = Form::factory()->multiStep()->create();

            expect($form->is_multi_step)->toBeTrue()
                ->and($form->show_progress_bar)->toBeTrue();
        });
    });

    describe('boot', function (): void {
        it('auto-generates slug from name', function (): void {
            $form = Form::factory()->create(['name' => 'Contact Form', 'slug' => null]);

            expect($form->slug)->toBe('contact-form');
        });

        it('generates unique slugs', function (): void {
            Form::factory()->create(['name' => 'Contact Form', 'slug' => 'contact-form']);
            $form2 = Form::factory()->create(['name' => 'Contact Form', 'slug' => null]);

            expect($form2->slug)->toBe('contact-form-1');
        });
    });

    describe('relationships', function (): void {
        it('has many fields', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->count(3)->for($form)->create();

            expect($form->fields)->toHaveCount(3);
        });

        it('has many steps', function (): void {
            $form = Form::factory()->multiStep()->create();
            FormStep::factory()->count(2)->for($form)->create();

            expect($form->steps)->toHaveCount(2);
        });

        it('has many submissions', function (): void {
            $form = Form::factory()->create();
            FormSubmission::factory()->count(2)->for($form)->create();

            expect($form->submissions)->toHaveCount(2);
        });

        it('has many notifications', function (): void {
            $form = Form::factory()->create();
            FormNotification::factory()->count(2)->for($form)->create();

            expect($form->notifications)->toHaveCount(2);
        });
    });

    describe('scopes', function (): void {
        it('filters active forms', function (): void {
            Form::factory()->create(['is_active' => true]);
            Form::factory()->create(['is_active' => false]);

            expect(Form::active()->count())->toBe(1);
        });

        it('filters multi-step forms', function (): void {
            Form::factory()->create(['is_multi_step' => true]);
            Form::factory()->create(['is_multi_step' => false]);

            expect(Form::multiStep()->count())->toBe(1);
        });

        it('filters single-step forms', function (): void {
            Form::factory()->create(['is_multi_step' => true]);
            Form::factory()->create(['is_multi_step' => false]);

            expect(Form::singleStep()->count())->toBe(1);
        });
    });

    describe('accessors', function (): void {
        it('counts unread submissions', function (): void {
            $form = Form::factory()->create();
            FormSubmission::factory()->for($form)->create(['is_read' => false]);
            FormSubmission::factory()->for($form)->create(['is_read' => true]);

            expect($form->unread_submissions_count)->toBe(1);
        });

        it('counts total submissions', function (): void {
            $form = Form::factory()->create();
            FormSubmission::factory()->count(3)->for($form)->create();

            expect($form->total_submissions_count)->toBe(3);
        });

        it('gets fields ordered for single-step form', function (): void {
            $form = Form::factory()->create(['is_multi_step' => false]);
            FormField::factory()->for($form)->create(['sort_order' => 2]);
            FormField::factory()->for($form)->create(['sort_order' => 1]);

            $ordered = $form->fields_ordered;

            expect($ordered->first()->sort_order)->toBe(1)
                ->and($ordered->last()->sort_order)->toBe(2);
        });
    });

    describe('methods', function (): void {
        it('gets a setting value', function (): void {
            $form = Form::factory()->create([
                'settings' => ['color' => 'blue', 'nested' => ['key' => 'value']],
            ]);

            expect($form->getSetting('color'))->toBe('blue')
                ->and($form->getSetting('nested.key'))->toBe('value')
                ->and($form->getSetting('missing', 'default'))->toBe('default');
        });

        it('duplicates a form', function (): void {
            $form = Form::factory()->create(['name' => 'Original Form']);
            FormField::factory()->for($form)->create();
            FormNotification::factory()->for($form)->create();

            $clone = $form->duplicate();

            expect($clone->name)->toBe('Original Form (Copy)')
                ->and($clone->is_active)->toBeFalse()
                ->and($clone->fields)->toHaveCount(1)
                ->and($clone->notifications)->toHaveCount(1)
                ->and($clone->fields->first()->uuid)->not->toBe($form->fields->first()->uuid);
        });

        it('uses slug as route key name', function (): void {
            $form = new Form;

            expect($form->getRouteKeyName())->toBe('slug');
        });
    });
});
