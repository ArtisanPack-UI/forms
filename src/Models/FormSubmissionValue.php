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
 * @property string $field_type
 * @property string|null $value
 * @property array|null $value_array
 * @property int|null $upload_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read FormSubmission $submission
 * @property-read FormField|null $field
 * @property-read FormUpload|null $upload
 * @property-read string $display_value
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
     * Gets the display value, handling arrays and file uploads.
     *
     * @since 1.0.0
     *
     * @return string The display value.
     */
    public function getDisplayValueAttribute(): string
    {
        // Handle array values (checkbox groups, multi-select)
        if ( ! empty( $this->value_array ) ) {
            return implode( ', ', $this->value_array );
        }

        // Handle file uploads
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
}
