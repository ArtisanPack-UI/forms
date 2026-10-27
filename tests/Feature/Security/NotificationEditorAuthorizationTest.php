<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\NotificationEditor;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormNotification;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Models\User;

/**
 * Guards SEC-03: the notification editor checks the manageNotifications
 * ability on mount and in every action, only changes the attributes it
 * edits, and validates them like the notification API.
 */
beforeEach( function (): void {
    config( ['artisanpack.forms.authorization.restrict_by_owner' => true] );

    $this->owner        = User::factory()->create();
    $this->stranger     = User::factory()->create();
    $this->form         = Form::factory()->create( ['user_id' => $this->owner->id] );
    $this->otherForm    = Form::factory()->create( ['user_id' => $this->stranger->id] );
    $this->notification = FormNotification::factory()->for( $this->form )->create( ['subject' => 'Original'] );
} );

it( 'is forbidden to mount for a form the user may not edit', function (): void {
    Livewire::actingAs( $this->stranger )->test( NotificationEditor::class, ['form' => $this->form] )
        ->assertForbidden();
} );

it( 'is forbidden to mount for a guest', function (): void {
    Livewire::test( NotificationEditor::class, ['form' => $this->form] )
        ->assertForbidden();
} );

it( 'never moves a notification to another form', function (): void {
    Livewire::actingAs( $this->owner )->test( NotificationEditor::class, ['form' => $this->form] )
        ->call( 'updateNotification', ['form_id' => $this->otherForm->id, 'sort_order' => 99, 'subject' => 'Changed'] )
        ->assertHasNoErrors();

    $notification = $this->notification->fresh();

    expect( $notification->form_id )->toBe( $this->form->id )
        ->and( $notification->subject )->toBe( 'Changed' )
        ->and( $notification->sort_order )->not->toBe( 99 );
} );

it( 'validates notification attributes', function (): void {
    Livewire::actingAs( $this->owner )->test( NotificationEditor::class, ['form' => $this->form] )
        ->call( 'updateNotification', ['to_email' => 'not-an-email'] )
        ->assertHasErrors( 'to_email' );

    Livewire::actingAs( $this->owner )->test( NotificationEditor::class, ['form' => $this->form] )
        ->call( 'updateNotification', ['cc_emails' => 'a@example.com, nope'] )
        ->assertHasErrors( 'cc_emails' );

    Livewire::actingAs( $this->owner )->test( NotificationEditor::class, ['form' => $this->form] )
        ->call( 'updateNotification', ['type' => 'nonsense'] )
        ->assertHasErrors( 'type' );

    expect( $this->notification->fresh()->to_email )->not->toBe( 'not-an-email' );
} );

it( 'only accepts a recipient field that is an email field on this form', function (): void {
    FormField::factory()->for( $this->form )->create( ['type' => 'email', 'name' => 'contact_email'] );
    FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'name'] );

    Livewire::actingAs( $this->owner )->test( NotificationEditor::class, ['form' => $this->form] )
        ->call( 'updateNotification', ['to_field' => 'name'] )
        ->assertHasErrors( 'to_field' )
        ->call( 'updateNotification', ['to_field' => 'contact_email'] )
        ->assertHasNoErrors();

    expect( $this->notification->fresh()->to_field )->toBe( 'contact_email' );
} );

it( 'rejects an unknown notification type when adding', function (): void {
    Livewire::actingAs( $this->owner )->test( NotificationEditor::class, ['form' => $this->form] )
        ->call( 'addNotification', 'nonsense' )
        ->assertHasErrors( 'type' );

    expect( $this->form->notifications()->count() )->toBe( 1 );
} );

it( 'locks the selected notification and ignores other forms\' notifications', function (): void {
    $foreign = FormNotification::factory()->for( $this->otherForm )->create();

    $editor = Livewire::actingAs( $this->owner )->test( NotificationEditor::class, ['form' => $this->form] )
        ->call( 'selectNotification', $foreign->id )
        ->assertSet( 'selectedNotificationId', $this->notification->id );

    expect( fn () => $editor->set( 'selectedNotificationId', $foreign->id ) )
        ->toThrow( CannotUpdateLockedPropertyException::class );
} );

it( 'forbids actions once the user may no longer manage notifications', function ( string $method, Closure $arguments ): void {
    $editor = Livewire::actingAs( $this->owner )->test( NotificationEditor::class, ['form' => $this->form] );

    $this->actingAs( $this->stranger );

    $editor->call( $method, ...$arguments->call( $this ) )->assertForbidden();

    expect( $this->notification->fresh() )->not->toBeNull()
        ->and( $this->notification->fresh()->subject )->toBe( 'Original' )
        ->and( $this->form->notifications()->count() )->toBe( 1 );
} )->with( [
    'addNotification'        => ['addNotification', fn (): array => [FormNotification::TYPE_ADMIN]],
    'selectNotification'     => ['selectNotification', fn (): array => [$this->notification->id]],
    'updateNotification'     => ['updateNotification', fn (): array => [['subject' => 'Changed']]],
    'toggleActive'           => ['toggleActive', fn (): array => [$this->notification->id]],
    'duplicateNotification'  => ['duplicateNotification', fn (): array => [$this->notification->id]],
    'deleteNotification'     => ['deleteNotification', fn (): array => [$this->notification->id]],
    'reorderNotifications'   => ['reorderNotifications', fn (): array => [[$this->notification->id]]],
    'updateConditionalLogic' => ['updateConditionalLogic', fn (): array => [['action' => 'send', 'logic' => 'all', 'rules' => []]]],
    'clearConditionalLogic'  => ['clearConditionalLogic', fn (): array => []],
    'save'                   => ['save', fn (): array => []],
] );
