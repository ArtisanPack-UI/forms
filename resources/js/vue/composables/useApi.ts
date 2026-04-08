/**
 * useApi composable for authenticated API communication.
 *
 * Provides typed methods for GET, POST, PUT, and DELETE requests
 * to the Forms REST API with error handling and CSRF token support.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import { onUnmounted } from 'vue';

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

/** Options for configuring the useApi composable. */
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

/** Return type of the useApi composable. */
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
 * Vue composable for making authenticated API calls.
 *
 * @example
 * ```ts
 * const { get, post, put, del } = useApi({ baseUrl: '/api/v1/forms' });
 * const forms = await get<PaginatedResponse<Form>>('/');
 * const form = await post<{ data: Form }>('/', { name: 'New Form' });
 * ```
 */
export function useApi( options: UseApiOptions ): UseApiReturn {
	const { baseUrl, csrfToken, authorization, credentials = 'include' } = options;
	const abortControllers = new Map<string, AbortController>();

	onUnmounted( () => {
		for ( const controller of abortControllers.values() ) {
			controller.abort();
		}

		abortControllers.clear();
	} );

	function buildHeaders( isJson: boolean = true ): Record<string, string> {
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

		const xsrfToken = getXsrfToken();

		if ( xsrfToken ) {
			headers['X-XSRF-TOKEN'] = xsrfToken;
		}

		if ( authorization ) {
			headers['Authorization'] = authorization;
		}

		return headers;
	}

	function buildUrl( path: string, params?: Record<string, string> ): string {
		const url = new URL( `${baseUrl}${path}`, window.location.origin );

		if ( params ) {
			for ( const [key, value] of Object.entries( params ) ) {
				if ( value !== undefined && value !== '' ) {
					url.searchParams.set( key, value );
				}
			}
		}

		return url.toString();
	}

	async function handleResponse<T>( response: Response ): Promise<T> {
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
	}

	async function get<T>( path: string, params?: Record<string, string> ): Promise<T> {
		const url = buildUrl( path, params );
		const key = `GET:${url}`;
		abortControllers.get( key )?.abort();

		const controller = new AbortController();
		abortControllers.set( key, controller );

		try {
			const response = await fetch( url, {
				method: 'GET',
				headers: buildHeaders( false ),
				credentials,
				signal: controller.signal,
			} );

			return handleResponse<T>( response );
		} finally {
			if ( abortControllers.get( key ) === controller ) {
				abortControllers.delete( key );
			}
		}
	}

	async function post<T>( path: string, body?: unknown ): Promise<T> {
		const response = await fetch( buildUrl( path ), {
			method: 'POST',
			headers: buildHeaders(),
			credentials,
			body: body !== undefined ? JSON.stringify( body ) : undefined,
		} );

		return handleResponse<T>( response );
	}

	async function put<T>( path: string, body?: unknown ): Promise<T> {
		const response = await fetch( buildUrl( path ), {
			method: 'PUT',
			headers: buildHeaders(),
			credentials,
			body: body !== undefined ? JSON.stringify( body ) : undefined,
		} );

		return handleResponse<T>( response );
	}

	async function del<T = void>( path: string ): Promise<T> {
		const response = await fetch( buildUrl( path ), {
			method: 'DELETE',
			headers: buildHeaders(),
			credentials,
		} );

		return handleResponse<T>( response );
	}

	async function download( path: string, filename?: string ): Promise<void> {
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
	}

	return { get, post, put, del, download };
}
