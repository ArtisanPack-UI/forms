<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use ArtisanPackUI\Forms\Support\SubmissionRules;
use Livewire\Livewire;

/**
 * Guards issue #116: client-side length limits, the character / word counter
 * and the textarea word limit.
 */
beforeEach( function (): void {
    $this->form = Form::factory()->create( ['is_active' => true] );
} );

describe( 'length attributes', function (): void {
    it( 'renders minlength and maxlength from the length rules', function ( string $type ): void {
        $field = FormField::factory()->for( $this->form )->create( [
            'type'             => $type,
            'name'             => 'bio',
            'validation_rules' => ['min' => 0, 'max' => 140],
        ] );

        expect( FieldRenderer::render( $field, '' ) )->toContain( 'minlength="0"' )->toContain( 'maxlength="140"' );
    } )->with( ['text', 'textarea'] );

    it( 'renders no length attributes without length rules', function (): void {
        $field = FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'bio'] );

        expect( FieldRenderer::render( $field, '' ) )->not->toContain( 'maxlength' )->not->toContain( 'minlength' );
    } );

    it( 'only reads length limits on text and textarea fields', function (): void {
        $number = FormField::factory()->make( ['type' => 'number', 'name' => 'n', 'validation_rules' => ['max' => 5]] );

        expect( $number->getLengthLimit( 'max' ) )->toBeNull();
    } );
} );

describe( 'counter', function (): void {
    it( 'offers the counter on text and textarea fields and the word limit on textareas', function (): void {
        $text     = array_column( FieldSettings::schema( FormField::factory()->make( ['type' => 'text', 'name' => 't'] ) ), 'key' );
        $textarea = array_column( FieldSettings::schema( FormField::factory()->make( ['type' => 'textarea', 'name' => 't'] ) ), 'key' );
        $email    = array_column( FieldSettings::schema( FormField::factory()->make( ['type' => 'email', 'name' => 't'] ) ), 'key' );

        expect( $text )->toContain( 'show_counter' )->not->toContain( 'max_words' )
            ->and( $textarea )->toContain( 'show_counter', 'max_words' )
            ->and( $email )->not->toContain( 'show_counter' );
    } );

    it( 'renders a counter tied to the input and a throttled polite live region', function (): void {
        $field = FormField::factory()->for( $this->form )->create( [
            'type'             => 'text',
            'name'             => 'bio',
            'help_text'        => null,
            'field_config'     => ['show_counter' => true],
            'validation_rules' => ['max' => 140],
        ] );
        $html = FieldRenderer::render( $field, '' );

        expect( $html )->toContain( 'id="counter-bio"' )
            ->toContain( '0 of 140 characters' )
            ->toContain( 'aria-describedby="counter-bio"' )
            ->toContain( 'aria-live="polite"' )
            ->toContain( 'setTimeout(() => { this.announcement = this.text; }, 1000)' )
            ->toContain( 'x-on:input="update($event.target.value)"' );
    } );

    it( 'counts characters without a limit', function (): void {
        $field = FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'bio', 'field_config' => ['show_counter' => true]] );

        expect( FieldRenderer::render( $field, '' ) )->toContain( '0 characters' )->toContain( 'limit: null' );
    } );

    it( 'counts words when a textarea has a word limit', function (): void {
        $field = FormField::factory()->for( $this->form )->create( [
            'type'             => 'textarea',
            'name'             => 'essay',
            'field_config'     => ['show_counter' => true],
            'validation_rules' => ['max_words' => 250, 'max' => 5000],
        ] );

        expect( FieldRenderer::render( $field, '' ) )->toContain( '0 of 250 words' )->toContain( 'words: true' );
    } );

    it( 'renders no counter when off', function (): void {
        $field = FormField::factory()->for( $this->form )->create( ['type' => 'textarea', 'name' => 'essay'] );

        expect( FieldRenderer::render( $field, '' ) )->not->toContain( 'counter-essay' )->not->toContain( 'aria-live' );
    } );
} );

describe( 'word limit', function (): void {
    it( 'counts words between whitespace', function ( string $text, int $count ): void {
        expect( SubmissionRules::countWords( $text ) )->toBe( $count );
    } )->with( [
        ['', 0],
        ['   ', 0],
        ['one', 1],
        ["one  two\nthree\tfour", 4],
        ['  café au lait  ', 3],
    ] );

    it( 'adds the forms_max_words rule with a message', function (): void {
        $field = FormField::factory()->make( ['type' => 'textarea', 'name' => 'essay', 'label' => 'Essay', 'validation_rules' => ['max_words' => 3]] );

        expect( $field->buildValidationRules() )->toContain( 'forms_max_words:3' )
            ->and( $field->buildValidationMessages()['forms_max_words'] )->toBe( 'The Essay may not be longer than 3 words.' );
    } );

    it( 'rejects too many words and accepts the limit', function (): void {
        FormField::factory()->for( $this->form )->create( ['type' => 'textarea', 'name' => 'essay', 'validation_rules' => ['max_words' => 3]] );

        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.essay', 'one two three four' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.essay' => 'forms_max_words'] )
            ->set( 'formData.essay', 'one two three' )
            ->call( 'submit' )
            ->assertHasNoErrors();
    } );

    it( 'checks the word limit through the API', function (): void {
        FormField::factory()->for( $this->form )->create( ['type' => 'textarea', 'name' => 'essay', 'validation_rules' => ['max_words' => 2]] );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['essay' => 'too many words'],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( 'data.essay' );
    } );
} );
