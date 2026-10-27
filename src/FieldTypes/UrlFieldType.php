<?php

/**
 * URL field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\UrlSchemes;

/**
 * Web address input.
 *
 * Only the `allowed_schemes` are accepted (`http` and `https` unless the
 * field sets others). Laravel's `url` rule needs `://` after the scheme, so
 * schemes such as `mailto:` cannot be used. An address typed without a scheme, such as
 * `example.com`, gets `https://` before validation and again when stored
 * (see UrlSchemes).
 *
 * @since 1.6.0
 */
class UrlFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'url';

    /**
     * {@inheritDoc}
     *
     * Adds `https://` to an address typed without a scheme.
     *
     * @since 1.6.0
     */
    public function normalize( FormField $field, mixed $value ): mixed
    {
        return UrlSchemes::withScheme( $value, $field->getAllowedUrlSchemes() );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['url'], [
            'label'    => __( 'URL' ),
            'defaults' => [
                'label'       => __( 'Website URL' ),
                'placeholder' => 'https://example.com',
            ],
            'settings' => [
                ...$this->lengthSettings(),
                [
                    'key'         => 'allowed_schemes',
                    'input'       => 'list',
                    'store'       => 'validation_rules',
                    'tab'         => 'validation',
                    'label'       => __( 'Allowed Schemes' ),
                    'placeholder' => 'http, https',
                    'hint'        => __( 'The URL schemes accepted, comma-separated, such as https or ftp. Defaults to http and https. Only schemes followed by :// work, so not mailto: or tel:. Addresses typed without one get https://.' ),
                    'monospace'   => true,
                ],
                $this->noDuplicatesSetting(),
                $this->readOnlySetting(),
            ],
        ] );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeRules( FormField $field ): array
    {
        // Laravel puts the schemes into its URL pattern unescaped, so `git+ssh` would not match itself
        return ['url:' . implode( ',', array_map( fn ( string $scheme ): string => preg_quote( $scheme, '~' ), $field->getAllowedUrlSchemes() ) )];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeMessages( FormField $field ): array
    {
        return ['url' => __( 'Please enter a valid URL for :field.', ['field' => $field->label] )];
    }
}
