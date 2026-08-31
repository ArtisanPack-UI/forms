<?php

/**
 * Form submission model.
 *
 * Represents a form submission including metadata, status flags,
 * and admin notes. Links to submitted values and file uploads.
 *
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Models;

use ArtisanPackUI\Forms\Database\Factories\FormSubmissionFactory;
use ArtisanPackUI\Forms\Enums\SubmissionStatus;
use ArtisanPackUI\Forms\Events\FormSubmitted;
use ArtisanPackUI\Forms\Events\SubmissionDeleted;
use ArtisanPackUI\Forms\Events\SubmissionUpdated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Form submission model class.
 *
 * Represents a form submission including metadata, status flags,
 * and admin notes. Links to submitted values and file uploads.
 *
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
 * @property float|null $spam_score
 * @property SubmissionStatus $status
 * @property string|null $admin_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
     * The attributes that are mass assignable.
     *
     * @since 1.0.0
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
        'spam_score',
        'status',
        'admin_notes',
    ];

    // =========================================
    // Relationships
    // =========================================

    /**
     * Gets the form that owns this submission.
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
     * Gets the values associated with this submission.
     *
     * @since 1.0.0
     *
     * @return HasMany<FormSubmissionValue, $this> The values relationship.
     */
    public function values(): HasMany
    {
        return $this->hasMany( FormSubmissionValue::class, 'submission_id' );
    }

    /**
     * Gets the uploads associated with this submission.
     *
     * @since 1.0.0
     *
     * @return HasMany<FormUpload, $this> The uploads relationship.
     */
    public function uploads(): HasMany
    {
        return $this->hasMany( FormUpload::class, 'submission_id' );
    }

    // =========================================
    // Scopes
    // =========================================

    /**
     * Scopes a query to only include unread submissions.
     *
     * @since 1.0.0
     *
     * @param  Builder<FormSubmission>  $query  The query builder instance.
     *
     * @return Builder<FormSubmission> The modified query builder.
     */
    public function scopeUnread( Builder $query ): Builder
    {
        return $query->where( 'is_read', false );
    }

    /**
     * Scopes a query to only include read submissions.
     *
     * @since 1.0.0
     *
     * @param  Builder<FormSubmission>  $query  The query builder instance.
     *
     * @return Builder<FormSubmission> The modified query builder.
     */
    public function scopeRead( Builder $query ): Builder
    {
        return $query->where( 'is_read', true );
    }

    /**
     * Scopes a query to only include non-spam submissions.
     *
     * @since 1.0.0
     *
     * @param  Builder<FormSubmission>  $query  The query builder instance.
     *
     * @return Builder<FormSubmission> The modified query builder.
     */
    public function scopeNotSpam( Builder $query ): Builder
    {
        return $query->where( 'is_spam', false );
    }

    /**
     * Scopes a query to only include spam submissions.
     *
     * @since 1.0.0
     *
     * @param  Builder<FormSubmission>  $query  The query builder instance.
     *
     * @return Builder<FormSubmission> The modified query builder.
     */
    public function scopeSpam( Builder $query ): Builder
    {
        return $query->where( 'is_spam', true );
    }

    /**
     * Scopes a query to only include starred submissions.
     *
     * @since 1.0.0
     *
     * @param  Builder<FormSubmission>  $query  The query builder instance.
     *
     * @return Builder<FormSubmission> The modified query builder.
     */
    public function scopeStarred( Builder $query ): Builder
    {
        return $query->where( 'is_starred', true );
    }

    /**
     * Scopes a query to submissions matching the given status.
     *
     * @since 1.5.0
     *
     * @param  Builder<FormSubmission>  $query  The query builder instance.
     * @param  SubmissionStatus  $status  The status to filter by.
     *
     * @return Builder<FormSubmission> The modified query builder.
     */
    public function scopeWithStatus( Builder $query, SubmissionStatus $status ): Builder
    {
        return $query->where( 'status', $status->value );
    }

    /**
     * Scopes a query to only include quarantined submissions.
     *
     * @since 1.5.0
     *
     * @param  Builder<FormSubmission>  $query  The query builder instance.
     *
     * @return Builder<FormSubmission> The modified query builder.
     */
    public function scopeQuarantined( Builder $query ): Builder
    {
        return $query->where( 'status', SubmissionStatus::Quarantined->value );
    }

    /**
     * Scopes a query to only include recent submissions.
     *
     * @since 1.0.0
     *
     * @param  Builder<FormSubmission>  $query  The query builder instance.
     * @param  int  $days  Number of days to look back.
     *
     * @return Builder<FormSubmission> The modified query builder.
     */
    public function scopeRecent( Builder $query, int $days = 30 ): Builder
    {
        return $query->where( 'created_at', '>=', now()->subDays( $days ) );
    }

    // =========================================
    // Accessors
    // =========================================

    /**
     * Gets submission data as a collection keyed by field name.
     *
     * @since 1.0.0
     *
     * @return Collection<string, string> The submission data collection.
     */
    public function getDataAttribute(): Collection
    {
        return $this->values->mapWithKeys( function ( FormSubmissionValue $value ) {
            return [$value->field_name => $value->display_value];
        } );
    }

    /**
     * Gets submission data as an array.
     *
     * @since 1.0.0
     *
     * @return array<string, string> The submission data array.
     */
    public function getDataArrayAttribute(): array
    {
        return $this->data->toArray();
    }

    // =========================================
    // Methods
    // =========================================

    /**
     * Marks the submission as read.
     *
     * @since 1.0.0
     */
    public function markAsRead(): void
    {
        $this->update( ['is_read' => true] );
    }

    /**
     * Marks the submission as unread.
     *
     * @since 1.0.0
     */
    public function markAsUnread(): void
    {
        $this->update( ['is_read' => false] );
    }

    /**
     * Toggles the spam status.
     *
     * @since 1.0.0
     */
    public function toggleSpam(): void
    {
        $this->update( ['is_spam' => ! $this->is_spam] );
    }

    /**
     * Toggles the starred status.
     *
     * @since 1.0.0
     */
    public function toggleStar(): void
    {
        $this->update( ['is_starred' => ! $this->is_starred] );
    }

    /**
     * Determines whether the submission is currently quarantined.
     *
     * @since 1.5.0
     *
     * @return bool True if the submission is quarantined.
     */
    public function isQuarantined(): bool
    {
        return SubmissionStatus::Quarantined === $this->status;
    }

    /**
     * Quarantines the submission, optionally recording a spam score.
     *
     * @since 1.5.0
     *
     * @param  float|null  $spamScore  The spam score to record, if any.
     */
    public function quarantine( ?float $spamScore = null ): void
    {
        $attributes = ['status' => SubmissionStatus::Quarantined];

        if ( null !== $spamScore ) {
            $attributes['spam_score'] = $spamScore;
        }

        $this->update( $attributes );
    }

    /**
     * Releases the submission from quarantine back to the received state.
     *
     * @since 1.5.0
     */
    public function release(): void
    {
        $this->update( ['status' => SubmissionStatus::Received] );
    }

    /**
     * Gets a submitted value by field name.
     *
     * @since 1.0.0
     *
     * @param  string  $fieldName  The field name to retrieve.
     *
     * @return string|null The field value or null if not found.
     */
    public function getValue( string $fieldName ): ?string
    {
        return $this->values
            ->firstWhere( 'field_name', $fieldName )
            ?->display_value;
    }

    /**
     * Gets the first email value from the submission.
     *
     * @since 1.0.0
     *
     * @return string|null The email value or null if not found.
     */
    public function getEmailValue(): ?string
    {
        $emailValue = $this->values->first( function ( $value ) {
            return 'email' === $value->field_type;
        } );

        return $emailValue?->value;
    }

    /**
     * Creates a new factory instance for the model.
     *
     * @since 1.0.0
     *
     * @return FormSubmissionFactory The factory instance.
     */
    protected static function newFactory(): FormSubmissionFactory
    {
        return FormSubmissionFactory::new();
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
            'is_read'    => 'boolean',
            'is_spam'    => 'boolean',
            'is_starred' => 'boolean',
            'spam_score' => 'float',
            'status'     => SubmissionStatus::class,
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
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating( function ( FormSubmission $submission ): void {
            if ( empty( $submission->submission_number ) ) {
                $submission->submission_number = static::generateSubmissionNumber( $submission->form_id );
            }
        } );

        static::created( function ( FormSubmission $submission ): void {
            // Fire action hook for extensibility
            doAction( 'ap.forms.submission.created', $submission );

            // Dispatch Laravel event
            FormSubmitted::dispatch( $submission );
        } );

        static::updated( function ( FormSubmission $submission ): void {
            // Fire action hook for extensibility
            doAction( 'ap.forms.submission.updated', $submission );

            // Dispatch Laravel event
            SubmissionUpdated::dispatch( $submission );
        } );

        static::deleted( function ( FormSubmission $submission ): void {
            // Fire action hook for extensibility
            doAction( 'ap.forms.submission.deleted', $submission );

            // Dispatch Laravel event
            SubmissionDeleted::dispatch( $submission );
        } );
    }

    /**
     * Generates a unique submission number for a form.
     *
     * @since 1.0.0
     *
     * @param  int  $formId  The form ID to generate a submission number for.
     *
     * @return string The unique submission number.
     */
    protected static function generateSubmissionNumber( int $formId ): string
    {
        $format = config( 'artisanpack.forms.submissions.submission_number_format', 'FORM-{year}-{sequence}' );

        $count = static::where( 'form_id', $formId )
            ->whereYear( 'created_at', now()->year )
            ->count() + 1;

        $replacements = [
            '{year}'     => now()->format( 'Y' ),
            '{sequence}' => sprintf( '%05d', $count ),
            '{form_id}'  => $formId,
        ];

        return str_replace( array_keys( $replacements ), array_values( $replacements), $format);
    }
}
