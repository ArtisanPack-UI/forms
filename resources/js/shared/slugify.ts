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
 * ASCII transliterations for common Latin letters that `NFKD` normalization
 * cannot decompose (ligatures and stroked/barred letters have no combining
 * form to strip). Without these, `slugify` drops the letter entirely — e.g.
 * `Æther` would become `ther` — where Laravel's `Str::slug` transliterates it,
 * yielding `aether`.
 *
 * Applied after `NFKD`, diacritic stripping and lowercasing, so keys are
 * lowercase only and precomposed forms such as `Ǽ` or `ǿ` are covered once
 * they reduce to `æ` / `ø`. The middle dot (U+00B7) that `NFKD` splits out of
 * the Catalan `ŀ` is dropped, matching `Str::slug( 'coŀlegi' ) === 'collegi'`.
 */
const TRANSLITERATIONS: Record<string, string> = {
	'æ': 'ae',
	'œ': 'oe',
	'ß': 'ss',
	'ø': 'o',
	'đ': 'd',
	'ð': 'd',
	'þ': 'th',
	'ł': 'l',
	'ħ': 'h',
	'ı': 'i',
	'ŋ': 'n',
	'ĸ': 'k',
	'ŧ': 't',
	'ƒ': 'f',
	'\u00b7': '',
};

/**
 * Replace the non-decomposable Latin letters in {@link TRANSLITERATIONS} with
 * their ASCII equivalents.
 *
 * @param value Normalized, lowercased string to transliterate.
 * @returns The string with mapped letters replaced.
 */
function transliterate( value: string ): string {
	let result = '';

	for ( const char of value ) {
		result += TRANSLITERATIONS[ char ] ?? char;
	}

	return result;
}

/**
 * Convert a string to a URL-friendly slug.
 *
 * @param value Raw string to slugify.
 * @returns Lowercased, hyphen-separated slug. Returns an empty string for
 *          empty or whitespace-only input.
 */
export function slugify( value: string ): string {
	const normalized = value
		.normalize( 'NFKD' )
		.replace( DIACRITICS_PATTERN, '' )
		.toLowerCase();

	return transliterate( normalized )
		.trim()
		.replace( NON_ALPHANUM_PATTERN, '-' )
		.replace( EDGE_HYPHENS_PATTERN, '' );
}
