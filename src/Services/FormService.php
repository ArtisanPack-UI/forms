<?php

/**
 * Form service.
 *
 * Business logic layer for form CRUD operations. Handles creating,
 * updating, deleting, and duplicating forms with their related data.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Services;

use ArtisanPackUI\Forms\Models\Form;
use Illuminate\Support\Facades\DB;

/**
 * Form service class.
 *
 * Business logic layer for form CRUD operations. Handles creating,
 * updating, deleting, and duplicating forms with their related data.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class FormService
{
    /**
     * Creates a new form.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $data The form data.
     *
     * @return Form The created form.
     */
    public function create( array $data ): Form
    {
        return Form::create( $data );
    }

    /**
     * Updates an existing form.
     *
     * @since 1.0.0
     *
     * @param Form                 $form The form to update.
     * @param array<string, mixed> $data The updated form data.
     *
     * @return Form The updated form.
     */
    public function update( Form $form, array $data ): Form
    {
        $form->update( $data );

        return $form->fresh();
    }

    /**
     * Deletes a form and all its related data.
     *
     * Related data (fields, steps, submissions, notifications) are handled
     * by database cascading deletes, but we wrap this in a transaction
     * for safety.
     *
     * @since 1.0.0
     *
     * @param Form $form The form to delete.
     *
     * @return bool True on success.
     */
    public function delete( Form $form ): bool
    {
        return DB::transaction( function () use ( $form ): bool {
            return (bool) $form->delete();
        } );
    }

    /**
     * Duplicates a form with all its steps, fields, and notifications.
     *
     * Creates a copy of the form with "(Copy)" appended to the name,
     * a new unique slug, and is_active set to false.
     *
     * @since 1.0.0
     *
     * @param Form $form The form to duplicate.
     *
     * @return Form The duplicated form.
     */
    public function duplicate( Form $form ): Form
    {
        return DB::transaction( function () use ( $form ): Form {
            return $form->duplicate();
        } );
    }

    /**
     * Publishes a form (sets as active).
     *
     * @since 1.0.0
     *
     * @param Form $form The form to publish.
     *
     * @return void
     */
    public function publish( Form $form ): void
    {
        $form->update( ['is_active' => true] );
    }

    /**
     * Unpublishes a form (sets as inactive).
     *
     * @since 1.0.0
     *
     * @param Form $form The form to unpublish.
     *
     * @return void
     */
    public function unpublish( Form $form ): void
    {
        $form->update( ['is_active' => false] );
    }

    /**
     * Toggles the published status of a form.
     *
     * @since 1.0.0
     *
     * @param Form $form The form to toggle.
     *
     * @return void
     */
    public function togglePublish( Form $form ): void
    {
        $form->update( ['is_active' => ! $form->is_active] );
    }

    /**
     * Finds a form by its slug.
     *
     * @since 1.0.0
     *
     * @param string $slug The form slug.
     *
     * @return Form|null The form or null if not found.
     */
    public function getBySlug( string $slug ): ?Form
    {
        return Form::where( 'slug', $slug )->first();
    }

    /**
     * Finds a form by its ID.
     *
     * @since 1.0.0
     *
     * @param int $id The form ID.
     *
     * @return Form|null The form or null if not found.
     */
    public function getById( int $id ): ?Form
    {
        return Form::find( $id);
    }
}
