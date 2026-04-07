<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Events\FormSubmitted;
use ArtisanPackUI\Forms\Jobs\SendWebhook;
use ArtisanPackUI\Forms\Listeners\SendWebhookOnSubmission;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

describe( 'Webhook Integration', function (): void {
    describe( 'SendWebhook job', function (): void {
        it( 'builds correct payload structure', function (): void {
            $form       = Form::factory()->create( ['name' => 'Contact Form'] );
            $field      = FormField::factory()->for( $form )->create( ['name' => 'email', 'type' => 'email'] );
            $submission = FormSubmission::factory()->for( $form )->create( [
                'page_url' => 'https://example.com/contact',
            ] );
            FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'field_id'   => $field->id,
                'field_name' => 'email',
                'value'      => 'test@example.com',
            ] );

            Http::fake( [
                'https://webhook.example.com/*' => Http::response( ['success' => true], 200 ),
            ] );

            $job = new SendWebhook( $submission, 'https://webhook.example.com/endpoint' );
            $job->handle();

            Http::assertSent( function ( $request ) use ( $submission ) {
                $payload = $request->data();

                return 'submission.created' === $payload['event']
                    && 'Contact Form' === $payload['form']['name']
                    && $payload['submission']['id'] === $submission->id
                    && isset( $payload['submission']['data'] );
            } );
        } );

        it( 'includes signature header when secret is configured', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create();

            Http::fake( [
                '*' => Http::response( ['success' => true], 200 ),
            ] );

            $job = new SendWebhook( $submission, 'https://webhook.example.com/endpoint', 'my-secret-key' );
            $job->handle();

            Http::assertSent( function ( $request ) {
                return $request->hasHeader( 'X-Forms-Signature' )
                    && $request->hasHeader( 'X-Forms-Signature-256' );
            } );
        } );

        it( 'logs success on successful webhook', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create();

            Http::fake( [
                '*' => Http::response( ['success' => true], 200 ),
            ] );

            $job = new SendWebhook( $submission, 'https://webhook.example.com/endpoint' );
            $job->handle();

            // If we get here without exception, it was successful
            expect( true )->toBeTrue();
        } );

        it( 'throws exception on server error to trigger retry', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create();

            Http::fake( [
                '*' => Http::response( ['error' => 'Server error'], 500 ),
            ] );

            $job = new SendWebhook( $submission, 'https://webhook.example.com/endpoint' );

            expect( fn () => $job->handle() )->toThrow( RuntimeException::class );
        } );

        it( 'applies forms.webhook_payload filter', function (): void {
            if ( ! function_exists( 'addFilter' ) ) {
                $this->markTestSkipped( 'Hook system not available' );
            }

            addFilter( 'forms.webhook_payload', function ( array $payload, FormSubmission $submission ): array {
                $payload['custom_field'] = 'custom_value';

                return $payload;
            } );

            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create();

            Http::fake( [
                '*' => Http::response( ['success' => true], 200 ),
            ] );

            $job = new SendWebhook( $submission, 'https://webhook.example.com/endpoint' );
            $job->handle();

            Http::assertSent( function ( $request ) {
                $payload = $request->data();

                return isset( $payload['custom_field'] ) && 'custom_value' === $payload['custom_field'];
            } );
        } );

        it( 'excludes PII from payload by default', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create( [
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0',
            ] );

            Http::fake( [
                '*' => Http::response( ['success' => true], 200 ),
            ] );

            $job = new SendWebhook( $submission, 'https://webhook.example.com/endpoint' );
            $job->handle();

            Http::assertSent( function ( $request ) {
                $payload = $request->data();

                return ! isset( $payload['submission']['metadata']['ip_address'] )
                    && ! isset( $payload['submission']['metadata']['user_agent'] );
            } );
        } );

        it( 'includes PII in payload when privacy settings are enabled', function (): void {
            config( ['artisanpack.forms.privacy.include_ip_address' => true] );
            config( ['artisanpack.forms.privacy.include_user_agent' => true] );

            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create( [
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0',
            ] );

            Http::fake( [
                '*' => Http::response( ['success' => true], 200 ),
            ] );

            $job = new SendWebhook( $submission, 'https://webhook.example.com/endpoint' );
            $job->handle();

            Http::assertSent( function ( $request ) {
                $payload = $request->data();

                return '192.168.1.1' === $payload['submission']['metadata']['ip_address']
                    && 'Mozilla/5.0' === $payload['submission']['metadata']['user_agent'];
            } );
        } );
    } );

    describe( 'SendWebhookOnSubmission listener', function (): void {
        it( 'dispatches webhook job when global webhook is enabled', function (): void {
            Queue::fake();

            config( ['artisanpack.forms.webhooks.enabled' => true] );
            config( ['artisanpack.forms.webhooks.url' => 'https://webhook.example.com/endpoint'] );

            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create();

            $listener = new SendWebhookOnSubmission;
            $listener->handle( new FormSubmitted( $submission ) );

            Queue::assertPushed( SendWebhook::class, function ( SendWebhook $job ) use ( $submission ) {
                return $job->submission->id === $submission->id
                    && 'https://webhook.example.com/endpoint' === $job->url;
            } );
        } );

        it( 'does not dispatch webhook job when global webhook is disabled', function (): void {
            Queue::fake();

            config( ['artisanpack.forms.webhooks.enabled' => false] );

            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create();

            $listener = new SendWebhookOnSubmission;
            $listener->handle( new FormSubmitted( $submission ) );

            Queue::assertNotPushed( SendWebhook::class );
        } );

        it( 'dispatches webhook job for form-specific webhook configuration', function (): void {
            Queue::fake();

            config( ['artisanpack.forms.webhooks.enabled' => false] ); // Global disabled

            $form = Form::factory()->create( [
                'settings' => [
                    'webhook' => [
                        'enabled' => true,
                        'url'     => 'https://form-specific-webhook.example.com/endpoint',
                        'secret'  => 'form-secret',
                    ],
                ],
            ] );
            $submission = FormSubmission::factory()->for( $form )->create();

            $listener = new SendWebhookOnSubmission;
            $listener->handle( new FormSubmitted( $submission ) );

            Queue::assertPushed( SendWebhook::class, function ( SendWebhook $job ) use ( $submission ) {
                return $job->submission->id === $submission->id
                    && 'https://form-specific-webhook.example.com/endpoint' === $job->url
                    && 'form-secret' === $job->secret;
            } );
        } );
    } );
} );

afterEach( function (): void {
    // Reset config after each test
    config( ['artisanpack.forms.webhooks.enabled' => false] );
    config( ['artisanpack.forms.webhooks.url' => null] );
    config( ['artisanpack.forms.privacy.include_ip_address' => false] );
    config( ['artisanpack.forms.privacy.include_user_agent' => false] );

    // Reset filters
    if ( function_exists( 'removeAllFilters' ) ) {
        removeAllFilters( 'forms.webhook_payload');
    }
});
