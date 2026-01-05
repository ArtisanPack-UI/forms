<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Services;

use ArtisanPackUI\Forms\Models\Form;
use Illuminate\Support\Facades\DB;

/**
 * FormService
 *
 * Business logic layer for form CRUD operations. Handles creating,
 * updating, deleting, and duplicating forms with their related data.
 *
 * @since 1.0.0
 */
class FormService
{
    /**
     * Create a new form.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Form
    {
        return Form::create($data);
    }

    /**
     * Update an existing form.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Form $form, array $data): Form
    {
        $form->update($data);

        return $form->fresh();
    }

    /**
     * Delete a form and all its related data.
     *
     * Related data (fields, steps, submissions, notifications) are handled
     * by database cascading deletes, but we wrap this in a transaction
     * for safety.
     */
    public function delete(Form $form): bool
    {
        return DB::transaction(function () use ($form): bool {
            return (bool) $form->delete();
        });
    }

    /**
     * Duplicate a form with all its steps, fields, and notifications.
     *
     * Creates a copy of the form with "(Copy)" appended to the name,
     * a new unique slug, and is_active set to false.
     */
    public function duplicate(Form $form): Form
    {
        return DB::transaction(function () use ($form): Form {
            return $form->duplicate();
        });
    }

    /**
     * Publish a form (set as active).
     */
    public function publish(Form $form): void
    {
        $form->update(['is_active' => true]);
    }

    /**
     * Unpublish a form (set as inactive).
     */
    public function unpublish(Form $form): void
    {
        $form->update(['is_active' => false]);
    }

    /**
     * Toggle the published status of a form.
     */
    public function togglePublish(Form $form): void
    {
        $form->update(['is_active' => ! $form->is_active]);
    }

    /**
     * Find a form by its slug.
     */
    public function getBySlug(string $slug): ?Form
    {
        return Form::where('slug', $slug)->first();
    }

    /**
     * Find a form by its ID.
     */
    public function getById(int $id): ?Form
    {
        return Form::find($id);
    }
}
