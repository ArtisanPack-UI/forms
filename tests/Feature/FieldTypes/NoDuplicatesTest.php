<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Enums\SubmissionStatus;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\FieldSettings;
use ArtisanPackUI\Forms\Support\SubmissionRules;
use Livewire\Livewire;

/**
 * Guards issue #100: a "No duplicates" option that rejects a value already
 * submitted for the field.
 */
describe( 'no duplicates setting', function (): void {
    it( 'is a Validation tab toggle on text, email, phone, number, url and hidden fields', function ( string $type ): void {
        expect( FieldSettings::find( FormField::factory()->make( ['type' => $type] ), 'no_duplicates' ) )
            ->toMatchArray( ['tab' => 'validation', 'input' => 'toggle', 'store' => 'field_config'] );
    } )->with( ['text', 'email', 'phone', 'number', 'url', 'hidden'] );

    it( 'is not offered on other types', function ( string $type ): void {
        expect( FieldSettings::find( FormField::factory()->make( ['type' => $type] ), 'no_duplicates' ) )->toBeNull();
    } )->with( ['textarea', 'select', 'radio', 'checkbox_group', 'date', 'file'] );

    it( 'adds a rule keyed by the field, case-insensitive for email', function (): void {
        $text  = FormField::factory()->create( ['type' => 'text', 'field_config' => ['no_duplicates' => true]] );
        $email = FormField::factory()->create( ['type' => 'email', 'field_config' => ['no_duplicates' => true]] );
        $off   = FormField::factory()->create( ['type' => 'text'] );

        expect( $text->buildValidationRules() )->toContain( 'forms_no_duplicates:' . $text->id )
            ->and( $email->buildValidationRules() )->toContain( 'forms_no_duplicates:' . $email->id . ',ci' )
            ->and( implode( '|', array_filter( $off->buildValidationRules(), 'is_string' ) ) )->not->toContain( 'forms_no_duplicates' );
    } );

    it( 'skips the rule for an unsaved field', function (): void {
        $field = FormField::factory()->make( ['type' => 'text', 'field_config' => ['no_duplicates' => true]] );

        expect( implode( '|', array_filter( $field->buildValidationRules(), 'is_string' ) ) )->not->toContain( 'forms_no_duplicates' );
    } );

    it( 'skips the submission an admin is editing', function (): void {
        expect( SubmissionRules::ignoringSubmission( ['required', 'forms_no_duplicates:4,ci'], 12 ) )
            ->toBe( ['required', 'forms_no_duplicates:4,ci,12'] );
    } );
} );

describe( 'no duplicates validation', function (): void {
    beforeEach( function (): void {
        $this->form  = Form::factory()->create( ['is_active' => true] );
        $this->email = FormField::factory()->for( $this->form )->create( [
            'type'         => 'email',
            'name'         => 'email',
            'label'        => 'Email',
            'field_config' => ['no_duplicates' => true],
        ] );
        $this->code = FormField::factory()->for( $this->form )->create( [
            'type'         => 'text',
            'name'         => 'code',
            'label'        => 'Code',
            'sort_order'   => 2,
            'field_config' => ['no_duplicates' => true],
        ] );

        $this->submit = fn ( array $data ) => Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->afterSpamDelay()
            ->set( 'formData', $data )
            ->call( 'submit' );
    } );

    it( 'accepts the first value and rejects it the second time', function (): void {
        ( $this->submit )( ['email' => 'ada@example.com', 'code' => 'A1'] )->assertHasNoErrors()->assertSet( 'isSubmitted', true );

        ( $this->submit )( ['email' => 'ada@example.com', 'code' => 'B2'] )
            ->assertHasErrors( ['formData.email' => SubmissionRules::NO_DUPLICATES] )
            ->assertSee( 'This Email has already been submitted.' );

        expect( FormSubmission::count() )->toBe( 1 );
    } );

    it( 'compares email addresses case-insensitively and other text exactly', function (): void {
        app( SubmissionService::class )->create( $this->form, ['email' => 'Ada@Example.com', 'code' => 'abc'] );

        ( $this->submit )( ['email' => 'ada@example.COM', 'code' => 'ABC'] )
            ->assertHasErrors( 'formData.email' )
            ->assertHasNoErrors( 'formData.code' );
    } );

    it( 'ignores spam and quarantined submissions', function (): void {
        app( SubmissionService::class )->create( $this->form, ['email' => 'spam@example.com'] )->update( ['is_spam' => true] );
        app( SubmissionService::class )->create( $this->form, ['email' => 'held@example.com'] )->quarantine();

        ( $this->submit )( ['email' => 'spam@example.com'] )->assertHasNoErrors();
        ( $this->submit )( ['email' => 'held@example.com'] )->assertHasNoErrors();
    } );

    it( 'counts archived submissions', function (): void {
        app( SubmissionService::class )->create( $this->form, ['email' => 'kept@example.com'] )->update( ['status' => SubmissionStatus::Archived] );

        ( $this->submit )( ['email' => 'kept@example.com'] )->assertHasErrors( 'formData.email' );
    } );

    it( 'only looks at values of the same field', function (): void {
        app( SubmissionService::class )->create( $this->form, ['code' => 'ada@example.com'] );

        ( $this->submit )( ['email' => 'ada@example.com'] )->assertHasNoErrors();
    } );

    it( 'lets a blank optional value through', function (): void {
        app( SubmissionService::class )->create( $this->form, ['email' => '', 'code' => ''] );

        ( $this->submit )( ['email' => '', 'code' => ''] )->assertHasNoErrors();
    } );

    it( 'uses the custom error message', function (): void {
        $this->email->update( ['field_config' => ['no_duplicates' => true, 'error_message' => 'You already signed up.']] );
        app( SubmissionService::class )->create( $this->form, ['email' => 'ada@example.com'] );

        ( $this->submit )( ['email' => 'ada@example.com'] )->assertSee( 'You already signed up.' );
    } );

    it( 'rejects a duplicate through the API', function (): void {
        app( SubmissionService::class )->create( $this->form, ['email' => 'ada@example.com'] );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['email' => 'ADA@example.com'],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( 'data.email' );
    } );
} );
