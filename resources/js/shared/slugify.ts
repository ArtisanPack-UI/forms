/**
 * Slugify helper for client-side derivation of URL-friendly identifiers.
 *
 * Mirrors the server-side Laravel Str::slug behavior closely enough to keep
 * the FormBuilder slug field in sync with the form name while the user is
 * still editing. The server remains the source of truth for uniqueness and
 * final normalization on save.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.2
 */

const DIACRITICS_PATTERN = /[̀-ͯ]/g;
const NON_ALPHANUM_PATTERN = /[^a-z0-9]+/g;
const EDGE_HYPHENS_PATTERN = /^-+|-+$/g;

/**
 * Convert a string to a URL-friendly slug.
 *
 * @param value Raw string to slugify.
 * @returns Lowercased, hyphen-separated slug. Returns an empty string for
 *          empty or whitespace-only input.
 */
export function slugify( value: string ): string {
	return value
		.normalize( 'NFKD' )
		.replace( DIACRITICS_PATTERN, '' )
		.toLowerCase()
		.trim()
		.replace( NON_ALPHANUM_PATTERN, '-' )
		.replace( EDGE_HYPHENS_PATTERN, '' );
}
