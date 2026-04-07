<?php

/**
 * Bulk submission API request.
 *
 * Handles validation for bulk submission actions via the API.
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

use ArtisanPackUI\Forms\Models\FormSubmission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk submission API request class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class BulkSubmissionApiRequest extends FormRequest
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
        return $this->user()?->can( 'bulkAction', FormSubmission::class ) ?? false;
    }

    /**
     * Gets the validation rules that apply to the request.
     *
     * @since 1.1.0
     *
     * @return array<string, array<int, string>> The validation rules.
     */
    public function rules(): array
    {
        return [
            'action'   => ['required', 'string', 'in:delete,mark_read,mark_unread,mark_spam,mark_not_spam'],
            'ids'      => ['required', 'array', 'min:1'],
            'ids.*'    => ['required', 'integer'],
        ];
    }
}
