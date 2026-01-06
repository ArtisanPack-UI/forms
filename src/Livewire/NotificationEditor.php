<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Livewire;

use ArtisanPackUI\Forms\Config\ConditionalLogic;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Services\ConditionalLogicService;
use ArtisanPackUI\Forms\Services\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * NotificationEditor Livewire Component
 *
 * Provides an interface for managing form notifications including:
 * - Notification list with add/delete/duplicate actions
 * - Recipient, sender, and content configuration
 * - Placeholder system for dynamic content
 * - Conditional sending logic
 *
 * @since 1.0.0
 */
class NotificationEditor extends Component
{
    /**
     * The form being edited.
     */
    public Form $form;

    /**
     * The currently selected notification ID.
     */
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
        ConditionalLogicService $conditionalLogicService
    ): void {
        $this->notificationService = $notificationService;
        $this->conditionalLogicService = $conditionalLogicService;
    }

    /**
     * Mount the component.
     */
    public function mount(Form $form): void
    {
        $this->form = $form->load(['notifications', 'fields']);

        // Select first notification if exists
        $firstNotification = $this->form->notifications()->orderBy('sort_order')->first();
        if ($firstNotification) {
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
        return $this->form->notifications()->orderBy('sort_order')->get();
    }

    /**
     * Get the currently selected notification.
     */
    #[Computed]
    public function selectedNotification(): ?FormNotification
    {
        if ($this->selectedNotificationId === null) {
            return null;
        }

        return $this->form->notifications()->where('id', $this->selectedNotificationId)->first();
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
                'label' => 'Admin Notification',
                'description' => 'Notify administrators of new submissions',
                'icon' => 'o-bell',
            ],
            FormNotification::TYPE_AUTORESPONDER => [
                'label' => 'Autoresponder',
                'description' => 'Auto-reply to form submitter',
                'icon' => 'o-envelope',
            ],
            FormNotification::TYPE_CUSTOM => [
                'label' => 'Custom Notification',
                'description' => 'Send to any recipients',
                'icon' => 'o-paper-airplane',
            ],
        ];
    }

    /**
     * Get available email fields from the form.
     *
     * @return Collection<int, \ArtisanPackUI\Forms\Models\FormField>
     */
    #[Computed]
    public function emailFields(): Collection
    {
        return $this->form->fields()->where('type', 'email')->orderBy('sort_order')->get();
    }

    /**
     * Get available placeholders for templates.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function availablePlaceholders(): array
    {
        return $this->notificationService->getAvailablePlaceholders($this->form);
    }

    /**
     * Get fields that can be used as condition targets.
     *
     * @return Collection<int, \ArtisanPackUI\Forms\Models\FormField>
     */
    #[Computed]
    public function availableConditionFields(): Collection
    {
        return $this->form->fields()
            ->whereNotIn('type', ['heading', 'paragraph', 'divider', 'spacer', 'html'])
            ->orderBy('sort_order')
            ->get();
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
                'label' => 'Send notification',
                'description' => 'Send when conditions are met',
            ],
            'skip' => [
                'label' => 'Skip notification',
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
    public function addNotification(string $type): void
    {
        $notification = $this->notificationService->create($this->form, $type);

        $this->selectedNotificationId = $notification->id;
        $this->markDirty();

        unset($this->notifications);

        $this->dispatch('notification-added', notificationId: $notification->id);
    }

    /**
     * Select a notification for editing.
     */
    public function selectNotification(int $notificationId): void
    {
        $this->selectedNotificationId = $notificationId;
        unset($this->selectedNotification);
    }

    /**
     * Update the selected notification.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateNotification(array $data): void
    {
        $notification = $this->selectedNotification;

        if ($notification === null) {
            return;
        }

        $this->notificationService->update($notification, $data);
        $this->markDirty();

        unset($this->notifications, $this->selectedNotification);
    }

    /**
     * Toggle the active status of a notification.
     */
    public function toggleActive(int $notificationId): void
    {
        $notification = $this->notificationService->getById($this->form, $notificationId);

        if ($notification) {
            $this->notificationService->toggleActive($notification);
            $this->markDirty();

            unset($this->notifications, $this->selectedNotification);
        }
    }

    /**
     * Duplicate a notification.
     */
    public function duplicateNotification(int $notificationId): void
    {
        $notification = $this->notificationService->getById($this->form, $notificationId);

        if ($notification) {
            $newNotification = $this->notificationService->duplicate($notification);
            $this->selectedNotificationId = $newNotification->id;
            $this->markDirty();

            unset($this->notifications, $this->selectedNotification);

            $this->dispatch('notification-duplicated', notificationId: $newNotification->id);
        }
    }

    /**
     * Delete a notification.
     */
    public function deleteNotification(int $notificationId): void
    {
        $notification = $this->notificationService->getById($this->form, $notificationId);

        if ($notification) {
            $this->notificationService->delete($notification);
            $this->markDirty();

            // Select next available notification
            if ($this->selectedNotificationId === $notificationId) {
                $this->form->unsetRelation('notifications');
                $firstNotification = $this->form->notifications()->orderBy('sort_order')->first();
                $this->selectedNotificationId = $firstNotification?->id;
            }

            unset($this->notifications, $this->selectedNotification);

            $this->dispatch('notification-deleted', notificationId: $notificationId);
        }
    }

    /**
     * Reorder notifications based on drag-and-drop.
     *
     * @param  array<int, int>  $orderedIds
     */
    #[On('notifications-reordered')]
    public function reorderNotifications(array $orderedIds): void
    {
        $this->notificationService->reorder($this->form, $orderedIds);
        $this->markDirty();

        unset($this->notifications);
    }

    // =========================================
    // Conditional Logic
    // =========================================

    /**
     * Update conditional logic for the selected notification.
     *
     * @param  array<string, mixed>  $logic
     */
    public function updateConditionalLogic(array $logic): void
    {
        $notification = $this->selectedNotification;

        if ($notification === null) {
            return;
        }

        // Clean up any references to deleted fields
        $logic = $this->conditionalLogicService->cleanupDeletedFieldReferences(
            $logic,
            $this->form->fields
        );

        $this->notificationService->update($notification, ['conditional_logic' => $logic]);
        $this->markDirty();

        unset($this->notifications, $this->selectedNotification);

        $this->dispatch('conditional-logic-updated');
    }

    /**
     * Clear conditional logic from the selected notification.
     */
    public function clearConditionalLogic(): void
    {
        $notification = $this->selectedNotification;

        if ($notification === null) {
            return;
        }

        $this->notificationService->update($notification, ['conditional_logic' => null]);
        $this->markDirty();

        unset($this->notifications, $this->selectedNotification);

        $this->dispatch('conditional-logic-cleared');
    }

    // =========================================
    // State Management
    // =========================================

    /**
     * Mark as having unsaved changes.
     */
    protected function markDirty(): void
    {
        $this->isDirty = true;
        $this->dispatch('auto-save-trigger');
    }

    /**
     * Save changes (triggered by auto-save).
     */
    public function save(): void
    {
        // All changes are already saved immediately via service methods
        $this->isDirty = false;
        $this->dispatch('notifications-saved');
    }

    // =========================================
    // Render
    // =========================================

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('forms::livewire.notification-editor');
    }
}
