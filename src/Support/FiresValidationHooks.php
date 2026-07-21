<?php

/**
 * Trait firing ap.forms.beforeValidate / ap.forms.validated action hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

/**
 * Adds validation-lifecycle hooks to a FormRequest.
 *
 * Fires the `ap.forms.beforeValidate` action just before Laravel validates
 * the request, and the `ap.forms.validated` action once validation has
 * succeeded.
 *
 * A consuming class that also defines its own `prepareForValidation` or
 * `passedValidation` silently shadows the trait method (PHP resolves class
 * methods over trait methods). In that case call `$this->fireBeforeValidate()`
 * / `$this->fireValidated()` from the override — `parent::` does NOT work
 * because the parent is `Illuminate\Foundation\Http\FormRequest`, not the trait.
 *
 * Note: `passedValidation` runs after validation succeeds; a subscriber that
 * mutates the request (e.g. via `$request->merge(...)`) bypasses re-validation.
 *
 * @since 1.3.0
 */
trait FiresValidationHooks
{
    /**
     * Default `prepareForValidation` hook — shadowed by any class-level override.
     *
     * @since 1.3.0
     */
    protected function prepareForValidation(): void
    {
        $this->fireBeforeValidate();
    }

    /**
     * Default `passedValidation` hook — shadowed by any class-level override.
     *
     * @since 1.3.0
     */
    protected function passedValidation(): void
    {
        $this->fireValidated();
    }

    /**
     * Fires the `ap.forms.beforeValidate` action. Call this from an overriding
     * `prepareForValidation` to keep the hook firing.
     *
     * @since 1.3.0
     */
    protected function fireBeforeValidate(): void
    {
        doAction( 'ap.forms.beforeValidate', $this );
    }

    /**
     * Fires the `ap.forms.validated` action. Call this from an overriding
     * `passedValidation` to keep the hook firing.
     *
     * @since 1.3.0
     */
    protected function fireValidated(): void
    {
        doAction( 'ap.forms.validated', $this, $this->validated() );
    }
}
