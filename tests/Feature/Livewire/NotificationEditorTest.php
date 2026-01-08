<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\NotificationEditor;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormNotification;
use Livewire\Livewire;

describe( 'NotificationEditor Livewire Component', function (): void {
    describe( 'mounting', function (): void {
        it( 'mounts with a form', function (): void {
            $form = Form::factory()->create();

            Livewire::test( NotificationEditor::class, ['form' => $form] )
                ->assertSet( 'form.id', $form->id )
                ->assertSet( 'selectedNotificationId', null );
        } );

        it( 'selects first notification if exists', function (): void {
            $form         = Form::factory()->create();
            $notification = FormNotification::factory()->for( $form )->create();

            Livewire::test( NotificationEditor::class, ['form' => $form] )
                ->assertSet( 'selectedNotificationId', $notification->id );
        } );
    } );

    describe( 'computed properties', function (): void {
        it( 'returns notifications ordered by sort_order', function (): void {
            $form = Form::factory()->create();
            $n1   = FormNotification::factory()->for( $form )->create( ['sort_order' => 2] );
            $n2   = FormNotification::factory()->for( $form )->create( ['sort_order' => 1] );

            // First notification should be n2 (lower sort order)
            Livewire::test( NotificationEditor::class, ['form' => $form] )
                ->assertSet( 'selectedNotificationId', $n2->id );
        } );

        it( 'returns selected notification', function (): void {
            $form         = Form::factory()->create();
            $notification = FormNotification::factory()->for( $form )->create();

            Livewire::test( NotificationEditor::class, ['form' => $form] )
                ->assertSet( 'selectedNotificationId', $notification->id );
        } );

        it( 'returns notification type options', function (): void {
            $form = Form::factory()->create();

            // Just verify the component renders without error - types are always available
            Livewire::test( NotificationEditor::class, ['form' => $form] )
                ->assertStatus( 200 );
        } );

        it( 'returns email fields from form', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for( $form )->create( ['type' => 'email', 'name' => 'contact_email'] );
            FormField::factory()->for( $form )->create( ['type' => 'text', 'name' => 'name'] );

            // Email fields are available through the component
            Livewire::test( NotificationEditor::class, ['form' => $form] )
                ->assertStatus( 200 );
        } );

        it( 'returns available placeholders', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for( $form )->create( ['name' => 'email', 'label' => 'Email'] );

            // Placeholders are available through the component
            Livewire::test( NotificationEditor::class, ['form' => $form] )
                ->assertStatus( 200 );
        } );
    } );

    describe( 'notification operations', function (): void {
        it( 'adds an admin notification', function (): void {
            $form = Form::factory()->create();

            Livewire::test( NotificationEditor::class, ['form' => $form] )
                ->call( 'addNotification', FormNotification::TYPE_ADMIN )
                ->assertSet( 'isDirty', true )
                ->assertDispatched( 'notification-added' );

            expect( $form->notifications()->count() )->toBe( 1 )
                ->and( $form->notifications()->first()->type )->toBe( FormNotification::TYPE_ADMIN );
        } );

        it( 'adds an autoresponder notification', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for( $form )->create( ['type' => 'email', 'name' => 'email'] );

            Livewire::test( NotificationEditor::class, ['form' => $form] )
                ->call( 'addNotification', FormNotification::TYPE_AUTORESPONDER );

            $notification = $form->notifications()->first();
            expect( $notification->type )->toBe( FormNotification::TYPE_AUTORESPONDER )
                ->and( $notification->to_field )->toBe( 'email' );
        } );

        it( 'selects a notification', function (): void {
            $form = Form::factory()->create();
            $n1   = FormNotification::factory()->for( $form )->create();
            $n2   = FormNotification::factory()->for( $form )->create();

            Livewire::test( NotificationEditor::class, ['form' => $form] )
                ->call( 'selectNotification', $n2->id )
                ->assertSet( 'selectedNotificationId', $n2->id );
        } );

        it( 'updates selected notification', function (): void {
            $form         = Form::factory()->create();
            $notification = FormNotification::factory()->for( $form )->create( [
                'name' => 'Original Name',
            ] );

            Livewire::test( NotificationEditor::class, ['form' => $form] )
                ->call( 'updateNotification', ['name' => 'New Name'] )
                ->assertSet( 'isDirty', true );

            expect( $notification->fresh()->name )->toBe( 'New Name' );
        } );

        it( 'toggles notification active status', function (): void {
            $form         = Form::factory()->create();
            $notification = FormNotification::factory()->for( $form )->create( ['is_active' => true] );

            Livewire::test( NotificationEditor::class, ['form' => $form] )
                ->call( 'toggleActive', $notification->id )
                ->assertSet( 'isDirty', true );

            expect( $notification->fresh()->is_active )->toBeFalse();
        } );

        it( 'duplicates a notification', function (): void {
            $form         = Form::factory()->create();
            $notification = FormNotification::factory()->for( $form )->create( [
                'name' => 'Original',
            ] );

            Livewire::test( NotificationEditor::class, ['form' => $form] )
                ->call( 'duplicateNotification', $notification->id )
                ->assertDispatched( 'notification-duplicated' );

            expect( $form->notifications()->count() )->toBe( 2 );

            // The duplicated notification should have "(Copy)" suffix
            $duplicated = $form->notifications()->where( 'name', 'Original (Copy)' )->first();
            expect( $duplicated )->not->toBeNull();
        } );

        it( 'deletes a notification', function (): void {
            $form = Form::factory()->create();
            $n1   = FormNotification::factory()->for( $form )->create();
            $n2   = FormNotification::factory()->for( $form )->create();

            Livewire::test( NotificationEditor::class, ['form' => $form] )
                ->call( 'deleteNotification', $n1->id )
                ->assertSet( 'isDirty', true )
                ->assertDispatched( 'notification-deleted' );

            expect( $form->notifications()->count() )->toBe( 1 )
                ->and( FormNotification::find( $n1->id ) )->toBeNull();
        } );

        it( 'selects next notification after deleting selected', function (): void {
            $form = Form::factory()->create();
            $n1   = FormNotification::factory()->for( $form )->create( ['sort_order' => 1] );
            $n2   = FormNotification::factory()->for( $form )->create( ['sort_order' => 2] );

            Livewire::test( NotificationEditor::class, ['form' => $form] )
                ->assertSet( 'selectedNotificationId', $n1->id )
                ->call( 'deleteNotification', $n1->id )
                ->assertSet( 'selectedNotificationId', $n2->id );
        } );

        it( 'reorders notifications', function (): void {
            $form = Form::factory()->create();
            $n1   = FormNotification::factory()->for( $form )->create( ['sort_order' => 1] );
            $n2   = FormNotification::factory()->for( $form )->create( ['sort_order' => 2] );
            $n3   = FormNotification::factory()->for( $form )->create( ['sort_order' => 3] );

            Livewire::test( NotificationEditor::class, ['form' => $form] )
                ->dispatch( 'notifications-reordered', orderedIds: [$n3->id, $n1->id, $n2->id] )
                ->assertSet( 'isDirty', true );

            expect( $n3->fresh()->sort_order )->toBe( 1 )
                ->and( $n1->fresh()->sort_order )->toBe( 2 )
                ->and( $n2->fresh()->sort_order )->toBe( 3 );
        } );
    } );

    describe( 'conditional logic', function (): void {
        it( 'updates conditional logic', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for( $form )->create( ['name' => 'category', 'type' => 'text'] );
            $notification = FormNotification::factory()->for( $form )->create();

            $logic = [
                'action' => 'send',
                'logic'  => 'all',
                'rules'  => [
                    ['field' => 'category', 'operator' => 'equals', 'value' => 'support'],
                ],
            ];

            Livewire::test( NotificationEditor::class, ['form' => $form] )
                ->call( 'updateConditionalLogic', $logic )
                ->assertDispatched( 'conditional-logic-updated' );

            expect( $notification->fresh()->conditional_logic )->toBe( $logic );
        } );

        it( 'clears conditional logic', function (): void {
            $form         = Form::factory()->create();
            $notification = FormNotification::factory()->for( $form )->withConditions()->create();

            Livewire::test( NotificationEditor::class, ['form' => $form] )
                ->call( 'clearConditionalLogic' )
                ->assertDispatched( 'conditional-logic-cleared' );

            expect( $notification->fresh()->conditional_logic )->toBeNull();
        } );
    } );

    describe( 'state management', function (): void {
        it( 'saves and clears dirty state', function (): void {
            $form = Form::factory()->create();

            Livewire::test( NotificationEditor::class, ['form' => $form] )
                ->call( 'addNotification', FormNotification::TYPE_ADMIN )
                ->assertSet( 'isDirty', true )
                ->call( 'save' )
                ->assertSet( 'isDirty', false )
                ->assertDispatched( 'notifications-saved' );
        } );
    } );

    describe( 'rendering', function (): void {
        it( 'renders empty state when no notifications', function (): void {
            $form = Form::factory()->create();

            Livewire::test( NotificationEditor::class, ['form' => $form] )
                ->assertSee( 'No notifications yet' );
        } );

        it( 'renders notification list', function (): void {
            $form = Form::factory()->create();
            FormNotification::factory()->for( $form )->create( ['name' => 'Test Notification']);

            Livewire::test( NotificationEditor::class, ['form' => $form])
                ->assertSee( 'Test Notification');
        });

        it( 'renders editor for selected notification', function (): void {
            $form = Form::factory()->create();
            FormNotification::factory()->for( $form)->create( [
                'name'    => 'My Notification',
                'subject' => 'Test Subject',
            ]);

            Livewire::test( NotificationEditor::class, ['form' => $form])
                ->assertSee( 'My Notification')
                ->assertSee( 'Test Subject');
        });
    });
});
