<?php

/**
 * Files uploaded to "Do not store" fields.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormUpload;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Keeps a "Do not store" file only until its notifications and webhooks are sent.
 *
 * A file uploaded to a file field with "Do not store" on is stored like any
 * other upload, so the notification and webhook jobs of its submission can
 * read it, but no submission value points at it: the value travels in the
 * jobs' encrypted payloads instead (see FormSubmission::withTransientValues()).
 * The upload's `pending_deliveries` counts what still needs the file. The
 * submission holds it while it is saved and its jobs are dispatched, each job
 * holds it from dispatch until it has run (or failed for the last time), and
 * whoever releases the last hold deletes the file and its upload record.
 *
 * @since 1.6.0
 */
class TransientUploads
{
    /**
     * Gets the IDs of the uploads held by "Do not store" file values.
     *
     * @since 1.6.0
     *
     * @param  list<array<string, mixed>>  $values  FormSubmissionValue attributes, as FormSubmission::getTransientValues() returns them.
     *
     * @return list<int> The upload IDs.
     */
    public static function idsIn( array $values ): array
    {
        $ids = [];

        foreach ( $values as $attributes ) {
            if ( 'file' !== ( $attributes['field_type'] ?? null ) ) {
                continue;
            }

            $list = $attributes['value_array'] ?? null;

            foreach ( is_array( $list ) && [] !== $list ? $list : [$attributes['upload_id'] ?? null] as $id ) {
                if ( is_numeric( $id ) && (int) $id > 0 ) {
                    $ids[] = (int) $id;
                }
            }
        }

        return array_values( array_unique( $ids ) );
    }

    /**
     * Gets the "Do not store" uploads of a submission's loaded values.
     *
     * Read from the transient values a delivery job puts back on the
     * submission, for the job to attach or send the files.
     *
     * @since 1.6.0
     *
     * @param  FormSubmission  $submission  The submission.
     *
     * @return list<FormUpload> The uploads still on their disks.
     */
    public static function uploadsOf( FormSubmission $submission ): array
    {
        $uploads = [];

        foreach ( $submission->values as $value ) {
            if ( $value->exists || 'file' !== $value->field_type ) {
                continue;
            }

            foreach ( $value->getUploads() as $upload ) {
                if ( $upload->isTransient() && Storage::disk( $upload->disk )->exists( $upload->path ) ) {
                    $uploads[] = $upload;
                }
            }
        }

        return $uploads;
    }

    /**
     * Adds a hold on uploads that are still waiting for their deliveries.
     *
     * @since 1.6.0
     *
     * @param  list<int>  $ids  The upload IDs.
     */
    public static function hold( array $ids ): void
    {
        if ( [] === $ids ) {
            return;
        }

        FormUpload::query()->whereKey( $ids )->whereNotNull( 'pending_deliveries' )->increment( 'pending_deliveries' );
    }

    /**
     * Releases a hold on uploads, deleting each one nothing holds any more.
     *
     * @since 1.6.0
     *
     * @param  list<int>  $ids  The upload IDs.
     */
    public static function release( array $ids ): void
    {
        if ( [] === $ids ) {
            return;
        }

        FormUpload::query()->whereKey( $ids )->where( 'pending_deliveries', '>', 0 )->decrement( 'pending_deliveries' );

        static::delete( FormUpload::query()->whereKey( $ids )->where( 'pending_deliveries', '<=', 0 ) );
    }

    /**
     * Deletes "Do not store" files still held after a cutoff.
     *
     * A safety net for a job that never ran or never reported back, used by
     * `forms:prune-submissions`.
     *
     * @since 1.6.0
     *
     * @param  DateTimeInterface  $before  Uploads created before this are deleted.
     * @param  bool  $isDryRun  Whether to only count them.
     *
     * @return int The number of uploads deleted, or that would be.
     */
    public static function prune( DateTimeInterface $before, bool $isDryRun = false ): int
    {
        $query = FormUpload::query()->whereNotNull( 'pending_deliveries' )->where( 'created_at', '<', $before );

        return $isDryRun ? $query->count() : static::delete( $query );
    }

    /**
     * Deletes the uploads a query finds, with their files.
     *
     * A file that cannot be deleted is logged and the rest are still deleted.
     *
     * @since 1.6.0
     *
     * @param  Builder<FormUpload>  $query  The query.
     *
     * @return int The number of uploads deleted.
     */
    protected static function delete( Builder $query ): int
    {
        $deleted = 0;

        foreach ( $query->get() as $upload ) {
            try {
                $deleted += $upload->delete() ? 1 : 0;
            } catch ( Throwable $exception ) {
                Log::warning( 'ArtisanPackUI Forms: "Do not store" file not removed', [
                    'upload_id' => $upload->id,
                    'error'     => $exception::class,
                ] );
            }
        }

        return $deleted;
    }
}
