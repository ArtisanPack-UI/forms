<?php

/**
 * Prune form submissions artisan command.
 *
 * Deletes form submissions older than the configured retention period,
 * including associated uploaded files from storage.
 * Can be scheduled to run daily using Laravel's task scheduler.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Console\Commands;

use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Prune form submissions artisan command class.
 *
 * Deletes form submissions older than the configured retention period,
 * including associated uploaded files from storage.
 * Can be scheduled to run daily using Laravel's task scheduler.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class PruneFormSubmissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $signature = 'forms:prune-submissions
                            {--days= : Override the retention days from config}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $description = 'Redact field values past their field\'s redaction period, then delete form submissions and associated files older than the retention period';

    /**
     * Tracks the number of files deleted.
     *
     * @since 1.0.0
     */
    protected int $filesDeleted = 0;

    /**
     * Tracks the number of files that failed to delete.
     *
     * @since 1.0.0
     */
    protected int $filesFailed = 0;

    /**
     * Executes the console command.
     *
     * @since 1.0.0
     *
     * @return int The command exit status code.
     */
    public function handle(): int
    {
        $retentionDays = $this->getRetentionDays();

        if ( null !== $retentionDays && $retentionDays <= 0 ) {
            $this->error( 'Retention days must be a positive integer.' );

            return self::FAILURE;
        }

        $isDryRun = (bool) $this->option( 'dry-run' );

        // Fields with "Redact after N days" are handled whatever the retention period
        $this->redactExpiredValues( $isDryRun );

        if ( null === $retentionDays ) {
            $this->info( 'Retention days is not configured. Submissions will be kept forever.' );

            return self::SUCCESS;
        }

        $cutoffDate = now()->subDays( $retentionDays );

        // Get submission IDs to delete
        $submissionIds = $this->getExpiredSubmissionIds( $cutoffDate );

        if ( $submissionIds->isEmpty() ) {
            $this->info( 'No submissions found older than ' . $retentionDays . ' days.' );

            return self::SUCCESS;
        }

        // Get associated uploads
        $uploads = $this->getUploadsForSubmissions( $submissionIds );

        if ( $isDryRun ) {
            return $this->handleDryRun( $submissionIds, $uploads, $retentionDays );
        }

        return $this->handlePrune( $submissionIds, $uploads, $retentionDays );
    }

    /**
     * Clears stored values of fields whose "Redact after N days" period has passed.
     *
     * The value is emptied and `redacted_at` set, so the admin shows it as
     * redacted and later runs skip it. Age is measured from the submission.
     *
     * @since 1.6.0
     *
     * @param bool $isDryRun Whether to only count the values.
     *
     * @return int The number of values redacted, or that would be.
     */
    protected function redactExpiredValues( bool $isDryRun ): int
    {
        $total = 0;

        // field_config is JSON, so the setting is read in PHP to stay portable across databases
        foreach ( FormField::query()->whereNotNull( 'field_config' )->lazyById() as $field ) {
            $days = $field->getRedactAfterDays();

            if ( null === $days ) {
                continue;
            }

            $query = FormSubmissionValue::query()
                ->where( 'field_id', $field->getKey() )
                ->whereNull( 'redacted_at' )
                ->whereIn( 'submission_id', FormSubmission::query()->select( 'id' )->where( 'created_at', '<', now()->subDays( $days ) ) );

            $total += $isDryRun
                ? $query->count()
                : $query->update( ['value' => null, 'value_array' => null, 'redacted_at' => now()] );
        }

        if ( $total > 0 ) {
            $this->info( $isDryRun
                ? "[Dry Run] Would redact {$total} field value(s) past their field's redaction period."
                : "Redacted {$total} field value(s) past their field's redaction period." );
        }

        return $total;
    }

    /**
     * Gets submission IDs that are older than the cutoff date.
     *
     * @since 1.0.0
     *
     * @param DateTimeInterface $cutoffDate The cutoff date for expired submissions.
     *
     * @return Collection<int, int> Collection of submission IDs to delete.
     */
    protected function getExpiredSubmissionIds( DateTimeInterface $cutoffDate ): Collection
    {
        return DB::table( 'form_submissions' )
            ->where( 'created_at', '<', $cutoffDate )
            ->pluck( 'id' );
    }

    /**
     * Gets all uploads associated with the given submission IDs.
     *
     * @since 1.0.0
     *
     * @param Collection<int, int> $submissionIds Collection of submission IDs.
     *
     * @return Collection<int, object> Collection of upload records.
     */
    protected function getUploadsForSubmissions( Collection $submissionIds ): Collection
    {
        return DB::table( 'form_uploads' )
            ->whereIn( 'submission_id', $submissionIds )
            ->select( ['id', 'submission_id', 'disk', 'path', 'original_name'] )
            ->get();
    }

    /**
     * Handles dry run - shows what would be deleted without making changes.
     *
     * @since 1.0.0
     *
     * @param Collection<int, int>    $submissionIds Collection of submission IDs to delete.
     * @param Collection<int, object> $uploads       Collection of upload records.
     * @param int                     $retentionDays The retention period in days.
     *
     * @return int The command exit status code.
     */
    protected function handleDryRun( Collection $submissionIds, Collection $uploads, int $retentionDays ): int
    {
        $submissionCount = $submissionIds->count();
        $fileCount       = $uploads->count();

        $this->info( "[Dry Run] Would delete {$submissionCount} submission(s) older than {$retentionDays} days." );

        if ( $fileCount > 0 ) {
            $this->info( "[Dry Run] Would delete {$fileCount} associated file(s):" );

            foreach ( $uploads as $upload ) {
                $this->line( "  - [{$upload->disk}] {$upload->path} ({$upload->original_name})" );
            }
        }

        return self::SUCCESS;
    }

    /**
     * Handles the actual pruning of submissions and files.
     *
     * @since 1.0.0
     *
     * @param Collection<int, int>    $submissionIds Collection of submission IDs to delete.
     * @param Collection<int, object> $uploads       Collection of upload records.
     * @param int                     $retentionDays The retention period in days.
     *
     * @return int The command exit status code.
     */
    protected function handlePrune( Collection $submissionIds, Collection $uploads, int $retentionDays ): int
    {
        $submissionCount = $submissionIds->count();

        try {
            DB::beginTransaction();

            // Delete physical files first
            $this->deleteUploadedFiles( $uploads );

            // Delete submissions (cascades to form_uploads and form_submission_values)
            DB::table( 'form_submissions' )
                ->whereIn( 'id', $submissionIds )
                ->delete();

            DB::commit();

            $this->info( "Deleted {$submissionCount} submission(s) older than {$retentionDays} days." );

            if ( $this->filesDeleted > 0 ) {
                $this->info( "Deleted {$this->filesDeleted} associated file(s) from storage." );
            }

            if ( $this->filesFailed > 0 ) {
                $this->warn( "Failed to delete {$this->filesFailed} file(s). Check logs for details." );
            }

            return self::SUCCESS;
        } catch ( Throwable $e ) {
            DB::rollBack();

            $this->error( 'Failed to prune submissions: ' . $e->getMessage() );

            Log::error( 'Forms prune command failed', [
                'error'          => $e->getMessage(),
                'submission_ids' => $submissionIds->toArray(),
            ] );

            return self::FAILURE;
        }
    }

    /**
     * Deletes uploaded files from storage.
     *
     * @since 1.0.0
     *
     * @param Collection<int, object> $uploads Collection of upload records.
     */
    protected function deleteUploadedFiles( Collection $uploads ): void
    {
        foreach ( $uploads as $upload ) {
            $this->deleteFile( $upload );
        }
    }

    /**
     * Deletes a single file from storage.
     *
     * @since 1.0.0
     *
     * @param object $upload The upload record to delete.
     */
    protected function deleteFile( object $upload ): void
    {
        try {
            $disk = Storage::disk( $upload->disk );

            if ( $disk->exists( $upload->path ) ) {
                $disk->delete( $upload->path );
                $this->filesDeleted++;

                Log::info( 'Pruned form upload file', [
                    'upload_id' => $upload->id,
                    'disk'      => $upload->disk,
                    'path'      => $upload->path,
                ] );
            } else {
                // File doesn't exist, count as deleted (already gone)
                $this->filesDeleted++;

                Log::warning( 'Form upload file not found during prune', [
                    'upload_id' => $upload->id,
                    'disk'      => $upload->disk,
                    'path'      => $upload->path,
                ] );
            }
        } catch ( Throwable $e ) {
            $this->filesFailed++;

            Log::error( 'Failed to delete form upload file during prune', [
                'upload_id' => $upload->id,
                'disk'      => $upload->disk,
                'path'      => $upload->path,
                'error'     => $e->getMessage(),
            ] );
        }
    }

    /**
     * Gets the retention days from option or config.
     *
     * @since 1.0.0
     *
     * @return int|null The retention days or null if not configured.
     */
    protected function getRetentionDays(): ?int
    {
        $days = $this->option( 'days' );

        if ( null !== $days ) {
            return (int) $days;
        }

        $configDays = config( 'artisanpack.forms.submissions.retention_days' );

        if ( null === $configDays ) {
            return null;
        }

        return (int) $configDays;
    }
}
