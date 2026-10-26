<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormFieldResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use ArtisanPackUI\Forms\Support\LiveMergeTags;
use ArtisanPackUI\Security\SecurityServiceProvider;
use Illuminate\Http\Request;
use Livewire\Livewire;

/**
 * Guards issue #126: paragraph formatting, live merge tags in HTML blocks
 * and heading alignment.
 */
beforeEach( function (): void {
    $this->app->register( SecurityServiceProvider::class );

    $this->form = Form::factory()->create( ['is_active' => true] );
} );

describe( 'paragraph formatting', function (): void {
    it( 'keeps bold, italic, underline and links and removes everything else', function (): void {
        $paragraph = FormField::factory()->for( $this->form )->create( [
            'type'         => 'paragraph',
            'name'         => 'intro',
            'field_config' => ['content' => "Read the <strong>terms</strong> and <em>policy</em>, <u>now</u>.\n"
                . '<a href="https://example.com/terms" onclick="x()" target="_blank">Terms</a> '
                . '<a href="javascript:alert(1)">Bad</a><script>alert(1)</script><img src="x.png"><h2>Big</h2>'],
        ] );

        $html = $paragraph->getParagraphHtml();

        expect( $html )->toContain( '<strong>terms</strong>' )
            ->toContain( '<em>policy</em>' )
            ->toContain( '<u>now</u>' )
            ->toContain( '<br>' )
            ->toContain( 'href="https://example.com/terms"' )
            ->toContain( 'rel="nofollow"' )
            ->not->toContain( 'onclick' )
            ->not->toContain( 'target' )
            ->not->toContain( 'href="javascript:' )
            ->not->toContain( '<script' )
            ->not->toContain( '<img' )
            ->not->toContain( '<h2' );
    } );

    it( 'renders the formatted paragraph in Blade and sends it to React and Vue', function (): void {
        $paragraph = FormField::factory()->for( $this->form )->create( [
            'type'         => 'paragraph',
            'name'         => 'intro',
            'field_config' => ['content' => 'Say <b>hi</b>'],
        ] );

        $resource = ( new FormFieldResource( $paragraph ) )->toArray( Request::create( '/' ) );

        expect( FieldRenderer::render( $paragraph ) )->toContain( 'Say <b>hi</b>' )
            ->and( $resource['content_html'] )->toBe( 'Say <b>hi</b>' );
    } );

    it( 'escapes the paragraph without the security package', function (): void {
        $this->app->forgetInstance( 'security' );
        $this->app->offsetUnset( 'security' );

        $paragraph = FormField::factory()->make( ['type' => 'paragraph', 'field_config' => ['content' => "<b>Hi</b>\nthere"]] );

        expect( $paragraph->getParagraphHtml() )->toBe( "&lt;b&gt;Hi&lt;/b&gt;<br>\nthere" );
    } );
} );

describe( 'heading alignment', function (): void {
    it( 'offers left, center and right and renders the class', function ( string $align, ?string $class ): void {
        $heading = FormField::factory()->for( $this->form )->create( [
            'type'         => 'heading',
            'name'         => 'title',
            'label'        => 'Title',
            'field_config' => ['level' => 'h2', 'align' => $align],
        ] );

        expect( FieldSettings::find( $heading, 'align' ) )->toMatchArray( ['input' => 'select', 'default' => 'left'] )
            ->and( $heading->getHeadingAlignment() )->toBe( $align )
            ->and( FieldRenderer::render( $heading ) )->toContain( $class ?? 'font-bold' );

        if ( null === $class ) {
            expect( FieldRenderer::render( $heading ) )->not->toContain( 'text-left' );
        }
    } )->with( [
        'left'   => ['left', null],
        'center' => ['center', 'text-center'],
        'right'  => ['right', 'text-right'],
    ] );

    it( 'falls back to left for an unknown alignment', function (): void {
        expect( FormField::factory()->make( ['type' => 'heading', 'field_config' => ['align' => 'justify']] )->getHeadingAlignment() )->toBe( 'left' );
    } );
} );

describe( 'live merge tags', function (): void {
    beforeEach( function (): void {
        $this->name   = FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'first_name', 'label' => 'First name', 'sort_order' => 0] );
        $this->plan   = FormField::factory()->for( $this->form )->create( [
            'type'         => 'select',
            'name'         => 'plan',
            'label'        => 'Plan',
            'sort_order'   => 1,
            'field_config' => ['options' => [['value' => 'pro', 'label' => 'Pro plan']]],
        ] );
        $this->upload = FormField::factory()->for( $this->form )->create( ['type' => 'file', 'name' => 'resume', 'sort_order' => 2] );
        $this->html   = FormField::factory()->for( $this->form )->create( [
            'type'         => 'html',
            'name'         => 'summary',
            'sort_order'   => 3,
            'field_config' => ['content' => '<p>Hi {first_name}, you chose {plan}. <a href="/x?n={first_name}" title="{plan}">Link</a> {resume} {unknown}</p>'],
        ] );
    } );

    it( 'replaces tags in the text with placeholders and leaves attributes and other tags alone', function (): void {
        $html = LiveMergeTags::placeholders( FormField::sanitizeHtml( $this->html->getLayoutContent() ), $this->form->fields()->get() );

        expect( $html )->toContain( 'Hi <span data-forms-merge-tag="first_name"></span>, you chose <span data-forms-merge-tag="plan"></span>.' )
            ->toContain( 'href="/x?n={first_name}"' )
            ->toContain( 'title="{plan}"' )
            ->toContain( '{resume}' )
            ->toContain( '{unknown}' );
    } );

    it( 'supports a composite field\'s part', function (): void {
        FormField::factory()->for( $this->form )->create( ['type' => 'name', 'name' => 'full_name', 'sort_order' => 4] );

        expect( LiveMergeTags::placeholders( 'Dear {full_name:last}', $this->form->fields()->get() ) )
            ->toBe( 'Dear <span data-forms-merge-tag="full_name" data-forms-merge-part="last"></span>' );
    } );

    it( 'gives the renderer each field\'s choice labels and separator', function (): void {
        $formats = LiveMergeTags::formats( $this->form->fields()->get() );

        expect( $formats )->toHaveKeys( ['first_name', 'plan'] )
            ->not->toHaveKeys( ['resume', 'summary'] )
            ->and( $formats['plan']['options'] )->toBe( ['pro' => 'Pro plan'] )
            ->and( $formats['first_name']['separator'] )->toBe( ', ' );
    } );

    it( 'fills the placeholders with Alpine in the Livewire renderer', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->assertSeeHtml( '<span data-forms-merge-tag="first_name"></span>' )
            ->assertSeeHtml( 'wire:ignore' )
            ->assertSeeHtml( "this.\$wire.watch('formData', () => this.fill());" )
            ->assertSeeHtml( 'tag.textContent = hidden[name]' )
            ->assertSeeHtml( "this.\$wire.watch('otherValues', () => this.fill());" )
            ->assertSeeHtml( 'Object.prototype.hasOwnProperty.call(options, text) ? options[text] : text' );
    } );

    it( 'leaves an HTML block without tags untouched', function (): void {
        $this->html->update( ['field_config' => ['content' => '<p>No tags here</p>']] );

        expect( FieldRenderer::render( $this->html->fresh() ) )->toContain( '<p>No tags here</p>' )
            ->not->toContain( 'x-data' );
    } );
} );
