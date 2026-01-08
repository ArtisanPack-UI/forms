<?php

/**
 * Helper functions for the Forms package.
 *
 * Provides global helper functions for accessibility utilities
 * including color contrast checking and toast duration settings.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

use ArtisanPackUI\Forms\A11y;

if ( ! function_exists( 'a11y' ) ) {
    /**
     * Gets the A11y accessibility helper instance.
     *
     * @since 1.0.0
     *
     * @return A11y The accessibility helper instance.
     */
    function a11y(): A11y
    {
        return app( 'a11y' );
    }
}

if ( ! function_exists( 'a11yCSSVarBlackOrWhite' ) ) {
    /**
     * Returns whether a text color should be black or white based on the background color.
     *
     * @since 1.0.0
     *
     * @param string $hexColor The hex code for the background color.
     *
     * @return string Either 'black' or 'white' for optimal contrast.
     */
    function a11yCSSVarBlackOrWhite( string $hexColor ): string
    {
        return a11y()->a11yCSSVarBlackOrWhite( $hexColor );
    }
}

if ( ! function_exists( 'a11yGetContrastColor' ) ) {
    /**
     * Returns the hex color code for optimal contrast against a background color.
     *
     * @since 1.0.0
     *
     * @param string $hexColor The hex code for the background color.
     *
     * @return string The hex color code (#000000 or #FFFFFF) for optimal contrast.
     */
    function a11yGetContrastColor( string $hexColor ): string
    {
        return a11y()->a11yGetContrastColor( $hexColor );
    }
}

if ( ! function_exists( 'getToastDuration' ) ) {
    /**
     * Gets the user's setting for how long the toast element should stay on the screen.
     *
     * @since 1.0.0
     *
     * @return float|int The toast duration in milliseconds.
     */
    function getToastDuration(): float|int
    {
        return a11y()->getToastDuration();
    }
}

if ( ! function_exists( 'a11yCheckContrastColor' ) ) {
    /**
     * Checks whether two colors have sufficient contrast between them.
     *
     * @since 1.0.0
     *
     * @param string $firstHexColor  The first color to check.
     * @param string $secondHexColor The second color to check.
     *
     * @return bool True if the colors meet WCAG contrast requirements, false otherwise.
     */
    function a11yCheckContrastColor( string $firstHexColor, string $secondHexColor ): bool
    {
        return a11y()->a11yCheckContrastColor( $firstHexColor, $secondHexColor );
    }
}
