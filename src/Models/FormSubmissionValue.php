<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Models;

use ArtisanPackUI\Forms\Database\Factories\FormSubmissionValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FormSubmissionValue Model
 *
 * Represents an individual submitted value with denormalized
 * field reference for data integrity after field changes.
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
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): FormSubmissionValueFactory
    {
        return FormSubmissionValueFactory::new();
    }

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_array' => 'array',
            'created_at' => 'datetime',
        ];
    }

    // =========================================
    // Relationships
    // =========================================

    /**
     * Get the submission that owns this value.
     *
     * @return BelongsTo<FormSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }

    /**
     * Get the field that this value belongs to.
     *
     * @return BelongsTo<FormField, $this>
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'field_id');
    }

    /**
     * Get the upload associated with this value.
     *
     * @return BelongsTo<FormUpload, $this>
     */
    public function upload(): BelongsTo
    {
        return $this->belongsTo(FormUpload::class, 'upload_id');
    }

    // =========================================
    // Accessors
    // =========================================

    /**
     * Get the display value, handling arrays and file uploads.
     */
    public function getDisplayValueAttribute(): string
    {
        // Handle array values (checkbox groups, multi-select)
        if (! empty($this->value_array)) {
            return implode(', ', $this->value_array);
        }

        // Handle file uploads
        if ($this->upload_id && $this->upload) {
            return $this->upload->original_name;
        }

        return $this->value ?? '';
    }

    /**
     * Check if this value is an array value.
     */
    public function getIsArrayValueAttribute(): bool
    {
        return ! empty($this->value_array);
    }

    /**
     * Check if this value represents a file upload.
     */
    public function getIsFileAttribute(): bool
    {
        return $this->field_type === 'file' && $this->upload_id !== null;
    }
}
