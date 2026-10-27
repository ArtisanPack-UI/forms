<?php

/**
 * Upload choice image API request.
 *
 * Handles validation for uploading an image choice's image via the API.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Http\Requests\Api;

use ArtisanPackUI\Forms\Support\ChoiceImages;
use ArtisanPackUI\Forms\Support\FiresValidationHooks;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload choice image API request class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */
class UploadChoiceImageApiRequest extends FormRequest
{
    use FiresValidationHooks;

    /**
     * Determines if the user is authorized to make this request.
     *
     * @since 1.6.0
     *
     * @return bool True if the user may edit the form.
     */
    public function authorize(): bool
    {
        return $this->user()?->can( 'update', $this->route( 'form' ) ) ?? false;
    }

    /**
     * Gets the validation rules that apply to the request.
     *
     * @since 1.6.0
     *
     * @return array<string, array<int, mixed>> The validation rules.
     */
    public function rules(): array
    {
        return [
            'image' => ChoiceImages::uploadRules(),
        ];
    }
}
