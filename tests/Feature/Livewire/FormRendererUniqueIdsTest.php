<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use Illuminate\Support\Facades\Blade;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * Guards LW-06: element IDs carry a per-form-instance prefix, so two forms
 * on one page don't share IDs (and labels, descriptions and focus targets
 * resolve to their own form).
 */

/**
 * Creates a form with fields whose names another form on the page also uses.
 */
function formWithCommonFields(): Form
{
    $form = Form::factory()->create( ['is_active' => true] );

    FormField::factory()->for( $form )->required()->create( ['type' => 'email', 'name' => 'email', 'label' => 'Email', 'help_text' => 'We reply here.', 'sort_order' => 1] );
    FormField::factory()->for( $form )->create( ['type' => 'radio', 'name' => 'topic', 'label' => 'Topic', 'help_text' => 'Pick one.', 'sort_order' => 2, 'field_config' => ['options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]]] );
    FormField::factory()->for( $form )->create( ['type' => 'date', 'name' => 'when', 'label' => 'When', 'sort_order' => 3] );
    FormField::factory()->for( $form )->create( ['type' => 'checkbox', 'name' => 'agree', 'label' => 'Agree', 'sort_order' => 4] );

    return $form;
}

/**
 * Gets the IDs used more than once in some HTML.
 *
 * @return array<string, int>
 */
function duplicateIds( string $html ): array
{
    preg_match_all( '/\sid="([^"]+)"/', $html, $matches );

    return array_filter( array_count_values( $matches[1] ), fn ( int $count ): bool => $count > 1 );
}

it( 'gives two forms with the same field names distinct element IDs', function (): void {
    $first  = formWithCommonFields();
    $second = formWithCommonFields();

    $html = Livewire::test( FormRenderer::class, ['form' => $first] )->html()
        . Livewire::test( FormRenderer::class, ['form' => $second] )->html();

    expect( duplicateIds( $html ) )->toBe( [] )
        ->and( $html )->toContain( 'id="hint-f' . $first->id . '-email"' )
        ->and( $html )->toContain( 'id="hint-f' . $second->id . '-email"' )
        ->and( $html )->toContain( 'id="website_url_f' . $first->id . '"' )
        ->and( $html )->toContain( 'id="website_url_f' . $second->id . '"' );
} );

it( 'points labels and descriptions at its own form\'s elements', function (): void {
    $form = formWithCommonFields();
    $html = Livewire::test( FormRenderer::class, ['form' => $form] )->html();

    preg_match_all( '/\s(?:for|aria-labelledby|aria-describedby|aria-controls)="([^"]+)"/', $html, $references );
    preg_match_all( '/\sid="([^"]+)"/', $html, $ids );

    $referenced = array_unique( array_merge( ...array_map( fn ( string $value ): array => explode( ' ', $value ), $references[1] ) ) );

    expect( $referenced )->not->toBeEmpty();

    foreach ( $referenced as $id ) {
        expect( $id )->toMatch( '/(^|[-_])f' . $form->id . '([-_]|$)/' )
            ->and( $ids[1] )->toContain( $id );
    }
} );

it( 'gives a second renderer of the same form in one request its own prefix', function (): void {
    $form = formWithCommonFields();

    $html = Blade::render( '<div><livewire:form-renderer :form="$form" /><livewire:form-renderer :form="$form" /></div>', ['form' => $form] );

    expect( $html )->toContain( 'id="hint-f' . $form->id . '-email"' )
        ->and( $html )->toContain( 'id="hint-f' . $form->id . '-2-email"' )
        ->and( duplicateIds( $html ) )->toBe( [] );
} );

it( 'keeps the IDs across re-renders', function (): void {
    $form      = formWithCommonFields();
    $component = Livewire::test( FormRenderer::class, ['form' => $form] );

    $component->set( 'formData.email', 'ada@example.com' )
        ->assertSet( 'idPrefix', 'f' . $form->id )
        ->assertSeeHtml( 'id="hint-f' . $form->id . '-email"' );
} );

it( 'keeps the ID prefix locked', function (): void {
    Livewire::test( FormRenderer::class, ['form' => formWithCommonFields()] )
        ->set( 'idPrefix', 'other' );
} )->throws( CannotUpdateLockedPropertyException::class );
