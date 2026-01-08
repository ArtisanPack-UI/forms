<?php

/**
 * Form model.
 *
 * Represents the main form definition including basic info,
 * display settings, multi-step configuration, and status.
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

use ArtisanPackUI\Forms\Database\Factories\FormFactory;
use ArtisanPackUI\Forms\Events\FormCreated;
use ArtisanPackUI\Forms\Events\FormDeleted;
use ArtisanPackUI\Forms\Events\FormUpdated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Form model class.
 *
 * Represents the main form definition including basic info,
 * display settings, multi-step configuration, and status.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 *
 * @property int                              $id
 * @property int|null                         $user_id
 * @property string                           $name
 * @property string                           $slug
 * @property string|null                      $description
 * @property string                           $submit_button_text
 * @property string|null                      $success_message
 * @property string|null                      $redirect_url
 * @property array|null                       $settings
 * @property bool                             $is_multi_step
 * @property bool                             $show_progress_bar
 * @property bool                             $allow_step_navigation
 * @property bool                             $is_active
 * @property \Illuminate\Support\Carbon|null  $created_at
 * @property \Illuminate\Support\Carbon|null  $updated_at
 * @property-read int                         $unread_submissions_count
 * @property-read int                         $total_submissions_count
 * @property-read Collection                  $active_notifications
 * @property-read Collection                  $fields_ordered
 */
class Form extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.0.0
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'submit_button_text',
        'success_message',
        'redirect_url',
        'settings',
        'is_multi_step',
        'show_progress_bar',
        'allow_step_navigation',
        'is_active',
    ];

    // =========================================
    // Relationships
    // =========================================

    /**
     * Gets the user who owns this form.
     *
     * Returns the user model based on the configured user model class.
     * If no user_id is set, this relationship returns null.
     *
     * @since 1.0.0
     *
     * @return BelongsTo<Model, $this> The owner relationship.
     */
    public function owner(): BelongsTo
    {
        $userModel = config( 'artisanpack.forms.authorization.user_model', 'App\\Models\\User' );

        return $this->belongsTo( $userModel, 'user_id' );
    }

    /**
     * Gets the fields associated with this form.
     *
     * @since 1.0.0
     *
     * @return HasMany<FormField, $this> The fields relationship.
     */
    public function fields(): HasMany
    {
        return $this->hasMany( FormField::class )->orderBy( 'sort_order' );
    }

    /**
     * Gets the steps associated with this form.
     *
     * @since 1.0.0
     *
     * @return HasMany<FormStep, $this> The steps relationship.
     */
    public function steps(): HasMany
    {
        return $this->hasMany( FormStep::class )->orderBy( 'sort_order' );
    }

    /**
     * Gets the submissions associated with this form.
     *
     * @since 1.0.0
     *
     * @return HasMany<FormSubmission, $this> The submissions relationship.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany( FormSubmission::class );
    }

    /**
     * Gets the notifications associated with this form.
     *
     * @since 1.0.0
     *
     * @return HasMany<FormNotification, $this> The notifications relationship.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany( FormNotification::class )->orderBy( 'sort_order' );
    }

    // =========================================
    // Scopes
    // =========================================

    /**
     * Scopes a query to only include active forms.
     *
     * @since 1.0.0
     *
     * @param Builder<Form> $query The query builder instance.
     *
     * @return Builder<Form> The modified query builder.
     */
    public function scopeActive( Builder $query ): Builder
    {
        return $query->where( 'is_active', true );
    }

    /**
     * Scopes a query to only include multi-step forms.
     *
     * @since 1.0.0
     *
     * @param Builder<Form> $query The query builder instance.
     *
     * @return Builder<Form> The modified query builder.
     */
    public function scopeMultiStep( Builder $query ): Builder
    {
        return $query->where( 'is_multi_step', true );
    }

    /**
     * Scopes a query to only include single-step forms.
     *
     * @since 1.0.0
     *
     * @param Builder<Form> $query The query builder instance.
     *
     * @return Builder<Form> The modified query builder.
     */
    public function scopeSingleStep( Builder $query ): Builder
    {
        return $query->where( 'is_multi_step', false );
    }

    // =========================================
    // Accessors
    // =========================================

    /**
     * Gets the count of unread submissions.
     *
     * @since 1.0.0
     *
     * @return int The unread submissions count.
     */
    public function getUnreadSubmissionsCountAttribute(): int
    {
        return $this->submissions()->unread()->count();
    }

    /**
     * Gets the total count of submissions.
     *
     * @since 1.0.0
     *
     * @return int The total submissions count.
     */
    public function getTotalSubmissionsCountAttribute(): int
    {
        return $this->submissions()->count();
    }

    /**
     * Gets the active notifications for this form.
     *
     * @since 1.0.0
     *
     * @return Collection<int, FormNotification> The active notifications.
     */
    public function getActiveNotificationsAttribute(): Collection
    {
        return $this->notifications()->active()->get();
    }

    /**
     * Gets all fields in order, respecting multi-step structure.
     *
     * @since 1.0.0
     *
     * @return Collection<int, FormField> The ordered fields.
     */
    public function getFieldsOrderedAttribute(): Collection
    {
        if ( $this->is_multi_step ) {
            return $this->steps()
                ->with( ['fields' => fn ( $q ) => $q->orderBy( 'sort_order' )] )
                ->get()
                ->flatMap->fields;
        }

        return $this->fields()->orderBy( 'sort_order' )->get();
    }

    // =========================================
    // Methods
    // =========================================

    /**
     * Gets a setting value from the settings array.
     *
     * @since 1.0.0
     *
     * @param string $key     The setting key using dot notation.
     * @param mixed  $default The default value if key not found.
     *
     * @return mixed The setting value or default.
     */
    public function getSetting( string $key, mixed $default = null ): mixed
    {
        return data_get( $this->settings, $key, $default );
    }

    /**
     * Duplicates this form with all its steps, fields, and notifications.
     *
     * @since 1.0.0
     *
     * @return self The duplicated form instance.
     */
    public function duplicate(): self
    {
        $clone            = $this->replicate();
        $clone->name      = $this->name . ' (Copy)';
        $clone->slug      = static::generateUniqueSlug( $clone->name );
        $clone->is_active = false;
        $clone->save();

        // Clone steps and their fields
        foreach ( $this->steps as $step ) {
            $newStep          = $step->replicate();
            $newStep->form_id = $clone->id;
            $newStep->save();

            // Clone fields in step
            foreach ( $step->fields as $field ) {
                $newField          = $field->replicate();
                $newField->form_id = $clone->id;
                $newField->step_id = $newStep->id;
                $newField->uuid    = Str::uuid()->toString();
                $newField->save();
            }
        }

        // Clone fields without steps
        foreach ( $this->fields()->whereNull( 'step_id' )->get() as $field ) {
            $newField          = $field->replicate();
            $newField->form_id = $clone->id;
            $newField->uuid    = Str::uuid()->toString();
            $newField->save();
        }

        // Clone notifications
        foreach ( $this->notifications as $notification ) {
            $newNotification          = $notification->replicate();
            $newNotification->form_id = $clone->id;
            $newNotification->save();
        }

        return $clone;
    }

    /**
     * Gets the route key for the model.
     *
     * @since 1.0.0
     *
     * @return string The route key name.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Creates a new factory instance for the model.
     *
     * @since 1.0.0
     *
     * @return FormFactory The factory instance.
     */
    protected static function newFactory(): FormFactory
    {
        return FormFactory::new();
    }

    /**
     * Gets the attributes that should be cast.
     *
     * @since 1.0.0
     *
     * @return array<string, string> The cast definitions.
     */
    protected function casts(): array
    {
        return [
            'settings'              => 'array',
            'is_multi_step'         => 'boolean',
            'show_progress_bar'     => 'boolean',
            'allow_step_navigation' => 'boolean',
            'is_active'             => 'boolean',
        ];
    }

    // =========================================
    // Boot
    // =========================================

    /**
     * Bootstraps the model and its traits.
     *
     * Sets up model event listeners for creating, created, updated, and deleted events.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating( function ( Form $form ): void {
            if ( empty( $form->slug ) ) {
                $form->slug = static::generateUniqueSlug( $form->name );
            }
        } );

        static::created( function ( Form $form ): void {
            // Fire action hook for extensibility
            if ( function_exists( 'doAction' ) ) {
                doAction( 'forms.form.created', $form );
            }

            // Dispatch Laravel event
            FormCreated::dispatch( $form );
        } );

        static::updated( function ( Form $form ): void {
            // Fire action hook for extensibility
            if ( function_exists( 'doAction' ) ) {
                doAction( 'forms.form.updated', $form );
            }

            // Dispatch Laravel event
            FormUpdated::dispatch( $form );
        } );

        static::deleted( function ( Form $form ): void {
            // Fire action hook for extensibility
            if ( function_exists( 'doAction' ) ) {
                doAction( 'forms.form.deleted', $form );
            }

            // Dispatch Laravel event
            FormDeleted::dispatch( $form );
        } );
    }

    /**
     * Generates a unique slug from the given name.
     *
     * @since 1.0.0
     *
     * @param string $name The name to generate a slug from.
     *
     * @return string The unique slug.
     */
    protected static function generateUniqueSlug( string $name ): string
    {
        $slug         = Str::slug( $name );
        $originalSlug = $slug;
        $count        = 1;

        while ( static::where( 'slug', $slug )->exists() ) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }
}
