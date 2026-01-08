<?php

/**
 * Form upload model.
 *
 * Represents metadata about a file uploaded through a form submission.
 * Includes storage location, file info, and helper methods.
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

use ArtisanPackUI\Forms\Database\Factories\FormUploadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Form upload model class.
 *
 * Represents metadata about a file uploaded through a form submission.
 * Includes storage location, file info, and helper methods.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @property int $id
 * @property int $submission_id
 * @property int|null $field_id
 * @property string $original_name
 * @property string $stored_name
 * @property string $disk
 * @property string $path
 * @property string|null $mime_type
 * @property int $size
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read FormSubmission $submission
 * @property-read FormField|null $field
 * @property-read string $url
 * @property-read string $full_path
 * @property-read string $human_size
 * @property-read string $extension
 * @property-read bool $is_image
 *
 * @since 1.0.0
 */
class FormUpload extends Model
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
        'submission_id',
        'field_id',
        'original_name',
        'stored_name',
        'disk',
        'path',
        'mime_type',
        'size',
    ];

    // =========================================
    // Relationships
    // =========================================

    /**
     * Gets the submission that owns this upload.
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
     * Gets the field that this upload belongs to.
     *
     * @since 1.0.0
     *
     * @return BelongsTo<FormField, $this> The field relationship.
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo( FormField::class, 'field_id' );
    }

    // =========================================
    // Accessors
    // =========================================

    /**
     * Gets the public URL for this upload.
     *
     * @since 1.0.0
     *
     * @return string The public URL.
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk( $this->disk )->url( $this->path );
    }

    /**
     * Gets the full filesystem path for this upload.
     *
     * @since 1.0.0
     *
     * @return string The full filesystem path.
     */
    public function getFullPathAttribute(): string
    {
        return Storage::disk( $this->disk )->path( $this->path );
    }

    /**
     * Gets the human-readable file size.
     *
     * @since 1.0.0
     *
     * @return string The human-readable file size.
     */
    public function getHumanSizeAttribute(): string
    {
        return $this->humanFileSize();
    }

    /**
     * Gets the human-readable file size as a method.
     *
     * @since 1.0.0
     *
     * @return string The human-readable file size.
     */
    public function humanFileSize(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ( $i = 0; $bytes > 1024 && $i < count( $units ) - 1; $i++ ) {
            $bytes /= 1024;
        }

        return round( $bytes, 2 ) . ' ' . $units[ $i ];
    }

    /**
     * Gets the file extension.
     *
     * @since 1.0.0
     *
     * @return string The file extension.
     */
    public function getExtensionAttribute(): string
    {
        return pathinfo( $this->original_name, PATHINFO_EXTENSION );
    }

    /**
     * Checks if this upload is an image.
     *
     * @since 1.0.0
     *
     * @return bool True if this upload is an image.
     */
    public function getIsImageAttribute(): bool
    {
        return str_starts_with( $this->mime_type ?? '', 'image/' );
    }

    // =========================================
    // Methods
    // =========================================

    /**
     * Downloads this file.
     *
     * @since 1.0.0
     *
     * @return StreamedResponse The streamed download response.
     */
    public function download(): StreamedResponse
    {
        return Storage::disk( $this->disk )->download(
            $this->path,
            $this->original_name,
        );
    }

    /**
     * Deletes this upload including the file from storage.
     *
     * Deletes the database record first to avoid orphaned records
     * if the delete fails. The file is only removed after successful
     * database deletion.
     *
     * @since 1.0.0
     *
     * @return bool|null True on success, false on failure, null if not deleted.
     */
    public function delete(): ?bool
    {
        // Store path and disk before deleting the model
        $disk = $this->disk;
        $path = $this->path;

        // Delete database record first
        $result = parent::delete();

        // Only delete the file if the database record was successfully deleted
        if ( $result ) {
            Storage::disk( $disk )->delete( $path );
        }

        return $result;
    }

    /**
     * Creates a new factory instance for the model.
     *
     * @since 1.0.0
     *
     * @return FormUploadFactory The factory instance.
     */
    protected static function newFactory(): FormUploadFactory
    {
        return FormUploadFactory::new();
    }
}
