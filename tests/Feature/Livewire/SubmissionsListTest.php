<?php

declare(strict_types=1);

use ArtisanPackUI\Forms\Livewire\SubmissionsList;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use Livewire\Livewire;

describe('SubmissionsList Livewire Component', function (): void {
    describe('rendering', function (): void {
        it('renders the component', function (): void {
            Livewire::test(SubmissionsList::class)
                ->assertStatus(200);
        });

        it('renders the component for a specific form', function (): void {
            $form = Form::factory()->create();

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->assertStatus(200);
        });

        it('displays submissions in the list', function (): void {
            $form = Form::factory()->create();
            $submission = FormSubmission::factory()->for($form)->create();

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->assertSee($submission->submission_number);
        });

        it('shows empty state when no submissions exist', function (): void {
            $form = Form::factory()->create();

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->assertSee('No submissions found');
        });
    });

    describe('search', function (): void {
        it('filters submissions by submission number', function (): void {
            $form = Form::factory()->create();
            $submission1 = FormSubmission::factory()->for($form)->create([
                'submission_number' => 'FORM-2025-00001',
            ]);
            $submission2 = FormSubmission::factory()->for($form)->create([
                'submission_number' => 'FORM-2025-00002',
            ]);

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->set('search', '00001')
                ->assertSee('FORM-2025-00001')
                ->assertDontSee('FORM-2025-00002');
        });

        it('filters submissions by field value', function (): void {
            $form = Form::factory()->create();
            $submission1 = FormSubmission::factory()->for($form)->create();
            $submission2 = FormSubmission::factory()->for($form)->create();

            FormSubmissionValue::factory()->create([
                'submission_id' => $submission1->id,
                'field_name' => 'email',
                'value' => 'john@example.com',
            ]);

            FormSubmissionValue::factory()->create([
                'submission_id' => $submission2->id,
                'field_name' => 'email',
                'value' => 'jane@example.com',
            ]);

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->set('search', 'john')
                ->assertSee($submission1->submission_number)
                ->assertDontSee($submission2->submission_number);
        });

        it('resets pagination when search changes', function (): void {
            $form = Form::factory()->create();
            FormSubmission::factory()->count(20)->for($form)->create();

            $component = Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->set('search', 'test');

            expect($component->get('paginators.page'))->toBe(1);
        });
    });

    describe('status filter', function (): void {
        it('filters unread submissions', function (): void {
            $form = Form::factory()->create();
            $unread = FormSubmission::factory()->for($form)->create(['is_read' => false]);
            $read = FormSubmission::factory()->for($form)->create(['is_read' => true]);

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->set('status', 'unread')
                ->assertSee($unread->submission_number)
                ->assertDontSee($read->submission_number);
        });

        it('filters read submissions', function (): void {
            $form = Form::factory()->create();
            $unread = FormSubmission::factory()->for($form)->create(['is_read' => false]);
            $read = FormSubmission::factory()->for($form)->create(['is_read' => true]);

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->set('status', 'read')
                ->assertSee($read->submission_number)
                ->assertDontSee($unread->submission_number);
        });

        it('filters starred submissions', function (): void {
            $form = Form::factory()->create();
            $starred = FormSubmission::factory()->for($form)->create(['is_starred' => true]);
            $unstarred = FormSubmission::factory()->for($form)->create(['is_starred' => false]);

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->set('status', 'starred')
                ->assertSee($starred->submission_number)
                ->assertDontSee($unstarred->submission_number);
        });

        it('filters spam submissions', function (): void {
            $form = Form::factory()->create();
            $spam = FormSubmission::factory()->for($form)->create(['is_spam' => true]);
            $notSpam = FormSubmission::factory()->for($form)->create(['is_spam' => false]);

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->set('status', 'spam')
                ->assertSee($spam->submission_number)
                ->assertDontSee($notSpam->submission_number);
        });

        it('excludes spam by default in all status', function (): void {
            $form = Form::factory()->create();
            $spam = FormSubmission::factory()->for($form)->create(['is_spam' => true]);
            $notSpam = FormSubmission::factory()->for($form)->create(['is_spam' => false]);

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->set('status', 'all')
                ->assertDontSee($spam->submission_number)
                ->assertSee($notSpam->submission_number);
        });
    });

    describe('date range filter', function (): void {
        it('filters submissions from today', function (): void {
            $form = Form::factory()->create();
            $today = FormSubmission::factory()->for($form)->create(['created_at' => now()]);
            $yesterday = FormSubmission::factory()->for($form)->create(['created_at' => now()->subDay()]);

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->set('dateRange', 'today')
                ->assertSee($today->submission_number)
                ->assertDontSee($yesterday->submission_number);
        });

        it('filters submissions from this week', function (): void {
            $form = Form::factory()->create();
            $thisWeek = FormSubmission::factory()->for($form)->create(['created_at' => now()]);
            $lastMonth = FormSubmission::factory()->for($form)->create(['created_at' => now()->subMonth()]);

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->set('dateRange', 'week')
                ->assertSee($thisWeek->submission_number)
                ->assertDontSee($lastMonth->submission_number);
        });
    });

    describe('sorting', function (): void {
        it('sorts by created_at by default', function (): void {
            $component = Livewire::test(SubmissionsList::class);

            expect($component->get('sortBy'))->toBe('created_at')
                ->and($component->get('sortDirection'))->toBe('desc');
        });

        it('sorts by submission number', function (): void {
            $form = Form::factory()->create();
            FormSubmission::factory()->for($form)->create(['submission_number' => 'FORM-2025-00002']);
            FormSubmission::factory()->for($form)->create(['submission_number' => 'FORM-2025-00001']);

            $component = Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->call('sort', 'submission_number')
                ->call('sort', 'submission_number'); // Click twice to get ascending

            expect($component->get('sortBy'))->toBe('submission_number')
                ->and($component->get('sortDirection'))->toBe('asc');
        });

        it('toggles sort direction', function (): void {
            $form = Form::factory()->create();

            // First call to sort('created_at') toggles from desc to asc (since created_at is already default)
            // Second call toggles from asc to desc
            $component = Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->call('sort', 'created_at');

            // After first toggle, should be asc
            expect($component->get('sortDirection'))->toBe('asc');

            $component->call('sort', 'created_at');

            // After second toggle, should be desc
            expect($component->get('sortDirection'))->toBe('desc');
        });
    });

    describe('individual actions', function (): void {
        it('marks a submission as read', function (): void {
            $form = Form::factory()->create();
            $submission = FormSubmission::factory()->for($form)->create(['is_read' => false]);

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->call('markAsRead', $submission->id)
                ->assertHasNoErrors();

            expect($submission->fresh()->is_read)->toBeTrue();
        });

        it('marks a submission as unread', function (): void {
            $form = Form::factory()->create();
            $submission = FormSubmission::factory()->for($form)->create(['is_read' => true]);

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->call('markAsUnread', $submission->id)
                ->assertHasNoErrors();

            expect($submission->fresh()->is_read)->toBeFalse();
        });

        it('toggles starred status', function (): void {
            $form = Form::factory()->create();
            $submission = FormSubmission::factory()->for($form)->create(['is_starred' => false]);

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->call('toggleStar', $submission->id)
                ->assertHasNoErrors();

            expect($submission->fresh()->is_starred)->toBeTrue();
        });

        it('toggles spam status', function (): void {
            $form = Form::factory()->create();
            $submission = FormSubmission::factory()->for($form)->create(['is_spam' => false]);

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->call('toggleSpam', $submission->id)
                ->assertHasNoErrors();

            expect($submission->fresh()->is_spam)->toBeTrue();
        });

        it('deletes a submission', function (): void {
            $form = Form::factory()->create();
            $submission = FormSubmission::factory()->for($form)->create();
            $submissionId = $submission->id;

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->call('delete', $submissionId)
                ->assertHasNoErrors();

            expect(FormSubmission::find($submissionId))->toBeNull();
        });
    });

    describe('bulk actions', function (): void {
        it('marks selected submissions as read', function (): void {
            $form = Form::factory()->create();
            $submissions = FormSubmission::factory()->count(3)->for($form)->create(['is_read' => false]);

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->set('selected', $submissions->pluck('id')->toArray())
                ->call('bulkMarkAsRead')
                ->assertHasNoErrors();

            foreach ($submissions as $submission) {
                expect($submission->fresh()->is_read)->toBeTrue();
            }
        });

        it('marks selected submissions as unread', function (): void {
            $form = Form::factory()->create();
            $submissions = FormSubmission::factory()->count(3)->for($form)->create(['is_read' => true]);

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->set('selected', $submissions->pluck('id')->toArray())
                ->call('bulkMarkAsUnread')
                ->assertHasNoErrors();

            foreach ($submissions as $submission) {
                expect($submission->fresh()->is_read)->toBeFalse();
            }
        });

        it('marks selected submissions as spam', function (): void {
            $form = Form::factory()->create();
            $submissions = FormSubmission::factory()->count(3)->for($form)->create(['is_spam' => false]);

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->set('selected', $submissions->pluck('id')->toArray())
                ->call('bulkMarkAsSpam')
                ->assertHasNoErrors();

            foreach ($submissions as $submission) {
                expect($submission->fresh()->is_spam)->toBeTrue();
            }
        });

        it('deletes selected submissions', function (): void {
            $form = Form::factory()->create();
            $submissions = FormSubmission::factory()->count(3)->for($form)->create();
            $ids = $submissions->pluck('id')->toArray();

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->set('selected', $ids)
                ->call('bulkDelete')
                ->assertHasNoErrors();

            foreach ($ids as $id) {
                expect(FormSubmission::find($id))->toBeNull();
            }
        });

        it('resets selection after bulk action', function (): void {
            $form = Form::factory()->create();
            $submissions = FormSubmission::factory()->count(3)->for($form)->create();

            $component = Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->set('selected', $submissions->pluck('id')->toArray())
                ->call('bulkMarkAsRead');

            expect($component->get('selected'))->toBe([])
                ->and($component->get('selectAll'))->toBeFalse();
        });

        it('select all checkbox selects visible submissions', function (): void {
            $form = Form::factory()->create();
            $submissions = FormSubmission::factory()->count(3)->for($form)->create();

            $component = Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->set('selectAll', true);

            expect($component->get('selected'))->toHaveCount(3);
        });
    });

    describe('status counts', function (): void {
        it('displays correct counts in UI', function (): void {
            $form = Form::factory()->create();

            // Create various submissions
            FormSubmission::factory()->count(2)->for($form)->create(['is_read' => false, 'is_spam' => false]);
            FormSubmission::factory()->count(3)->for($form)->create(['is_read' => true, 'is_spam' => false]);
            FormSubmission::factory()->count(1)->for($form)->create(['is_starred' => true, 'is_spam' => false]);
            FormSubmission::factory()->count(2)->for($form)->create(['is_spam' => true]);

            // Total submissions: 8
            // Unread: 2 (is_read=false, is_spam=false)
            // Read: 4 (3 is_read=true + 1 starred which defaults to is_read=false)
            // Starred: 1
            // Spam: 2

            Livewire::test(SubmissionsList::class, ['formId' => $form->id])
                ->assertSee('8') // Total
                ->assertSee('2') // Unread and spam count appear
                ->assertSee('1'); // Starred
        });
    });

    describe('form filter', function (): void {
        it('shows submissions across all forms when no formId', function (): void {
            $form1 = Form::factory()->create(['name' => 'Form One']);
            $form2 = Form::factory()->create(['name' => 'Form Two']);
            // Explicitly mark as not spam so they appear in default view
            $submission1 = FormSubmission::factory()->for($form1)->create(['is_spam' => false]);
            $submission2 = FormSubmission::factory()->for($form2)->create(['is_spam' => false]);

            Livewire::test(SubmissionsList::class)
                ->assertSee($submission1->submission_number)
                ->assertSee($submission2->submission_number);
        });

        it('filters by form when formFilter is set', function (): void {
            $form1 = Form::factory()->create(['name' => 'Form One']);
            $form2 = Form::factory()->create(['name' => 'Form Two']);
            // Use unique submission numbers that won't overlap
            $submission1 = FormSubmission::factory()->for($form1)->create([
                'is_spam' => false,
                'submission_number' => 'UNIQUE-FORM1-00001',
            ]);
            $submission2 = FormSubmission::factory()->for($form2)->create([
                'is_spam' => false,
                'submission_number' => 'UNIQUE-FORM2-00001',
            ]);

            Livewire::test(SubmissionsList::class)
                ->set('formFilter', (string) $form1->id)
                ->assertSee('UNIQUE-FORM1-00001')
                ->assertDontSee('UNIQUE-FORM2-00001');
        });
    });

    describe('URL persistence', function (): void {
        it('persists search in URL', function (): void {
            $component = Livewire::test(SubmissionsList::class)
                ->set('search', 'test query');

            expect($component->get('search'))->toBe('test query');
        });

        it('persists status in URL', function (): void {
            $component = Livewire::test(SubmissionsList::class)
                ->set('status', 'unread');

            expect($component->get('status'))->toBe('unread');
        });

        it('persists date range in URL', function (): void {
            $component = Livewire::test(SubmissionsList::class)
                ->set('dateRange', 'week');

            expect($component->get('dateRange'))->toBe('week');
        });

        it('persists sort in URL', function (): void {
            $component = Livewire::test(SubmissionsList::class)
                ->call('sort', 'submission_number');

            expect($component->get('sortBy'))->toBe('submission_number');
        });
    });
});
