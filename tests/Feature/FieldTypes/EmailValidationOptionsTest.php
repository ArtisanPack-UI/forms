<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\EmailDomains;
use ArtisanPackUI\Forms\Support\FieldSettings;
use ArtisanPackUI\Forms\Support\SubmissionRules;
use Livewire\Livewire;

/**
 * Guards issue #119: email validation strictness and allowed / blocked domain lists.
 */
beforeEach( function (): void {
    $this->form  = Form::factory()->create( ['is_active' => true] );
    $this->email = FormField::factory()->for( $this->form )->create( [
        'type'  => 'email',
        'name'  => 'email',
        'label' => 'Email',
    ] );
} );

describe( 'EmailDomains', function (): void {
    it( 'normalizes domain lists', function (): void {
        expect( EmailDomains::normalizeList( [' @Gmail.com ', 'yahoo.com', '', 'gmail.com', 42] ) )->toBe( ['gmail.com', 'yahoo.com'] )
            ->and( EmailDomains::normalizeList( 'a.com, b.org' ) )->toBe( ['a.com', 'b.org'] )
            ->and( EmailDomains::normalizeList( null ) )->toBe( [] );
    } );

    it( 'matches a domain and its subdomains, case-insensitively', function ( string $email, bool $matches ): void {
        expect( EmailDomains::matches( (string) EmailDomains::domainOf( $email ), ['example.com'] ) )->toBe( $matches );
    } )->with( [
        'exact'         => ['jo@example.com', true],
        'uppercase'     => ['jo@EXAMPLE.COM', true],
        'subdomain'     => ['jo@mail.example.com', true],
        'lookalike'     => ['jo@notexample.com', false],
        'other tld'     => ['jo@example.org', false],
        'suffix domain' => ['jo@example.com.evil.net', false],
    ] );

    it( 'lets values without a domain through for the email rule to judge', function (): void {
        expect( EmailDomains::passes( 'no-at-sign', ['allow', 'example.com'] ) )->toBeTrue()
            ->and( EmailDomains::passes( 42, ['block', 'example.com'] ) )->toBeTrue()
            ->and( EmailDomains::passes( 'jo@example.com', ['other', 'example.com'] ) )->toBeTrue();
    } );
} );

describe( 'settings', function (): void {
    it( 'offers the strictness and domain settings in the validation tab', function (): void {
        $keys = array_column( FieldSettings::forTab( $this->email, 'validation' ), 'key' );

        expect( $keys )->toContain( 'email_validation', 'allowed_domains', 'blocked_domains' )
            ->and( FieldSettings::find( $this->email, 'allowed_domains' )['store'] )->toBe( 'validation_rules' )
            ->and( array_column( FieldSettings::find( $this->email, 'email_validation' )['options'], 'value' ) )->toBe( ['rfc', 'dns', 'strict'] );
    } );

    it( 'stores domain lists as arrays', function (): void {
        $update = FieldSettings::update( $this->email, FieldSettings::find( $this->email, 'blocked_domains' ), 'gmail.com, yahoo.com' );

        expect( $update['validation_rules']['blocked_domains'] )->toBe( ['gmail.com', 'yahoo.com'] );
    } );
} );

describe( 'validation rules', function (): void {
    it( 'keeps the plain email rule by default', function (): void {
        expect( $this->email->buildValidationRules() )->toBe( ['nullable', 'email'] )
            ->and( $this->email->getEmailValidation() )->toBe( 'rfc' );
    } );

    it( 'adds the strictness the field asks for', function ( string $mode, string $rule ): void {
        $this->email->update( ['validation_rules' => ['email_validation' => $mode]] );

        expect( $this->email->fresh()->buildValidationRules() )->toContain( $rule );
    } )->with( [
        'rfc'    => ['rfc', 'email'],
        'dns'    => ['dns', 'email:rfc,dns'],
        'strict' => ['strict', 'email:strict'],
    ] );

    it( 'falls back to rfc for an unknown strictness', function (): void {
        $this->email->update( ['validation_rules' => ['email_validation' => 'bogus']] );

        expect( $this->email->fresh()->buildValidationRules() )->toContain( 'email' );
    } );

    it( 'adds the domain rules after the email and confirmation rules', function (): void {
        $this->email->update( [
            'field_config'     => ['confirm_email' => true],
            'validation_rules' => ['allowed_domains' => ['Example.com'], 'blocked_domains' => ['@spam.example.com']],
        ] );

        expect( $this->email->fresh()->buildValidationRules() )->toBe( [
            'nullable',
            'email',
            'confirmed',
            SubmissionRules::EMAIL_DOMAIN . ':allow,example.com',
            SubmissionRules::EMAIL_DOMAIN . ':block,spam.example.com',
        ] );
    } );
} );

describe( 'submission', function (): void {
    it( 'rejects a blocked domain and accepts others in the Livewire renderer', function (): void {
        $this->email->update( ['validation_rules' => ['blocked_domains' => ['gmail.com']]] );

        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.email', 'jo@Gmail.com' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.email' => SubmissionRules::EMAIL_DOMAIN] )
            ->assertSee( 'Please use an email address from an accepted domain for Email.' )
            ->set( 'formData.email', 'jo@company.com' )
            ->call( 'submit' )
            ->assertHasNoErrors();

        expect( $this->form->submissions()->first()->values->first()->value )->toBe( 'jo@company.com' );
    } );

    it( 'accepts only allowed domains through the API', function (): void {
        $this->email->update( ['validation_rules' => ['allowed_domains' => ['company.com']]] );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['email' => 'jo@other.com'],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( 'data.email' );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['email' => 'jo@team.company.com'],
            '_form_loaded_at' => time() - 10,
        ] )->assertCreated();
    } );

    it( 'applies the custom error message to the domain rule', function (): void {
        $this->email->update( [
            'field_config'     => ['error_message' => 'Use your work email'],
            'validation_rules' => ['blocked_domains' => ['gmail.com']],
        ] );

        expect( $this->email->fresh()->buildValidationMessages()[ SubmissionRules::EMAIL_DOMAIN ] )->toBe( 'Use your work email' );
    } );
} );
