<?php

declare(strict_types=1);

use ArtisanPackUI\Forms\Livewire\FormsList;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use Livewire\Livewire;

describe('FormsList Livewire Component', function (): void {
    describe('rendering', function (): void {
        it('renders the component', function (): void {
            Livewire::test(FormsList::class)
                ->assertStatus(200);
        });

        it('displays forms in the list', function (): void {
            $form = Form::factory()->create(['name' => 'Test Form']);

            Livewire::test(FormsList::class)
                ->assertSee('Test Form');
        });

        it('shows empty state when no forms exist', function (): void {
            Livewire::test(FormsList::class)
                ->assertSee('No forms found');
        });
    });

    describe('search', function (): void {
        it('filters forms by name', function (): void {
            Form::factory()->create(['name' => 'Contact Form']);
            Form::factory()->create(['name' => 'Newsletter Form']);

            Livewire::test(FormsList::class)
                ->set('search', 'Contact')
                ->assertSee('Contact Form')
                ->assertDontSee('Newsletter Form');
        });

        it('filters forms by slug', function (): void {
            Form::factory()->create(['name' => 'Form One', 'slug' => 'form-one']);
            Form::factory()->create(['name' => 'Form Two', 'slug' => 'form-two']);

            Livewire::test(FormsList::class)
                ->set('search', 'form-one')
                ->assertSee('Form One')
                ->assertDontSee('Form Two');
        });

        it('resets pagination when search changes', function (): void {
            Form::factory()->count(20)->create();

            $component = Livewire::test(FormsList::class)
                ->set('search', 'test');

            expect($component->get('paginators.page'))->toBe(1);
        });
    });

    describe('status filter', function (): void {
        it('filters active forms', function (): void {
            Form::factory()->create(['name' => 'Active Form', 'is_active' => true]);
            Form::factory()->create(['name' => 'Inactive Form', 'is_active' => false]);

            Livewire::test(FormsList::class)
                ->set('statusFilter', 'active')
                ->assertSee('Active Form')
                ->assertDontSee('Inactive Form');
        });

        it('filters inactive forms', function (): void {
            Form::factory()->create(['name' => 'Active Form', 'is_active' => true]);
            Form::factory()->create(['name' => 'Inactive Form', 'is_active' => false]);

            Livewire::test(FormsList::class)
                ->set('statusFilter', 'inactive')
                ->assertSee('Inactive Form')
                ->assertDontSee('Active Form');
        });

        it('shows all forms when filter is all', function (): void {
            Form::factory()->create(['name' => 'Active Form', 'is_active' => true]);
            Form::factory()->create(['name' => 'Inactive Form', 'is_active' => false]);

            Livewire::test(FormsList::class)
                ->set('statusFilter', 'all')
                ->assertSee('Active Form')
                ->assertSee('Inactive Form');
        });
    });

    describe('sorting', function (): void {
        it('sorts by name ascending', function (): void {
            Form::factory()->create(['name' => 'Zebra Form']);
            Form::factory()->create(['name' => 'Alpha Form']);

            $component = Livewire::test(FormsList::class)
                ->call('sort', 'name')
                ->assertSeeInOrder(['Alpha Form', 'Zebra Form']);
        });

        it('toggles sort direction', function (): void {
            Form::factory()->create(['name' => 'Zebra Form']);
            Form::factory()->create(['name' => 'Alpha Form']);

            $component = Livewire::test(FormsList::class)
                ->call('sort', 'name')
                ->call('sort', 'name');

            expect($component->get('sortDirection'))->toBe('desc');
        });

        it('sorts by created_at by default', function (): void {
            $component = Livewire::test(FormsList::class);

            expect($component->get('sortBy'))->toBe('created_at')
                ->and($component->get('sortDirection'))->toBe('desc');
        });
    });

    describe('actions', function (): void {
        it('duplicates a form', function (): void {
            $form = Form::factory()->create(['name' => 'Original Form']);

            Livewire::test(FormsList::class)
                ->call('duplicate', $form->id)
                ->assertHasNoErrors();

            expect(Form::where('name', 'Original Form (Copy)')->exists())->toBeTrue();
        });

        it('deletes a form', function (): void {
            $form = Form::factory()->create();
            $formId = $form->id;

            Livewire::test(FormsList::class)
                ->call('delete', $formId)
                ->assertHasNoErrors();

            expect(Form::find($formId))->toBeNull();
        });

        it('toggles publish status', function (): void {
            $form = Form::factory()->inactive()->create();

            Livewire::test(FormsList::class)
                ->call('togglePublish', $form->id)
                ->assertHasNoErrors();

            expect($form->fresh()->is_active)->toBeTrue();
        });
    });

    describe('counts', function (): void {
        it('displays field count', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->count(5)->for($form)->create();

            Livewire::test(FormsList::class)
                ->assertSee('5');
        });

        it('displays submission count', function (): void {
            $form = Form::factory()->create();
            FormSubmission::factory()->count(10)->for($form)->create();

            Livewire::test(FormsList::class)
                ->assertSee('10');
        });

        it('displays unread submission badge', function (): void {
            $form = Form::factory()->create();
            FormSubmission::factory()->count(3)->for($form)->create(['is_read' => false]);

            Livewire::test(FormsList::class)
                ->assertSee('3 new');
        });
    });

    describe('URL persistence', function (): void {
        it('persists search in URL', function (): void {
            $component = Livewire::test(FormsList::class)
                ->set('search', 'test query');

            expect($component->get('search'))->toBe('test query');
        });

        it('persists sort in URL', function (): void {
            $component = Livewire::test(FormsList::class)
                ->call('sort', 'name');

            expect($component->get('sortBy'))->toBe('name');
        });

        it('persists status filter in URL', function (): void {
            $component = Livewire::test(FormsList::class)
                ->set('statusFilter', 'active');

            expect($component->get('statusFilter'))->toBe('active');
        });
    });
});
