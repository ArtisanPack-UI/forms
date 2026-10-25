/**
 * Email field domain lists.
 *
 * Mirrors `EmailDomains` on the server: a listed domain matches the address's
 * domain and its subdomains, so `example.com` covers `mail.example.com`.
 * Entries may start with `@` and are compared case-insensitively. The DNS
 * check (`email_validation: dns`) runs on the server only.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FormField } from '../types/artisanpack-forms';

/** Normalize a stored domain list: lowercase, without a leading `@`, blanks removed. */
export function normalizeDomainList( domains: unknown ): string[] {
	const entries = typeof domains === 'string' ? domains.split( ',' ) : Array.isArray( domains ) ? domains : [];
	const normalized: string[] = [];

	for ( const entry of entries ) {
		if ( typeof entry !== 'string' ) {
			continue;
		}

		const domain = entry.trim().replace( /^@+/, '' ).toLowerCase().replace( /^[.\s]+|[.\s]+$/g, '' );

		if ( domain !== '' && !normalized.includes( domain ) ) {
			normalized.push( domain );
		}
	}

	return normalized;
}

/** Get an email field's allowed or blocked domains. */
export function getEmailDomains( field: FormField, list: 'allowed' | 'blocked' ): string[] {
	if ( field.type !== 'email' ) {
		return [];
	}

	return normalizeDomainList( list === 'allowed' ? field.validation_rules?.allowed_domains : field.validation_rules?.blocked_domains );
}

/** Get the domain part of an address, lowercased, or undefined. */
export function getEmailDomain( email: string ): string | undefined {
	const at = email.lastIndexOf( '@' );
	const domain = at < 0 ? '' : email.slice( at + 1 ).toLowerCase().replace( /^[.\s]+|[.\s]+$/g, '' );

	return domain === '' ? undefined : domain;
}

/** Whether a domain is one of the listed domains or a subdomain of one. */
export function matchesDomain( domain: string, domains: string[] ): boolean {
	return domains.some( ( listed ) => domain === listed || domain.endsWith( `.${listed}` ) );
}

/**
 * Whether an address's domain is accepted by the field's allowed and blocked
 * lists, mirroring the `forms_email_domain` rule. Addresses without a domain
 * pass, leaving them to the format check.
 */
export function isAcceptedEmailDomain( field: FormField, email: string ): boolean {
	const domain = getEmailDomain( email.trim() );

	if ( domain === undefined ) {
		return true;
	}

	const allowed = getEmailDomains( field, 'allowed' );

	if ( allowed.length > 0 && !matchesDomain( domain, allowed ) ) {
		return false;
	}

	return !matchesDomain( domain, getEmailDomains( field, 'blocked' ) );
}
