<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\FieldRenderer;

/**
 * Guards LW-10: invalid and required states on groups use attributes their
 * roles support.
 */
it( 'marks a rating radiogroup with an error invalid', function (): void {
    $field = FormField::factory()->make( ['type' => 'rating', 'name' => 'stars', 'label' => 'Stars'] );

    expect( FieldRenderer::render( $field, null, 'Pick a rating.' ) )->toMatch( '/role="radiogroup"[^>]*aria-invalid="true"/s' )
        ->and( FieldRenderer::render( $field ) )->not->toContain( 'aria-invalid' );
} );

it( 'marks a toggle with an error invalid', function (): void {
    $field = FormField::factory()->make( ['type' => 'toggle', 'name' => 'opt_in', 'label' => 'Opt in'] );

    expect( FieldRenderer::render( $field, false, 'Required.' ) )->toMatch( '/role="switch"[^>]*aria-invalid="true"/s' )
        ->and( FieldRenderer::render( $field, false ) )->not->toContain( 'aria-invalid' );
} );

it( 'says a checkbox group is required in its label, not with aria-required on the group', function (): void {
    $field = FormField::factory()->required()->make( [
        'type'         => 'checkbox_group',
        'name'         => 'topics',
        'label'        => 'Topics',
        'field_config' => ['options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]],
    ] );

    $html = FieldRenderer::render( $field, [] );

    preg_match( '/<div[^>]*role="group"[^>]*>/s', $html, $group );

    expect( $group[0] ?? '' )->not->toBe( '' )->not->toContain( 'aria-required' )
        ->and( $html )->toContain( '<span class="sr-only">(required)</span>' )
        ->and( $html )->toMatch( '/<span class="text-error"\s+aria-hidden="true"\s*>\*<\/span>/' );
} );

it( 'marks the first input of a required list required', function (): void {
    $field = FormField::factory()->required()->make( [
        'type'         => 'list',
        'name'         => 'people',
        'label'        => 'People',
        'field_config' => ['columns' => "Name\nEmail | email"],
    ] );

    $html = FieldRenderer::render( $field, [] );

    expect( substr_count( $html, 'aria-required="true"' ) )->toBe( 1 )
        ->and( $html )->toMatch( '/id="field-people-0-name"[^>]*aria-required="true"/s' );
} );
