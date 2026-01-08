<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Policies;

use ArtisanPackUI\Forms\Models\Form;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * FormPolicy
 *
 * Authorization policy for Form model. Controls access to form management
 * operations including viewing, creating, updating, and deleting forms.
 *
 * SECURITY WARNING: By default, this policy uses permissive mode where any
 * authenticated user can access any form. For multi-user applications, enable
 * ownership enforcement by setting `artisanpack.forms.authorization.restrict_by_owner`
 * to `true`, or override these methods in your application's policy.
 *
 * To override this policy, create your own FormPolicy and register it in your
 * AuthServiceProvider:
 *
 * ```php
 * Gate::policy(\ArtisanPackUI\Forms\Models\Form::class, \App\Policies\FormPolicy::class);
 * ```
 *
 * @since 1.0.0
 */
class FormPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any forms.
     *
     * By default, any authenticated user can view the forms list.
     * Override this method in your application to customize access.
     */
    public function viewAny( ?Authenticatable $user ): bool
    {
        return null !== $user;
    }

    /**
     * Determine whether the user can view the form.
     *
     * Active forms are viewable by anyone (for public rendering).
     * Inactive/draft forms require authentication and ownership (if enabled).
     */
    public function view( ?Authenticatable $user, Form $form ): bool
    {
        // Active forms are publicly viewable
        if ( $form->is_active ) {
            return true;
        }

        // Inactive forms require authentication
        if ( null === $user ) {
            return false;
        }

        // Check ownership if restriction is enabled
        if ( $this->isOwnershipRestricted() ) {
            return $this->isOwnerOrAdmin( $user, $form );
        }

        return true;
    }

    /**
     * Determine whether the user can create forms.
     *
     * By default, any authenticated user can create forms.
     * Override this method in your application to customize access.
     */
    public function create( ?Authenticatable $user ): bool
    {
        return null !== $user;
    }

    /**
     * Determine whether the user can update the form.
     *
     * When ownership restriction is enabled, only the form owner (or admin)
     * can update the form. Otherwise, any authenticated user can update.
     */
    public function update( ?Authenticatable $user, Form $form ): bool
    {
        if ( null === $user ) {
            return false;
        }

        if ( $this->isOwnershipRestricted() ) {
            return $this->isOwnerOrAdmin( $user, $form );
        }

        return true;
    }

    /**
     * Determine whether the user can delete the form.
     *
     * WARNING: This is a destructive action. When ownership restriction is
     * enabled, only the form owner (or admin) can delete the form.
     */
    public function delete( ?Authenticatable $user, Form $form ): bool
    {
        if ( null === $user ) {
            return false;
        }

        if ( $this->isOwnershipRestricted() ) {
            return $this->isOwnerOrAdmin( $user, $form );
        }

        return true;
    }

    /**
     * Determine whether the user can duplicate the form.
     *
     * By default, any authenticated user who can create forms can duplicate.
     */
    public function duplicate( ?Authenticatable $user, Form $form ): bool
    {
        return $this->create( $user );
    }

    /**
     * Determine whether the user can view submissions for the form.
     *
     * When ownership restriction is enabled, only the form owner (or admin)
     * can view submissions. This protects potentially sensitive PII data.
     */
    public function viewSubmissions( ?Authenticatable $user, Form $form ): bool
    {
        if ( null === $user ) {
            return false;
        }

        if ( $this->isOwnershipRestricted() ) {
            return $this->isOwnerOrAdmin( $user, $form );
        }

        return true;
    }

    /**
     * Determine whether the user can export submissions for the form.
     *
     * By default, users who can view submissions can also export them.
     */
    public function exportSubmissions( ?Authenticatable $user, Form $form ): bool
    {
        return $this->viewSubmissions( $user, $form );
    }

    /**
     * Determine whether the user can manage form settings.
     *
     * By default, users who can update the form can manage settings.
     */
    public function manageSettings( ?Authenticatable $user, Form $form ): bool
    {
        return $this->update( $user, $form );
    }

    /**
     * Determine whether the user can manage form notifications.
     *
     * By default, users who can update the form can manage notifications.
     */
    public function manageNotifications( ?Authenticatable $user, Form $form ): bool
    {
        return $this->update( $user, $form );
    }

    /**
     * Check if ownership-based access control is enabled.
     */
    protected function isOwnershipRestricted(): bool
    {
        return (bool) config( 'artisanpack.forms.authorization.restrict_by_owner', false );
    }

    /**
     * Check if the user is the form owner or an admin.
     *
     * Admins can bypass ownership checks if `allow_admin_bypass` is enabled.
     * The admin check looks for an `is_admin` attribute on the user model.
     */
    protected function isOwnerOrAdmin( Authenticatable $user, Form $form ): bool
    {
        // Check if user is admin and admin bypass is allowed
        if ( $this->isAdmin( $user ) && config( 'artisanpack.forms.authorization.allow_admin_bypass', true ) ) {
            return true;
        }

        // Check ownership - form must have an owner and user must match
        if ( null === $form->user_id ) {
            // Forms without owners are accessible to all authenticated users
            // This supports legacy forms created before ownership was added
            return true;
        }

        return $form->user_id === $user->getAuthIdentifier();
    }

    /**
     * Check if the user is an admin.
     *
     * Checks for an `is_admin` attribute on the user model.
     * Override this method to implement custom admin detection.
     */
    protected function isAdmin( Authenticatable $user ): bool
    {
        // Check for is_admin attribute if it exists
        if ( method_exists( $user, 'getAttribute')) {
            return (bool) $user->getAttribute( 'is_admin');
        }

        return false;
    }
}
