<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Jobs;

use ArtisanPackUI\Forms\Models\FormSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SendWebhook Job
 *
 * Queued job that sends a webhook notification when a form submission is created.
 * Supports webhook secret verification for secure integrations.
 *
 * @since 1.0.0
 */
class SendWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * The form submission to send.
     */
    public FormSubmission $submission;

    /**
     * The webhook URL to send to.
     */
    public string $url;

    /**
     * The optional webhook secret for signature verification.
     */
    public ?string $secret;

    /**
     * Create a new job instance.
     */
    public function __construct(FormSubmission $submission, string $url, ?string $secret = null)
    {
        $this->submission = $submission;
        $this->url = $url;
        $this->secret = $secret;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Ensure relationships are loaded
        $this->submission->loadMissing(['form', 'values.field']);

        // Build the webhook payload
        $payload = $this->buildPayload();

        // Build headers
        $headers = $this->buildHeaders($payload);

        try {
            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->post($this->url, $payload);

            if ($response->successful()) {
                Log::info('Webhook sent successfully', [
                    'submission_id' => $this->submission->id,
                    'form_id' => $this->submission->form_id,
                    'url' => $this->url,
                    'status' => $response->status(),
                ]);
            } else {
                Log::warning('Webhook received non-success response', [
                    'submission_id' => $this->submission->id,
                    'form_id' => $this->submission->form_id,
                    'url' => $this->url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                // Throw exception to trigger retry for server errors
                if ($response->serverError()) {
                    throw new \RuntimeException("Webhook failed with status {$response->status()}");
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to send webhook', [
                'submission_id' => $this->submission->id,
                'form_id' => $this->submission->form_id,
                'url' => $this->url,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Build the webhook payload.
     *
     * @return array<string, mixed>
     */
    protected function buildPayload(): array
    {
        // Build metadata, respecting privacy settings
        $metadata = [
            'page_url' => $this->submission->page_url,
            'referrer_url' => $this->submission->referrer_url,
        ];

        // Only include IP address if explicitly enabled in config
        if (config('artisanpack.forms.privacy.include_ip_address', false)) {
            $metadata['ip_address'] = $this->submission->ip_address;
        }

        // Only include user agent if explicitly enabled in config
        if (config('artisanpack.forms.privacy.include_user_agent', false)) {
            $metadata['user_agent'] = $this->submission->user_agent;
        }

        $payload = [
            'event' => 'submission.created',
            'timestamp' => now()->toIso8601String(),
            'form' => [
                'id' => $this->submission->form->id,
                'name' => $this->submission->form->name,
                'slug' => $this->submission->form->slug,
            ],
            'submission' => [
                'id' => $this->submission->id,
                'submission_number' => $this->submission->submission_number,
                'created_at' => $this->submission->created_at->toIso8601String(),
                'data' => $this->submission->data_array,
                'metadata' => $metadata,
            ],
        ];

        // Apply filter hook to allow modifying the webhook payload
        if (function_exists('applyFilters')) {
            $payload = applyFilters('forms.webhook_payload', $payload, $this->submission);
        }

        return $payload;
    }

    /**
     * Build the request headers.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    protected function buildHeaders(array $payload): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'ArtisanPackUI-Forms/1.0',
            'X-Forms-Event' => 'submission.created',
            'X-Forms-Delivery' => (string) $this->submission->id,
        ];

        // Add signature if secret is configured
        if ($this->secret !== null) {
            $signature = $this->generateSignature($payload);
            $headers['X-Forms-Signature'] = $signature;
            $headers['X-Forms-Signature-256'] = 'sha256='.$signature;
        }

        return $headers;
    }

    /**
     * Generate HMAC signature for the payload.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function generateSignature(array $payload): string
    {
        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', $jsonPayload, $this->secret);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Webhook job failed permanently', [
            'submission_id' => $this->submission->id,
            'form_id' => $this->submission->form_id,
            'url' => $this->url,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'webhook',
            'submission:'.$this->submission->id,
            'form:'.$this->submission->form_id,
        ];
    }
}
