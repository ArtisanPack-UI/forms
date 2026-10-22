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
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

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
     * @param  array<string, string>  $subErrors  A composite field's errors by sub-input key (since 1.6.0; see subInputErrors()).
     *
     * @return string The (possibly filtered) HTML.
     */
    public static function render( FormField $field, mixed $value = null, ?string $error = null, array $subErrors = [] ): string
    {
        $view = $field->fieldType()->view();

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
            'field'     => $field,
            'value'     => $value,
            'error'     => $error,
            'subErrors' => $subErrors,
        ] )->render();

        return applyFilters( 'ap.forms.fieldRender', $html, $field );
    }

    /**
     * Gets the first error for each of a composite field's sub-inputs.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     * @param  MessageBag|ViewErrorBag  $errors  The validation errors.
     * @param  string  $prefix  The key the field's value is nested under.
     *
     * @return array<string, string> The errors by sub-input key; empty for other fields.
     */
    public static function subInputErrors( FormField $field, MessageBag|ViewErrorBag $errors, string $prefix = 'formData' ): array
    {
        $subErrors = [];

        foreach ( array_keys( $field->getEnabledSubInputs() ) as $key ) {
            $error = $errors->first( "{$prefix}.{$field->name}.{$key}" );

            if ( '' !== $error ) {
                $subErrors[ $key ] = $error;
            }
        }

        return $subErrors;
    }
}
