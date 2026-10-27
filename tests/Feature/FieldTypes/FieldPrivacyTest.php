<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Jobs\SendFormNotification;
use ArtisanPackUI\Forms\Jobs\SendWebhook;
use ArtisanPackUI\Forms\Livewire\SubmissionDetail;
use ArtisanPackUI\Forms\Livewire\SubmissionsList;
use ArtisanPackUI\Forms\Mail\FormSubmissionNotification;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\FieldPrivacy;
use ArtisanPackUI\Forms\Support\FieldSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Models\User;

/**
 * Guards issue #101: per-field privacy — do not store, mask in admin and
 * redact after N days.
 */
describe( 'per-field privacy settings', function (): void {
    it( 'are Advanced tab settings on input fields', function (): void {
        $field = FormField::factory()->make( ['type' => 'text'] );

        expect( FieldSettings::find( $field, 'do_not_store' ) )->toMatchArray( ['tab' => 'advanced', 'input' => 'toggle'] )
            ->and( FieldSettings::find( $field, 'mask_in_admin' ) )->toMatchArray( ['tab' => 'advanced', 'input' => 'toggle'] )
            ->and( FieldSettings::find( $field, 'redact_after_days' ) )->toMatchArray( ['tab' => 'advanced', 'input' => 'integer', 'min' => 1, 'max' => FormField::MAX_REDACT_DAYS] );
    } );

    it( 'are offered on file fields (#157)', function (): void {
        $field = FormField::factory()->make( ['type' => 'file'] );

        expect( FieldSettings::find( $field, 'do_not_store' ) )->toMatchArray( ['tab' => 'advanced', 'input' => 'toggle'] )
            ->and( FieldSettings::find( $field, 'mask_in_admin' ) )->toMatchArray( ['tab' => 'advanced', 'input' => 'toggle'] )
            ->and( FieldSettings::find( $field, 'redact_after_days' ) )->toMatchArray( ['tab' => 'advanced', 'input' => 'integer'] );
    } );

    it( 'are not offered on layout fields', function ( string $type ): void {
        $field = FormField::factory()->make( ['type' => $type] );

        expect( FieldSettings::find( $field, 'do_not_store' ) )->toBeNull()
            ->and( FieldSettings::find( $field, 'mask_in_admin' ) )->toBeNull()
            ->and( FieldSettings::find( $field, 'redact_after_days' ) )->toBeNull();
    } )->with( ['heading', 'html'] );

    it( 'reads only valid redaction periods', function ( mixed $days, ?int $expected ): void {
        $field = FormField::factory()->make( ['type' => 'text', 'field_config' => ['redact_after_days' => $days]] );

        expect( $field->getRedactAfterDays() )->toBe( $expected );
    } )->with( [
        'days'          => [30, 30],
        'numeric text'  => ['7', 7],
        'zero'          => [0, null],
        'too long'      => [FormField::MAX_REDACT_DAYS + 1, null],
        'not a number'  => ['soon', null],
    ] );
} );

describe( 'do not store', function (): void {
    beforeEach( function (): void {
        $this->form = Form::factory()->create( ['is_active' => true] );
        FormField::factory()->for( $this->form )->create( ['type' => 'email', 'name' => 'email', 'label' => 'Email'] );
        FormField::factory()->for( $this->form )->create( [
            'type'         => 'text',
            'name'         => 'ssn',
            'label'        => 'SSN',
            'sort_order'   => 2,
            'field_config' => ['do_not_store' => true],
        ] );
    } );

    it( 'never writes the value to the database', function (): void {
        $submission = app( SubmissionService::class )->create( $this->form, ['email' => 'ada@example.com', 'ssn' => '123-45-6789'] );

        expect( FormSubmissionValue::where( 'field_name', 'ssn' )->exists() )->toBeFalse()
            ->and( $submission->fresh()->getValue( 'ssn' ) )->toBeNull()
            ->and( $submission->fresh()->getValue( 'email' ) )->toBe( 'ada@example.com' );
    } );

    it( 'keeps the value on the submission for the rest of the request', function (): void {
        $submission = app( SubmissionService::class )->create( $this->form, ['email' => 'ada@example.com', 'ssn' => '123-45-6789'] );

        expect( $submission->getValue( 'ssn' ) )->toBe( '123-45-6789' )
            ->and( $submission->data_array )->toMatchArray( ['ssn' => '123-45-6789'] )
            ->and( $submission->getTransientValues() )->toHaveCount( 1 );
    } );

    it( 'reaches the notification email', function (): void {
        Mail::fake();
        FormNotification::factory()->for( $this->form )->create( ['message' => 'SSN: {ssn}'] );

        app( SubmissionService::class )->create( $this->form, ['email' => 'ada@example.com', 'ssn' => '123-45-6789'] );

        Mail::assertSent( FormSubmissionNotification::class, fn ( FormSubmissionNotification $mail ): bool => str_contains( $mail->parsedMessage, '123-45-6789' ) );
    } );

    it( 'reaches the webhook payload', function (): void {
        Http::fake( ['*' => Http::response( [], 200 )] );
        config( ['artisanpack.forms.webhooks.enabled' => true, 'artisanpack.forms.webhooks.url' => 'https://hooks.example.com/forms'] );

        app( SubmissionService::class )->create( $this->form, ['email' => 'ada@example.com', 'ssn' => '123-45-6789'] );

        Http::assertSent( fn ( $request ): bool => '123-45-6789' === ( $request['submission']['data']['ssn'] ?? null ) );
    } );

    it( 'travels in an encrypted job payload', function (): void {
        Queue::fake();
        FormNotification::factory()->for( $this->form )->create();
        config( ['artisanpack.forms.webhooks.enabled' => true, 'artisanpack.forms.webhooks.url' => 'https://hooks.example.com/forms'] );

        app( SubmissionService::class )->create( $this->form, ['email' => 'ada@example.com', 'ssn' => '123-45-6789'] );

        Queue::assertPushed( SendFormNotification::class, fn ( SendFormNotification $job ): bool => $job->shouldBeEncrypted && '123-45-6789' === $job->transientValues[0]['value'] );
        Queue::assertPushed( SendWebhook::class, fn ( SendWebhook $job ): bool => $job->shouldBeEncrypted );
    } );

    it( 'leaves jobs for submissions without such values unencrypted', function (): void {
        Queue::fake();
        FormNotification::factory()->for( $this->form )->create();

        app( SubmissionService::class )->create( $this->form, ['email' => 'ada@example.com'] );

        Queue::assertPushed( SendFormNotification::class, fn ( SendFormNotification $job ): bool => ! $job->shouldBeEncrypted && [] === $job->transientValues );
    } );

    it( 'does not duplicate the values when a job adds them again', function (): void {
        $submission = app( SubmissionService::class )->create( $this->form, ['email' => 'ada@example.com', 'ssn' => '123-45-6789'] );

        $submission->withTransientValues( $submission->getTransientValues() );

        expect( $submission->values->where( 'field_name', 'ssn' ) )->toHaveCount( 1 );
    } );
} );

describe( 'mask in admin', function (): void {
    beforeEach( function (): void {
        $this->form = Form::factory()->create();
        FormField::factory()->for( $this->form )->create( [
            'type'         => 'text',
            'name'         => 'account',
            'label'        => 'Account Number',
            'field_config' => ['mask_in_admin' => true],
        ] );
        FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'name', 'label' => 'Name', 'sort_order' => 2] );

        $this->submission = app( SubmissionService::class )->create( $this->form, ['account' => '9876543210', 'name' => 'Ada'] );
        $this->staff      = User::factory()->create( ['is_admin' => false] );
        $this->admin      = User::factory()->create( ['is_admin' => true] );
    } );

    it( 'keeps the last four characters of a long value', function ( string $value, string $masked ): void {
        expect( FieldPrivacy::mask( $value ) )->toBe( $masked );
    } )->with( [
        'long'  => ['9876543210', '••••3210'],
        'short' => ['1234567', '••••'],
        'blank' => ['', ''],
    ] );

    it( 'lets admins reveal masked values, and the filter grant it to others', function (): void {
        expect( FieldPrivacy::canReveal( $this->admin ) )->toBeTrue()
            ->and( FieldPrivacy::canReveal( $this->staff ) )->toBeFalse()
            ->and( FieldPrivacy::canReveal() )->toBeFalse();

        addFilter( 'ap.forms.canRevealMaskedValues', fn ( bool $allowed, $user ): bool => $allowed || 'staff' === $user?->name );
        $this->staff->update( ['name' => 'staff'] );

        expect( FieldPrivacy::canReveal( $this->staff ) )->toBeTrue();

        removeAllFilters( 'ap.forms.canRevealMaskedValues' );
    } );

    it( 'masks the value in the API for users who may not reveal it', function (): void {
        $response = $this->actingAs( $this->staff )->getJson( route( 'api.forms.submissions.show', [$this->form, $this->submission] ) );
        $values   = collect( $response->json( 'data.values' ) )->keyBy( 'field_name' );

        expect( $values['account'] )->toMatchArray( ['value' => '••••3210', 'display_value' => '••••3210', 'is_masked' => true] )
            ->and( $values['name'] )->toMatchArray( ['value' => 'Ada', 'is_masked' => false] )
            ->and( $response->getContent() )->not->toContain( '9876543210' );
    } );

    it( 'shows the value in the API to users who may reveal it', function (): void {
        $response = $this->actingAs( $this->admin )->getJson( route( 'api.forms.submissions.show', [$this->form, $this->submission] ) );
        $values   = collect( $response->json( 'data.values' ) )->keyBy( 'field_name' );

        expect( $values['account'] )->toMatchArray( ['value' => '9876543210', 'is_masked' => false] );
    } );

    it( 'masks the value in the submission detail and list', function (): void {
        $this->actingAs( $this->staff );

        Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
            ->assertSee( '••••3210' )
            ->assertSee( 'Masked' )
            ->assertDontSee( '9876543210' );

        Livewire::test( SubmissionsList::class, ['formId' => $this->form->id] )
            ->assertDontSee( '9876543210' );
    } );

    it( 'does not find submissions by a masked value', function (): void {
        $this->actingAs( $this->staff );

        Livewire::test( SubmissionsList::class, ['formId' => $this->form->id] )
            ->set( 'search', '9876543210' )
            ->assertDontSee( $this->submission->submission_number );

        $this->actingAs( $this->admin );

        Livewire::test( SubmissionsList::class, ['formId' => $this->form->id] )
            ->set( 'search', '9876543210' )
            ->assertSee( $this->submission->submission_number );
    } );

    it( 'leaves the field out of exports unless the user may reveal it', function (): void {
        $exporter    = app( ExportService::class );
        $submissions = FormSubmission::with( 'values.field' )->get();

        $this->actingAs( $this->staff );

        expect( $exporter->buildHeaders( $this->form ) )->not->toContain( 'Account Number' )
            ->and( $exporter->buildRows( $this->form, $submissions )[0] )->not->toContain( '9876543210' )
            ->and( $exporter->exportToJson( $this->form, $submissions )[0]['data'] )->not->toHaveKey( 'account' );

        $this->actingAs( $this->admin );

        expect( $exporter->buildHeaders( $this->form ) )->toContain( 'Account Number' )
            ->and( $exporter->buildRows( $this->form, $submissions )[0] )->toContain( '9876543210' )
            ->and( $exporter->exportToJson( $this->form, $submissions )[0]['data'] )->toHaveKey( 'account' );
    } );
} );

describe( 'redact after N days', function (): void {
    beforeEach( function (): void {
        $this->form = Form::factory()->create();
        FormField::factory()->for( $this->form )->create( [
            'type'         => 'text',
            'name'         => 'diagnosis',
            'label'        => 'Diagnosis',
            'field_config' => ['redact_after_days' => 30],
        ] );
        FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'name', 'sort_order' => 2] );

        $this->old = app( SubmissionService::class )->create( $this->form, ['diagnosis' => 'Flu', 'name' => 'Ada'] );
        $this->old->forceFill( ['created_at' => now()->subDays( 31 )] )->save();

        $this->recent = app( SubmissionService::class )->create( $this->form, ['diagnosis' => 'Cold', 'name' => 'Grace'] );
    } );

    it( 'clears values older than the period, even without a retention period', function (): void {
        config( ['artisanpack.forms.submissions.retention_days' => null] );

        $this->artisan( 'forms:prune-submissions' )
            ->expectsOutputToContain( 'Redacted 1 field value(s)' )
            ->expectsOutputToContain( 'Retention days is not configured' )
            ->assertSuccessful();

        $old    = $this->old->values()->get()->keyBy( 'field_name' );
        $recent = $this->recent->values()->get()->keyBy( 'field_name' );

        expect( $old['diagnosis']->value )->toBeNull()
            ->and( $old['diagnosis']->redacted_at )->not->toBeNull()
            ->and( $old['diagnosis']->isRedacted() )->toBeTrue()
            ->and( $old['diagnosis']->display_value )->toBe( '[Redacted]' )
            ->and( $old['name']->value )->toBe( 'Ada' )
            ->and( $recent['diagnosis']->value )->toBe( 'Cold' );
    } );

    it( 'skips values it already redacted', function (): void {
        $this->artisan( 'forms:prune-submissions' )->assertSuccessful();

        $this->artisan( 'forms:prune-submissions' )
            ->doesntExpectOutputToContain( 'Redacted' )
            ->assertSuccessful();
    } );

    it( 'only counts the values on a dry run', function (): void {
        $this->artisan( 'forms:prune-submissions', ['--dry-run' => true] )
            ->expectsOutputToContain( '[Dry Run] Would redact 1 field value(s)' )
            ->assertSuccessful();

        expect( $this->old->values()->where( 'field_name', 'diagnosis' )->value( 'value' ) )->toBe( 'Flu' );
    } );

    it( 'shows a redacted value in the API', function (): void {
        $this->artisan( 'forms:prune-submissions' )->assertSuccessful();

        $values = collect(
            $this->actingAs( User::factory()->create() )
                ->getJson( route( 'api.forms.submissions.show', [$this->form, $this->old] ) )
                ->json( 'data.values' ),
        )->keyBy( 'field_name' );

        expect( $values['diagnosis'] )->toMatchArray( ['value' => null, 'display_value' => '[Redacted]', 'is_redacted' => true] );
    } );
} );
