<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Services\NotificationService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use Illuminate\Support\Facades\Mail;

/**
 * Guards BE-4: notification conditions read a checkbox group or multi-select
 * as a list, so `includes` / `not_includes` match.
 */
beforeEach( function (): void {
    Mail::fake();

    $this->form = Form::factory()->create( ['is_active' => true] );
} );

it( 'matches includes and not_includes on a list of choices', function ( string $type, string $operator, array $tags, bool $sends ): void {
    FormField::factory()->for( $this->form )->create( [
        'type'         => $type,
        'name'         => 'tags',
        'field_config' => ['options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]],
    ] );

    $notification = FormNotification::factory()->for( $this->form )->create( [
        'conditional_logic' => ['action' => 'send', 'logic' => 'all', 'rules' => [['field' => 'tags', 'operator' => $operator, 'value' => 'a']]],
    ] );

    $submission = app( SubmissionService::class )->create( $this->form, ['tags' => $tags] );

    expect( app( NotificationService::class )->shouldSendNotification( $notification, $submission->fresh() ) )->toBe( $sends );
} )->with( ['checkbox_group', 'select_multiple'] )->with( [
    'includes, present'     => ['includes', ['a', 'b'], true],
    'includes, absent'      => ['includes', ['b'], false],
    'not_includes, present' => ['not_includes', ['a', 'b'], false],
    'not_includes, absent'  => ['not_includes', ['b'], true],
] );

it( 'still reads a text field\'s value as its text', function (): void {
    FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'name'] );

    $notification = FormNotification::factory()->for( $this->form )->create( [
        'conditional_logic' => ['action' => 'send', 'logic' => 'all', 'rules' => [['field' => 'name', 'operator' => 'equals', 'value' => 'Ada']]],
    ] );

    $submission = app( SubmissionService::class )->create( $this->form, ['name' => 'Ada'] );

    expect( app( NotificationService::class )->shouldSendNotification( $notification, $submission->fresh() ) )->toBeTrue();
} );
