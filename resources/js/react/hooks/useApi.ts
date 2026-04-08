/**
 * useApi hook for authenticated API communication.
 *
 * Provides typed methods for GET, POST, PUT, and DELETE requests
 * to the Forms REST API with error handling and CSRF token support.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import { useCallback, useMemo, useRef } from 'react';

import type {
	ErrorResponse,
	ValidationErrorResponse,
} from '../../types/artisanpack-forms';

/** Error class for API responses with validation errors. */
export class ApiValidationError extends Error {
	public readonly errors: Record<string, string[]>;
	public readonly status: number;

	constructor( message: string, errors: Record<string, string[]>, status: number ) {
		super( message );
		this.name = 'ApiValidationError';
		this.errors = errors;
		this.status = status;
	}
}

/** Error class for general API errors. */
export class ApiError extends Error {
	public readonly status: number;

	constructor( message: string, status: number ) {
		super( message );
		this.name = 'ApiError';
		this.status = status;
	}
}

/** Options for configuring the useApi hook. */
export interface UseApiOptions {
	/** Base URL for the forms API (e.g. "/api/v1/forms"). */
	baseUrl: string;
	/** Optional CSRF token for non-API middleware. */
	csrfToken?: string;
	/** Optional authorization header value (e.g. "Bearer token"). */
	authorization?: string;
	/** Fetch credentials mode. Defaults to 'include' for cross-origin Sanctum support. */
	credentials?: RequestCredentials;
}

/** Return type of the useApi hook. */
export interface UseApiReturn {
	/** Perform a GET request. */
	get: <T>( path: string, params?: Record<string, string> ) => Promise<T>;
	/** Perform a POST request with JSON body. */
	post: <T>( path: string, body?: unknown ) => Promise<T>;
	/** Perform a PUT request with JSON body. */
	put: <T>( path: string, body?: unknown ) => Promise<T>;
	/** Perform a DELETE request. */
	del: <T = void>( path: string ) => Promise<T>;
	/** Download a file from the API. */
	download: ( path: string, filename?: string ) => Promise<void>;
}

/**
 * Reads a CSRF token from a meta tag if present.
 */
function getMetaCsrfToken(): string | null {
	const meta = document.querySelector( 'meta[name="csrf-token"]' );

	return meta?.getAttribute( 'content' ) ?? null;
}

/**
 * Reads the XSRF-TOKEN cookie set by Laravel Sanctum.
 */
function getXsrfToken(): string | null {
	const match = document.cookie.match( /(?:^|;\s*)XSRF-TOKEN=([^;]*)/ );

	return match ? decodeURIComponent( match[1] ) : null;
}

/**
 * React hook for making authenticated API calls.
 *
 * @example
 * ```tsx
 * const api = useApi({ baseUrl: '/api/v1/forms' });
 * const forms = await api.get<PaginatedResponse<Form>>('/');
 * const form = await api.post<{ data: Form }>('/', { name: 'New Form' });
 * ```
 */
export function useApi( options: UseApiOptions ): UseApiReturn {
	const { baseUrl, csrfToken, authorization, credentials = 'include' } = options;
	const abortControllers = useRef<Map<string, AbortController>>( new Map() );

	const buildHeaders = useCallback(
		( isJson: boolean = true ): Record<string, string> => {
			const headers: Record<string, string> = {
				Accept: 'application/json',
			};

			if ( isJson ) {
				headers['Content-Type'] = 'application/json';
			}

			const token = csrfToken ?? getMetaCsrfToken();

			if ( token ) {
				headers['X-CSRF-TOKEN'] = token;
			}

			// Sanctum SPA authentication uses the XSRF-TOKEN cookie
			const xsrfToken = getXsrfToken();

			if ( xsrfToken ) {
				headers['X-XSRF-TOKEN'] = xsrfToken;
			}

			if ( authorization ) {
				headers['Authorization'] = authorization;
			}

			return headers;
		},
		[csrfToken, authorization],
	);

	const buildUrl = useCallback(
		( path: string, params?: Record<string, string> ): string => {
			const url = new URL( `${baseUrl}${path}`, window.location.origin );

			if ( params ) {
				for ( const [key, value] of Object.entries( params ) ) {
					if ( value !== undefined && value !== '' ) {
						url.searchParams.set( key, value );
					}
				}
			}

			return url.toString();
		},
		[baseUrl],
	);

	const handleResponse = useCallback( async <T>( response: Response ): Promise<T> => {
		if ( response.status === 204 ) {
			return undefined as T;
		}

		if ( response.status === 422 ) {
			const data: ValidationErrorResponse = await response.json();
			throw new ApiValidationError( data.message, data.errors, 422 );
		}

		if ( !response.ok ) {
			let message = `Request failed with status ${response.status}`;

			try {
				const data: ErrorResponse = await response.json();
				message = data.message;
			} catch {
				// Use the default message
			}

			throw new ApiError( message, response.status );
		}

		return response.json() as Promise<T>;
	}, [] );

	const get = useCallback(
		async <T>( path: string, params?: Record<string, string> ): Promise<T> => {
			const url = buildUrl( path, params );
			const key = `GET:${url}`;
			abortControllers.current.get( key )?.abort();

			const controller = new AbortController();
			abortControllers.current.set( key, controller );

			try {
				const response = await fetch( url, {
					method: 'GET',
					headers: buildHeaders(),
					credentials,
					signal: controller.signal,
				} );

				return handleResponse<T>( response );
			} finally {
				if ( abortControllers.current.get( key ) === controller ) {
					abortControllers.current.delete( key );
				}
			}
		},
		[buildUrl, buildHeaders, handleResponse, credentials],
	);

	const post = useCallback(
		async <T>( path: string, body?: unknown ): Promise<T> => {
			const response = await fetch( buildUrl( path ), {
				method: 'POST',
				headers: buildHeaders(),
				credentials,
				body: body !== undefined ? JSON.stringify( body ) : undefined,
			} );

			return handleResponse<T>( response );
		},
		[buildUrl, buildHeaders, handleResponse, credentials],
	);

	const put = useCallback(
		async <T>( path: string, body?: unknown ): Promise<T> => {
			const response = await fetch( buildUrl( path ), {
				method: 'PUT',
				headers: buildHeaders(),
				credentials,
				body: body !== undefined ? JSON.stringify( body ) : undefined,
			} );

			return handleResponse<T>( response );
		},
		[buildUrl, buildHeaders, handleResponse, credentials],
	);

	const del = useCallback(
		async <T = void>( path: string ): Promise<T> => {
			const response = await fetch( buildUrl( path ), {
				method: 'DELETE',
				headers: buildHeaders(),
				credentials,
			} );

			return handleResponse<T>( response );
		},
		[buildUrl, buildHeaders, handleResponse, credentials],
	);

	const download = useCallback(
		async ( path: string, filename?: string ): Promise<void> => {
			const response = await fetch( buildUrl( path ), {
				method: 'GET',
				headers: buildHeaders( false ),
				credentials,
			} );

			if ( !response.ok ) {
				throw new ApiError( 'Download failed.', response.status );
			}

			const blob = await response.blob();
			const url = URL.createObjectURL( blob );
			const a = document.createElement( 'a' );
			a.href = url;
			a.download = filename ?? 'download';
			document.body.appendChild( a );
			a.click();
			document.body.removeChild( a );
			URL.revokeObjectURL( url );
		},
		[buildUrl, buildHeaders, credentials],
	);

	return useMemo(
		() => ( { get, post, put, del, download } ),
		[get, post, put, del, download],
	);
}
