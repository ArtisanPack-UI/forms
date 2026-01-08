<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Jobs\SendFormNotification;
use ArtisanPackUI\Forms\Mail\FormSubmissionNotification;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

describe( 'SendFormNotification Job', function (): void {
    describe( 'job configuration', function (): void {
        it( 'has correct retry configuration', function (): void {
            $notification = FormNotification::factory()->create();
            $submission   = FormSubmission::factory()->for( $notification->form )->create();

            $job = new SendFormNotification( $notification, $submission );

            expect( $job->tries )->toBe( 3 )
                ->and( $job->backoff )->toBe( 60 );
        } );

        it( 'has job tags', function (): void {
            $notification = FormNotification::factory()->create();
            $submission   = FormSubmission::factory()->for( $notification->form )->create();

            $job  = new SendFormNotification( $notification, $submission );
            $tags = $job->tags();

            expect( $tags )->toContain( 'form-notification' )
                ->and( $tags )->toContain( 'notification:' . $notification->id )
                ->and( $tags )->toContain( 'submission:' . $submission->id );
        } );
    } );

    describe( 'sending emails', function (): void {
        it( 'sends email to static recipients', function (): void {
            Mail::fake();

            $notification = FormNotification::factory()->create( [
                'to_email' => 'admin@example.com',
                'to_field' => null,
            ] );
            $submission = FormSubmission::factory()->for( $notification->form )->create();

            $job = new SendFormNotification( $notification, $submission );
            $job->handle();

            Mail::assertSent( FormSubmissionNotification::class, function ( $mail ) {
                return $mail->hasTo( 'admin@example.com' );
            } );
        } );

        it( 'sends email to dynamic recipient from field', function (): void {
            Mail::fake();

            $notification = FormNotification::factory()->create( [
                'to_email' => null,
                'to_field' => 'contact_email',
            ] );
            $submission = FormSubmission::factory()->for( $notification->form )->create();
            FormSubmissionValue::factory()->for( $submission, 'submission' )->create( [
                'field_name' => 'contact_email',
                'value'      => 'user@example.com',
            ] );

            $job = new SendFormNotification( $notification, $submission );
            $job->handle();

            Mail::assertSent( FormSubmissionNotification::class, function ( $mail ) {
                return $mail->hasTo( 'user@example.com' );
            } );
        } );

        it( 'sends to multiple recipients', function (): void {
            Mail::fake();

            $notification = FormNotification::factory()->create( [
                'to_email' => 'admin1@example.com, admin2@example.com',
                'to_field' => null,
            ] );
            $submission = FormSubmission::factory()->for( $notification->form )->create();

            $job = new SendFormNotification( $notification, $submission );
            $job->handle();

            Mail::assertSent( FormSubmissionNotification::class, function ( $mail ) {
                return $mail->hasTo( 'admin1@example.com' ) && $mail->hasTo( 'admin2@example.com' );
            } );
        } );

        it( 'logs warning when no recipients', function (): void {
            Mail::fake();
            Log::shouldReceive( 'warning' )
                ->once()
                ->withArgs( function ( $message ) {
                    return str_contains( $message, 'no valid recipients' );
                } );

            $notification = FormNotification::factory()->create( [
                'to_email' => null,
                'to_field' => null,
            ] );
            $submission = FormSubmission::factory()->for( $notification->form )->create();

            $job = new SendFormNotification( $notification, $submission );
            $job->handle();

            Mail::assertNothingSent();
        } );

        it( 'logs success after sending', function (): void {
            Mail::fake();
            Log::shouldReceive( 'info' )
                ->once()
                ->withArgs( function ( $message ) {
                    return str_contains( $message, 'sent successfully' );
                } );

            $notification = FormNotification::factory()->create( [
                'to_email' => 'test@example.com',
            ] );
            $submission = FormSubmission::factory()->for( $notification->form )->create();

            $job = new SendFormNotification( $notification, $submission );
            $job->handle();
        } );

        it( 'rethrows exceptions for retry', function (): void {
            Mail::shouldReceive( 'to->send' )
                ->andThrow( new Exception( 'Mail server error' ) );
            Log::shouldReceive( 'error' )->once();

            $notification = FormNotification::factory()->create( [
                'to_email' => 'test@example.com',
            ] );
            $submission = FormSubmission::factory()->for( $notification->form )->create();

            $job = new SendFormNotification( $notification, $submission );

            expect( fn () => $job->handle() )->toThrow( Exception::class );
        } );
    } );

    describe( 'failed job handling', function (): void {
        it( 'logs error on permanent failure', function (): void {
            Log::shouldReceive( 'error' )
                ->once()
                ->withArgs( function ( $message ) {
                    return str_contains( $message, 'failed permanently' );
                } );

            $notification = FormNotification::factory()->create();
            $submission   = FormSubmission::factory()->for( $notification->form)->create();

            $job = new SendFormNotification( $notification, $submission);
            $job->failed( new Exception( 'Permanent failure'));
        });
    });
});
