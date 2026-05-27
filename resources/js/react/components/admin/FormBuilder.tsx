/**
 * FormBuilder admin component.
 *
 * Main builder interface with drag-and-drop field ordering, field
 * palette sidebar, field settings editor panel, multi-step
 * configuration, form settings, live preview toggle, and auto-save
 * with dirty state tracking.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import { Alert } from '@artisanpack-ui/react';
import { Badge } from '@artisanpack-ui/react';
import { Button } from '@artisanpack-ui/react';
import { Checkbox } from '@artisanpack-ui/react';
import { Divider } from '@artisanpack-ui/react';
import { Input } from '@artisanpack-ui/react';
import { Loading } from '@artisanpack-ui/react';
import { Textarea } from '@artisanpack-ui/react';

import type {
	FieldType,
	Form,
	FormField,
	FormStep,
	StoreFieldRequest,
	StoreStepRequest,
	UpdateFieldRequest,
	UpdateFormRequest,
} from '../../../types/artisanpack-forms';
import { slugify } from '../../../shared/slugify';
import { useApi } from '../../hooks/useApi';
import type { UseApiOptions } from '../../hooks/useApi';
import { ApiValidationError } from '../../hooks/useApi';
import { useAutoSave } from '../../hooks/useAutoSave';
import { FieldEditor } from './FieldEditor';
import { FieldPalette } from './FieldPalette';

/** Props for the FormBuilder component. */
export interface FormBuilderProps extends UseApiOptions {
	/** The form slug to load and edit. */
	formSlug: string;
	/** Callback when navigating back to the forms list. */
	onBack?: () => void;
	/** Callback to view submissions for this form. */
	onViewSubmissions?: () => void;
	/** Optional CSS class name. */
	className?: string;
}

/** Internal type for dragging state. */
interface DragState {
	draggedIndex: number;
	overIndex: number;
}

/**
 * Main form builder component with drag-and-drop, settings, and auto-save.
 *
 * @example
 * ```tsx
 * <FormBuilder
 *   baseUrl="/api/v1/forms"
 *   formSlug="contact-us"
 *   onBack={() => navigate('/forms')}
 * />
 * ```
 */
export function FormBuilder( {
	baseUrl,
	csrfToken,
	authorization,
	credentials,
	formSlug,
	onBack,
	onViewSubmissions,
	className,
}: FormBuilderProps ): React.ReactElement {
	const { get, post, put, del } = useApi( { baseUrl, csrfToken, authorization, credentials } );

	// Core state
	const [form, setForm] = useState<Form | null>( null );
	const [fields, setFields] = useState<FormField[]>( [] );
	const [steps, setSteps] = useState<FormStep[]>( [] );
	const [isLoading, setIsLoading] = useState( true );
	const [error, setError] = useState<string | null>( null );
	const [validationErrors, setValidationErrors] = useState<Record<string, string[]>>( {} );

	// UI state
	const [selectedFieldId, setSelectedFieldId] = useState<number | null>( null );
	const [activeStepId, setActiveStepId] = useState<number | null>( null );
	const [activePanel, setActivePanel] = useState<'palette' | 'settings' | 'editor'>( 'palette' );
	const [showPreview, setShowPreview] = useState( false );

	// Drag state
	const [dragState, setDragState] = useState<DragState | null>( null );

	// Form settings state (local copy for auto-save)
	const [formSettings, setFormSettings] = useState<UpdateFormRequest>( {} );

	// Whether the user has taken manual control of the slug field. While
	// false, the slug auto-follows the form name (slugified). Flips to true
	// the first time the user edits the slug input directly. Local-only —
	// not persisted to the server.
	const [slugIsManual, setSlugIsManual] = useState( false );

	// Ref for latest form data (used in auto-save callback)
	const formSettingsRef = useRef( formSettings );
	formSettingsRef.current = formSettings;

	// Reset form state when switching forms
	useEffect( () => {
		setFormSettings( {} );
		formSettingsRef.current = {};
		setForm( null );
		setFields( [] );
		setSteps( [] );
		setSelectedFieldId( null );
		setActiveStepId( null );
		setActivePanel( 'palette' );
		setShowPreview( false );
		setError( null );
		setValidationErrors( {} );
		setSlugIsManual( false );
	}, [formSlug] );

	// Auto-save
	const { isDirty, isSaving, lastSavedAt, saveError, markDirty, saveNow } = useAutoSave( {
		onSave: async () => {
			if ( !form ) {
				return;
			}

			const data = formSettingsRef.current;

			if ( Object.keys( data ).length > 0 ) {
				const savedKeys = Object.keys( data );
				await put( `/${form.id}`, data );
				setFormSettings( ( prev ) => {
					const next = { ...prev };
					for ( const key of savedKeys ) {
						if ( prev[key as keyof UpdateFormRequest] === data[key as keyof UpdateFormRequest] ) {
							delete next[key as keyof UpdateFormRequest];
						}
					}
					return next;
				} );
			}
		},
		debounceMs: 2000,
	} );

	// Load form data
	const loadForm = useCallback( async () => {
		setIsLoading( true );
		setError( null );

		try {
			const response = await get<{ data: Form }>( `/${formSlug}` );
			const formData = response.data;
			setForm( formData );
			setFields( formData.fields ?? [] );
			setSteps( formData.steps ?? [] );

			// If the persisted slug differs from what we'd derive from the
			// current name, the user (or a prior session) already customized
			// it — start in manual mode so editing the name doesn't clobber
			// that custom slug.
			const persistedSlug = formData.slug ?? '';
			if ( persistedSlug !== slugify( formData.name ) ) {
				setSlugIsManual( true );
			}

			// Set active step to first step if multi-step
			if ( formData.is_multi_step && formData.steps && formData.steps.length > 0 ) {
				const sorted = [...formData.steps].sort( ( a, b ) => a.sort_order - b.sort_order );
				setActiveStepId( sorted[0].id );
			}
		} catch ( err ) {
			const message = err instanceof Error ? err.message : 'Failed to load form.';
			setError( message );
		} finally {
			setIsLoading( false );
		}
	}, [get, formSlug] );

	useEffect( () => {
		loadForm();
	}, [loadForm] );

	// Update form settings
	const updateFormSetting = useCallback( ( updates: UpdateFormRequest ) => {
		setFormSettings( ( prev ) => ( { ...prev, ...updates } ) );
		setForm( ( prev ) => prev ? { ...prev, ...updates } as Form : prev );
		markDirty();
	}, [markDirty] );

	// Get fields for the active step (or all fields for single-step)
	const currentFields = useMemo( () => {
		if ( !form?.is_multi_step ) {
			return [...fields].sort( ( a, b ) => a.sort_order - b.sort_order );
		}

		if ( null === activeStepId ) {
			// Show unassigned fields
			return fields
				.filter( ( f ) => null === f.step_id )
				.sort( ( a, b ) => a.sort_order - b.sort_order );
		}

		return fields
			.filter( ( f ) => f.step_id === activeStepId )
			.sort( ( a, b ) => a.sort_order - b.sort_order );
	}, [fields, form, activeStepId] );

	const selectedField = useMemo(
		() => fields.find( ( f ) => f.id === selectedFieldId ) ?? null,
		[fields, selectedFieldId],
	);

	const sortedSteps = useMemo(
		() => [...steps].sort( ( a, b ) => a.sort_order - b.sort_order ),
		[steps],
	);

	// -----------------------------------------------------------------------
	// Field CRUD
	// -----------------------------------------------------------------------

	const addField = useCallback( async ( type: FieldType ) => {
		if ( !form ) {
			return;
		}

		try {
			const request: StoreFieldRequest = {
				name: `${type}_${Date.now()}`,
				type,
				label: type.replace( '_', ' ' ).replace( /\b\w/g, ( c ) => c.toUpperCase() ),
				sort_order: currentFields.length,
				step_id: form.is_multi_step ? activeStepId : null,
			};

			const response = await post<{ data: FormField }>(
				`/${form.id}/fields`,
				request,
			);

			setFields( ( prev ) => [...prev, response.data] );
			setSelectedFieldId( response.data.id );
			setActivePanel( 'editor' );
		} catch ( err ) {
			if ( err instanceof ApiValidationError ) {
				setValidationErrors( err.errors );
			} else {
				setError( err instanceof Error ? err.message : 'Failed to add field.' );
			}
		}
	}, [post, form, currentFields.length, activeStepId] );

	const updateField = useCallback( async ( fieldId: number, data: UpdateFieldRequest ) => {
		if ( !form ) {
			return;
		}

		const field = fields.find( ( f ) => f.id === fieldId );

		if ( !field ) {
			return;
		}

		// Optimistic update
		setFields( ( prev ) => prev.map( ( f ) =>
			f.id === fieldId ? { ...f, ...data } as FormField : f,
		) );

		try {
			const response = await put<{ data: FormField }>(
				`/${form.id}/fields/${fieldId}`,
				data,
			);
			setFields( ( prev ) => prev.map( ( f ) =>
				f.id === fieldId ? response.data : f,
			) );
			setValidationErrors( {} );
		} catch ( err ) {
			// Revert optimistic update
			setFields( ( prev ) => prev.map( ( f ) =>
				f.id === fieldId ? field : f,
			) );

			if ( err instanceof ApiValidationError ) {
				setValidationErrors( err.errors );
			} else {
				setError( err instanceof Error ? err.message : 'Failed to update field.' );
			}
		}
	}, [put, form, fields] );

	const deleteField = useCallback( async ( fieldId: number ) => {
		if ( !form ) {
			return;
		}

		try {
			await del( `/${form.id}/fields/${fieldId}` );
			setFields( ( prev ) => prev.filter( ( f ) => f.id !== fieldId ) );

			if ( selectedFieldId === fieldId ) {
				setSelectedFieldId( null );
				setActivePanel( 'palette' );
			}
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to delete field.' );
		}
	}, [del, form, selectedFieldId] );

	const duplicateField = useCallback( async ( fieldId: number ) => {
		if ( !form ) {
			return;
		}

		const field = fields.find( ( f ) => f.id === fieldId );

		if ( !field ) {
			return;
		}

		try {
			const request: StoreFieldRequest = {
				name: `${field.name}_copy`,
				type: field.type,
				label: field.label ? `${field.label} (Copy)` : null,
				placeholder: field.placeholder,
				help_text: field.help_text,
				is_required: field.is_required,
				validation_rules: field.validation_rules,
				field_config: field.field_config,
				default_value: field.default_value,
				width: field.width,
				css_classes: field.css_classes,
				sort_order: field.sort_order + 1,
				step_id: field.step_id,
			};

			const response = await post<{ data: FormField }>(
				`/${form.id}/fields`,
				request,
			);

			setFields( ( prev ) => [...prev, response.data] );
			setSelectedFieldId( response.data.id );
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to duplicate field.' );
		}
	}, [post, form, fields] );

	// -----------------------------------------------------------------------
	// Field reordering (drag-and-drop)
	// -----------------------------------------------------------------------

	const handleDragStart = useCallback( ( index: number ) => {
		setDragState( { draggedIndex: index, overIndex: index } );
	}, [] );

	const handleDragOver = useCallback( ( index: number ) => {
		setDragState( ( prev ) => prev ? { ...prev, overIndex: index } : prev );
	}, [] );

	const handleDragEnd = useCallback( async () => {
		if ( !form || !dragState || dragState.draggedIndex === dragState.overIndex ) {
			setDragState( null );

			return;
		}

		const reordered = [...currentFields];
		const [moved] = reordered.splice( dragState.draggedIndex, 1 );
		reordered.splice( dragState.overIndex, 0, moved );

		// Update sort_order locally
		const updatedFields = reordered.map( ( f, i ) => ( { ...f, sort_order: i } ) );
		setFields( ( prev ) => {
			const unchanged = prev.filter( ( f ) => !updatedFields.some( ( u ) => u.id === f.id ) );

			return [...unchanged, ...updatedFields];
		} );

		setDragState( null );

		// Persist to API
		try {
			const orderedUuids = updatedFields.map( ( f ) => f.uuid );
			await post( `/${form.id}/fields/reorder`, {
				ordered_uuids: orderedUuids,
				step_id: form.is_multi_step ? activeStepId : null,
			} );
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to reorder fields.' );
			await loadForm();
		}
	}, [post, form, dragState, currentFields, activeStepId, loadForm] );

	// Handle drop from palette
	const handleCanvasDrop = useCallback( ( e: React.DragEvent ) => {
		e.preventDefault();
		const fieldType = e.dataTransfer.getData( 'application/x-field-type' );

		if ( fieldType ) {
			addField( fieldType as FieldType );
		}
	}, [addField] );

	const handleCanvasDragOver = useCallback( ( e: React.DragEvent ) => {
		e.preventDefault();
		e.dataTransfer.dropEffect = 'copy';
	}, [] );

	// -----------------------------------------------------------------------
	// Step management
	// -----------------------------------------------------------------------

	const addStep = useCallback( async () => {
		if ( !form ) {
			return;
		}

		try {
			const request: StoreStepRequest = {
				title: `Step ${steps.length + 1}`,
				sort_order: steps.length,
			};

			const response = await post<{ data: FormStep }>(
				`/${form.id}/steps`,
				request,
			);

			setSteps( ( prev ) => [...prev, response.data] );
			setActiveStepId( response.data.id );
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to add step.' );
		}
	}, [post, form, steps.length] );

	const updateStep = useCallback( async ( stepId: number, data: Partial<FormStep> ) => {
		if ( !form ) {
			return;
		}

		// Optimistic update
		const previousStep = steps.find( ( s ) => s.id === stepId );
		setSteps( ( prev ) => prev.map( ( s ) =>
			s.id === stepId ? { ...s, ...data } as FormStep : s,
		) );

		try {
			await put<{ data: FormStep }>(
				`/${form.id}/steps/${stepId}`,
				data,
			);
		} catch ( err ) {
			// Rollback on failure
			if ( previousStep ) {
				setSteps( ( prev ) => prev.map( ( s ) =>
					s.id === stepId ? previousStep : s,
				) );
			}
			setError( err instanceof Error ? err.message : 'Failed to update step.' );
		}
	}, [put, form, steps] );

	const deleteStep = useCallback( async ( stepId: number ) => {
		if ( !form || steps.length <= 1 ) {
			return;
		}

		if ( !window.confirm( 'Are you sure you want to delete this step? Fields will be unassigned.' ) ) {
			return;
		}

		try {
			await del( `/${form.id}/steps/${stepId}` );
			setSteps( ( prev ) => prev.filter( ( s ) => s.id !== stepId ) );
			setFields( ( prev ) => prev.map( ( f ) =>
				f.step_id === stepId ? { ...f, step_id: null } : f,
			) );

			if ( activeStepId === stepId ) {
				const remaining = steps.filter( ( s ) => s.id !== stepId );
				setActiveStepId( remaining[0]?.id ?? null );
			}
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to delete step.' );
		}
	}, [del, form, steps, activeStepId] );

	const reorderSteps = useCallback( async ( stepId: number, direction: 'up' | 'down' ) => {
		if ( !form ) {
			return;
		}

		const sorted = [...steps].sort( ( a, b ) => a.sort_order - b.sort_order );
		const index = sorted.findIndex( ( s ) => s.id === stepId );

		if ( -1 === index ) {
			return;
		}

		const swapIndex = 'up' === direction ? index - 1 : index + 1;

		if ( swapIndex < 0 || swapIndex >= sorted.length ) {
			return;
		}

		// Swap sort_order values
		const reordered = [...sorted];
		[reordered[index], reordered[swapIndex]] = [reordered[swapIndex], reordered[index]];
		const updated = reordered.map( ( s, i ) => ( { ...s, sort_order: i } ) );
		setSteps( updated );

		try {
			await post( `/${form.id}/steps/reorder`, {
				ordered_ids: updated.map( ( s ) => s.id ),
			} );
		} catch ( err ) {
			// Rollback
			setSteps( sorted );
			setError( err instanceof Error ? err.message : 'Failed to reorder steps.' );
		}
	}, [form, steps, post] );

	const enableMultiStep = useCallback( async () => {
		if ( !form ) {
			return;
		}

		try {
			await put( `/${form.id}`, { is_multi_step: true } );
			const updatedForm = { ...form, is_multi_step: true };
			setForm( updatedForm );

			// Create first step if none exist
			if ( steps.length === 0 ) {
				const request = { title: 'Step 1', sort_order: 0 };
				const response = await post<{ data: FormStep }>( `/${form.id}/steps`, request );
				setSteps( ( prev ) => [...prev, response.data] );
				setActiveStepId( response.data.id );
			}
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to enable multi-step.' );
		}
	}, [form, put, post, steps.length] );

	const disableMultiStep = useCallback( async () => {
		if ( !form ) {
			return;
		}

		try {
			await put( `/${form.id}`, { is_multi_step: false } );
			setForm( ( prev ) => prev ? { ...prev, is_multi_step: false } : prev );
			setActiveStepId( null );
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to disable multi-step.' );
		}
	}, [put, form] );

	// -----------------------------------------------------------------------
	// Render
	// -----------------------------------------------------------------------

	if ( isLoading ) {
		return (
			<div className={`flex items-center justify-center gap-2 p-8 ${className ?? ''}`} role="status">
				<Loading size="md" />
				<span>Loading form builder...</span>
			</div>
		);
	}

	if ( error && !form ) {
		return (
			<div className={`p-8 ${className ?? ''}`} role="alert">
				<Alert color="error">
					<p>{error}</p>
					<div className="flex gap-2">
						<Button size="sm" color="outline" onClick={loadForm}>Retry</Button>
						{onBack && <Button size="sm" color="ghost" onClick={onBack}>Back to Forms</Button>}
					</div>
				</Alert>
			</div>
		);
	}

	if ( !form ) {
		return <div className={`p-8 text-center text-base-content/60 ${className ?? ''}`}>Form not found.</div>;
	}

	return (
		<div className={`flex flex-col h-full ${className ?? ''}`}>
			{/* Top bar */}
			<div className="flex items-center justify-between bg-base-200 px-4 py-3 border-b border-base-300">
				<div className="flex items-center gap-3">
					{onBack && (
						<Button size="sm" color="ghost" onClick={onBack}>
							&larr; Back
						</Button>
					)}
					<h1 className="text-lg font-bold">{form.name}</h1>
					<Badge color={form.is_active ? 'success' : 'warning'} value={form.is_active ? 'Active' : 'Draft'} />
				</div>
				<div className="flex items-center gap-3">
					{isDirty && <span className="text-sm text-warning">Unsaved changes</span>}
					{isSaving && (
						<span className="flex items-center gap-1 text-sm text-info">
							<Loading size="xs" />
							Saving...
						</span>
					)}
					{lastSavedAt && !isSaving && !isDirty && (
						<span className="text-sm text-success">
							Saved {lastSavedAt.toLocaleTimeString()}
						</span>
					)}
					{saveError && <span className="text-sm text-error">{saveError}</span>}
					<Button
						size="sm"
						color="outline"
						onClick={() => setShowPreview( !showPreview )}
					>
						{showPreview ? 'Edit' : 'Preview'}
					</Button>
					<Button
						size="sm"
						color="primary"
						onClick={saveNow}
						disabled={isSaving}
					>
						{isSaving ? 'Saving...' : 'Save'}
					</Button>
				</div>
			</div>

			{/* Error banner */}
			{error && (
				<Alert color="error" className="mx-4 mt-3">
					<span>{error}</span>
					<Button size="sm" color="ghost" onClick={() => setError( null )}>Dismiss</Button>
				</Alert>
			)}

			{/* Validation errors */}
			{Object.keys( validationErrors ).length > 0 && (
				<Alert color="error" className="mx-4 mt-3">
					<ul className="list-disc list-inside">
						{Object.entries( validationErrors ).map( ( [key, messages] ) =>
							messages.map( ( msg, i ) => (
								<li key={`${key}-${i}`}>{msg}</li>
							) ),
						)}
					</ul>
				</Alert>
			)}

			{/* Main layout */}
			<div className="flex flex-1 overflow-hidden">
				{/* Left sidebar */}
				<div className="w-64 shrink-0 bg-base-200 border-r border-base-300 flex flex-col overflow-hidden">
					{/* Sidebar tabs */}
					<div className="tabs tabs-bordered px-2 pt-2">
						<button
							type="button"
							className={`tab ${activePanel === 'palette' ? 'tab-active' : ''}`}
							onClick={() => setActivePanel( 'palette' )}
						>
							Fields
						</button>
						<button
							type="button"
							className={`tab ${activePanel === 'settings' ? 'tab-active' : ''}`}
							onClick={() => setActivePanel( 'settings' )}
						>
							Settings
						</button>
					</div>

					{/* Field palette */}
					<div className="flex-1 overflow-y-auto p-3">
						{/* Editor lives in the right-hand pane, so the left sidebar keeps the palette visible whenever the user has not switched to the Settings tab. */}
						{activePanel !== 'settings' && (
							<FieldPalette onAddField={addField} />
						)}

						{/* Form settings */}
						{activePanel === 'settings' && (
							<div className="space-y-4">
								<h3 className="text-base font-semibold">Form Settings</h3>

								<Input
									id="form-name"
									label="Form Name"
									value={form.name}
									onChange={( e ) => {
										const name = e.target.value;
										const updates: UpdateFormRequest = { name };
										if ( !slugIsManual ) {
											updates.slug = slugify( name );
										}
										updateFormSetting( updates );
									}}
								/>

								<Input
									id="form-slug"
									label="Slug"
									value={form.slug ?? ''}
									onChange={( e ) => {
										if ( !slugIsManual ) {
											setSlugIsManual( true );
										}
										updateFormSetting( { slug: e.target.value } );
									}}
									hint={
										slugIsManual
											? 'URL-friendly identifier for the form.'
											: 'Derived from the form name — edit to override.'
									}
								/>

								<Textarea
									id="form-description"
									label="Description"
									rows={3}
									value={form.description ?? ''}
									onChange={( e ) => updateFormSetting( { description: e.target.value || null } )}
								/>

								<Input
									id="form-submit-text"
									label="Submit Button Text"
									value={form.submit_button_text}
									onChange={( e ) => updateFormSetting( { submit_button_text: e.target.value } )}
								/>

								<Textarea
									id="form-success-msg"
									label="Success Message"
									rows={2}
									value={form.success_message ?? ''}
									onChange={( e ) => updateFormSetting( { success_message: e.target.value || null } )}
								/>

								<Input
									id="form-redirect"
									label="Redirect URL"
									type="url"
									value={form.redirect_url ?? ''}
									onChange={( e ) => updateFormSetting( { redirect_url: e.target.value || null } )}
								/>

								<Checkbox
									label="Active (Published)"
									checked={form.is_active}
									onChange={( e ) => updateFormSetting( { is_active: e.target.checked } )}
								/>

								<Divider />
								<h4 className="text-sm font-semibold">Multi-Step</h4>

								<Checkbox
									label="Enable Multi-Step"
									checked={form.is_multi_step}
									onChange={( e ) => {
										if ( e.target.checked ) {
											enableMultiStep();
										} else {
											disableMultiStep();
										}
									}}
								/>

								{form.is_multi_step && (
									<>
										<Checkbox
											label="Show Progress Bar"
											checked={form.show_progress_bar}
											onChange={( e ) => updateFormSetting( { show_progress_bar: e.target.checked } )}
										/>
										<Checkbox
											label="Allow Step Navigation"
											checked={form.allow_step_navigation}
											onChange={( e ) => updateFormSetting( { allow_step_navigation: e.target.checked } )}
										/>
									</>
								)}
							</div>
						)}
					</div>
				</div>

				{/* Canvas */}
				<div className="flex-1 flex flex-col overflow-y-auto bg-base-100">
					{/* Step tabs (multi-step only) */}
					{form.is_multi_step && (
						<div className="flex items-center gap-1 border-b border-base-300 px-4 pt-2">
							<div className="tabs tabs-bordered">
								{sortedSteps.map( ( step, index ) => (
									<div key={step.id} className="flex items-center">
										<button
											type="button"
											className={`tab ${step.id === activeStepId ? 'tab-active' : ''}`}
											onClick={() => setActiveStepId( step.id )}
										>
											<span>{step.title || `Step ${index + 1}`}</span>
											<span className="ml-1 badge badge-sm badge-ghost">
												{fields.filter( ( f ) => f.step_id === step.id ).length}
											</span>
										</button>
										<div className="flex flex-col -ml-1">
											{index > 0 && (
												<button
													type="button"
													className="btn btn-ghost btn-xs px-0.5 py-0 h-auto min-h-0 text-xs"
													onClick={() => reorderSteps( step.id, 'up' )}
													title="Move step left"
													aria-label={`Move ${step.title || `Step ${index + 1}`} left`}
												>
													&#8249;
												</button>
											)}
											{index < sortedSteps.length - 1 && (
												<button
													type="button"
													className="btn btn-ghost btn-xs px-0.5 py-0 h-auto min-h-0 text-xs"
													onClick={() => reorderSteps( step.id, 'down' )}
													title="Move step right"
													aria-label={`Move ${step.title || `Step ${index + 1}`} right`}
												>
													&#8250;
												</button>
											)}
										</div>
									</div>
								) )}
								{fields.some( ( f ) => null === f.step_id ) && (
									<button
										type="button"
										className={`tab ${null === activeStepId ? 'tab-active' : ''}`}
										onClick={() => setActiveStepId( null )}
									>
										Unassigned
										<span className="ml-1 badge badge-sm badge-warning">
											{fields.filter( ( f ) => null === f.step_id ).length}
										</span>
									</button>
								)}
							</div>
							<Button
								size="sm"
								color="ghost"
								className="btn-circle"
								onClick={addStep}
								title="Add Step"
							>
								+
							</Button>
						</div>
					)}

					{/* Step settings */}
					{form.is_multi_step && activeStepId && (
						<div className="bg-base-200 px-4 py-3 border-b border-base-300">
							{sortedSteps
								.filter( ( s ) => s.id === activeStepId )
								.map( ( step ) => (
									<div key={step.id} className="flex items-center gap-2">
										<Input
											id={`step-title-${step.id}`}
											label="Step title"
											value={step.title ?? ''}
											onChange={( e ) => updateStep( step.id, { title: e.target.value } )}
											placeholder="Step title"
											className="flex-1"
										/>
										<Input
											id={`step-desc-${step.id}`}
											label="Step description"
											value={step.description ?? ''}
											onChange={( e ) => updateStep( step.id, { description: e.target.value || null } )}
											placeholder="Step description (optional)"
											className="flex-1"
										/>
										{steps.length > 1 && (
											<Button
												size="sm"
												color="error"
												onClick={() => deleteStep( step.id )}
												title="Delete step"
											>
												Delete Step
											</Button>
										)}
									</div>
								) )}
						</div>
					)}

					{/* Preview mode */}
					{showPreview && (
						<div className="flex-1 p-6 overflow-y-auto bg-base-100">
							<div className="max-w-2xl mx-auto space-y-4">
								<h2 className="text-xl font-bold">{form.name}</h2>
								{form.description && <p className="text-base-content/70">{form.description}</p>}
								{currentFields.map( ( field ) => (
									<div key={field.id} className="form-control">
										{field.label && 'hidden' !== field.type && (
											<label className="label">
												<span className="label-text">
													{field.label}
													{field.is_required && <span className="text-error ml-1">*</span>}
												</span>
											</label>
										)}
										{['text', 'email', 'phone', 'number', 'url', 'date', 'time'].includes( field.type ) && (
											<input type={'phone' === field.type ? 'tel' : field.type} className="input input-bordered" placeholder={field.placeholder ?? ''} disabled />
										)}
										{'textarea' === field.type && (
											<textarea className="textarea textarea-bordered" placeholder={field.placeholder ?? ''} disabled rows={3} />
										)}
										{'select' === field.type && (
											<select className="select select-bordered" disabled>
												<option>{field.placeholder ?? 'Select...'}</option>
											</select>
										)}
										{'checkbox' === field.type && (
											<label className="label cursor-pointer justify-start gap-2">
												<input type="checkbox" className="checkbox" disabled />
												<span className="label-text">{field.label}</span>
											</label>
										)}
										{'file' === field.type && (
											<input type="file" className="file-input file-input-bordered" disabled />
										)}
										{'heading' === field.type && (
											<h3 className="text-lg font-bold">{field.default_value ?? field.label}</h3>
										)}
										{'paragraph' === field.type && (
											<p className="text-base-content/70">{field.default_value ?? ''}</p>
										)}
										{'divider' === field.type && <div className="divider" />}
										{field.help_text && <label className="label"><span className="label-text-alt">{field.help_text}</span></label>}
									</div>
								) )}
								<button className="btn btn-primary" disabled>{form.submit_button_text}</button>
							</div>
						</div>
					)}

					{/* Field canvas */}
					{!showPreview && (
						<div
							className="flex-1 p-4 min-h-48"
							onDrop={handleCanvasDrop}
							onDragOver={handleCanvasDragOver}
						>
							{0 === currentFields.length && (
								<div className="flex items-center justify-center h-full border-2 border-dashed border-base-300 rounded-lg p-8">
									<p className="text-base-content/50">Drag fields here or click a field type to add it.</p>
								</div>
							)}

							<div className="space-y-2">
								{currentFields.map( ( field, index ) => (
									<div
										key={field.id}
										className={`card card-compact bg-base-100 shadow-sm border cursor-pointer transition-all ${
											selectedFieldId === field.id ? 'ring-2 ring-primary border-primary' : 'border-base-300 hover:border-base-content/30'
										} ${
											dragState?.overIndex === index && dragState.draggedIndex !== index
												? 'border-t-2 border-t-primary'
												: ''
										} ${
											'full' === field.width ? 'w-full' : 'half' === field.width ? 'w-1/2' : 'third' === field.width ? 'w-1/3' : 'two-thirds' === field.width ? 'w-2/3' : 'w-full'
										}`}
										tabIndex={0}
										role="button"
										aria-label={`Edit ${field.label || field.name} field`}
										draggable
										onDragStart={() => handleDragStart( index )}
										onDragOver={( e ) => {
											e.preventDefault();
											handleDragOver( index );
										}}
										onDragEnd={handleDragEnd}
										onClick={() => {
											setSelectedFieldId( field.id );
											setActivePanel( 'editor' );
										}}
										onKeyDown={( e ) => {
											if ( e.target !== e.currentTarget ) {
												return;
											}
											if ( 'Enter' === e.key || ' ' === e.key ) {
												e.preventDefault();
												setSelectedFieldId( field.id );
												setActivePanel( 'editor' );
											}
										}}
									>
										<div className="card-body flex-row items-center gap-3">
											<div className="cursor-grab text-base-content/40 hover:text-base-content/70" title="Drag to reorder">
												&#x2630;
											</div>
											<div className="flex flex-col flex-1 min-w-0">
												<Badge color="ghost" value={field.type} className="badge-sm" />
												<span className="font-medium truncate">
													{field.label || field.name}
													{field.is_required && <span className="text-error ml-0.5">*</span>}
												</span>
											</div>
											<div className="flex items-center gap-1">
												<Button
													size="xs"
													color="ghost"
													onClick={( e ) => {
														e.stopPropagation();
														duplicateField( field.id );
													}}
													title="Duplicate"
												>
													&#x2398;
												</Button>
												<Button
													size="xs"
													color="ghost"
													className="text-error"
													onClick={( e ) => {
														e.stopPropagation();

														if ( window.confirm( 'Delete this field?' ) ) {
															deleteField( field.id );
														}
													}}
													title="Delete"
												>
													&times;
												</Button>
											</div>
										</div>
									</div>
								) )}
							</div>
						</div>
					)}
				</div>

				{/* Right sidebar (field editor) */}
				{selectedField && (
					<div className="w-80 shrink-0 bg-base-200 border-l border-base-300 overflow-y-auto p-4">
						<FieldEditor
							field={selectedField}
							allFields={fields}
							onChange={updateField}
							onDelete={deleteField}
							onDuplicate={duplicateField}
							onClose={() => {
								setSelectedFieldId( null );
								setActivePanel( 'palette' );
							}}
						/>
					</div>
				)}
			</div>
		</div>
	);
}
