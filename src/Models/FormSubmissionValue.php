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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read FormSubmission $submission
 * @property-read FormField|null $field
 * @property-read FormUpload|null $upload
 * @property-read string $display_value
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
     * their checked / unchecked text. Use getRawValue() where the stored value
     * matters.
     *
     * @since 1.0.0
     *
     * @return string The display value.
     */
    public function getDisplayValueAttribute(): string
    {
        return FieldTypes::handler( (string) $this->field_type )->display( $this );
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
     * @since 1.6.0
     *
     * @return list<string> The display labels, or an empty list for a scalar value.
     */
    public function getDisplayValuesAttribute(): array
    {
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
     * name. Used where the submitted value matters rather than its label,
     * such as conditional logic, webhooks and the JSON export.
     *
     * @since 1.6.0
     *
     * @return string The raw value.
     */
    public function getRawValue(): string
    {
        if ( ! empty( $this->value_array ) ) {
            return implode( ', ', $this->value_array );
        }

        if ( $this->upload_id && $this->upload ) {
            return $this->upload->original_name;
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
        return ! empty( $this->value_array );
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
            'value_array' => 'array',
            'created_at'  => 'datetime',
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

        return $this->getSourceField()?->getOptionLabel( $value ) ?? $value;
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
