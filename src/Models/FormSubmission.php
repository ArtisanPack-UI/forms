<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Models;

use ArtisanPackUI\Forms\Database\Factories\FormSubmissionFactory;
use ArtisanPackUI\Forms\Events\FormSubmitted;
use ArtisanPackUI\Forms\Events\SubmissionDeleted;
use ArtisanPackUI\Forms\Events\SubmissionUpdated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * FormSubmission Model
 *
 * Represents a form submission including metadata, status flags,
 * and admin notes. Links to submitted values and file uploads.
 *
 * @property int $id
 * @property int $form_id
 * @property string $submission_number
 * @property string|null $page_url
 * @property string|null $referrer_url
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property bool $is_read
 * @property bool $is_spam
 * @property bool $is_starred
 * @property string|null $admin_notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Form $form
 * @property-read Collection $data
 * @property-read array $data_array
 *
 * @since 1.0.0
 */
class FormSubmission extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): FormSubmissionFactory
    {
        return FormSubmissionFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'form_id',
        'submission_number',
        'page_url',
        'referrer_url',
        'ip_address',
        'user_agent',
        'is_read',
        'is_spam',
        'is_starred',
        'admin_notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'is_spam' => 'boolean',
            'is_starred' => 'boolean',
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

        static::creating(function (FormSubmission $submission): void {
            if (empty($submission->submission_number)) {
                $submission->submission_number = static::generateSubmissionNumber($submission->form_id);
            }
        });

        static::created(function (FormSubmission $submission): void {
            // Fire action hook for extensibility
            if (function_exists('doAction')) {
                doAction('forms.submission.created', $submission);
            }

            // Dispatch Laravel event
            FormSubmitted::dispatch($submission);
        });

        static::updated(function (FormSubmission $submission): void {
            // Fire action hook for extensibility
            if (function_exists('doAction')) {
                doAction('forms.submission.updated', $submission);
            }

            // Dispatch Laravel event
            SubmissionUpdated::dispatch($submission);
        });

        static::deleted(function (FormSubmission $submission): void {
            // Fire action hook for extensibility
            if (function_exists('doAction')) {
                doAction('forms.submission.deleted', $submission);
            }

            // Dispatch Laravel event
            SubmissionDeleted::dispatch($submission);
        });
    }

    /**
     * Generate a unique submission number for a form.
     */
    protected static function generateSubmissionNumber(int $formId): string
    {
        $format = config('artisanpack.forms.submissions.submission_number_format', 'FORM-{year}-{sequence}');

        $count = static::where('form_id', $formId)
            ->whereYear('created_at', now()->year)
            ->count() + 1;

        $replacements = [
            '{year}' => now()->format('Y'),
            '{sequence}' => sprintf('%05d', $count),
            '{form_id}' => $formId,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $format);
    }

    // =========================================
    // Relationships
    // =========================================

    /**
     * Get the form that owns this submission.
     *
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Get the values associated with this submission.
     *
     * @return HasMany<FormSubmissionValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(FormSubmissionValue::class, 'submission_id');
    }

    /**
     * Get the uploads associated with this submission.
     *
     * @return HasMany<FormUpload, $this>
     */
    public function uploads(): HasMany
    {
        return $this->hasMany(FormUpload::class, 'submission_id');
    }

    // =========================================
    // Scopes
    // =========================================

    /**
     * Scope a query to only include unread submissions.
     *
     * @param  Builder<FormSubmission>  $query
     * @return Builder<FormSubmission>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope a query to only include read submissions.
     *
     * @param  Builder<FormSubmission>  $query
     * @return Builder<FormSubmission>
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope a query to only include non-spam submissions.
     *
     * @param  Builder<FormSubmission>  $query
     * @return Builder<FormSubmission>
     */
    public function scopeNotSpam(Builder $query): Builder
    {
        return $query->where('is_spam', false);
    }

    /**
     * Scope a query to only include spam submissions.
     *
     * @param  Builder<FormSubmission>  $query
     * @return Builder<FormSubmission>
     */
    public function scopeSpam(Builder $query): Builder
    {
        return $query->where('is_spam', true);
    }

    /**
     * Scope a query to only include starred submissions.
     *
     * @param  Builder<FormSubmission>  $query
     * @return Builder<FormSubmission>
     */
    public function scopeStarred(Builder $query): Builder
    {
        return $query->where('is_starred', true);
    }

    /**
     * Scope a query to only include recent submissions.
     *
     * @param  Builder<FormSubmission>  $query
     * @return Builder<FormSubmission>
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // =========================================
    // Accessors
    // =========================================

    /**
     * Get submission data as a collection keyed by field name.
     *
     * @return Collection<string, string>
     */
    public function getDataAttribute(): Collection
    {
        return $this->values->mapWithKeys(function (FormSubmissionValue $value) {
            return [$value->field_name => $value->display_value];
        });
    }

    /**
     * Get submission data as an array.
     *
     * @return array<string, string>
     */
    public function getDataArrayAttribute(): array
    {
        return $this->data->toArray();
    }

    // =========================================
    // Methods
    // =========================================

    /**
     * Mark the submission as read.
     */
    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }

    /**
     * Mark the submission as unread.
     */
    public function markAsUnread(): void
    {
        $this->update(['is_read' => false]);
    }

    /**
     * Toggle the spam status.
     */
    public function toggleSpam(): void
    {
        $this->update(['is_spam' => ! $this->is_spam]);
    }

    /**
     * Toggle the starred status.
     */
    public function toggleStar(): void
    {
        $this->update(['is_starred' => ! $this->is_starred]);
    }

    /**
     * Get a submitted value by field name.
     */
    public function getValue(string $fieldName): ?string
    {
        return $this->values
            ->firstWhere('field_name', $fieldName)
            ?->display_value;
    }

    /**
     * Get the first email value from the submission.
     */
    public function getEmailValue(): ?string
    {
        $emailValue = $this->values->first(function ($value) {
            return $value->field_type === 'email';
        });

        return $emailValue?->value;
    }
}
