<?php

/**
 * Form submission value model.
 *
 * Represents an individual submitted value with denormalized
 * field reference for data integrity after field changes.
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

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Database\Factories\FormSubmissionValueFactory;
use ArtisanPackUI\Forms\FieldTypes\AbstractFieldType;
use ArtisanPackUI\Forms\FieldTypes\ListFieldType;
use ArtisanPackUI\Forms\Support\FieldPrivacy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Form submission value model class.
 *
 * Represents an individual submitted value with denormalized
 * field reference for data integrity after field changes.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @property int $id
 * @property int $submission_id
 * @property int|null $field_id
 * @property string $field_name
 * @property string $field_label
 * @property string|null $field_admin_label
 * @property string $field_type
 * @property string|null $value
 * @property array|null $value_array
 * @property int|null $upload_id
 * @property bool|null $is_masked
 * @property int|null $redact_after_days
 * @property \Illuminate\Support\Carbon|null $redacted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read FormSubmission $submission
 * @property-read FormField|null $field
 * @property-read FormUpload|null $upload
 * @property-read string $display_value
 * @property-read string $masked_display_value
 * @property-read string $display_label
 * @property-read list<string> $display_values
 * @property-read bool $is_array_value
 * @property-read bool $is_file
 *
 * @since 1.0.0
 */
class FormSubmissionValue extends Model
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.0.0
     *
     * @var list<string>
     */
    protected $fillable = [
        'submission_id',
        'field_id',
        'field_name',
        'field_label',
        'field_admin_label',
        'field_type',
        'value',
        'value_array',
        'upload_id',
        'is_masked',
        'redact_after_days',
        'redacted_at',
        'created_at',
    ];

    // =========================================
    // Relationships
    // =========================================

    /**
     * Gets the submission that owns this value.
     *
     * @since 1.0.0
     *
     * @return BelongsTo<FormSubmission, $this> The submission relationship.
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo( FormSubmission::class, 'submission_id' );
    }

    /**
     * Gets the field that this value belongs to.
     *
     * @since 1.0.0
     *
     * @return BelongsTo<FormField, $this> The field relationship.
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo( FormField::class, 'field_id' );
    }

    /**
     * Gets the upload associated with this value.
     *
     * @since 1.0.0
     *
     * @return BelongsTo<FormUpload, $this> The upload relationship.
     */
    public function upload(): BelongsTo
    {
        return $this->belongsTo( FormUpload::class, 'upload_id' );
    }

    // =========================================
    // Accessors
    // =========================================

    /**
     * Gets the human-readable display value.
     *
     * Asks the field type's handler, which by default shows array values as
     * their option labels and file values as the uploaded file name. Choice
     * fields show the chosen option label (falling back to the raw value when
     * the field or option no longer exists) and checkboxes and toggles show
     * their checked / unchecked text. A value redacted by
     * `forms:prune-submissions` shows as "[Redacted]". Use getRawValue() where
     * the stored value matters.
     *
     * @since 1.0.0
     *
     * @return string The display value.
     */
    public function getDisplayValueAttribute(): string
    {
        if ( $this->isRedacted() ) {
            return __( '[Redacted]' );
        }

        return FieldTypes::handler( (string) $this->field_type )->display( $this );
    }

    /**
     * Gets the display value as HTML, for values that hold markup.
     *
     * A rich text textarea's sanitized HTML (see TextareaFieldType); null for
     * every other value, whose display value is plain text to escape. A
     * redacted value has none.
     *
     * @since 1.6.0
     *
     * @return string|null Safe HTML, or null for plain text.
     */
    public function getDisplayHtmlAttribute(): ?string
    {
        if ( $this->isRedacted() ) {
            return null;
        }

        $handler = FieldTypes::handler( (string) $this->field_type );

        return $handler instanceof AbstractFieldType ? $handler->displayHtml( $this ) : null;
    }

    /**
     * Gets the display value with all but its last characters masked.
     *
     * A file value is masked in full, so no part of a file name shows.
     *
     * @since 1.6.0
     *
     * @return string The masked display value, such as `••••1234`.
     */
    public function getMaskedDisplayValueAttribute(): string
    {
        $display = $this->display_value;

        if ( 'file' === $this->field_type ) {
            return '' === $display || $this->isRedacted() ? '' : FieldPrivacy::MASK;
        }

        return FieldPrivacy::mask( $display );
    }

    /**
     * Gets the label shown for the value in submissions.
     *
     * The field's admin label as it was when the value was submitted, falling
     * back to its label and then its name.
     *
     * @since 1.6.0
     *
     * @return string The label.
     */
    public function getDisplayLabelAttribute(): string
    {
        foreach ( [$this->field_admin_label, $this->field_label] as $label ) {
            if ( is_string( $label ) && '' !== trim( $label ) ) {
                return $label;
            }
        }

        return (string) $this->field_name;
    }

    /**
     * Gets the label shown for the value in a notification.
     *
     * Admin notifications prefer the admin label; other notifications, such as
     * the autoresponder sent to the visitor, keep the public label. The field's
     * current label is used while the field exists.
     *
     * @since 1.6.0
     *
     * @param  bool  $preferAdminLabel  Whether to use the admin label when one was set.
     *
     * @return string The label.
     */
    public function getNotificationLabel( bool $preferAdminLabel = true ): string
    {
        if ( $preferAdminLabel ) {
            $adminLabel = null === $this->field ? $this->field_admin_label : $this->field->getStoredAdminLabel();

            if ( is_string( $adminLabel ) && '' !== trim( $adminLabel ) ) {
                return $adminLabel;
            }
        }

        return $this->field?->label ?? $this->field_name;
    }

    /**
     * Gets the display labels for an array value, one per selected item.
     *
     * A file value gives its file names (since 1.6.0).
     *
     * @since 1.6.0
     *
     * @return list<string> The display labels, or an empty list for a scalar value.
     */
    public function getDisplayValuesAttribute(): array
    {
        if ( 'file' === $this->field_type ) {
            return array_map( fn ( FormUpload $upload ): string => $upload->original_name, $this->getUploads() );
        }

        if ( empty( $this->value_array ) ) {
            return [];
        }

        $labels = [];

        foreach ( $this->value_array as $item ) {
            $labels[] = is_scalar( $item ) ? $this->getChoiceLabel( (string) $item ) : (string) json_encode( $item );
        }

        return $labels;
    }

    /**
     * Gets the stored value as a string, without mapping it to labels.
     *
     * Array values are comma-joined and file values give the uploaded file
     * names, comma-joined. Used where the submitted value matters rather than its label,
     * such as conditional logic, webhooks and the JSON export.
     *
     * @since 1.6.0
     *
     * @return string The raw value.
     */
    public function getRawValue(): string
    {
        if ( 'file' === $this->field_type ) {
            return implode( ', ', array_map( fn ( FormUpload $upload ): string => $upload->original_name, $this->getUploads() ) );
        }

        if ( ! empty( $this->value_array ) ) {
            $hasRows = false;
            $items   = [];

            // A list's rows (since 1.6.0) are joined with semicolons, each row's filled cells with commas
            foreach ( $this->value_array as $item ) {
                if ( is_array( $item ) ) {
                    $hasRows = true;
                    $items[] = implode( ', ', array_filter( array_map( 'strval', array_filter( $item, 'is_scalar' ) ), fn ( string $cell ): bool => '' !== $cell ) );
                } else {
                    $items[] = is_scalar( $item ) ? (string) $item : '';
                }
            }

            return implode( $hasRows ? '; ' : ', ', $items );
        }

        if ( $this->upload_id && $this->upload ) {
            return $this->upload->original_name;
        }

        return $this->value ?? '';
    }

    /**
     * Gets the stored value in its submitted shape.
     *
     * A composite field's value is a map of sub-input keys, and a list
     * field's a list of rows keyed by column, which are kept as arrays so
     * webhooks, the JSON export and notification conditions see each part.
     * Every other value is getRawValue().
     *
     * @since 1.6.0
     *
     * @return array<string, mixed>|string The value.
     */
    public function getStructuredValue(): array|string
    {
        if ( is_array( $this->value_array ) && [] !== $this->value_array && ( ! array_is_list( $this->value_array ) || ListFieldType::TYPE === $this->field_type ) ) {
            return $this->value_array;
        }

        return $this->getRawValue();
    }

    /**
     * Gets the value as conditional logic reads it.
     *
     * A list of choices stays a list, so `includes` / `not_includes` rules
     * can test it; a composite or consent value stays a map; anything else
     * is its stored text. A file value is its file names, as in
     * getRawValue().
     *
     * @since 1.6.0
     *
     * @return array<array-key, mixed>|string The value.
     */
    public function getConditionValue(): array|string
    {
        if ( 'file' === $this->field_type ) {
            return $this->getRawValue();
        }

        if ( is_array( $this->value_array ) && [] !== $this->value_array ) {
            return $this->value_array;
        }

        return $this->value ?? '';
    }

    /**
     * Checks if this value is an array value.
     *
     * @since 1.0.0
     *
     * @return bool True if this is an array value.
     */
    public function getIsArrayValueAttribute(): bool
    {
        return 'file' !== $this->field_type && ! empty( $this->value_array );
    }

    /**
     * Gets the uploads a file value holds, in upload order.
     *
     * Multi-file values list their upload IDs in `value_array` (since 1.6.0);
     * single-file values point at their upload with `upload_id`. Only the
     * value's own submission's uploads are returned. Uses the submission's
     * `uploads` relation when it is loaded.
     *
     * @since 1.6.0
     *
     * @return list<FormUpload> The uploads; empty when none remain.
     */
    public function getUploads(): array
    {
        if ( 'file' !== $this->field_type ) {
            return [];
        }

        $ids = is_array( $this->value_array ) && [] !== $this->value_array && array_is_list( $this->value_array )
            ? array_values( array_filter( array_map( 'intval', $this->value_array ) ) )
            : array_filter( [$this->upload_id] );

        if ( [] === $ids ) {
            return [];
        }

        if ( [$this->upload_id] === $ids && $this->upload ) {
            return [$this->upload];
        }

        $uploads = $this->relationLoaded( 'submission' ) && $this->submission?->relationLoaded( 'uploads' )
            ? $this->submission->uploads->where( 'submission_id', $this->submission_id )->whereIn( 'id', $ids )
            : FormUpload::query()->where( 'submission_id', $this->submission_id )->whereIn( 'id', $ids )->get();

        return $uploads->sortBy( fn ( FormUpload $upload ): int|false => array_search( $upload->id, $ids, true ) )->values()->all();
    }

    /**
     * Checks if this value represents a file upload.
     *
     * @since 1.0.0
     *
     * @return bool True if this value is a file upload.
     */
    public function getIsFileAttribute(): bool
    {
        return 'file' === $this->field_type && null !== $this->upload_id;
    }

    /**
     * Checks whether the value's field masks it in the admin.
     *
     * Reads the field's current setting. Once the field is deleted, the
     * setting copied onto the value when it was stored (`is_masked`) is used,
     * so deleting a masked field does not reveal its values.
     *
     * @since 1.6.0
     *
     * @return bool True when masked.
     */
    public function isMasked(): bool
    {
        $field = $this->getSourceField();

        if ( null !== $field ) {
            return $field->isMaskedInAdmin();
        }

        return true === (bool) $this->is_masked;
    }

    /**
     * Checks whether `forms:prune-submissions` cleared the value.
     *
     * @since 1.6.0
     *
     * @return bool True when redacted.
     */
    public function isRedacted(): bool
    {
        return null !== $this->redacted_at;
    }

    /**
     * Creates a new factory instance for the model.
     *
     * @since 1.0.0
     *
     * @return FormSubmissionValueFactory The factory instance.
     */
    protected static function newFactory(): FormSubmissionValueFactory
    {
        return FormSubmissionValueFactory::new();
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
            'value_array'       => 'array',
            'is_masked'         => 'boolean',
            'redact_after_days' => 'integer',
            'redacted_at'       => 'datetime',
            'created_at'        => 'datetime',
        ];
    }

    /**
     * Maps a choice field's option value to its label.
     *
     * Non-choice values, and values whose field or option was deleted, are
     * returned unchanged.
     *
     * @since 1.6.0
     *
     * @param  string  $value  The stored option value.
     *
     * @return string The option label, or the value itself.
     */
    protected function getChoiceLabel( string $value ): string
    {
        if ( '' === $value || ! in_array( $this->field_type, FormField::CHOICE_TYPES, true ) ) {
            return $value;
        }

        return $this->getSourceField()?->getChoiceDisplayLabel( $value ) ?? $value;
    }

    /**
     * Gets the field this value was submitted for, if it still exists.
     *
     * @since 1.6.0
     *
     * @return FormField|null The field, or null when it was deleted.
     */
    protected function getSourceField(): ?FormField
    {
        return null === $this->field_id ? null : $this->field;
    }
}
