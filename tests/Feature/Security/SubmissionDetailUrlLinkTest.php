<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\SubmissionDetail;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use Livewire\Livewire;
use Tests\Models\User;

/**
 * Guards SEC-06 (render side): the submission detail links only http(s)
 * URLs, so a stored `javascript:` value can't run in the admin.
 */
beforeEach( function (): void {
    $this->actingAs( User::factory()->create() );
} );

it( 'does not render a :dataset URL value inside an href', function ( string $url ): void {
    $submission = FormSubmission::factory()->for( Form::factory()->create() )->create();

    FormSubmissionValue::factory()->create( [
        'submission_id' => $submission->id,
        'field_type'    => 'url',
        'field_label'   => 'Website',
        'value'         => $url,
    ] );

    Livewire::test( SubmissionDetail::class, ['submission' => $submission] )
        ->assertSee( $url )
        ->assertDontSeeHtml( 'href="' . e( $url ) . '"' );
} )->with( [
    'javascript' => 'javascript://example.com/%0aalert(document.cookie)',
    'data'       => 'data:text/html,<script>alert(1)</script>',
    'vbscript'   => 'vbscript:msgbox(1)',
] );

it( 'links an https URL value', function (): void {
    $submission = FormSubmission::factory()->for( Form::factory()->create() )->create();

    FormSubmissionValue::factory()->create( [
        'submission_id' => $submission->id,
        'field_type'    => 'url',
        'field_label'   => 'Website',
        'value'         => 'https://example.com/a',
    ] );

    Livewire::test( SubmissionDetail::class, ['submission' => $submission] )
        ->assertSeeHtml( 'href="https://example.com/a"' );
} );

it( 'does not link a page or referrer URL with an unsafe scheme', function (): void {
    $submission = FormSubmission::factory()->for( Form::factory()->create() )->create( [
        'page_url'     => 'javascript:alert(1)',
        'referrer_url' => 'https://example.com/from',
    ] );

    Livewire::test( SubmissionDetail::class, ['submission' => $submission] )
        ->assertDontSeeHtml( 'href="javascript:alert(1)"' )
        ->assertSee( 'javascript:alert(1)' )
        ->assertSeeHtml( 'href="https://example.com/from"' );
} );
