<?php

/**
 * Field renderer that applies the ap.forms.fieldRender filter.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

use ArtisanPackUI\Forms\Models\FormField;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

/**
 * Renders a form field and pipes the resulting HTML through the
 * `ap.forms.fieldRender` filter so integration packages can wrap or mutate
 * the markup for a specific field.
 *
 * @since 1.3.0
 */
class FieldRenderer
{
    /**
     * Renders a field's blade partial and applies the fieldRender filter.
     *
     * @since 1.3.0
     *
     * @param  FormField  $field  The field being rendered.
     * @param  mixed  $value  The current field value.
     * @param  string|null  $error  The current validation error, if any.
     *
     * @return string The (possibly filtered) HTML.
     */
    public static function render( FormField $field, mixed $value = null, ?string $error = null ): string
    {
        $view = 'forms::components.fields.' . $field->type;

        if ( ! View::exists( $view ) ) {
            // A custom field type registered via `ap.forms.fieldTypes` may not ship a blade
            // partial; swallow the render so one bad type doesn't crash the whole form,
            // and give integration packages a shot at supplying the HTML via the filter.
            Log::warning( 'ArtisanPackUI Forms: missing field partial', [
                'type'     => $field->type,
                'view'     => $view,
                'field_id' => $field->id,
            ] );

            return applyFilters( 'ap.forms.fieldRender', '', $field );
        }

        $html = View::make( $view, [
            'field' => $field,
            'value' => $value,
            'error' => $error,
        ] )->render();

        return applyFilters( 'ap.forms.fieldRender', $html, $field );
    }
}
