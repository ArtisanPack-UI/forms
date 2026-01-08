<?php

/**
 * Form step model.
 *
 * Represents a step/page in a multi-step form including
 * title, description, and navigation button text.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Models;

use ArtisanPackUI\Forms\Database\Factories\FormStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Form step model class.
 *
 * Represents a step/page in a multi-step form including
 * title, description, and navigation button text.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @property int $id
 * @property int $form_id
 * @property string $title
 * @property string|null $description
 * @property int $sort_order
 * @property string $next_button_text
 * @property string $prev_button_text
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Form $form
 * @property-read int $step_number
 * @property-read bool $is_first_step
 * @property-read bool $is_last_step
 *
 * @since 1.0.0
 */
class FormStep extends Model
{
    use HasFactory;

    /**
     * Cached step number (set when loading via withStepNumbers scope).
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    public ?int $cached_step_number = null;

    /**
     * Cached min sort_order for the form's steps.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    public ?int $cached_min_sort_order = null;

    /**
     * Cached max sort_order for the form's steps.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    public ?int $cached_max_sort_order = null;

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.0.0
     *
     * @var list<string>
     */
    protected $fillable = [
        'form_id',
        'title',
        'description',
        'sort_order',
        'next_button_text',
        'prev_button_text',
    ];

    // =========================================
    // Relationships
    // =========================================

    /**
     * Gets the form that owns this step.
     *
     * @since 1.0.0
     *
     * @return BelongsTo<Form, $this> The form relationship.
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo( Form::class );
    }

    /**
     * Gets the fields associated with this step.
     *
     * @since 1.0.0
     *
     * @return HasMany<FormField, $this> The fields relationship.
     */
    public function fields(): HasMany
    {
        return $this->hasMany( FormField::class, 'step_id' )->orderBy( 'sort_order' );
    }

    // =========================================
    // Accessors
    // =========================================

    /**
     * Gets the step number (1-indexed position).
     *
     * Uses cached value if available (set via withStepNumbers()),
     * otherwise falls back to a query.
     *
     * @since 1.0.0
     *
     * @return int The step number.
     */
    public function getStepNumberAttribute(): int
    {
        // Use cached value if available
        if ( null !== $this->cached_step_number ) {
            return $this->cached_step_number;
        }

        // Try to compute from loaded relation to avoid N+1
        if ( $this->relationLoaded( 'form' ) && $this->form->relationLoaded( 'steps' ) ) {
            $stepNumber = 1;
            foreach ( $this->form->steps as $step ) {
                if ( $step->sort_order <= $this->sort_order ) {
                    if ( $step->id === $this->id ) {
                        return $stepNumber;
                    }
                    $stepNumber++;
                }
            }

            return $stepNumber;
        }

        // Fallback to query
        return $this->form->steps()
            ->where( 'sort_order', '<=', $this->sort_order )
            ->count();
    }

    /**
     * Checks if this is the first step.
     *
     * Uses cached value if available, otherwise falls back to a query.
     *
     * @since 1.0.0
     *
     * @return bool True if this is the first step.
     */
    public function getIsFirstStepAttribute(): bool
    {
        // Use cached value if available
        if ( null !== $this->cached_min_sort_order ) {
            return $this->sort_order === $this->cached_min_sort_order;
        }

        // Try to compute from loaded relation to avoid N+1
        if ( $this->relationLoaded( 'form' ) && $this->form->relationLoaded( 'steps' ) ) {
            $minSortOrder = $this->form->steps->min( 'sort_order' );

            return $this->sort_order === $minSortOrder;
        }

        // Fallback to query
        return $this->sort_order === $this->form->steps()->min( 'sort_order' );
    }

    /**
     * Checks if this is the last step.
     *
     * Uses cached value if available, otherwise falls back to a query.
     *
     * @since 1.0.0
     *
     * @return bool True if this is the last step.
     */
    public function getIsLastStepAttribute(): bool
    {
        // Use cached value if available
        if ( null !== $this->cached_max_sort_order ) {
            return $this->sort_order === $this->cached_max_sort_order;
        }

        // Try to compute from loaded relation to avoid N+1
        if ( $this->relationLoaded( 'form' ) && $this->form->relationLoaded( 'steps' ) ) {
            $maxSortOrder = $this->form->steps->max( 'sort_order' );

            return $this->sort_order === $maxSortOrder;
        }

        // Fallback to query
        return $this->sort_order === $this->form->steps()->max( 'sort_order' );
    }

    // =========================================
    // Methods
    // =========================================

    /**
     * Computes and caches step numbers and bounds for a collection of steps.
     *
     * Call this after loading steps to avoid N+1 queries when accessing
     * step_number, is_first_step, and is_last_step attributes.
     *
     * @since 1.0.0
     *
     * @param \Illuminate\Support\Collection<int, FormStep> $steps The steps collection.
     *
     * @return \Illuminate\Support\Collection<int, FormStep> The steps with cached values.
     */
    public static function withStepNumbers( \Illuminate\Support\Collection $steps ): \Illuminate\Support\Collection
    {
        if ( $steps->isEmpty() ) {
            return $steps;
        }

        // Sort by sort_order to ensure correct numbering
        $sorted = $steps->sortBy( 'sort_order' )->values();

        // Calculate bounds
        $minSortOrder = $sorted->first()->sort_order;
        $maxSortOrder = $sorted->last()->sort_order;

        // Assign step numbers and cache bounds
        $stepNumber = 1;
        foreach ( $sorted as $step ) {
            $step->cached_step_number    = $stepNumber;
            $step->cached_min_sort_order = $minSortOrder;
            $step->cached_max_sort_order = $maxSortOrder;
            $stepNumber++;
        }

        return $steps;
    }

    /**
     * Gets the previous step in the form.
     *
     * @since 1.0.0
     *
     * @return self|null The previous step or null if this is the first step.
     */
    public function getPreviousStep(): ?self
    {
        return $this->form->steps()
            ->where( 'sort_order', '<', $this->sort_order )
            ->orderBy( 'sort_order', 'desc' )
            ->first();
    }

    /**
     * Gets the next step in the form.
     *
     * @since 1.0.0
     *
     * @return self|null The next step or null if this is the last step.
     */
    public function getNextStep(): ?self
    {
        return $this->form->steps()
            ->where( 'sort_order', '>', $this->sort_order )
            ->orderBy( 'sort_order' )
            ->first();
    }

    /**
     * Creates a new factory instance for the model.
     *
     * @since 1.0.0
     *
     * @return FormStepFactory The factory instance.
     */
    protected static function newFactory(): FormStepFactory
    {
        return FormStepFactory::new();
    }
}
