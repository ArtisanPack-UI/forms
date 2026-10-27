/**
 * URL field schemes.
 *
 * Mirrors `UrlSchemes` on the server: a URL field accepts its allowed
 * schemes (`http` and `https` unless it sets others), and an address typed
 * without a scheme, such as `example.com`, gets `https://` (`http://` when
 * only `http` is allowed). `localhost:8080` reads as a host and port.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FormField } from '../types/artisanpack-forms';

/** The schemes a URL field allows when it sets none. */
export const DEFAULT_URL_SCHEMES: readonly string[] = ['http', 'https'];

/**
 * Schemes a URL field never allows, whatever its setting says: they run
 * script or read local data when the link is opened. Mirrors
 * `UrlSchemes::BLOCKED` (since 1.6.0).
 */
export const BLOCKED_URL_SCHEMES: readonly string[] = ['javascript', 'data', 'vbscript', 'file', 'blob'];

/**
 * Normalize a stored scheme list, falling back to `http` and `https`.
 * Blocked schemes such as `javascript` are dropped (since 1.6.0).
 */
export function normalizeSchemeList( schemes: unknown ): string[] {
	const entries = typeof schemes === 'string' ? schemes.split( ',' ) : Array.isArray( schemes ) ? schemes : [];
	const normalized: string[] = [];

	for ( const entry of entries ) {
		if ( typeof entry !== 'string' ) {
			continue;
		}

		const scheme = entry.trim().replace( /:(\/\/)?$/, '' ).toLowerCase();

		if ( /^[a-z][a-z0-9+.-]*$/.test( scheme ) && !BLOCKED_URL_SCHEMES.includes( scheme ) && !normalized.includes( scheme ) ) {
			normalized.push( scheme );
		}
	}

	return normalized.length > 0 ? normalized : [...DEFAULT_URL_SCHEMES];
}

/** Get the schemes a URL field accepts. */
export function getAllowedUrlSchemes( field: FormField ): string[] {
	return normalizeSchemeList( field.validation_rules?.allowed_schemes );
}

/** Whether a value starts with a scheme; a colon before a digit is a port. */
export function hasUrlScheme( value: string ): boolean {
	return /^[a-z][a-z0-9+.-]*:(?!\d)/i.test( value );
}

/**
 * Add a scheme to an address typed without one. Blank values, values with a
 * scheme and fields allowing neither `https` nor `http` are returned trimmed.
 */
export function withUrlScheme( value: string, allowed: string[] = [...DEFAULT_URL_SCHEMES] ): string {
	const trimmed = value.trim();
	const scheme = allowed.includes( 'https' ) ? 'https' : allowed.includes( 'http' ) ? 'http' : undefined;

	if ( trimmed === '' || scheme === undefined || hasUrlScheme( trimmed ) ) {
		return trimmed === '' ? value : trimmed;
	}

	return trimmed.startsWith( '//' ) ? `${scheme}:${trimmed}` : `${scheme}://${trimmed}`;
}

/**
 * Whether a URL is absolute and uses one of the allowed schemes, followed by
 * `://` as the server's `url` rule requires (so not `mailto:`).
 */
export function isAllowedUrl( value: string, allowed: string[] ): boolean {
	if ( !/^[a-z][a-z0-9+.-]*:\/\//i.test( value ) ) {
		return false;
	}

	try {
		const url = new URL( value );

		return allowed.includes( url.protocol.replace( /:$/, '' ).toLowerCase() );
	} catch {
		return false;
	}
}
