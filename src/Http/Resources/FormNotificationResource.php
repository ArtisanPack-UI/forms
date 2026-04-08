<?php

/**
 * Form notification API resource.
 *
 * Transforms FormNotification model instances into JSON API responses.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Form notification API resource class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class FormNotificationResource extends JsonResource
{
    /**
     * Transforms the resource into an array.
     *
     * @since 1.1.0
     *
     * @param Request $request The incoming request.
     *
     * @return array<string, mixed> The transformed resource data.
     */
    public function toArray( Request $request ): array
    {
        return [
            'id'                      => $this->id,
            'form_id'                 => $this->form_id,
            'type'                    => $this->type,
            'name'                    => $this->name,
            'to_email'                => $this->to_email,
            'to_field'                => $this->to_field,
            'cc_emails'               => $this->cc_emails,
            'bcc_emails'              => $this->bcc_emails,
            'reply_to_email'          => $this->reply_to_email,
            'reply_to_field'          => $this->reply_to_field,
            'from_name'               => $this->from_name,
            'from_email'              => $this->from_email,
            'subject'                 => $this->subject,
            'message'                 => $this->message,
            'conditional_logic'       => $this->conditional_logic,
            'include_submission_data' => $this->include_submission_data,
            'is_active'               => $this->is_active,
            'sort_order'              => $this->sort_order,
            'created_at'              => $this->created_at?->toIso8601String(),
            'updated_at'              => $this->updated_at?->toIso8601String(),
        ];
    }
}
