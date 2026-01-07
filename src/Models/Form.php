<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Models;

use ArtisanPackUI\Forms\Database\Factories\FormFactory;
use ArtisanPackUI\Forms\Events\FormCreated;
use ArtisanPackUI\Forms\Events\FormDeleted;
use ArtisanPackUI\Forms\Events\FormUpdated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Form Model
 *
 * Represents the main form definition including basic info,
 * display settings, multi-step configuration, and status.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $submit_button_text
 * @property string|null $success_message
 * @property string|null $redirect_url
 * @property array|null $settings
 * @property bool $is_multi_step
 * @property bool $show_progress_bar
 * @property bool $allow_step_navigation
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int $unread_submissions_count
 * @property-read int $total_submissions_count
 * @property-read Collection $active_notifications
 * @property-read Collection $fields_ordered
 *
 * @since 1.0.0
 */
class Form extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): FormFactory
    {
        return FormFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_multi_step' => 'boolean',
            'show_progress_bar' => 'boolean',
            'allow_step_navigation' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // =========================================
    // Boot
    // =========================================

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Form $form): void {
            if (empty($form->slug)) {
                $form->slug = static::generateUniqueSlug($form->name);
            }
        });

        static::created(function (Form $form): void {
            // Fire action hook for extensibility
            if (function_exists('doAction')) {
                doAction('forms.form.created', $form);
            }

            // Dispatch Laravel event
            FormCreated::dispatch($form);
        });

        static::updated(function (Form $form): void {
            // Fire action hook for extensibility
            if (function_exists('doAction')) {
                doAction('forms.form.updated', $form);
            }

            // Dispatch Laravel event
            FormUpdated::dispatch($form);
        });

        static::deleted(function (Form $form): void {
            // Fire action hook for extensibility
            if (function_exists('doAction')) {
                doAction('forms.form.deleted', $form);
            }

            // Dispatch Laravel event
            FormDeleted::dispatch($form);
        });
    }

    /**
     * Generate a unique slug from the given name.
     */
    protected static function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug.'-'.$count;
            $count++;
        }

        return $slug;
    }

    // =========================================
    // Relationships
    // =========================================

    /**
     * Get the fields associated with this form.
     *
     * @return HasMany<FormField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('sort_order');
    }

    /**
     * Get the steps associated with this form.
     *
     * @return HasMany<FormStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(FormStep::class)->orderBy('sort_order');
    }

    /**
     * Get the submissions associated with this form.
     *
     * @return HasMany<FormSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /**
     * Get the notifications associated with this form.
     *
     * @return HasMany<FormNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(FormNotification::class)->orderBy('sort_order');
    }

    // =========================================
    // Scopes
    // =========================================

    /**
     * Scope a query to only include active forms.
     *
     * @param  Builder<Form>  $query
     * @return Builder<Form>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include multi-step forms.
     *
     * @param  Builder<Form>  $query
     * @return Builder<Form>
     */
    public function scopeMultiStep(Builder $query): Builder
    {
        return $query->where('is_multi_step', true);
    }

    /**
     * Scope a query to only include single-step forms.
     *
     * @param  Builder<Form>  $query
     * @return Builder<Form>
     */
    public function scopeSingleStep(Builder $query): Builder
    {
        return $query->where('is_multi_step', false);
    }

    // =========================================
    // Accessors
    // =========================================

    /**
     * Get the count of unread submissions.
     */
    public function getUnreadSubmissionsCountAttribute(): int
    {
        return $this->submissions()->unread()->count();
    }

    /**
     * Get the total count of submissions.
     */
    public function getTotalSubmissionsCountAttribute(): int
    {
        return $this->submissions()->count();
    }

    /**
     * Get the active notifications for this form.
     *
     * @return Collection<int, FormNotification>
     */
    public function getActiveNotificationsAttribute(): Collection
    {
        return $this->notifications()->active()->get();
    }

    /**
     * Get all fields in order, respecting multi-step structure.
     *
     * @return Collection<int, FormField>
     */
    public function getFieldsOrderedAttribute(): Collection
    {
        if ($this->is_multi_step) {
            return $this->steps()
                ->with(['fields' => fn ($q) => $q->orderBy('sort_order')])
                ->get()
                ->flatMap->fields;
        }

        return $this->fields()->orderBy('sort_order')->get();
    }

    // =========================================
    // Methods
    // =========================================

    /**
     * Get a setting value from the settings array.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Duplicate this form with all its steps, fields, and notifications.
     */
    public function duplicate(): self
    {
        $clone = $this->replicate();
        $clone->name = $this->name.' (Copy)';
        $clone->slug = static::generateUniqueSlug($clone->name);
        $clone->is_active = false;
        $clone->save();

        // Clone steps and their fields
        foreach ($this->steps as $step) {
            $newStep = $step->replicate();
            $newStep->form_id = $clone->id;
            $newStep->save();

            // Clone fields in step
            foreach ($step->fields as $field) {
                $newField = $field->replicate();
                $newField->form_id = $clone->id;
                $newField->step_id = $newStep->id;
                $newField->uuid = Str::uuid()->toString();
                $newField->save();
            }
        }

        // Clone fields without steps
        foreach ($this->fields()->whereNull('step_id')->get() as $field) {
            $newField = $field->replicate();
            $newField->form_id = $clone->id;
            $newField->uuid = Str::uuid()->toString();
            $newField->save();
        }

        // Clone notifications
        foreach ($this->notifications as $notification) {
            $newNotification = $notification->replicate();
            $newNotification->form_id = $clone->id;
            $newNotification->save();
        }

        return $clone;
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
