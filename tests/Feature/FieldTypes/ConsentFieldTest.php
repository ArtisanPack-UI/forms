<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\FieldTypes\ConsentFieldType;
use ArtisanPackUI\Forms\Http\Resources\FormRenderResource;
use ArtisanPackUI\Forms\Http\Resources\FormSubmissionValueResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Livewire\SubmissionDetail;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\ConditionalLogicService;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use ArtisanPackUI\Security\SecurityServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Models\User;

/**
 * Guards issue #112: the Consent field, which stores the consent text, a
 * revision hash and a timestamp with the value.
 */
beforeEach( function (): void {
    $this->app->register( SecurityServiceProvider::class );

    $this->form    = Form::factory()->create( ['is_active' => true] );
    $this->consent = FormField::factory()->for( $this->form )->required()->create( [
        'type'         => 'consent',
        'name'         => 'gdpr',
        'label'        => 'Privacy',
        'field_config' => [
            'consent_text'        => 'I agree to the privacy policy.',
            'consent_description' => 'Read the <a href="https://example.com/privacy">policy</a>.<script>alert(1)</script>',
        ],
    ] );
} );

afterEach( function (): void {
    Carbon::setTestNow();
} );

describe( 'definition', function (): void {
    it( 'is a registered type with the consent settings', function (): void {
        expect( $this->consent->fieldType() )->toBeInstanceOf( ConsentFieldType::class )
            ->and( array_column( FieldSettings::schema( $this->consent ), 'input', 'key' ) )->toMatchArray( ['consent_text' => 'text', 'consent_description' => 'textarea'] )
            ->and( array_column( FieldSettings::schema( $this->consent ), 'key' ) )->not->toContain( 'dynamic_population', 'visibility' );
    } );

    it( 'sanitizes the description with kses, keeping links', function (): void {
        $description = ConsentFieldType::description( $this->consent );

        expect( $description )->toContain( '<a href="https://example.com/privacy">policy</a>' )
            ->not->toContain( '<script' );
    } );

    it( 'falls back to a default checkbox text', function (): void {
        $this->consent->update( ['field_config' => []] );

        expect( ConsentFieldType::checkboxText( $this->consent->fresh() ) )->toBe( 'I agree to the privacy policy.' );
    } );
} );

describe( 'validation', function (): void {
    it( 'requires the box to be checked, even after being unchecked', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.gdpr', true )
            ->set( 'formData.gdpr', false )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.gdpr' => 'accepted'] );
    } );

    it( 'requires the box through the API', function (): void {
        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['gdpr' => '0'],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( 'data.gdpr' );
    } );
} );

describe( 'storage', function (): void {
    it( 'stores the text, description, revision and time with the consent', function (): void {
        Carbon::setTestNow( '2026-10-24 12:00:00' );

        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.gdpr', true )
            ->call( 'submit' )
            ->assertHasNoErrors();

        $value = $this->form->submissions()->first()->values()->first();

        expect( $value->value_array )->toBe( [
            'consented'    => '1',
            'text'         => 'I agree to the privacy policy.',
            'description'  => ConsentFieldType::description( $this->consent ),
            'revision'     => ConsentFieldType::revision( $this->consent ),
            'consented_at' => '2026-10-24T12:00:00+00:00',
        ] )
            ->and( $value->display_value )->toBe( 'Consented' );
    } );

    it( 'changes the revision when the consent text changes', function (): void {
        $before = ConsentFieldType::revision( $this->consent );

        $this->consent->update( ['field_config' => ['consent_text' => 'I agree to the new policy.']] );

        expect( ConsentFieldType::revision( $this->consent->fresh() ) )->not->toBe( $before )->toHaveLength( 64 );
    } );

    it( 'stores only the revision and time when the privacy config says not to keep the text', function (): void {
        config()->set( 'artisanpack.forms.privacy.consent.store_text', false );

        app( SubmissionService::class )->create( $this->form, ['gdpr' => true] );

        expect( array_keys( $this->form->submissions()->first()->values()->first()->value_array ) )->toBe( ['consented', 'revision', 'consented_at'] );
    } );

    it( 'stores an unchecked optional consent as not consented', function (): void {
        $this->consent->update( ['is_required' => false] );

        app( SubmissionService::class )->create( $this->form, ['gdpr' => false] );

        $value = $this->form->submissions()->first()->values()->first();

        expect( $value->value_array )->toBe( ['consented' => '0'] )
            ->and( $value->display_value )->toBe( 'Not consented' );
    } );

    it( 'gives the API and webhooks the stored consent', function (): void {
        $submission = app( SubmissionService::class )->create( $this->form, ['gdpr' => true] );
        $resource   = ( new FormSubmissionValueResource( $submission->values()->first() ) )->toArray( Request::create( '/' ) );

        expect( $resource['value_array'] )->toHaveKeys( ['consented', 'text', 'revision', 'consented_at'] )
            ->and( $submission->fresh()->data_array['gdpr'] )->toHaveKey( 'revision' );
    } );

    it( 'exports Consented as one column', function (): void {
        $submission = app( SubmissionService::class )->create( $this->form, ['gdpr' => true] );
        $export     = app( ExportService::class );

        expect( $export->buildHeaders( $this->form ) )->toContain( 'Privacy' )
            ->and( $export->buildRow( $this->form, $submission->fresh(), $this->form->fields()->get() ) )->toContain( 'Consented' );
    } );

    it( 'reads a stored consent as checked in conditional logic', function (): void {
        $submission = app( SubmissionService::class )->create( $this->form, ['gdpr' => true] );
        $service    = app( ConditionalLogicService::class )->withFields( $this->form->fields()->get() );

        expect( $service->evaluateRule( ['field' => 'gdpr', 'operator' => 'checked', 'value' => ''], $submission->fresh()->data_array ) )->toBeTrue()
            ->and( $service->evaluateRule( ['field' => 'gdpr', 'operator' => 'checked', 'value' => ''], ['gdpr' => ['consented' => '0']] ) )->toBeFalse();
    } );
} );

describe( 'rendering', function (): void {
    it( 'renders the checkbox text and the sanitized description', function (): void {
        $html = FieldRenderer::render( $this->consent, false );

        expect( $html )->toContain( 'type="checkbox"' )
            ->toContain( 'wire:model.live="formData.gdpr"' )
            ->toContain( 'I agree to the privacy policy.' )
            ->toContain( 'href="https://example.com/privacy"' )
            ->toContain( 'aria-describedby="consent-description-gdpr' )
            ->toContain( 'aria-labelledby="label-gdpr consent-text-gdpr"' )
            ->toContain( 'id="consent-text-gdpr"' )
            ->not->toContain( '<script>alert(1)</script>' );
    } );

    it( 'sends the text and sanitized description to the React and Vue renderers', function (): void {
        $fields = ( new FormRenderResource( $this->form->load( ['fields', 'steps.fields'] ) ) )->toArray( Request::create( '/' ) )['fields']->resolve();

        expect( $fields[0]['consent']['text'] )->toBe( 'I agree to the privacy policy.' )
            ->and( $fields[0]['consent']['description'] )->not->toContain( '<script' )
            ->and( $fields[0]['field_config']['consent_description'] )->not->toContain( '<script' );
    } );

    it( 'shows the stored consent text in the submission detail', function (): void {
        $submission = app( SubmissionService::class )->create( $this->form, ['gdpr' => true] );

        Livewire::actingAs( User::factory()->create() )
            ->test( SubmissionDetail::class, ['submission' => $submission] )
            ->assertSee( 'Consented' )
            ->assertSee( 'View consent text' )
            ->assertSee( 'I agree to the privacy policy.' );
    } );
} );
