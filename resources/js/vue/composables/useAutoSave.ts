/**
 * useAutoSave composable for debounced automatic saving.
 *
 * Tracks dirty state and triggers a save callback after a configurable
 * debounce period. Provides manual save and dirty state tracking.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import { type Ref, onUnmounted, ref } from 'vue';

/** Return type of the useAutoSave composable. */
export interface UseAutoSaveReturn {
	isDirty: Ref<boolean>;
	isSaving: Ref<boolean>;
	lastSavedAt: Ref<Date | null>;
	saveError: Ref<string | null>;
	markDirty: () => void;
	saveNow: () => Promise<void>;
	clearDirty: () => void;
}

/** Options for configuring the useAutoSave composable. */
export interface UseAutoSaveOptions {
	/** Callback to execute when saving. Should return a promise. */
	onSave: () => Promise<void>;
	/** Debounce delay in milliseconds. Defaults to 2000. */
	debounceMs?: number;
	/** Whether auto-save is enabled. Defaults to true. */
	enabled?: boolean;
}

/**
 * Vue composable for auto-saving form data with debouncing.
 *
 * @example
 * ```ts
 * const { isDirty, isSaving, lastSavedAt, markDirty, saveNow } = useAutoSave({
 *   onSave: async () => {
 *     await api.put(`/${formSlug}`, formData);
 *   },
 *   debounceMs: 2000,
 * });
 *
 * // Call markDirty() whenever the user changes something
 * markDirty();
 * ```
 */
export function useAutoSave( options: UseAutoSaveOptions ): UseAutoSaveReturn {
	const { debounceMs = 2000, enabled = true } = options;

	const isDirty = ref( false );
	const isSaving = ref( false );
	const lastSavedAt = ref<Date | null>( null );
	const saveError = ref<string | null>( null );

	let timer: ReturnType<typeof setTimeout> | null = null;
	let isSavingInternal = false;
	let pendingSave = false;

	/**
	 * We store the onSave callback so the latest closure is always called.
	 * In Vue, we can simply re-read options.onSave since closures capture
	 * reactive refs automatically.
	 */
	async function performSave(): Promise<void> {
		if ( isSavingInternal ) {
			return;
		}

		isSavingInternal = true;
		isSaving.value = true;
		saveError.value = null;

		try {
			await options.onSave();
			lastSavedAt.value = new Date();

			// Only clear dirty if no pending retry is queued
			if ( !pendingSave ) {
				isDirty.value = false;
			}
		} catch ( err ) {
			const message = err instanceof Error ? err.message : 'Save failed.';
			saveError.value = message;
		} finally {
			isSavingInternal = false;
			isSaving.value = false;

			if ( pendingSave ) {
				pendingSave = false;
				timer = setTimeout( () => {
					timer = null;
					performSave();
				}, 0 );
			}
		}
	}

	function markDirty(): void {
		isDirty.value = true;
		saveError.value = null;

		if ( !enabled ) {
			return;
		}

		if ( isSavingInternal ) {
			pendingSave = true;

			return;
		}

		if ( timer ) {
			clearTimeout( timer );
		}

		timer = setTimeout( () => {
			performSave();
		}, debounceMs );
	}

	async function saveNow(): Promise<void> {
		if ( timer ) {
			clearTimeout( timer );
			timer = null;
		}

		await performSave();
	}

	function clearDirty(): void {
		isDirty.value = false;
		pendingSave = false;

		if ( timer ) {
			clearTimeout( timer );
			timer = null;
		}
	}

	onUnmounted( () => {
		if ( timer ) {
			clearTimeout( timer );
		}
	} );

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
