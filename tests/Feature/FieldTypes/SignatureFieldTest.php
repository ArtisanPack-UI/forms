<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Config\ConditionalLogic;
use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\FieldTypes\SignatureFieldType;
use ArtisanPackUI\Forms\Http\Resources\FormFieldResource;
use ArtisanPackUI\Forms\Livewire\FormBuilder;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Livewire\SubmissionDetail;
use ArtisanPackUI\Forms\Mail\FormSubmissionNotification;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Models\FormUpload;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Services\NotificationService;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

/**
 * Guards issue #129: the signature field.
 */
const SIGNATURE_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

beforeEach( function (): void {
    Storage::fake( config( 'artisanpack.forms.uploads.disk', 'local' ) );

    $this->form      = Form::factory()->create( ['is_active' => true] );
    $this->signature = FormField::factory()->for( $this->form )->create( [
        'type'        => 'signature',
        'name'        => 'signature',
        'label'       => 'Signature',
        'help_text'   => null,
        'is_required' => true,
    ] );
    $this->drawn = SignatureFieldType::DATA_URL_PREFIX . SIGNATURE_PNG;

    $this->submitApi = fn ( mixed $value ) => $this->postJson( route( 'api.forms.submit', $this->form ), [
        'data'            => ['signature' => $value],
        '_form_loaded_at' => time() - 10,
    ] );
} );

describe( 'registration', function (): void {
    it( 'is an advanced field without privacy or administrative settings', function (): void {
        $config = FieldTypes::getTypeConfig( 'signature' );

        expect( FieldTypes::CATEGORY_FIELDS['advanced'] )->toContain( 'signature' )
            ->and( $config )->toMatchArray( ['label' => 'Signature', 'icon' => 'o-pencil-square', 'supports_default_value' => false] )
            ->and( array_column( $config['settings'], 'key' ) )->toContain( 'pad_height' )
            ->not->toContain( 'visibility', 'do_not_store', 'mask_in_admin', 'redact_after_days', 'dynamic_population' );
    } );

    it( 'can be added from the Livewire builder palette', function (): void {
        Livewire::test( FormBuilder::class, ['form' => $this->form] )->call( 'addField', 'signature' );

        expect( $this->form->fields()->where( 'id', '!=', $this->signature->id )->sole()->type )->toBe( 'signature' );
    } );

    it( 'offers the empty and not-empty conditional logic operators', function (): void {
        expect( array_keys( ConditionalLogic::getOperatorsForType( 'signature' ) ) )->toBe( ['is_empty', 'is_not_empty'] );
    } );
} );

describe( 'validation', function (): void {
    it( 'accepts a drawn PNG or a typed name', function ( string $value ): void {
        expect( SignatureFieldType::isValidSignature( $value ) )->toBeTrue();
    } )->with( [
        'drawn' => fn () => SignatureFieldType::DATA_URL_PREFIX . SIGNATURE_PNG,
        'typed' => 'Ada Lovelace',
        'empty' => '',
    ] );

    it( 'rejects other data URLs, bad PNGs and long or control-character names', function ( mixed $value ): void {
        expect( SignatureFieldType::isValidSignature( $value ) )->toBeFalse();
    } )->with( [
        'jpeg'          => 'data:image/jpeg;base64,' . SIGNATURE_PNG,
        'not base64'    => SignatureFieldType::DATA_URL_PREFIX . '!!!',
        'not a PNG'     => SignatureFieldType::DATA_URL_PREFIX . base64_encode( '<svg></svg>' ),
        'too large'     => fn () => SignatureFieldType::DATA_URL_PREFIX . base64_encode( "\x89PNG\r\n\x1A\n" . str_repeat( 'a', SignatureFieldType::MAX_BYTES ) ),
        'bad header'    => SignatureFieldType::DATA_URL_PREFIX . base64_encode( "\x89PNG\r\n\x1A\n<script>alert(1)</script>" ),
        'huge image'    => fn () => SignatureFieldType::DATA_URL_PREFIX . base64_encode( substr( base64_decode( SIGNATURE_PNG ), 0, 16 ) . pack( 'NN', 50000, 50000 ) . substr( base64_decode( SIGNATURE_PNG ), 24 ) ),
        'blank name'    => '   ',
        'long name'     => str_repeat( 'a', 256 ),
        'control chars' => "Ada\x07",
        'array'         => [['Ada']],
    ] );

    it( 'requires a signature and explains the alternatives', function (): void {
        ( $this->submitApi )( '' )->assertUnprocessable()->assertJsonValidationErrors( ['data.signature' => 'The Signature field is required.'] );

        ( $this->submitApi )( 'data:image/gif;base64,R0lGOD' )->assertUnprocessable()->assertJsonValidationErrors( [
            'data.signature' => 'Please draw your signature for Signature, or type your full name (up to 255 characters).',
        ] );
    } );
} );

describe( 'storage', function (): void {
    it( 'stores a drawn signature as a PNG upload', function (): void {
        ( $this->submitApi )( $this->drawn )->assertCreated();

        $submission = $this->form->submissions()->first();
        $value      = $submission->values->first();
        $upload     = FormUpload::query()->sole();

        expect( $value->upload_id )->toBe( $upload->id )
            ->and( $value->value )->toBeNull()
            ->and( $upload )->toMatchArray( ['submission_id' => $submission->id, 'original_name' => 'signature.png', 'mime_type' => 'image/png'] )
            ->and( Storage::disk( $upload->disk )->get( $upload->path ) )->toBe( base64_decode( SIGNATURE_PNG ) );
    } );

    it( 'stores a typed signature as text', function (): void {
        ( $this->submitApi )( '  Ada Lovelace ' )->assertCreated();

        expect( $this->form->submissions()->first()->values->first()->value )->toBe( 'Ada Lovelace' )
            ->and( FormUpload::query()->count() )->toBe( 0 );
    } );

    it( 'stores a drawn signature from the Livewire renderer', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->assertSet( 'formData.signature', '' )
            ->afterSpamDelay()
            ->set( 'formData.signature', $this->drawn )
            ->call( 'submit' )
            ->assertHasNoErrors();

        expect( FormUpload::query()->sole()->original_name )->toBe( 'signature.png' );
    } );
} );

describe( 'display', function (): void {
    it( 'shows a drawn signature as an inline image', function (): void {
        ( $this->submitApi )( $this->drawn )->assertCreated();

        $value = $this->form->submissions()->first()->values->first();

        expect( $value->display_value )->toBe( 'Signed (drawn)' )
            ->and( $value->display_html )->toContain( '<img src="data:image/png;base64,' . SIGNATURE_PNG . '"' )
            ->toContain( 'alt="Signature for Signature"' );
    } );

    it( 'shows a typed signature as text', function (): void {
        ( $this->submitApi )( 'Ada <b>' )->assertCreated();

        $value = $this->form->submissions()->first()->values->first();

        expect( $value->display_value )->toBe( 'Signed as "Ada <b>" (typed)' )
            ->and( $value->display_html )->toBeNull();
    } );

    it( 'exports the signature text', function (): void {
        ( $this->submitApi )( $this->drawn )->assertCreated();

        $submission = $this->form->submissions()->first();
        $row        = app( ExportService::class )->buildRow( $this->form, $submission->fresh( ['values'] ), $this->form->fields()->get() );

        expect( $row )->toContain( 'Signed (drawn)' );
    } );

    it( 'shows the image in the Livewire submission detail', function (): void {
        ( $this->submitApi )( $this->drawn )->assertCreated();

        $submission = $this->form->submissions()->first();

        Livewire::test( SubmissionDetail::class, ['submission' => $submission] )
            ->assertSeeHtml( '<img src="data:image/png;base64,' . SIGNATURE_PNG . '"' )
            ->assertSeeHtml( route( 'forms.uploads.download', [$this->form, $submission, FormUpload::query()->sole()] ) );
    } );
} );

describe( 'notifications', function (): void {
    beforeEach( function (): void {
        ( $this->submitApi )( $this->drawn )->assertCreated();

        $this->submission   = $this->form->submissions()->first();
        $this->notification = FormNotification::factory()->for( $this->form )->create( ['include_submission_data' => true] );
    } );

    it( 'embeds a drawn signature in the email instead of a data URL', function (): void {
        $upload   = FormUpload::query()->sole();
        $mailable = new FormSubmissionNotification( $this->notification, $this->submission->fresh( ['values'] ) );

        expect( $mailable->submissionDataTable )->toContain( 'src="cid:signature-' . $upload->id . '.png"' )
            ->not->toContain( 'data:image/png' );

        $mailable->build();
        $mailable->build();

        $email = new Symfony\Component\Mime\Email();

        ( new ReflectionMethod( $mailable, 'runCallbacks' ) )->invoke( $mailable, new Illuminate\Mail\Message( $email ) );

        expect( $email->getAttachments() )->toHaveCount( 1 )
            ->and( $email->getAttachments()[0]->getFilename() )->toBe( 'signature-' . $upload->id . '.png' )
            ->and( $email->getAttachments()[0]->getBody() )->toBe( base64_decode( SIGNATURE_PNG ) );
    } );

    it( 'leaves the image out when the email has no submission data', function (): void {
        $this->notification->update( ['include_submission_data' => false] );

        $mailable = new FormSubmissionNotification( $this->notification->fresh(), $this->submission->fresh( ['values'] ) );

        $mailable->build();

        expect( ( new ReflectionProperty( $mailable, 'callbacks' ) )->getValue( $mailable ) )->toBe( [] );
    } );

    it( 'inlines the PNG in the table when no embedder is given', function (): void {
        expect( app( NotificationService::class )->formatAllFieldsAsTable( $this->submission->fresh( ['values'] ) ) )
            ->toContain( 'data:image/png;base64,' . SIGNATURE_PNG );
    } );

    it( 'reads as text in plain-text notifications', function (): void {
        expect( app( NotificationService::class )->formatAllFieldsPlainText( $this->submission->fresh( ['values'] ) ) )
            ->toBe( 'Signature: Signed (drawn)' );
    } );
} );

describe( 'rendering', function (): void {
    beforeEach( function (): void {
        view()->share( 'errors', new ViewErrorBag() );
    } );

    it( 'renders a drawing pad with a typed alternative', function (): void {
        $html = FieldRenderer::render( $this->signature, '' );

        expect( $html )->toContain( 'role="img"' )
            ->toContain( 'aria-label="Signature pad. Draw your signature with a mouse, finger or stylus, or choose Type your name instead."' )
            ->toContain( 'Type your name' )
            ->toContain( 'Type your full name' )
            ->toContain( 'autocomplete="name"' )
            ->toContain( 'Clear signature' )
            ->toContain( 'aria-live="polite"' )
            ->toContain( 'height: 160px' )
            ->toContain( 'wire:ignore' );
    } );

    it( 'sets the value deferred on every change, so a quick submit carries it', function (): void {
        $html = FieldRenderer::render( $this->signature, '' );

        expect( $html )->toContain( "\$wire.\$set('formData.signature', value, false)" )
            ->toContain( 'x-on:input="sync(typed.trim())"' )
            ->not->toContain( 'debounce' );
    } );

    it( 'starts in typing mode for a typed value', function (): void {
        $html = FieldRenderer::render( $this->signature, 'Ada Lovelace' );

        expect( $html )->toContain( "mode: 'type'" )
            ->toContain( "typed: 'Ada Lovelace'" );
    } );

    it( 'sends the pad height to React and Vue', function (): void {
        $this->signature->update( ['field_config' => ['pad_height' => '240']] );

        $resource = ( new FormFieldResource( $this->signature->fresh() ) )->toArray( Request::create( '/' ) );

        expect( $resource['signature'] )->toBe( ['pad_height' => 240, 'max_bytes' => SignatureFieldType::MAX_BYTES] );
    } );
} );
