<?php

/**
 * Notification service.
 *
 * Business logic layer for notification CRUD operations and sending.
 * Handles creating, updating, deleting, duplicating notifications,
 * and processing notifications for form submissions.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Services;

use ArtisanPackUI\Forms\Jobs\SendFormNotification;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Models\FormSubmission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Notification service class.
 *
 * Business logic layer for notification CRUD operations and sending.
 * Handles creating, updating, deleting, duplicating notifications,
 * and processing notifications for form submissions.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class NotificationService
{
    /**
     * The conditional logic service instance.
     *
     * @since 1.0.0
     *
     * @var ConditionalLogicService
     */
    protected ConditionalLogicService $conditionalLogicService;

    /**
     * Creates a new notification service instance.
     *
     * @since 1.0.0
     *
     * @param ConditionalLogicService $conditionalLogicService The conditional logic service.
     */
    public function __construct( ConditionalLogicService $conditionalLogicService )
    {
        $this->conditionalLogicService = $conditionalLogicService;
    }

    // =========================================
    // CRUD Operations
    // =========================================

    /**
     * Creates a new notification for a form with defaults based on type.
     *
     * @since 1.0.0
     *
     * @param Form                 $form The form to add the notification to.
     * @param string               $type The notification type.
     * @param array<string, mixed> $data Additional data to override defaults.
     *
     * @return FormNotification The created notification.
     */
    public function create( Form $form, string $type, array $data = [] ): FormNotification
    {
        $defaults     = $this->getDefaultsForType( $type, $form );
        $maxSortOrder = $this->getMaxSortOrder( $form );

        $notificationData = array_merge( $defaults, [
            'form_id'    => $form->id,
            'type'       => $type,
            'sort_order' => $maxSortOrder + 1,
        ], $data );

        return FormNotification::create( $notificationData );
    }

    /**
     * Updates an existing notification.
     *
     * @since 1.0.0
     *
     * @param FormNotification     $notification The notification to update.
     * @param array<string, mixed> $data         The updated data.
     *
     * @return FormNotification The updated notification.
     */
    public function update( FormNotification $notification, array $data ): FormNotification
    {
        $notification->update( $data );

        $refreshed = $notification->fresh();

        if ( null === $refreshed ) {
            throw new RuntimeException( "Failed to refresh notification after update. Notification ID: {$notification->id}" );
        }

        return $refreshed;
    }

    /**
     * Deletes a notification.
     *
     * @since 1.0.0
     *
     * @param FormNotification $notification The notification to delete.
     *
     * @return bool True on success.
     */
    public function delete( FormNotification $notification ): bool
    {
        return (bool) $notification->delete();
    }

    /**
     * Duplicates a notification within the same form.
     *
     * @since 1.0.0
     *
     * @param FormNotification $notification The notification to duplicate.
     *
     * @return FormNotification The duplicated notification.
     */
    public function duplicate( FormNotification $notification ): FormNotification
    {
        return DB::transaction( function () use ( $notification ): FormNotification {
            $maxSortOrder = $this->getMaxSortOrder( $notification->form );

            $newNotification             = $notification->replicate();
            $newNotification->name       = $notification->name . ' (Copy)';
            $newNotification->sort_order = $maxSortOrder + 1;
            $newNotification->save();

            return $newNotification;
        } );
    }

    /**
     * Reorders notifications based on an array of IDs.
     *
     * @since 1.0.0
     *
     * @param Form            $form       The form containing the notifications.
     * @param array<int, int> $orderedIds Array of notification IDs in desired order.
     *
     * @return void
     */
    public function reorder( Form $form, array $orderedIds ): void
    {
        DB::transaction( function () use ( $form, $orderedIds ): void {
            $notifications = $form->notifications()->get()->keyBy( 'id' );

            foreach ( $orderedIds as $index => $id ) {
                if ( isset( $notifications[ $id ] ) ) {
                    $notifications[ $id ]->update( ['sort_order' => $index + 1] );
                }
            }
        } );
    }

    /**
     * Gets a notification by its ID within a form.
     *
     * @since 1.0.0
     *
     * @param Form $form The form to search in.
     * @param int  $id   The notification ID.
     *
     * @return FormNotification|null The notification or null if not found.
     */
    public function getById( Form $form, int $id ): ?FormNotification
    {
        return $form->notifications()->where( 'id', $id )->first();
    }

    /**
     * Gets all notifications for a form.
     *
     * @since 1.0.0
     *
     * @param Form $form The form to get notifications from.
     *
     * @return Collection<int, FormNotification> The notifications collection.
     */
    public function getNotifications( Form $form ): Collection
    {
        return $form->notifications()->orderBy( 'sort_order' )->get();
    }

    /**
     * Toggles the active status of a notification.
     *
     * @since 1.0.0
     *
     * @param FormNotification $notification The notification to toggle.
     *
     * @return FormNotification The updated notification.
     */
    public function toggleActive( FormNotification $notification ): FormNotification
    {
        $notification->update( ['is_active' => ! $notification->is_active] );

        return $notification->fresh() ?? $notification;
    }

    // =========================================
    // Sending Notifications
    // =========================================

    /**
     * Sends all applicable notifications for a form submission.
     *
     * @since 1.0.0
     *
     * @param FormSubmission $submission The form submission.
     *
     * @return int Number of notifications queued.
     */
    public function sendNotifications( FormSubmission $submission ): int
    {
        $submission->loadMissing( ['form', 'values'] );

        $notifications = $submission->form
            ->notifications()
            ->active()
            ->orderBy( 'sort_order' )
            ->get();

        $queuedCount = 0;

        foreach ( $notifications as $notification ) {
            if ( $this->shouldSendNotification( $notification, $submission ) ) {
                $this->queueNotification( $notification, $submission );
                $queuedCount++;
            }
        }

        return $queuedCount;
    }

    /**
     * Determines if a notification should be sent based on its conditions.
     *
     * @since 1.0.0
     *
     * @param FormNotification $notification The notification to check.
     * @param FormSubmission   $submission   The form submission.
     *
     * @return bool True if the notification should be sent.
     */
    public function shouldSendNotification( FormNotification $notification, FormSubmission $submission ): bool
    {
        // If no conditional logic, always send
        if ( ! $notification->has_conditional_logic ) {
            return true;
        }

        // Evaluate conditions using the same logic as field visibility
        $logic = $notification->conditional_logic;

        if ( empty( $logic['rules'] ) ) {
            return true;
        }

        $formData  = $submission->data_array;
        $action    = $logic['action'] ?? 'send'; // Default action for notifications
        $logicType = $logic['logic'] ?? 'all';
        $rules     = $logic['rules'];

        // Evaluate all rules
        $results = [];
        foreach ( $rules as $rule ) {
            $results[] = $this->conditionalLogicService->evaluateRule( $rule, $formData );
        }

        // Combine results based on logic type
        $conditionsMet = 'all' === $logicType
            ? ! in_array( false, $results, true )  // All must be true
            : in_array( true, $results, true );     // At least one must be true

        // Determine if we should send based on action
        // 'send': send when conditions are met
        // 'skip': skip (don't send) when conditions are met
        return 'send' === $action ? $conditionsMet : ! $conditionsMet;
    }

    /**
     * Queues a notification for sending.
     *
     * @since 1.0.0
     *
     * @param FormNotification $notification The notification to queue.
     * @param FormSubmission   $submission   The form submission.
     *
     * @return void
     */
    public function queueNotification( FormNotification $notification, FormSubmission $submission ): void
    {
        $queueName = config( 'artisanpack.forms.notifications.queue', 'default' );

        SendFormNotification::dispatch( $notification, $submission )
            ->onQueue( $queueName );
    }

    // =========================================
    // Placeholder System
    // =========================================

    /**
     * Gets all available placeholders for a form.
     *
     * @since 1.0.0
     *
     * @param Form $form The form to get placeholders for.
     *
     * @return array<string, string> Map of placeholder to description.
     */
    public function getAvailablePlaceholders( Form $form ): array
    {
        $placeholders = [
            '{form_name}'         => __( 'Form name' ),
            '{submission_number}' => __( 'Unique submission ID' ),
            '{submission_date}'   => __( 'Date of submission' ),
            '{submission_time}'   => __( 'Time of submission' ),
            '{all_fields}'        => __( 'All field values as list' ),
            '{page_url}'          => __( 'URL where form was submitted' ),
            '{ip_address}'        => __( "Submitter's IP address" ),
        ];

        // Add field-specific placeholders
        foreach ( $form->fields()->orderBy( 'sort_order' )->get() as $field ) {
            $placeholders[ '{' . $field->name . '}' ] = $field->label;
        }

        return $placeholders;
    }

    /**
     * Parses a template string with submission data.
     *
     * @since 1.0.0
     *
     * @param string         $template   The template string with placeholders.
     * @param FormSubmission $submission The form submission data.
     *
     * @return string The parsed template with placeholders replaced.
     */
    public function parseTemplate( string $template, FormSubmission $submission ): string
    {
        $parsed = $template;

        // Replace field placeholders
        foreach ( $submission->values as $value ) {
            $placeholder = '{' . $value->field_name . '}';
            $parsed      = str_replace( $placeholder, $value->display_value ?? '', $parsed );
        }

        // Replace system placeholders
        $replacements = [
            '{form_name}'         => $submission->form->name,
            '{submission_number}' => $submission->submission_number,
            '{submission_date}'   => $submission->created_at->format( 'F j, Y' ),
            '{submission_time}'   => $submission->created_at->format( 'g:i A' ),
            '{page_url}'          => $submission->page_url ?? '',
            '{ip_address}'        => $submission->ip_address ?? '',
            '{all_fields}'        => $this->formatAllFieldsPlainText( $submission ),
        ];

        foreach ( $replacements as $placeholder => $value ) {
            $parsed = str_replace( $placeholder, $value, $parsed );
        }

        return $parsed;
    }

    /**
     * Formats all fields as a readable plain-text list.
     *
     * Note: This method is intended for plain-text contexts (e.g., text emails,
     * placeholders that will be escaped). Do not use in raw HTML output.
     * For HTML contexts, use formatAllFieldsAsTable() which escapes values.
     *
     * @since 1.0.0
     *
     * @param FormSubmission $submission The form submission.
     *
     * @return string The formatted plain-text field list.
     */
    public function formatAllFieldsPlainText( FormSubmission $submission ): string
    {
        $lines = [];

        foreach ( $submission->values as $value ) {
            $label        = $value->field?->label ?? $value->field_name;
            $displayValue = $value->display_value ?? '';

            $lines[] = "{$label}: {$displayValue}";
        }

        return implode( "\n", $lines );
    }

    /**
     * Formats all fields as an HTML table.
     *
     * @since 1.0.0
     *
     * @param FormSubmission $submission The form submission.
     *
     * @return string The HTML table string.
     */
    public function formatAllFieldsAsTable( FormSubmission $submission ): string
    {
        $rows = [];

        foreach ( $submission->values as $value ) {
            $label        = e( $value->field?->label ?? $value->field_name );
            $displayValue = e( $value->display_value ?? '' );

            $rows[] = "<tr><td style=\"padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: 500;\">{$label}</td><td style=\"padding: 8px; border-bottom: 1px solid #e5e7eb;\">{$displayValue}</td></tr>";
        }

        if ( empty( $rows ) ) {
            return '<p>' . e( __( 'No data submitted.' ) ) . '</p>';
        }

        return '<table style="width: 100%; border-collapse: collapse; margin: 16px 0;">' . implode( '', $rows ) . '</table>';
    }

    /**
     * Gets CC email addresses as array.
     *
     * @since 1.0.0
     *
     * @param FormNotification $notification The notification.
     *
     * @return array<int, string> The CC email addresses.
     */
    public function getCcEmails( FormNotification $notification ): array
    {
        if ( empty( $notification->cc_emails ) ) {
            return [];
        }

        return array_filter(
            array_map( 'trim', explode( ',', $notification->cc_emails ) ),
        );
    }

    /**
     * Gets BCC email addresses as array.
     *
     * @since 1.0.0
     *
     * @param FormNotification $notification The notification.
     *
     * @return array<int, string> The BCC email addresses.
     */
    public function getBccEmails( FormNotification $notification ): array
    {
        if ( empty( $notification->bcc_emails ) ) {
            return [];
        }

        return array_filter(
            array_map( 'trim', explode( ',', $notification->bcc_emails ) ),
        );
    }

    // =========================================
    // Helpers
    // =========================================

    /**
     * Gets default values for a notification type.
     *
     * @since 1.0.0
     *
     * @param string $type The notification type.
     * @param Form   $form The form.
     *
     * @return array<string, mixed> The default values.
     */
    protected function getDefaultsForType( string $type, Form $form ): array
    {
        $defaults = [
            'is_active'               => true,
            'include_submission_data' => true,
        ];

        switch ( $type ) {
            case FormNotification::TYPE_ADMIN:
                return array_merge( $defaults, [
                    'name'       => __( 'Admin Notification' ),
                    'to_email'   => config( 'mail.from.address', '' ),
                    'subject'    => __( 'New Submission: {form_name}' ),
                    'message'    => __( "A new form submission has been received.\n\n{all_fields}" ),
                    'from_name'  => config( 'mail.from.name', config( 'app.name' ) ),
                    'from_email' => config( 'mail.from.address', '' ),
                ] );

            case FormNotification::TYPE_AUTORESPONDER:
                // Find first email field in form
                $emailField = $form->fields()->where( 'type', 'email' )->first();

                return array_merge( $defaults, [
                    'name'       => __( 'Autoresponder' ),
                    'to_field'   => $emailField?->name,
                    'subject'    => __( 'Thank you for contacting us' ),
                    'message'    => __( "Thank you for your submission to {form_name}.\n\nWe have received your information and will be in touch soon.\n\nSubmission Reference: {submission_number}" ),
                    'from_name'  => config( 'mail.from.name', config( 'app.name' ) ),
                    'from_email' => config( 'mail.from.address', '' ),
                ] );

            case FormNotification::TYPE_CUSTOM:
            default:
                return array_merge( $defaults, [
                    'name'       => __( 'Custom Notification' ),
                    'subject'    => __( 'Form Submission: {form_name}' ),
                    'message'    => '{all_fields}',
                    'from_name'  => config( 'mail.from.name', config( 'app.name' ) ),
                    'from_email' => config( 'mail.from.address', '' ),
                ] );
        }
    }

    /**
     * Gets the maximum sort order for notifications in a form.
     *
     * @since 1.0.0
     *
     * @param Form $form The form to check.
     *
     * @return int The maximum sort order value.
     */
    protected function getMaxSortOrder( Form $form ): int
    {
        return (int) $form->notifications()->max( 'sort_order');
    }
}
