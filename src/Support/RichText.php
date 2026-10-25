<?php

/**
 * Rich text support.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

/**
 * Sanitizes the HTML a rich text textarea submits and reads its text.
 *
 * Visitors may use paragraphs, line breaks, bold, italic, underline,
 * strikethrough, lists, quotes and links (http, https and mailto only, marked
 * `rel="nofollow"`). Everything else, every attribute but `href` included, is
 * removed with `kses()` when the value is saved and again when it is shown.
 *
 * Length and word limits count the text, not the markup (see toPlainText()).
 * `resources/js/shared/richText.ts` mirrors the conversion for React and Vue.
 *
 * @since 1.6.0
 */
class RichText
{
    /**
     * htmLawed configuration for visitor-submitted rich text.
     *
     * @since 1.6.0
     *
     * @var array<string, mixed>
     */
    public const SANITIZE_CONFIG = [
        'safe'           => 1,
        'elements'       => 'a, b, blockquote, br, em, i, li, ol, p, s, strong, u, ul',
        'deny_attribute' => '* -href',
        'schemes'        => 'href: http, https, mailto; *: http, https',
        'anti_link_spam' => ['`.`', ''],
        'comment'        => 1,
        'cdata'          => 1,
    ];

    /**
     * The longest rich text HTML a submission may hold, in characters.
     *
     * Length limits count the text, so without a cap on the markup a value
     * padded with empty tags would pass them. The cap matches the `value`
     * column (a `TEXT` column holds 65,535 bytes).
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const MAX_HTML_LENGTH = 65535;

    /**
     * Sanitizes rich text HTML.
     *
     * Falls back to the escaped text, with line breaks kept, when
     * artisanpack-ui/security is not loaded, so no markup gets through.
     *
     * @since 1.6.0
     *
     * @param  string  $html  The submitted HTML.
     *
     * @return string Safe HTML, or `''` when it holds no text.
     */
    public static function sanitize( string $html ): string
    {
        if ( '' === self::toPlainText( $html ) ) {
            return '';
        }

        if ( ! function_exists( 'security' ) || ! app()->bound( 'security' ) ) {
            return nl2br( e( self::toPlainText( $html ) ), false );
        }

        return trim( security()->kses( $html, self::SANITIZE_CONFIG ) );
    }

    /**
     * Converts rich text HTML to plain text.
     *
     * Line breaks and the ends of paragraphs, list items and quotes become new
     * lines, tags are removed and entities decoded. With `$listMarkers`, list
     * items start with `- `, for text emails and exports.
     *
     * @since 1.6.0
     *
     * @param  string  $html  The HTML.
     * @param  bool  $listMarkers  Whether to mark list items.
     *
     * @return string The text.
     */
    public static function toPlainText( string $html, bool $listMarkers = false ): string
    {
        $text = preg_replace( '/<br\s*\/?>/i', "\n", $html ) ?? $html;
        $text = preg_replace( '/<\/(p|div|li|blockquote|h[1-6])\s*>/i', "\n", $text ) ?? $text;

        if ( $listMarkers ) {
            $text = preg_replace( '/<li(\s[^>]*)?>/i', '- ', $text ) ?? $text;
        }

        $text = html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $text = str_replace( ["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text );
        $text = preg_replace( "/[ \t]+\n/", "\n", $text ) ?? $text;
        $text = preg_replace( "/\n{3,}/", "\n\n", $text ) ?? $text;

        return trim( $text );
    }

    /**
     * Counts the characters in rich text, as length limits see them.
     *
     * @since 1.6.0
     *
     * @param  string  $html  The HTML.
     *
     * @return int The number of characters.
     */
    public static function length( string $html ): int
    {
        return mb_strlen( self::toPlainText( $html ) );
    }

    /**
     * Blanks rich text that holds no text, such as the `<p><br></p>` an emptied editor leaves.
     *
     * Runs before validation, so a required rich text field left empty fails `required`.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The submitted value.
     *
     * @return mixed The value, or `''` when it holds no text.
     */
    public static function blankIfEmpty( mixed $value ): mixed
    {
        return is_string( $value ) && '' === self::toPlainText( $value ) ? '' : $value;
    }
}
