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
 * yielding `aether`. Keys cover both cases and map to the lowercase ASCII the
 * rest of the pipeline expects.
 */
const TRANSLITERATIONS: Record<string, string> = {
	'æ': 'ae', 'Æ': 'ae',
	'œ': 'oe', 'Œ': 'oe',
	'ß': 'ss',
	'ø': 'o', 'Ø': 'o',
	'đ': 'd', 'Đ': 'd', 'ð': 'd', 'Ð': 'd',
	'þ': 'th', 'Þ': 'th',
	'ł': 'l', 'Ł': 'l',
	'ħ': 'h', 'Ħ': 'h',
	'ı': 'i', 'İ': 'i',
	'ŋ': 'ng', 'Ŋ': 'ng',
	'ĸ': 'k',
};

const TRANSLITERATION_PATTERN = new RegExp(
	`[${Object.keys( TRANSLITERATIONS ).join( '' )}]`,
	'g',
);

/**
 * Replace the non-decomposable Latin letters in {@link TRANSLITERATIONS} with
 * their ASCII equivalents.
 *
 * @param value Raw string to transliterate.
 * @returns The string with mapped letters replaced.
 */
function transliterate( value: string ): string {
	return value.replace(
		TRANSLITERATION_PATTERN,
		( char ) => TRANSLITERATIONS[ char ] ?? char,
	);
}

/**
 * Convert a string to a URL-friendly slug.
 *
 * @param value Raw string to slugify.
 * @returns Lowercased, hyphen-separated slug. Returns an empty string for
 *          empty or whitespace-only input.
 */
export function slugify( value: string ): string {
	return transliterate( value )
		.normalize( 'NFKD' )
		.replace( DIACRITICS_PATTERN, '' )
		.toLowerCase()
		.trim()
		.replace( NON_ALPHANUM_PATTERN, '-' )
		.replace( EDGE_HYPHENS_PATTERN, '' );
}
