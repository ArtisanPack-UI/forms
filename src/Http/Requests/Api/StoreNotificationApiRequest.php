<?php

/**
 * Store notification API request.
 *
 * Handles validation for creating a new notification via the API.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Http\Requests\Api;

use ArtisanPackUI\Forms\Models\FormNotification;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Store notification API request class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class StoreNotificationApiRequest extends FormRequest
{
    /**
     * Determines if the user is authorized to make this request.
     *
     * @since 1.1.0
     *
     * @return bool True if authorized.
     */
    public function authorize(): bool
    {
        return $this->user()?->can( 'manageNotifications', $this->route( 'form' ) ) ?? false;
    }

    /**
     * Gets the validation rules that apply to the request.
     *
     * @since 1.1.0
     *
     * @return array<string, array<int, Closure|string>> The validation rules.
     */
    public function rules(): array
    {
        $types = implode( ',', [
            FormNotification::TYPE_ADMIN,
            FormNotification::TYPE_AUTORESPONDER,
            FormNotification::TYPE_CUSTOM,
        ] );

        return [
            'type'                       => ['required', 'string', "in:{$types}"],
            'name'                       => ['required', 'string', 'max:255'],
            'to_email'                   => ['nullable', 'email', 'max:255'],
            'to_field'                   => ['nullable', 'string', 'max:255'],
            'cc_emails'                  => ['bail', 'nullable', 'string', 'max:1000', $this->commaSeparatedEmailRule()],
            'bcc_emails'                 => ['bail', 'nullable', 'string', 'max:1000', $this->commaSeparatedEmailRule()],
            'reply_to_email'             => ['nullable', 'email', 'max:255'],
            'reply_to_field'             => ['nullable', 'string', 'max:255'],
            'from_name'                  => ['nullable', 'string', 'max:255'],
            'from_email'                 => ['nullable', 'email', 'max:255'],
            'subject'                    => ['nullable', 'string', 'max:500'],
            'message'                    => ['nullable', 'string'],
            'conditional_logic'          => ['nullable', 'array'],
            'conditional_logic.action'   => ['required_with:conditional_logic', 'string', 'in:send,skip'],
            'conditional_logic.logic'    => ['required_with:conditional_logic', 'string', 'in:all,any'],
            'conditional_logic.rules'    => ['required_with:conditional_logic', 'array'],
            'conditional_logic.rules.*'  => ['required', 'array'],
            'include_submission_data'    => ['nullable', 'boolean'],
            'is_active'                  => ['nullable', 'boolean'],
        ];
    }

    /**
     * Returns a closure that validates a comma-separated list of email addresses.
     *
     * @since 1.1.0
     *
     * @return Closure The validation closure.
     */
    private function commaSeparatedEmailRule(): Closure
    {
        return function ( string $attribute, mixed $value, Closure $fail ): void {
            if ( null === $value || '' === $value || ! is_string( $value ) ) {
                return;
            }

            foreach ( explode( ',', $value ) as $email ) {
                $email = trim( $email );

                if ( '' !== $email && ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
                    $fail( __( 'The :attribute contains an invalid email address: :email', ['attribute' => $attribute, 'email' => $email] ) );
                }
            }
        };
    }
}
