<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Services\SubmissionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Guards SEC-04: a visitor-uploaded "Do not store" file is attached to
 * notifications sent to fixed addresses, never to an address the visitor
 * typed (an autoresponder or a notification with a recipient field).
 */
beforeEach( function (): void {
    Storage::fake( 'form-uploads' );
    config( ['mail.default' => 'array'] );

    $this->form = Form::factory()->create( ['is_active' => true] );
    FormField::factory()->for( $this->form )->create( ['type' => 'email', 'name' => 'email', 'label' => 'Email'] );
    FormField::factory()->for( $this->form )->create( [
        'type'         => 'file',
        'name'         => 'document',
        'label'        => 'Document',
        'sort_order'   => 2,
        'field_config' => ['do_not_store' => true, 'allowed_types' => ['pdf'], 'max_size' => 200],
    ] );
} );

/**
 * Gets the attachments of the message sent to an address.
 *
 * @return array<int, mixed>
 */
function attachmentsSentTo( string $address ): array
{
    $message = Mail::mailer( 'array' )->getSymfonyTransport()->messages()
        ->first( fn ( $sent ): bool => collect( $sent->getOriginalMessage()->getTo() )->contains( fn ( $to ): bool => $address === $to->getAddress() ) );

    expect( $message )->not->toBeNull();

    return $message->getOriginalMessage()->getAttachments();
}

it( 'attaches the file to an admin notification but not to an autoresponder', function (): void {
    FormNotification::factory()->for( $this->form )->admin()->create( ['to_email' => 'admin@example.com'] );
    FormNotification::factory()->for( $this->form )->autoresponder()->create( ['include_submission_data' => true] );

    app( SubmissionService::class )->create(
        $this->form,
        ['email' => 'victim@example.com'],
        ['document' => UploadedFile::fake()->createWithContent( 'invoice.pdf', '%PDF-1.4 payload' )],
    );

    expect( attachmentsSentTo( 'admin@example.com' ) )->toHaveCount( 1 )
        ->and( attachmentsSentTo( 'victim@example.com' ) )->toBe( [] );
} );

it( 'does not attach the file to a custom notification sent to a submitted address', function (): void {
    FormNotification::factory()->for( $this->form )->custom()->create( ['to_email' => null, 'to_field' => 'email', 'include_submission_data' => true] );

    app( SubmissionService::class )->create(
        $this->form,
        ['email' => 'victim@example.com'],
        ['document' => UploadedFile::fake()->createWithContent( 'invoice.pdf', '%PDF-1.4 payload' )],
    );

    expect( attachmentsSentTo( 'victim@example.com' ) )->toBe( [] );
} );
