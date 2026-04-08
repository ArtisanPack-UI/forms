/**
 * useAutoSave hook for debounced automatic saving.
 *
 * Tracks dirty state and triggers a save callback after a configurable
 * debounce period. Provides manual save and dirty state tracking.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import { useCallback, useEffect, useRef, useState } from 'react';

/** Options for configuring the useAutoSave hook. */
export interface UseAutoSaveOptions {
	/** Callback to execute when saving. Should return a promise. */
	onSave: () => Promise<void>;
	/** Debounce delay in milliseconds. Defaults to 2000. */
	debounceMs?: number;
	/** Whether auto-save is enabled. Defaults to true. */
	enabled?: boolean;
}

/** Return type of the useAutoSave hook. */
export interface UseAutoSaveReturn {
	/** Whether unsaved changes exist. */
	isDirty: boolean;
	/** Whether a save is currently in progress. */
	isSaving: boolean;
	/** Timestamp of the last successful save, or null if never saved. */
	lastSavedAt: Date | null;
	/** Error from the last save attempt, or null. */
	saveError: string | null;
	/** Mark the state as dirty, triggering auto-save after debounce. */
	markDirty: () => void;
	/** Trigger an immediate save. */
	saveNow: () => Promise<void>;
	/** Clear the dirty flag without saving. */
	clearDirty: () => void;
}

/**
 * React hook for auto-saving form data with debouncing.
 *
 * @example
 * ```tsx
 * const { isDirty, isSaving, lastSavedAt, markDirty, saveNow } = useAutoSave({
 *   onSave: async () => {
 *     await api.put(`/${formSlug}`, formData);
 *   },
 *   debounceMs: 2000,
 * });
 *
 * // Call markDirty() whenever the user changes something
 * const handleChange = (value: string) => {
 *   setFormData(prev => ({ ...prev, name: value }));
 *   markDirty();
 * };
 * ```
 */
export function useAutoSave( options: UseAutoSaveOptions ): UseAutoSaveReturn {
	const { onSave, debounceMs = 2000, enabled = true } = options;

	const [isDirty, setIsDirty] = useState( false );
	const [isSaving, setIsSaving] = useState( false );
	const [lastSavedAt, setLastSavedAt] = useState<Date | null>( null );
	const [saveError, setSaveError] = useState<string | null>( null );

	const timerRef = useRef<ReturnType<typeof setTimeout> | null>( null );
	const isSavingRef = useRef( false );
	const pendingSaveRef = useRef( false );
	const onSaveRef = useRef( onSave );
	onSaveRef.current = onSave;

	const performSave = useCallback( async () => {
		if ( isSavingRef.current ) {
			return;
		}

		isSavingRef.current = true;
		setIsSaving( true );
		setSaveError( null );

		try {
			await onSaveRef.current();
			setLastSavedAt( new Date() );

			// Only clear dirty if no pending retry is queued
			if ( !pendingSaveRef.current ) {
				setIsDirty( false );
			}
		} catch ( err ) {
			const message = err instanceof Error ? err.message : 'Save failed.';
			setSaveError( message );
		} finally {
			isSavingRef.current = false;
			setIsSaving( false );

			if ( pendingSaveRef.current ) {
				pendingSaveRef.current = false;
				timerRef.current = setTimeout( () => {
					timerRef.current = null;
					performSave();
				}, 0 );
			}
		}
	}, [] );

	const markDirty = useCallback( () => {
		setIsDirty( true );
		setSaveError( null );

		if ( !enabled ) {
			return;
		}

		if ( isSavingRef.current ) {
			pendingSaveRef.current = true;

			return;
		}

		if ( timerRef.current ) {
			clearTimeout( timerRef.current );
		}

		timerRef.current = setTimeout( () => {
			performSave();
		}, debounceMs );
	}, [enabled, debounceMs, performSave] );

	const saveNow = useCallback( async () => {
		if ( timerRef.current ) {
			clearTimeout( timerRef.current );
			timerRef.current = null;
		}

		await performSave();
	}, [performSave] );

	const clearDirty = useCallback( () => {
		setIsDirty( false );

		if ( timerRef.current ) {
			clearTimeout( timerRef.current );
			timerRef.current = null;
		}
	}, [] );

	// Cleanup on unmount
	useEffect( () => {
		return () => {
			if ( timerRef.current ) {
				clearTimeout( timerRef.current );
			}
		};
	}, [] );

	return {
		isDirty,
		isSaving,
		lastSavedAt,
		saveError,
		markDirty,
		saveNow,
		clearDirty,
	};
}
