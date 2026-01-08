<?php

/**
 * Send webhook on submission listener.
 *
 * Listens for form submission events and dispatches webhook jobs
 * based on global and form-specific webhook configuration.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Listeners;

use ArtisanPackUI\Forms\Events\FormSubmitted;
use ArtisanPackUI\Forms\Jobs\SendWebhook;
use ArtisanPackUI\Forms\Models\FormSubmission;

/**
 * Send webhook on submission listener class.
 *
 * Listens for form submission events and dispatches webhook jobs
 * based on global and form-specific webhook configuration.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class SendWebhookOnSubmission
{
    /**
     * Handles the event.
     *
     * @since 1.0.0
     *
     * @param FormSubmitted $event The form submitted event.
     *
     * @return void
     */
    public function handle( FormSubmitted $event ): void
    {
        $this->sendWebhooks( $event->submission );
    }

    /**
     * Sends webhooks for a submission.
     *
     * Checks both global webhook configuration and form-specific webhook settings.
     *
     * @since 1.0.0
     *
     * @param FormSubmission $submission The form submission.
     *
     * @return void
     */
    public function sendWebhooks( FormSubmission $submission ): void
    {
        // Send global webhook if configured
        $this->sendGlobalWebhook( $submission );

        // Send form-specific webhook if configured
        $this->sendFormWebhook( $submission );
    }

    /**
     * Sends the global webhook if configured.
     *
     * @since 1.0.0
     *
     * @param FormSubmission $submission The form submission.
     *
     * @return void
     */
    protected function sendGlobalWebhook( FormSubmission $submission ): void
    {
        $enabled = config( 'artisanpack.forms.webhooks.enabled', false );
        $url     = config( 'artisanpack.forms.webhooks.url' );

        if ( ! $enabled || empty( $url ) ) {
            return;
        }

        $secret = config( 'artisanpack.forms.webhooks.secret' );
        $queue  = config( 'artisanpack.forms.webhooks.queue', 'default' );

        SendWebhook::dispatch( $submission, $url, $secret )
            ->onQueue( $queue );
    }

    /**
     * Sends the form-specific webhook if configured.
     *
     * @since 1.0.0
     *
     * @param FormSubmission $submission The form submission.
     *
     * @return void
     */
    protected function sendFormWebhook( FormSubmission $submission ): void
    {
        $submission->loadMissing( 'form' );

        $webhookSettings = $submission->form->getSetting( 'webhook' );

        if ( empty( $webhookSettings ) || empty( $webhookSettings['enabled'] ) || empty( $webhookSettings['url'] ) ) {
            return;
        }

        $url    = $webhookSettings['url'];
        $secret = $webhookSettings['secret'] ?? null;
        $queue  = config( 'artisanpack.forms.webhooks.queue', 'default' );

        SendWebhook::dispatch( $submission, $url, $secret )
            ->onQueue( $queue );
    }
}
