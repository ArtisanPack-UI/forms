<?php

/**
 * Send webhook job.
 *
 * Queued job that sends a webhook notification when a form submission is created.
 * Supports webhook secret verification for secure integrations.
 *
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Jobs;

use ArtisanPackUI\Forms\Models\FormSubmission;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Send webhook job class.
 *
 * Queued job that sends a webhook notification when a form submission is created.
 * Supports webhook secret verification for secure integrations.
 *
 *
 * @since      1.0.0
 */
class SendWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @since 1.0.0
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * Uses exponential backoff: 10 seconds, 60 seconds, then 5 minutes.
     *
     * @since 1.0.0
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * The form submission to send.
     *
     * @since 1.0.0
     */
    public FormSubmission $submission;

    /**
     * The webhook URL to send to.
     *
     * @since 1.0.0
     */
    public string $url;

    /**
     * The optional webhook secret for signature verification.
     *
     * @since 1.0.0
     */
    public ?string $secret;

    /**
     * Creates a new job instance.
     *
     * @since 1.0.0
     *
     * @param  FormSubmission  $submission  The form submission to send.
     * @param  string  $url  The webhook URL.
     * @param  string|null  $secret  Optional secret for signature verification.
     */
    public function __construct( FormSubmission $submission, string $url, ?string $secret = null )
    {
        $this->submission = $submission;
        $this->url        = $url;
        $this->secret     = $secret;
    }

    /**
     * Executes the job.
     *
     * Builds the payload, sends the webhook request, and handles the response.
     *
     * @since 1.0.0
     */
    public function handle(): void
    {
        // Ensure relationships are loaded
        $this->submission->loadMissing( ['form', 'values.field'] );

        // Build the webhook payload
        $payload = $this->buildPayload();

        // Build headers
        $headers = $this->buildHeaders( $payload );

        try {
            $response = Http::timeout( 30 )
                ->withHeaders( $headers )
                ->post( $this->url, $payload );

            if ( $response->successful() ) {
                Log::info( 'Webhook sent successfully', [
                    'submission_id' => $this->submission->id,
                    'form_id'       => $this->submission->form_id,
                    'url'           => $this->url,
                    'status'        => $response->status(),
                ] );
            } else {
                Log::warning( 'Webhook received non-success response', [
                    'submission_id' => $this->submission->id,
                    'form_id'       => $this->submission->form_id,
                    'url'           => $this->url,
                    'status'        => $response->status(),
                    'body'          => $response->body(),
                ] );

                // Throw exception to trigger retry for server errors
                if ( $response->serverError() ) {
                    throw new RuntimeException( "Webhook failed with status {$response->status()}" );
                }
            }
        } catch ( Exception $e ) {
            Log::error( 'Failed to send webhook', [
                'submission_id' => $this->submission->id,
                'form_id'       => $this->submission->form_id,
                'url'           => $this->url,
                'error'         => $e->getMessage(),
            ] );

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handles a job failure.
     *
     * Logs an error when the job fails permanently after all retry attempts.
     *
     * @since 1.0.0
     *
     * @param  Throwable  $exception  The exception that caused the failure.
     */
    public function failed( Throwable $exception ): void
    {
        Log::error( 'Webhook job failed permanently', [
            'submission_id' => $this->submission->id,
            'form_id'       => $this->submission->form_id,
            'url'           => $this->url,
            'error'         => $exception->getMessage(),
        ] );
    }

    /**
     * Gets the tags that should be assigned to the job.
     *
     * Tags are used for monitoring and filtering jobs in queue dashboards.
     *
     * @since 1.0.0
     *
     * @return array<int, string> The job tags.
     */
    public function tags(): array
    {
        return [
            'webhook',
            'submission:' . $this->submission->id,
            'form:' . $this->submission->form_id,
        ];
    }

    /**
     * Builds the webhook payload.
     *
     * Creates the JSON payload including form data, submission data, and metadata.
     * Respects privacy settings for IP address and user agent.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed> The webhook payload.
     */
    protected function buildPayload(): array
    {
        // Build metadata, respecting privacy settings
        $metadata = [
            'page_url'     => $this->submission->page_url,
            'referrer_url' => $this->submission->referrer_url,
        ];

        // Only include IP address if explicitly enabled in config
        if ( config( 'artisanpack.forms.privacy.include_ip_address', false ) ) {
            $metadata['ip_address'] = $this->submission->ip_address;
        }

        // Only include user agent if explicitly enabled in config
        if ( config( 'artisanpack.forms.privacy.include_user_agent', false ) ) {
            $metadata['user_agent'] = $this->submission->user_agent;
        }

        $payload = [
            'event'     => 'submission.created',
            'timestamp' => now()->toIso8601String(),
            'form'      => [
                'id'   => $this->submission->form->id,
                'name' => $this->submission->form->name,
                'slug' => $this->submission->form->slug,
            ],
            'submission' => [
                'id'                => $this->submission->id,
                'submission_number' => $this->submission->submission_number,
                'created_at'        => $this->submission->created_at->toIso8601String(),
                'data'              => $this->submission->data_array,
                'metadata'          => $metadata,
            ],
        ];

        // Apply filter hook to allow modifying the webhook payload
        $payload = applyFilters( 'ap.forms.webhookPayload', $payload, $this->submission );

        return $payload;
    }

    /**
     * Builds the request headers.
     *
     * Includes content type, user agent, event type, and optional HMAC signature.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $payload  The webhook payload for signature generation.
     *
     * @return array<string, string> The request headers.
     */
    protected function buildHeaders( array $payload ): array
    {
        $headers = [
            'Content-Type'     => 'application/json',
            'User-Agent'       => 'ArtisanPackUI-Forms/1.0',
            'X-Forms-Event'    => 'submission.created',
            'X-Forms-Delivery' => (string) $this->submission->id,
        ];

        // Add signature if secret is configured
        if ( null !== $this->secret ) {
            $signature                        = $this->generateSignature( $payload );
            $headers['X-Forms-Signature']     = $signature;
            $headers['X-Forms-Signature-256'] = 'sha256=' . $signature;
        }

        return $headers;
    }

    /**
     * Generates HMAC signature for the payload.
     *
     * Creates a SHA256 HMAC signature using the webhook secret.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $payload  The webhook payload to sign.
     *
     * @return string The HMAC signature.
     */
    protected function generateSignature( array $payload ): string
    {
        $jsonPayload = json_encode( $payload, JSON_THROW_ON_ERROR );

        return hash_hmac( 'sha256', $jsonPayload, $this->secret );
    }
}
