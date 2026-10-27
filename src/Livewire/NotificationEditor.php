<?php

/**
 * Notification editor Livewire component.
 *
 * Provides an interface for managing form notifications including:
 * - Notification list with add/delete/duplicate actions
 * - Recipient, sender, and content configuration
 * - Placeholder system for dynamic content
 * - Conditional sending logic
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Livewire;

use ArtisanPackUI\Forms\Config\ConditionalLogic;
use ArtisanPackUI\Forms\Http\Requests\Api\UpdateNotificationApiRequest;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Services\ConditionalLogicService;
use ArtisanPackUI\Forms\Services\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Notification editor Livewire component class.
 *
 * Provides an interface for managing form notifications including:
 * - Notification list with add/delete/duplicate actions
 * - Recipient, sender, and content configuration
 * - Placeholder system for dynamic content
 * - Conditional sending logic
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class NotificationEditor extends Component
{
    /**
     * The attributes updateNotification() may change.
     *
     * The notification's form, sort order and conditional logic are changed
     * through their own actions (or never), so a client cannot move a
     * notification to another form by sending `form_id`.
     *
     * @since 1.6.0
     *
     * @var array<int, string>
     */
    public const EDITABLE_ATTRIBUTES = [
        'name',
        'type',
        'to_email',
        'to_field',
        'cc_emails',
        'bcc_emails',
        'reply_to_email',
        'reply_to_field',
        'from_name',
        'from_email',
        'subject',
        'message',
        'include_submission_data',
        'is_active',
    ];

    /**
     * The form being edited.
     */
    public Form $form;

    /**
     * The currently selected notification ID.
     *
     * Locked since 1.6.0: it changes only through selectNotification(),
     * which checks that the notification belongs to this form.
     */
    #[Locked]
    public ?int $selectedNotificationId = null;

    /**
     * Whether there are unsaved changes.
     */
    public bool $isDirty = false;

    /**
     * The notification service instance.
     */
    protected NotificationService $notificationService;

    /**
     * The conditional logic service instance.
     */
    protected ConditionalLogicService $conditionalLogicService;

    /**
     * Boot the component with service injection.
     */
    public function boot(
        NotificationService $notificationService,
        ConditionalLogicService $conditionalLogicService,
    ): void {
        $this->notificationService     = $notificationService;
        $this->conditionalLogicService = $conditionalLogicService;
    }

    /**
     * Mount the component.
     */
    public function mount( Form $form ): void
    {
        $this->authorize( 'manageNotifications', $form );

        $this->form = $form->load( ['notifications', 'fields'] );

        // Select first notification if exists
        $firstNotification = $this->form->notifications()->orderBy( 'sort_order' )->first();
        if ( $firstNotification ) {
            $this->selectedNotificationId = $firstNotification->id;
        }
    }

    // =========================================
    // Computed Properties
    // =========================================

    /**
     * Get all notifications for the form.
     *
     * @return Collection<int, FormNotification>
     */
    #[Computed]
    public function notifications(): Collection
    {
        return $this->form->notifications()->orderBy( 'sort_order' )->get();
    }

    /**
     * Get the currently selected notification.
     */
    #[Computed]
    public function selectedNotification(): ?FormNotification
    {
        if ( null === $this->selectedNotificationId ) {
            return null;
        }

        return $this->form->notifications()->where( 'id', $this->selectedNotificationId )->first();
    }

    /**
     * Get notification type options.
     *
     * @return array<string, array{label: string, description: string, icon: string}>
     */
    #[Computed]
    public function notificationTypes(): array
    {
        return [
            FormNotification::TYPE_ADMIN => [
                'label'       => 'Admin Notification',
                'description' => 'Notify administrators of new submissions',
                'icon'        => 'o-bell',
            ],
            FormNotification::TYPE_AUTORESPONDER => [
                'label'       => 'Autoresponder',
                'description' => 'Auto-reply to form submitter',
                'icon'        => 'o-envelope',
            ],
            FormNotification::TYPE_CUSTOM => [
                'label'       => 'Custom Notification',
                'description' => 'Send to any recipients',
                'icon'        => 'o-paper-airplane',
            ],
        ];
    }

    /**
     * Get available email fields from the form.
     *
     * @return Collection<int, FormField>
     */
    #[Computed]
    public function emailFields(): Collection
    {
        return $this->form->fields()->where( 'type', 'email' )->orderBy( 'sort_order' )->get();
    }

    /**
     * Get available placeholders for templates.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function availablePlaceholders(): array
    {
        return $this->notificationService->getAvailablePlaceholders( $this->form );
    }

    /**
     * Get fields that can be used as condition targets.
     *
     * @return Collection<int, FormField>
     */
    #[Computed]
    public function availableConditionFields(): Collection
    {
        return $this->form->fields()
            ->orderBy( 'sort_order' )
            ->get()
            ->reject( fn ( FormField $field ): bool => $field->isLayoutField() )
            ->values();
    }

    /**
     * Get conditional logic action options for notifications.
     *
     * @return array<string, array{label: string, description: string}>
     */
    #[Computed]
    public function conditionActions(): array
    {
        return [
            'send' => [
                'label'       => 'Send notification',
                'description' => 'Send when conditions are met',
            ],
            'skip' => [
                'label'       => 'Skip notification',
                'description' => 'Skip when conditions are met',
            ],
        ];
    }

    /**
     * Get conditional logic types.
     *
     * @return array<string, array{label: string, description: string}>
     */
    #[Computed]
    public function conditionLogicTypes(): array
    {
        return ConditionalLogic::getLogicTypes();
    }

    /**
     * Get conditional logic operators.
     *
     * @return array<string, array<string, mixed>>
     */
    #[Computed]
    public function conditionOperators(): array
    {
        return ConditionalLogic::getOperators();
    }

    // =========================================
    // Notification Operations
    // =========================================

    /**
     * Add a new notification.
     */
    public function addNotification( string $type ): void
    {
        $this->authorizeUpdate();

        Validator::make(
            ['type' => $type],
            ['type' => ['required', 'string', Rule::in( array_keys( $this->notificationTypes() ) )]],
        )->validate();

        $notification = $this->notificationService->create( $this->form, $type );

        $this->selectedNotificationId = $notification->id;
        $this->markDirty();

        unset( $this->notifications );

        $this->dispatch( 'notification-added', notificationId: $notification->id );
    }

    /**
     * Select a notification for editing.
     */
    public function selectNotification( int $notificationId ): void
    {
        $this->authorizeUpdate();

        $notification = $this->notificationService->getById( $this->form, $notificationId );

        if ( null === $notification ) {
            return;
        }

        $this->selectedNotificationId = $notification->id;
        unset( $this->selectedNotification );
    }

    /**
     * Update the selected notification.
     *
     * Since 1.6.0, only the attributes in EDITABLE_ATTRIBUTES are changed,
     * and they are validated with the same rules as the notification API.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateNotification( array $data ): void
    {
        $this->authorizeUpdate();

        $notification = $this->selectedNotification;

        if ( null === $notification ) {
            return;
        }

        $data = array_intersect_key( $data, array_flip( self::EDITABLE_ATTRIBUTES ) );

        if ( [] === $data ) {
            return;
        }

        $validated = Validator::make( $data, $this->notificationRules( array_keys( $data ) ) )->validate();

        $this->notificationService->update( $notification, $validated );
        $this->markDirty();

        unset( $this->notifications, $this->selectedNotification );
    }

    /**
     * Toggle the active status of a notification.
     */
    public function toggleActive( int $notificationId ): void
    {
        $this->authorizeUpdate();

        $notification = $this->notificationService->getById( $this->form, $notificationId );

        if ( $notification ) {
            $this->notificationService->toggleActive( $notification );
            $this->markDirty();

            unset( $this->notifications, $this->selectedNotification );
        }
    }

    /**
     * Duplicate a notification.
     */
    public function duplicateNotification( int $notificationId ): void
    {
        $this->authorizeUpdate();

        $notification = $this->notificationService->getById( $this->form, $notificationId );

        if ( $notification ) {
            $newNotification              = $this->notificationService->duplicate( $notification );
            $this->selectedNotificationId = $newNotification->id;
            $this->markDirty();

            unset( $this->notifications, $this->selectedNotification );

            $this->dispatch( 'notification-duplicated', notificationId: $newNotification->id );
        }
    }

    /**
     * Delete a notification.
     */
    public function deleteNotification( int $notificationId ): void
    {
        $this->authorizeUpdate();

        $notification = $this->notificationService->getById( $this->form, $notificationId );

        if ( $notification ) {
            $this->notificationService->delete( $notification );
            $this->markDirty();

            // Select next available notification
            if ( $this->selectedNotificationId === $notificationId ) {
                $this->form->unsetRelation( 'notifications' );
                $firstNotification            = $this->form->notifications()->orderBy( 'sort_order' )->first();
                $this->selectedNotificationId = $firstNotification?->id;
            }

            unset( $this->notifications, $this->selectedNotification );

            $this->dispatch( 'notification-deleted', notificationId: $notificationId );
        }
    }

    /**
     * Reorder notifications based on drag-and-drop.
     *
     * @param  array<int, int>  $orderedIds
     */
    #[On( 'notifications-reordered' )]
    public function reorderNotifications( array $orderedIds ): void
    {
        $this->authorizeUpdate();

        $this->notificationService->reorder( $this->form, $orderedIds );
        $this->markDirty();

        unset( $this->notifications );
    }

    // =========================================
    // Conditional Logic
    // =========================================

    /**
     * Update conditional logic for the selected notification.
     *
     * @param  array<string, mixed>  $logic
     */
    public function updateConditionalLogic( array $logic ): void
    {
        $this->authorizeUpdate();

        $notification = $this->selectedNotification;

        if ( null === $notification ) {
            return;
        }

        // Clean up any references to deleted fields
        $logic = $this->conditionalLogicService->cleanupDeletedFieldReferences(
            $logic,
            $this->form->fields,
        );

        $this->notificationService->update( $notification, ['conditional_logic' => $logic] );
        $this->markDirty();

        unset( $this->notifications, $this->selectedNotification );

        $this->dispatch( 'conditional-logic-updated' );
    }

    /**
     * Clear conditional logic from the selected notification.
     */
    public function clearConditionalLogic(): void
    {
        $this->authorizeUpdate();

        $notification = $this->selectedNotification;

        if ( null === $notification ) {
            return;
        }

        $this->notificationService->update( $notification, ['conditional_logic' => null] );
        $this->markDirty();

        unset( $this->notifications, $this->selectedNotification );

        $this->dispatch( 'conditional-logic-cleared' );
    }

    /**
     * Save changes (triggered by auto-save).
     */
    public function save(): void
    {
        $this->authorizeUpdate();

        // All changes are already saved immediately via service methods
        $this->isDirty = false;
        $this->dispatch( 'notifications-saved' );
    }

    // =========================================
    // Render
    // =========================================

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view( 'forms::livewire.notification-editor' );
    }

    // =========================================
    // State Management
    // =========================================

    /**
     * Check that the current user may still manage this form's notifications.
     *
     * @since 1.6.0
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException When they may not.
     */
    protected function authorizeUpdate(): void
    {
        $this->authorize( 'manageNotifications', $this->form );
    }

    /**
     * Get the validation rules for the given notification attributes.
     *
     * Reuses the notification API's rules, and limits the recipient and
     * reply-to field references to this form's email fields.
     *
     * @since 1.6.0
     *
     * @param  array<int, string>  $attributes  The attributes being changed.
     *
     * @return array<string, mixed> The rules keyed by attribute.
     */
    protected function notificationRules( array $attributes ): array
    {
        $rules       = ( new UpdateNotificationApiRequest() )->rules();
        $emailFields = $this->form->fields()->where( 'type', 'email' )->pluck( 'name' )->all();

        $rules['to_field'][]       = Rule::in( $emailFields );
        $rules['reply_to_field'][] = Rule::in( $emailFields );

        return array_intersect_key( $rules, array_flip( $attributes ) );
    }

    /**
     * Mark as having unsaved changes.
     */
    protected function markDirty(): void
    {
        $this->isDirty = true;
        $this->dispatch( 'auto-save-trigger');
    }
}
