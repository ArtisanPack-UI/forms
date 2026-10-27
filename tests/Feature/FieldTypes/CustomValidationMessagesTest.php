<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormFieldResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\FieldSettings;
use Illuminate\Http\Request;
use Livewire\Livewire;

/**
 * Guards issue #96: custom validation error messages per field.
 */
describe( 'custom validation messages', function (): void {
    it( 'offers an error message and a required message in the Validation tab', function (): void {
        $field = FormField::factory()->make( ['type' => 'text'] );

        expect( FieldSettings::find( $field, 'error_message' ) )->toMatchArray( ['tab' => 'validation', 'store' => 'field_config'] )
            ->and( FieldSettings::find( $field, 'required_message' ) )->toMatchArray( ['tab' => 'validation', 'store' => 'field_config'] );
    } );

    it( 'is not offered on layout fields', function (): void {
        $field = FormField::factory()->make( ['type' => 'heading'] );

        expect( FieldSettings::find( $field, 'error_message' ) )->toBeNull()
            ->and( $field->buildValidationMessages() )->toBe( [] );
    } );

    it( 'replaces every message but the required one', function (): void {
        $field = FormField::factory()->required()->create( [
            'type'             => 'text',
            'name'             => 'member_id',
            'label'            => 'Member ID',
            'validation_rules' => ['pattern' => '/^\d{6}$/', 'min' => 6],
            'field_config'     => ['error_message' => 'Enter your 6-digit member ID'],
        ] );

        $messages = app( SubmissionService::class )->buildValidationMessages( collect( [$field] ) );

        expect( $messages['formData.member_id.regex'] )->toBe( 'Enter your 6-digit member ID' )
            ->and( $messages['formData.member_id.min'] )->toBe( 'Enter your 6-digit member ID' )
            ->and( $messages['formData.member_id.string'] )->toBe( 'Enter your 6-digit member ID' )
            ->and( $messages['formData.member_id.required'] )->toBe( 'The Member ID field is required.' )
            ->and( $messages )->not->toHaveKey( 'formData.member_id.nullable' );
    } );

    it( 'uses the required message for the presence rule, including a checkbox\'s accepted rule', function (): void {
        $text     = FormField::factory()->required()->create( ['type' => 'text', 'name' => 'name', 'field_config' => ['required_message' => 'Tell us your name']] );
        $checkbox = FormField::factory()->required()->create( ['type' => 'checkbox', 'name' => 'terms', 'field_config' => ['required_message' => 'Please accept the terms']] );

        $messages = app( SubmissionService::class )->buildValidationMessages( collect( [$text, $checkbox] ) );

        expect( $messages['formData.name.required'] )->toBe( 'Tell us your name' )
            ->and( $messages['formData.terms.accepted'] )->toBe( 'Please accept the terms' )
            ->and( $messages['formData.terms.boolean'] ?? null )->toBeNull();
    } );

    it( 'replaces the type\'s own messages', function (): void {
        $field = FormField::factory()->create( ['type' => 'email', 'name' => 'email', 'field_config' => ['error_message' => 'That email looks wrong']] );

        expect( $field->buildValidationMessages()['email'] )->toBe( 'That email looks wrong' );
    } );

    it( 'translates a message stored as a translation key', function (): void {
        app( 'translator' )->addLines( ['forms.member_id' => 'Saisissez votre numéro de membre'], 'fr' );
        app()->setLocale( 'fr' );

        $field = FormField::factory()->create( ['type' => 'text', 'field_config' => ['error_message' => 'forms.member_id']] );

        expect( $field->getCustomErrorMessage() )->toBe( 'Saisissez votre numéro de membre' );
    } );

    it( 'shows the custom message in the Livewire renderer', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->required()->create( [
            'type'             => 'text',
            'name'             => 'member_id',
            'validation_rules' => ['pattern' => '/^\d{6}$/'],
            'field_config'     => ['error_message' => 'Enter your 6-digit member ID', 'required_message' => 'Your member ID is required'],
        ] );

        Livewire::test( FormRenderer::class, ['form' => $form] )
            ->afterSpamDelay()
            ->set( 'formData.member_id', '' )
            ->call( 'submit' )
            ->assertSee( 'Your member ID is required' )
            ->set( 'formData.member_id', 'abc' )
            ->call( 'submit' )
            ->assertSee( 'Enter your 6-digit member ID' );
    } );

    it( 'shows the custom message from the submit API', function (): void {
        $form = Form::factory()->create( ['is_active' => true] );
        FormField::factory()->for( $form )->create( [
            'type'         => 'email',
            'name'         => 'email',
            'field_config' => ['error_message' => 'That email looks wrong'],
        ] );

        $response = $this->postJson( route( 'api.forms.submit', $form ), [
            'data'            => ['email' => 'nope'],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable();

        expect( $response->json( 'errors' )['data.email'] )->toBe( ['That email looks wrong'] );
    } );

    it( 'exposes the translated messages to the React and Vue renderers', function (): void {
        $field = FormField::factory()->create( ['type' => 'text', 'field_config' => ['error_message' => 'Bad value', 'required_message' => 'Needed']] );
        $plain = FormField::factory()->create( ['type' => 'text'] );

        $data      = ( new FormFieldResource( $field ) )->resolve( Request::create( '/' ) );
        $plainData = ( new FormFieldResource( $plain ) )->resolve( Request::create( '/' ) );

        expect( $data['error_message'] )->toBe( 'Bad value' )
            ->and( $data['required_message'] )->toBe( 'Needed' )
            ->and( $plainData )->not->toHaveKey( 'error_message' )
            ->and( $plainData )->not->toHaveKey( 'required_message' );
    } );
} );
