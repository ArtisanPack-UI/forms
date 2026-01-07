<?php

declare(strict_types=1);

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormSubmission;
use Tests\Models\User;

describe('Authorization Policies', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create();
    });

    describe('FormPolicy', function (): void {
        it('allows authenticated users to view forms list', function (): void {
            $this->actingAs($this->user)
                ->get(route('forms.index'))
                ->assertSuccessful();
        });

        it('denies unauthenticated users from viewing forms list', function (): void {
            $this->get(route('forms.index'))
                ->assertRedirect(route('login'));
        });

        it('allows authenticated users to create forms', function (): void {
            $this->actingAs($this->user)
                ->get(route('forms.create'))
                ->assertSuccessful();
        });

        it('allows authenticated users to edit forms', function (): void {
            $form = Form::factory()->create();

            $this->actingAs($this->user)
                ->get(route('forms.edit', $form))
                ->assertSuccessful();
        });

        it('allows authenticated users to delete forms', function (): void {
            $form = Form::factory()->create();

            $this->actingAs($this->user)
                ->delete(route('forms.destroy', $form))
                ->assertRedirect(route('forms.index'));

            $this->assertDatabaseMissing('forms', ['id' => $form->id]);
        });
    });

    describe('SubmissionPolicy', function (): void {
        it('allows authenticated users to view submissions list', function (): void {
            $form = Form::factory()->create();

            $this->actingAs($this->user)
                ->get(route('forms.submissions.index', $form))
                ->assertSuccessful();
        });

        it('allows authenticated users to view all submissions', function (): void {
            $this->actingAs($this->user)
                ->get(route('forms.submissions.all'))
                ->assertSuccessful();
        });

        it('allows authenticated users to view a submission', function (): void {
            $form = Form::factory()->create();
            $submission = FormSubmission::factory()->for($form)->create();

            $this->actingAs($this->user)
                ->get(route('forms.submissions.show', [$form, $submission]))
                ->assertSuccessful();
        });

        it('returns 404 when submission does not belong to form', function (): void {
            $form1 = Form::factory()->create();
            $form2 = Form::factory()->create();
            $submission = FormSubmission::factory()->for($form2)->create();

            $this->actingAs($this->user)
                ->get(route('forms.submissions.show', [$form1, $submission]))
                ->assertNotFound();
        });

        it('allows authenticated users to export submissions', function (): void {
            $form = Form::factory()->create();

            $this->actingAs($this->user)
                ->get(route('forms.submissions.export', $form))
                ->assertSuccessful()
                ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        });
    });

    describe('Ownership Enforcement', function (): void {
        beforeEach(function (): void {
            // Enable ownership restriction for these tests
            config(['artisanpack.forms.authorization.restrict_by_owner' => true]);
        });

        it('allows owner to edit their own form', function (): void {
            $form = Form::factory()->create(['user_id' => $this->user->id]);

            $this->actingAs($this->user)
                ->get(route('forms.edit', $form))
                ->assertSuccessful();
        });

        it('denies non-owner from editing form when ownership is enforced', function (): void {
            $owner = User::factory()->create();
            $form = Form::factory()->create(['user_id' => $owner->id]);

            $this->actingAs($this->user)
                ->get(route('forms.edit', $form))
                ->assertForbidden();
        });

        it('allows owner to delete their own form', function (): void {
            $form = Form::factory()->create(['user_id' => $this->user->id]);

            $this->actingAs($this->user)
                ->delete(route('forms.destroy', $form))
                ->assertRedirect(route('forms.index'));

            $this->assertDatabaseMissing('forms', ['id' => $form->id]);
        });

        it('denies non-owner from deleting form when ownership is enforced', function (): void {
            $owner = User::factory()->create();
            $form = Form::factory()->create(['user_id' => $owner->id]);

            $this->actingAs($this->user)
                ->delete(route('forms.destroy', $form))
                ->assertForbidden();

            $this->assertDatabaseHas('forms', ['id' => $form->id]);
        });

        it('allows owner to view submissions for their form', function (): void {
            $form = Form::factory()->create(['user_id' => $this->user->id]);

            $this->actingAs($this->user)
                ->get(route('forms.submissions.index', $form))
                ->assertSuccessful();
        });

        it('denies non-owner from viewing submissions when ownership is enforced', function (): void {
            $owner = User::factory()->create();
            $form = Form::factory()->create(['user_id' => $owner->id]);

            $this->actingAs($this->user)
                ->get(route('forms.submissions.index', $form))
                ->assertForbidden();
        });

        it('allows access to forms without owners (legacy forms)', function (): void {
            $form = Form::factory()->create(['user_id' => null]);

            $this->actingAs($this->user)
                ->get(route('forms.edit', $form))
                ->assertSuccessful();
        });

        it('allows admin to bypass ownership checks', function (): void {
            config(['artisanpack.forms.authorization.allow_admin_bypass' => true]);

            $owner = User::factory()->create();
            $admin = User::factory()->create(['is_admin' => true]);
            $form = Form::factory()->create(['user_id' => $owner->id]);

            $this->actingAs($admin)
                ->get(route('forms.edit', $form))
                ->assertSuccessful();
        });

        it('denies admin when admin bypass is disabled', function (): void {
            config(['artisanpack.forms.authorization.allow_admin_bypass' => false]);

            $owner = User::factory()->create();
            $admin = User::factory()->create(['is_admin' => true]);
            $form = Form::factory()->create(['user_id' => $owner->id]);

            $this->actingAs($admin)
                ->get(route('forms.edit', $form))
                ->assertForbidden();
        });
    });
});
