<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Policies;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormUpload;
use Closure;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

/**
 * SubmissionPolicy
 *
 * Authorization policy for FormSubmission model. Controls access to
 * submission viewing, updating, and deletion operations.
 *
 * SECURITY WARNING: By default, this policy uses permissive mode where any
 * authenticated user can access any submission. Form submissions often contain
 * PII (emails, phone numbers, addresses) - for multi-user applications, enable
 * ownership enforcement by setting `artisanpack.forms.authorization.restrict_by_owner`
 * to `true`, or override these methods in your application's policy.
 *
 * Ownership is determined through the submission's parent form. If the form has
 * an owner (user_id), only that user (or admins) can access its submissions.
 *
 * Example of overriding this policy:
 *
 * ```php
 * // In your AuthServiceProvider
 * Gate::policy(\ArtisanPackUI\Forms\Models\FormSubmission::class, \App\Policies\SubmissionPolicy::class);
 * ```
 *
 * @since 1.0.0
 */
class SubmissionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any submissions.
     *
     * This method authorizes access to submission listing features.
     * When ownership restriction is enabled, actual data filtering should
     * be applied using getOwnedSubmissionsScope() on the query.
     *
     * @see getOwnedSubmissionsScope() for query-level filtering
     */
    public function viewAny(?Authenticatable $user): bool
    {
        return $user !== null;
    }

    /**
     * Determine whether the user can view the submission.
     *
     * When ownership restriction is enabled, access is determined by the
     * parent form's ownership. This protects potentially sensitive PII.
     */
    public function view(?Authenticatable $user, FormSubmission $submission): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->isOwnershipRestricted()) {
            return $this->canAccessForm($user, $submission);
        }

        return true;
    }

    /**
     * Determine whether the user can update the submission.
     *
     * This includes marking as read/unread, starring, and adding notes.
     */
    public function update(?Authenticatable $user, FormSubmission $submission): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->isOwnershipRestricted()) {
            return $this->canAccessForm($user, $submission);
        }

        return true;
    }

    /**
     * Determine whether the user can delete the submission.
     *
     * When ownership restriction is enabled, only the form owner (or admin)
     * can delete submissions.
     */
    public function delete(?Authenticatable $user, FormSubmission $submission): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->isOwnershipRestricted()) {
            return $this->canAccessForm($user, $submission);
        }

        return true;
    }

    /**
     * Determine whether the user can mark the submission as spam.
     *
     * By default, users who can update the submission can mark it as spam.
     */
    public function markAsSpam(?Authenticatable $user, FormSubmission $submission): bool
    {
        return $this->update($user, $submission);
    }

    /**
     * Determine whether the user can download files from this submission.
     *
     * By default, users who can view the submission can download its files.
     */
    public function downloadFile(?Authenticatable $user, FormSubmission $submission, FormUpload $upload): bool
    {
        // Verify the upload belongs to this submission
        if ($upload->submission_id !== $submission->id) {
            return false;
        }

        return $this->view($user, $submission);
    }

    /**
     * Determine whether the user can export submissions.
     *
     * This authorizes access to export functionality. When ownership
     * restriction is enabled, actual data filtering should be applied
     * using getOwnedSubmissionsScope() on the export query.
     *
     * @see getOwnedSubmissionsScope() for query-level filtering
     */
    public function export(?Authenticatable $user): bool
    {
        return $user !== null;
    }

    /**
     * Determine whether the user can perform bulk operations on submissions.
     *
     * This includes bulk delete, bulk mark as read, bulk mark as spam, etc.
     * When ownership restriction is enabled, bulk operations should only
     * affect submissions the user has access to via getOwnedSubmissionsScope().
     *
     * @see getOwnedSubmissionsScope() for query-level filtering
     */
    public function bulkAction(?Authenticatable $user): bool
    {
        return $user !== null;
    }

    /**
     * Check if the user has unrestricted access to all submissions.
     *
     * Returns true if:
     * - Ownership restriction is disabled (permissive mode)
     * - User is an admin and admin bypass is enabled
     *
     * Use this to determine if query-level filtering is needed.
     */
    public function hasUnrestrictedAccess(?Authenticatable $user): bool
    {
        if ($user === null) {
            return false;
        }

        // If ownership is not restricted, everyone has unrestricted access
        if (! $this->isOwnershipRestricted()) {
            return true;
        }

        // Admins have unrestricted access if bypass is enabled
        if ($this->isAdmin($user) && config('artisanpack.forms.authorization.allow_admin_bypass', true)) {
            return true;
        }

        return false;
    }

    /**
     * Get a query scope closure for filtering submissions by ownership.
     *
     * Use this method when building queries that list, export, or perform
     * bulk operations on submissions to ensure users only see/affect
     * submissions they have access to.
     *
     * When ownership restriction is disabled, no filtering is applied
     * regardless of authentication status. When enabled, unauthenticated
     * users see nothing and authenticated users see only their submissions.
     *
     * Example usage:
     * ```php
     * $scope = app(SubmissionPolicy::class)->getOwnedSubmissionsScope($user);
     * $submissions = FormSubmission::query()->tap($scope)->get();
     * ```
     *
     * @return Closure(Builder<FormSubmission>): Builder<FormSubmission>
     */
    public function getOwnedSubmissionsScope(?Authenticatable $user): Closure
    {
        return function (Builder $query) use ($user): Builder {
            // If ownership is not restricted, no filtering needed
            if (! $this->isOwnershipRestricted()) {
                return $query;
            }

            // Ownership is restricted - check user access
            if ($user === null) {
                // No user with ownership enabled = no access
                return $query->whereRaw('1 = 0');
            }

            // Admins have unrestricted access if bypass is enabled
            if ($this->isAdmin($user) && config('artisanpack.forms.authorization.allow_admin_bypass', true)) {
                return $query;
            }

            // Restricted users only see submissions from their forms or unowned forms
            return $query->whereHas('form', function (Builder $formQuery) use ($user): void {
                $formQuery->where(function (Builder $q) use ($user): void {
                    $q->where('user_id', $user->getAuthIdentifier())
                        ->orWhereNull('user_id');
                });
            });
        };
    }

    /**
     * Check if ownership-based access control is enabled.
     */
    protected function isOwnershipRestricted(): bool
    {
        return (bool) config('artisanpack.forms.authorization.restrict_by_owner', false);
    }

    /**
     * Check if the user can access the submission's parent form.
     *
     * Access is granted if:
     * - The user is an admin (and admin bypass is enabled)
     * - The form has no owner (legacy/shared forms)
     * - The user owns the form
     */
    protected function canAccessForm(Authenticatable $user, FormSubmission $submission): bool
    {
        // Check if user is admin and admin bypass is allowed
        if ($this->isAdmin($user) && config('artisanpack.forms.authorization.allow_admin_bypass', true)) {
            return true;
        }

        // Load the form if not already loaded
        $form = $submission->form;

        if ($form === null) {
            // Orphaned submission - deny access when restricted
            return false;
        }

        // Forms without owners are accessible to all authenticated users
        if ($form->user_id === null) {
            return true;
        }

        // Check if user owns the form
        return $form->user_id === $user->getAuthIdentifier();
    }

    /**
     * Check if the user is an admin.
     *
     * Checks for an `is_admin` attribute on the user model.
     * Override this method to implement custom admin detection.
     */
    protected function isAdmin(Authenticatable $user): bool
    {
        // Check for is_admin attribute if it exists
        if (method_exists($user, 'getAttribute')) {
            return (bool) $user->getAttribute('is_admin');
        }

        return false;
    }

    /**
     * Scope a query to only include submissions for a specific form that the user can access.
     *
     * This is a convenience method for checking access to a single form's submissions.
     */
    public function canAccessFormSubmissions(?Authenticatable $user, Form $form): bool
    {
        if ($user === null) {
            return false;
        }

        if (! $this->isOwnershipRestricted()) {
            return true;
        }

        if ($this->isAdmin($user) && config('artisanpack.forms.authorization.allow_admin_bypass', true)) {
            return true;
        }

        // Form without owner is accessible
        if ($form->user_id === null) {
            return true;
        }

        return $form->user_id === $user->getAuthIdentifier();
    }
}
