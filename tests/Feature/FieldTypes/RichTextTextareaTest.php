<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormSubmissionValueResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Services\NotificationService;
use ArtisanPackUI\Forms\Support\DynamicPopulation;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use ArtisanPackUI\Forms\Support\PopulationContext;
use ArtisanPackUI\Forms\Support\RichText;
use ArtisanPackUI\Forms\Support\SubmissionRules;
use ArtisanPackUI\Security\SecurityServiceProvider;
use Illuminate\Http\Request;
use Livewire\Livewire;

/**
 * Guards issue #121: the rich text mode of the textarea.
 */
beforeEach( function (): void {
    $this->app->register( SecurityServiceProvider::class );

    $this->form    = Form::factory()->create( ['is_active' => true] );
    $this->message = FormField::factory()->for( $this->form )->create( [
        'type'         => 'textarea',
        'name'         => 'message',
        'label'        => 'Message',
        'help_text'    => null,
        'field_config' => ['rich_text' => true],
    ] );
} );

describe( 'RichText', function (): void {
    it( 'keeps formatting and removes everything else', function (): void {
        $html = RichText::sanitize(
            '<p onclick="steal()" style="color:red" class="x">Hi <strong>there</strong> <em>you</em></p>'
            . '<script>alert(1)</script><ul><li>One</li></ul><img src="x.png"><iframe src="https://evil.test"></iframe>',
        );

        expect( $html )->toContain( '<p>Hi <strong>there</strong> <em>you</em></p>' )
            ->toContain( '<ul><li>One</li></ul>' )
            ->not->toContain( 'onclick' )
            ->not->toContain( 'style' )
            ->not->toContain( 'class=' )
            ->not->toContain( '<script' )
            ->not->toContain( '<img' )
            ->not->toContain( '<iframe' );
    } );

    it( 'keeps safe links, marked nofollow, and drops unsafe ones', function (): void {
        expect( RichText::sanitize( '<p><a href="https://example.com" target="_blank">Site</a></p>' ) )
            ->toContain( 'href="https://example.com"' )
            ->toContain( 'rel="nofollow"' )
            ->not->toContain( 'target' )
            ->and( RichText::sanitize( '<p><a href="javascript:alert(1)">Click</a></p>' ) )
            ->not->toContain( 'href="javascript:' );
    } );

    it( 'blanks markup that holds no text', function (): void {
        expect( RichText::sanitize( '<p><br></p>' ) )->toBe( '' )
            ->and( RichText::blankIfEmpty( '<p>&nbsp;</p>' ) )->toBe( '' )
            ->and( RichText::blankIfEmpty( '<p>Hi</p>' ) )->toBe( '<p>Hi</p>' )
            ->and( RichText::blankIfEmpty( 42 ) )->toBe( 42 );
    } );

    it( 'converts markup to plain text', function (): void {
        $html = '<p>Hello &amp; welcome</p><p>Line<br>break</p><ul><li>One</li><li>Two</li></ul>';

        expect( RichText::toPlainText( $html ) )->toBe( "Hello & welcome\nLine\nbreak\nOne\nTwo" )
            ->and( RichText::toPlainText( $html, true ) )->toBe( "Hello & welcome\nLine\nbreak\n- One\n- Two" )
            ->and( RichText::length( '<p><strong>abc</strong></p>' ) )->toBe( 3 );
    } );
} );

describe( 'settings and rules', function (): void {
    it( 'offers the rich text toggle', function (): void {
        expect( FieldSettings::find( $this->message, 'rich_text' ) )->toMatchArray( ['input' => 'toggle', 'store' => 'field_config'] )
            ->and( $this->message->usesRichText() )->toBeTrue();
    } );

    it( 'counts the text of rich text for length and word limits', function (): void {
        $this->message->update( ['validation_rules' => ['min' => 5, 'max' => 100, 'max_words' => 20]] );
        $field = $this->message->fresh();

        expect( $field->buildValidationRules() )->toBe( [
            'nullable',
            'string',
            'max:' . RichText::MAX_HTML_LENGTH,
            SubmissionRules::MIN_TEXT . ':5',
            SubmissionRules::MAX_TEXT . ':100',
            SubmissionRules::MAX_WORDS . ':20,html',
        ] )
            ->and( $field->buildValidationMessages()[ SubmissionRules::MAX_TEXT ] )->toBe( 'The Message may not be greater than 100 characters.' );
    } );

    it( 'keeps the plain textarea rules without rich text', function (): void {
        $this->message->update( ['field_config' => [], 'validation_rules' => ['max' => 100, 'max_words' => 20]] );

        expect( $this->message->fresh()->buildValidationRules() )->toBe( ['nullable', 'string', 'max:100', SubmissionRules::MAX_WORDS . ':20'] );
    } );

    it( 'checks text length and words without the markup', function (): void {
        expect( SubmissionRules::withinTextLength( '<p><strong>abcd</strong></p>', ['4'], 'max' ) )->toBeTrue()
            ->and( SubmissionRules::withinTextLength( '<p>abcde</p>', ['4'], 'max' ) )->toBeFalse()
            ->and( SubmissionRules::withinTextLength( '<p>ab</p>', ['3'], 'min' ) )->toBeFalse()
            ->and( SubmissionRules::withinWordLimit( '<p>one</p><p>two</p>', ['1', 'html'] ) )->toBeFalse()
            ->and( SubmissionRules::withinWordLimit( '<p>one <b>two</b></p>', ['2', 'html'] ) )->toBeTrue();
    } );
} );

describe( 'submission', function (): void {
    it( 'caps the markup so empty tags cannot pad past the text limits', function (): void {
        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['message' => '<p>Hi' . str_repeat( '<b></b>', 10000 ) . '</p>'],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( ['data.message' => 'The Message is too long.'] );
    } );

    it( 'sanitizes the default and read-only values the editors start with', function (): void {
        $this->message->update( ['default_value' => '<p>Hi <img src=x onerror="steal()"></p>'] );
        $field = $this->message->fresh();

        expect( DynamicPopulation::initialValue( $field, PopulationContext::trusted() ) )->toBe( '<p>Hi </p>' );

        $field->update( ['field_config' => ['rich_text' => true, 'read_only' => true]] );

        expect( $field->fresh()->getReadOnlyValue() )->toBe( '<p>Hi </p>' );
    } );

    it( 'treats an emptied editor as empty for a required field', function (): void {
        $this->message->update( ['is_required' => true] );

        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.message', '<p><br></p>' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.message' => 'required'] );
    } );

    it( 'stores sanitized HTML from the Livewire renderer', function (): void {
        $this->message->update( ['validation_rules' => ['max' => 10]] );

        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.message', '<p><strong>Hello</strong> <span style="x">world</span></p><script>bad()</script>' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.message' => SubmissionRules::MAX_TEXT] )
            ->set( 'formData.message', '<p><strong>Hello</strong><script>bad()</script></p>' )
            ->call( 'submit' )
            ->assertHasNoErrors();

        $stored = $this->form->submissions()->first()->values->first()->value;

        expect( $stored )->toContain( '<strong>Hello</strong>' )->not->toContain( 'script' );
    } );

    it( 'sanitizes HTML submitted through the API', function (): void {
        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['message' => '<p onmouseover="x()">Hi <a href="javascript:x()">there</a></p>'],
            '_form_loaded_at' => time() - 10,
        ] )->assertCreated();

        expect( $this->form->submissions()->first()->values->first()->value )
            ->not->toContain( 'onmouseover' )
            ->not->toContain( 'href="javascript:' );
    } );
} );

describe( 'display', function (): void {
    beforeEach( function (): void {
        $this->submission = FormSubmission::factory()->for( $this->form )->create();
        $this->value      = FormSubmissionValue::factory()->create( [
            'submission_id' => $this->submission->id,
            'field_id'      => $this->message->id,
            'field_name'    => 'message',
            'field_label'   => 'Message',
            'field_type'    => 'textarea',
            'value'         => '<p>Hi <strong>team</strong></p><ul><li>One</li></ul><script>x()</script>',
        ] );
    } );

    it( 'shows plain text as the display value and sanitized HTML as the display HTML', function (): void {
        $value = $this->value->fresh();

        expect( $value->display_value )->toStartWith( "Hi team\n- One" )->not->toContain( '<' )
            ->and( $value->display_html )->toContain( '<strong>team</strong>' )->not->toContain( '<script' );
    } );

    it( 'has no display HTML for a plain textarea', function (): void {
        $this->message->update( ['field_config' => []] );

        expect( $this->value->fresh()->display_html )->toBeNull()
            ->and( $this->value->fresh()->display_value )->toContain( '<strong>team</strong>' );
    } );

    it( 'uses plain text in text emails and exports and HTML in HTML emails', function (): void {
        $submission = $this->submission->fresh( ['values'] );
        $service    = app( NotificationService::class );
        $row        = app( ExportService::class )->buildRow( $this->form, $submission, $this->form->fields()->get() );

        expect( $service->formatAllFieldsPlainText( $submission ) )->toContain( "Message: Hi team\n- One" )->not->toContain( '<strong>' )
            ->and( $service->formatAllFieldsAsTable( $submission ) )->toContain( '<strong>team</strong>' )->not->toContain( '<script' )
            ->and( implode( '|', $row ) )->toContain( "Hi team\n- One" )->not->toContain( '<strong>' );
    } );

    it( 'sends the display HTML to the React and Vue admins', function (): void {
        $resource = ( new FormSubmissionValueResource( $this->value->fresh() ) )->toArray( Request::create( '/' ) );

        expect( $resource['display_html'] )->toContain( '<strong>team</strong>' )
            ->and( $resource['display_value'] )->not->toContain( '<strong>' );
    } );
} );

describe( 'rendering', function (): void {
    it( 'renders a labelled toolbar and a contenteditable textbox', function (): void {
        $html = FieldRenderer::render( $this->message, '<p>Hi <script>x()</script></p>' );

        expect( $html )->toContain( 'role="toolbar"' )
            ->toContain( 'aria-label="Formatting for Message"' )
            ->toContain( 'aria-controls="field-message"' )
            ->toContain( 'role="textbox"' )
            ->toContain( 'aria-multiline="true"' )
            ->toContain( 'contenteditable="true"' )
            ->toContain( 'aria-labelledby="label-message"' )
            ->toContain( 'aria-label="Bold"' )
            ->toContain( 'x-bind:aria-pressed=' )
            ->toContain( 'x-on:keydown.right.prevent="move(1)"' )
            ->toContain( '><p>Hi x()</p></div>' )
            ->not->toContain( '<script' )
            ->not->toContain( '<textarea' );
    } );

    it( 'keeps Livewire from morphing the editor and passes the error state through a morphed element', function (): void {
        $this->message->update( ['help_text' => 'Tell us more'] );
        $html = FieldRenderer::render( $this->message->fresh(), '', 'The Message field is required.' );

        expect( $html )->toContain( 'wire:ignore' )
            ->toContain( 'data-forms-rich-text-state' )
            ->toContain( 'data-invalid="true"' )
            ->toContain( 'data-described-by="error-message hint-message"' )
            ->toContain( 'this.observer = new MutationObserver(readState);' )
            ->toContain( "x-bind:aria-invalid=\"invalid ? 'true' : null\"" )
            ->toContain( 'x-bind:aria-describedby="describedBy"' )
            ->and( strpos( $html, 'data-forms-rich-text-state' ) )->toBeLessThan( strpos( $html, 'wire:ignore' ) );
    } );

        it( 'locks the editor and its toolbar when read-only', function (): void {
        $this->message->update( ['field_config' => ['rich_text' => true, 'read_only' => true]] );

        expect( FieldRenderer::render( $this->message->fresh(), '' ) )->toContain( 'contenteditable="false"' )
            ->toContain( 'aria-readonly="true"' )
            ->toContain( 'disabled' );
    } );

    it( 'counts the editor text in the counter', function (): void {
        $this->message->update( ['field_config' => ['rich_text' => true, 'show_counter' => true]] );

        expect( FieldRenderer::render( $this->message->fresh(), '' ) )->toContain( 'read(element) {' )
            ->toContain( "querySelector('input, textarea, [contenteditable]')" );
    } );
} );

it( 'falls back to escaped text without the security package', function (): void {
    $this->app->forgetInstance( 'security' );
    $this->app->offsetUnset( 'security' );

    expect( RichText::sanitize( '<p>Hi <b onclick="x()">there</b></p><p>Bye</p>' ) )->toBe( "Hi there<br>\nBye" );
} );
