<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Listeners;

use ArtisanPackUI\Forms\Events\FormSubmitted;
use ArtisanPackUI\Forms\Jobs\SendWebhook;
use ArtisanPackUI\Forms\Models\FormSubmission;

/**
 * SendWebhookOnSubmission Listener
 *
 * Listens for form submission events and dispatches webhook jobs
 * based on global and form-specific webhook configuration.
 *
 * @since 1.0.0
 */
class SendWebhookOnSubmission
{
    /**
     * Handle the event.
     */
    public function handle(FormSubmitted $event): void
    {
        $this->sendWebhooks($event->submission);
    }

    /**
     * Send webhooks for a submission.
     *
     * Checks both global webhook configuration and form-specific webhook settings.
     */
    public function sendWebhooks(FormSubmission $submission): void
    {
        // Send global webhook if configured
        $this->sendGlobalWebhook($submission);

        // Send form-specific webhook if configured
        $this->sendFormWebhook($submission);
    }

    /**
     * Send the global webhook if configured.
     */
    protected function sendGlobalWebhook(FormSubmission $submission): void
    {
        $enabled = config('artisanpack.forms.webhooks.enabled', false);
        $url = config('artisanpack.forms.webhooks.url');

        if (! $enabled || empty($url)) {
            return;
        }

        $secret = config('artisanpack.forms.webhooks.secret');
        $queue = config('artisanpack.forms.webhooks.queue', 'default');

        SendWebhook::dispatch($submission, $url, $secret)
            ->onQueue($queue);
    }

    /**
     * Send the form-specific webhook if configured.
     */
    protected function sendFormWebhook(FormSubmission $submission): void
    {
        $submission->loadMissing('form');

        $webhookSettings = $submission->form->getSetting('webhook');

        if (empty($webhookSettings) || empty($webhookSettings['enabled']) || empty($webhookSettings['url'])) {
            return;
        }

        $url = $webhookSettings['url'];
        $secret = $webhookSettings['secret'] ?? null;
        $queue = config('artisanpack.forms.webhooks.queue', 'default');

        SendWebhook::dispatch($submission, $url, $secret)
            ->onQueue($queue);
    }
}
