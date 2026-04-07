<?php

/**
 * Notification API controller.
 *
 * Handles REST API requests for notification CRUD operations.
 * Delegates business logic to NotificationService.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Http\Controllers\Api;

use ArtisanPackUI\Forms\Http\Requests\Api\StoreNotificationApiRequest;
use ArtisanPackUI\Forms\Http\Requests\Api\UpdateNotificationApiRequest;
use ArtisanPackUI\Forms\Http\Resources\FormNotificationResource;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Services\NotificationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

/**
 * Notification API controller class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class NotificationApiController extends Controller
{
    use AuthorizesRequests;

    /**
     * Creates a new controller instance.
     *
     * @since 1.1.0
     *
     * @param NotificationService $notificationService The notification service instance.
     */
    public function __construct(
        protected NotificationService $notificationService,
    ) {
    }

    /**
     * Lists all notifications for a form.
     *
     * @since 1.1.0
     *
     * @param Form $form The form to get notifications from.
     *
     * @return AnonymousResourceCollection The notifications collection.
     */
    public function index( Form $form ): AnonymousResourceCollection
    {
        $this->authorize( 'manageNotifications', $form );

        $notifications = $this->notificationService->getNotifications( $form );

        return FormNotificationResource::collection( $notifications );
    }

    /**
     * Creates a new notification.
     *
     * @since 1.1.0
     *
     * @param StoreNotificationApiRequest $request The validated request.
     * @param Form                        $form    The form to add the notification to.
     *
     * @return FormNotificationResource The created notification resource.
     */
    public function store( StoreNotificationApiRequest $request, Form $form ): FormNotificationResource
    {
        $validated = $request->validated();
        $type      = $validated['type'];

        unset( $validated['type'] );

        $notification = $this->notificationService->create( $form, $type, $validated );

        return new FormNotificationResource( $notification );
    }

    /**
     * Updates a notification.
     *
     * @since 1.1.0
     *
     * @param UpdateNotificationApiRequest $request      The validated request.
     * @param Form                         $form         The parent form.
     * @param FormNotification             $notification The notification to update.
     *
     * @return FormNotificationResource The updated notification resource.
     */
    public function update( UpdateNotificationApiRequest $request, Form $form, FormNotification $notification ): FormNotificationResource
    {
        if ( $notification->form_id !== $form->id ) {
            abort( 404 );
        }

        $notification = $this->notificationService->update( $notification, $request->validated() );

        return new FormNotificationResource( $notification );
    }

    /**
     * Deletes a notification.
     *
     * @since 1.1.0
     *
     * @param Form             $form         The parent form.
     * @param FormNotification $notification The notification to delete.
     *
     * @return JsonResponse Empty response on success.
     */
    public function destroy( Form $form, FormNotification $notification ): JsonResponse
    {
        $this->authorize( 'manageNotifications', $form );

        if ( $notification->form_id !== $form->id ) {
            abort( 404 );
        }

        $this->notificationService->delete( $notification );

        return response()->json( null, 204 );
    }
}
