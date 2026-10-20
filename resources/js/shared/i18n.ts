/**
 * Translation helper for the React and Vue admin components.
 *
 * Keys are the English strings themselves, as with Laravel's JSON translation
 * files, so an untranslated key reads correctly. The host passes the
 * translated strings once at bootstrap, typically from the server:
 *
 * ```blade
 * <script>window.formsTranslations = @json( \ArtisanPackUI\Forms\Facades\Forms::jsTranslations() );</script>
 * ```
 *
 * ```ts
 * import { setTranslations } from '@/vendor/artisanpack-forms';
 *
 * setTranslations( window.formsTranslations );
 * ```
 *
 * Call `setTranslations()` before the components mount. Translations are not
 * reactive: strings already rendered (and Vue computeds that only call `t()`)
 * keep the text they had when they were first built.
 *
 * Every string passed to `t()` must be a single-quoted literal so the
 * server-side key list (`Forms::jsTranslationKeys()`) can be checked against
 * the components.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

/** Values substituted for `:placeholder` tokens. */
export type TranslationReplacements = Record<string, string | number>;

let translations: Record<string, string> = {};

/**
 * Register translated strings, keyed by their English text. Merges with
 * strings registered earlier.
 */
export function setTranslations( strings: Record<string, string> | null | undefined ): void {
	if ( !strings || typeof strings !== 'object' ) {
		return;
	}

	const next: Record<string, string> = { ...translations };

	for ( const [key, value] of Object.entries( strings ) ) {
		if ( typeof value === 'string' ) {
			next[key] = value;
		}
	}

	translations = next;
}

/** Get a copy of the registered translations. */
export function getTranslations(): Record<string, string> {
	return { ...translations };
}

/** Remove every registered translation (primarily for tests). */
export function clearTranslations(): void {
	translations = {};
}

/**
 * Translate a string, replacing `:name` tokens like Laravel's `__()`.
 *
 * @example
 * ```ts
 * t( 'Edit :type Field', { type: 'Email' } );
 * ```
 */
export function t( key: string, replacements: TranslationReplacements = {} ): string {
	let text = Object.prototype.hasOwnProperty.call( translations, key ) ? translations[key] : key;

	// Longest names first, so `:count` is not replaced inside `:counter`.
	const names = Object.keys( replacements ).sort( ( a, b ) => b.length - a.length );

	for ( const name of names ) {
		text = text.split( `:${name}` ).join( String( replacements[name] ) );
	}

	return text;
}
