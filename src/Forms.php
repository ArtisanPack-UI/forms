<?php

/**
 * Forms main class.
 *
 * Provides the main entry point for the Forms package functionality.
 * This class serves as the facade accessor for form-related operations.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

namespace ArtisanPackUI\Forms;

/**
 * Forms class.
 *
 * Main class for the Forms package that serves as the facade accessor.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class Forms
{
    /**
     * Path to the list of strings the React and Vue admin components translate.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const JS_TRANSLATION_KEYS_PATH = __DIR__ . '/../resources/js/shared/translation-keys.json';

    /**
     * Gets the strings the React and Vue admin components pass to `t()`.
     *
     * @since 1.6.0
     *
     * @return list<string> The English strings.
     */
    public function jsTranslationKeys(): array
    {
        $contents = is_readable( self::JS_TRANSLATION_KEYS_PATH ) ? file_get_contents( self::JS_TRANSLATION_KEYS_PATH ) : false;
        $keys     = false === $contents ? null : json_decode( $contents, true );

        return is_array( $keys ) ? array_values( array_filter( $keys, 'is_string' ) ) : [];
    }

    /**
     * Gets the React and Vue admin strings translated into the current locale.
     *
     * Pass the result to `setTranslations()` from the React or Vue entry point.
     * Each string is translated with `__()`, so the host's JSON language files
     * (`lang/{locale}.json`) supply the translations.
     *
     * @since 1.6.0
     *
     * @return array<string, string> Translations keyed by English string.
     */
    public function jsTranslations(): array
    {
        $translations = [];

        foreach ( $this->jsTranslationKeys() as $key ) {
            $translation          = __( $key );
            $translations[ $key ] = is_string( $translation ) ? $translation : $key;
        }

        return $translations;
    }
}
