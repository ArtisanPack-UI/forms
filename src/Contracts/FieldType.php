<?php

/**
 * Field type handler contract.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Contracts;

use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;

/**
 * Describes everything that is specific to one field type.
 *
 * Validation, storage, display, export, merge tags and rendering all ask the
 * field's handler rather than switching on the type, so a new type touches one
 * class. Register a handler through the `ap.forms.fieldTypes` filter, keyed by
 * the type name, as a class name or an instance; the array definitions accepted
 * before 1.6.0 still work. Extend AbstractFieldType rather than implementing
 * this interface from scratch.
 *
 * @since 1.6.0
 */
interface FieldType
{
    /**
     * Gets the type definition.
     *
     * Holds the metadata the builders read (`label`, `icon`, `category`,
     * `has_options`, `supports_placeholder`, `supports_default_value`,
     * `is_layout`, `validation_options`, `defaults`), the `sub_inputs` of a
     * composite type and the `settings` schema the builders render the type's
     * own settings from (see FieldSettings).
     *
     * @since 1.6.0
     *
     * @return array<string, mixed> The definition.
     */
    public function definition(): array;

    /**
     * Gets the validation rules for a field of this type.
     *
     * Keyed by the input the rules apply to: `''` for the field's own value,
     * or a dotted sub-key (such as `street`) for an input nested under it.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return array<string, list<mixed>> The rules by input key.
     */
    public function rules( FormField $field ): array;

    /**
     * Gets custom validation messages for a field of this type.
     *
     * Keyed by `{rule}` for the field's own value or `{sub-key}.{rule}` for a
     * nested input, without the field's validation key prefix.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return array<string, string> The messages.
     */
    public function messages( FormField $field ): array;

    /**
     * Normalizes a submitted value before it is stored.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     * @param  mixed  $value  The submitted value.
     *
     * @return mixed The value to store.
     */
    public function normalize( FormField $field, mixed $value ): mixed;

    /**
     * Gets the value a read-only field is submitted with, before the
     * 'ap.forms.readOnlyValue' filter runs.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return mixed The value, in the shape the type's rules accept.
     */
    public function readOnlyValue( FormField $field ): mixed;

    /**
     * Gets the human-readable form of a stored value.
     *
     * @since 1.6.0
     *
     * @param  FormSubmissionValue  $value  The stored value.
     *
     * @return string The display value.
     */
    public function display( FormSubmissionValue $value ): string;

    /**
     * Gets the CSV export column headers for a field of this type.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return list<string> The column headers.
     */
    public function exportColumns( FormField $field ): array;

    /**
     * Gets the CSV export cells for a field of this type, one per column.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     * @param  FormSubmissionValue|null  $value  The stored value, or null when none was submitted.
     *
     * @return list<string> The cells.
     */
    public function exportValues( FormField $field, ?FormSubmissionValue $value ): array;

    /**
     * Gets the notification merge tags a field of this type provides.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return array<string, string> Descriptions keyed by tag, such as `{email}`.
     */
    public function mergeTags( FormField $field ): array;

    /**
     * Gets the Blade view that renders a field of this type.
     *
     * @since 1.6.0
     *
     * @return string The view name.
     */
    public function view(): string;
}
