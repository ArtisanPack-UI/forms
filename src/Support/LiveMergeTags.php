<?php

/**
 * Live merge tag support for HTML blocks.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\FieldTypes\CaptchaFieldType;
use ArtisanPackUI\Forms\FieldTypes\CompositeFieldType;
use ArtisanPackUI\Forms\Models\FormField;

/**
 * Shows what a visitor entered inside an HTML block, as they type.
 *
 * An HTML block may write `{field_name}`, or `{field_name:key}` for one input
 * of a composite field. Renderers replace each tag in the block's text, not
 * in its attributes, with the field's current value as escaped text: a
 * choice shows its label, a list of choices or a composite field its filled
 * parts, and a field hidden by conditional logic nothing. Tags naming no
 * input on the public form (an unknown name, a file, a CAPTCHA or an
 * administrative field) are left as typed.
 *
 * The Livewire renderer gets placeholders from placeholders() and fills them
 * in with Alpine; `resources/js/shared/liveMergeTags.ts` does the same for
 * React and Vue.
 *
 * @since 1.6.0
 */
class LiveMergeTags
{
    /**
     * The merge tag pattern: a field name, then an optional `:key`.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const PATTERN = '/\{([A-Za-z0-9_\-]+)(?::([A-Za-z0-9_]+))?\}/';

    /**
     * Replaces the merge tags in an HTML block's text with placeholders.
     *
     * Each tag naming an input of the form becomes
     * `<span data-forms-merge-tag="{name}" data-forms-merge-part="{key}"></span>`.
     * Text inside tags (attributes) is left alone.
     *
     * @since 1.6.0
     *
     * @param  string  $html  The sanitized HTML.
     * @param  iterable<FormField>  $fields  The form's fields.
     *
     * @return string The HTML with placeholders.
     */
    public static function placeholders( string $html, iterable $fields ): string
    {
        $formats = self::formats( $fields );

        if ( [] === $formats || ! str_contains( $html, '{' ) ) {
            return $html;
        }

        $parts = preg_split( '/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE );

        if ( false === $parts ) {
            return $html;
        }

        foreach ( $parts as $index => $part ) {
            if ( str_starts_with( $part, '<' ) ) {
                continue;
            }

            $parts[ $index ] = (string) preg_replace_callback( self::PATTERN, function ( array $match ) use ( $formats ): string {
                if ( ! isset( $formats[ $match[1] ] ) ) {
                    return $match[0];
                }

                $part = isset( $match[2] ) && '' !== $match[2] ? ' data-forms-merge-part="' . e( $match[2] ) . '"' : '';

                return '<span data-forms-merge-tag="' . e( $match[1] ) . '"' . $part . '></span>';
            }, $part );
        }

        return implode( '', $parts );
    }

    /**
     * Gets how each input's value is shown in a merge tag, keyed by field name.
     *
     * `options` maps a choice's value to its label, and `separator` joins a
     * composite field's filled parts.
     *
     * @since 1.6.0
     *
     * @param  iterable<FormField>  $fields  The form's fields.
     *
     * @return array<string, array{type: string, options: array<string, string>, separator: string}> The formats.
     */
    public static function formats( iterable $fields ): array
    {
        $formats = [];

        foreach ( $fields as $field ) {
            if ( ! self::isTaggable( $field ) ) {
                continue;
            }

            $options = [];

            foreach ( FieldTypes::hasOptions( (string) $field->type ) ? $field->options : [] as $option ) {
                if ( is_array( $option ) && isset( $option['value'] ) && is_scalar( $option['value'] ) ) {
                    $options[ (string) $option['value'] ] = (string) ( $option['label'] ?? $option['value'] );
                }
            }

            foreach ( $field->getSubInputs() as $subInput ) {
                foreach ( $subInput['options'] as $option ) {
                    $options[ $option['value'] ] = $option['label'];
                }
            }

            $handler = $field->fieldType();

            $formats[ $field->name ] = [
                'type'      => (string) $field->type,
                'options'   => $options,
                'separator' => $handler instanceof CompositeFieldType ? (string) ( $handler->definition()['format_separator'] ?? ', ' ) : ', ',
            ];
        }

        return $formats;
    }

    /**
     * Checks whether a merge tag may show a field's value.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return bool True for an input on the public form.
     */
    protected static function isTaggable( FormField $field ): bool
    {
        return ! $field->isLayoutField()
            && ! $field->is_file_field
            && CaptchaFieldType::TYPE !== $field->type
            && ! $field->isAdministrative()
            && false !== ( $field->fieldType()->definition()['stores_value'] ?? true );
    }
}
