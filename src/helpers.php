<?php

use ArtisanPackUI\Forms\A11y;

if ( ! function_exists( 'a11y' ) ) {
    /**
     * Get the Eventy instance.
     *
     * @return A11y
     */
    function a11y()
    {
        return app( 'a11y' );
    }
}

if ( ! function_exists( 'a11yCSSVarBlackOrWhite' ) ) {
    /**
     * Returns whether a text color should be black or white based on the background color.
     *
     * @param  string  $hexColor  The hex code for the background color.
     *
     * @since 1.0.0
     */
    function a11yCSSVarBlackOrWhite( string $hexColor ): string
    {
        return a11y()->a11yCSSVarBlackOrWhite( $hexColor );
    }
}

if ( ! function_exists( 'a11yGetContrastColor' ) ) {
    /**
     * Returns whether a text color should be black or white based on the background color.
     *
     * @param  string  $hexColor  The hex code for the background color.
     *
     * @since 1.0.0
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
     */
    function getToastDuration(): float|int
    {
        return a11y()->getToastDuration();
    }
}

if ( ! function_exists( 'a11yCheckContrastColor' ) ) {
    /**
     * Returns whether two given colors have the correct amount of contrast between them.
     *
     * @param  string  $firstHexColor  The first color to check.
     * @param  string  $secondHexColor  The second color to check.
     *
     * @since 1.0.0
     */
    function a11yCheckContrastColor( string $firstHexColor, string $secondHexColor ): bool
    {
        return a11y()->a11yCheckContrastColor( $firstHexColor, $secondHexColor);
    }
}
