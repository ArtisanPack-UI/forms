<!--
  FormBuilder admin component.

  Main builder interface with drag-and-drop field ordering, field
  palette sidebar, field settings editor panel, multi-step
  configuration, form settings, live preview toggle, and auto-save
  with dirty state tracking.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.1.0
-->
<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import { Alert, Badge, Button, Checkbox, Divider, Input, Loading, Textarea } from '@artisanpack-ui/vue';

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
import { useApi } from '../../composables/useApi';
import type { UseApiOptions } from '../../composables/useApi';
import { ApiValidationError } from '../../composables/useApi';
import { useAutoSave } from '../../composables/useAutoSave';
import FieldEditor from './FieldEditor.vue';
import FieldPalette from './FieldPalette.vue';

/** Internal type for dragging state. */
interface DragState {
	draggedIndex: number;
	overIndex: number;
}

const props = withDefaults( defineProps<UseApiOptions & {
	/** The form slug to load and edit. */
	formSlug: string;
	/** Optional CSS class name. */
	className?: string;
}>(), {
	csrfToken: undefined,
	authorization: undefined,
	credentials: undefined,
	className: undefined,
} );

const emit = defineEmits<{
	back: [];
	'view-submissions': [];
}>();

const { get, post, put, del } = useApi( {
	baseUrl: props.baseUrl,
	csrfToken: props.csrfToken,
	authorization: props.authorization,
	credentials: props.credentials,
} );

// Core state
const form = ref<Form | null>( null );
const fields = ref<FormField[]>( [] );
const steps = ref<FormStep[]>( [] );
const isLoading = ref( true );
const error = ref<string | null>( null );
const validationErrors = ref<Record<string, string[]>>( {} );

// UI state
const selectedFieldId = ref<number | null>( null );
const activeStepId = ref<number | null>( null );
const activePanel = ref<'palette' | 'settings' | 'editor'>( 'palette' );
const showPreview = ref( false );

// Drag state
const dragState = ref<DragState | null>( null );

// Form settings state (local copy for auto-save)
const formSettings = ref<UpdateFormRequest>( {} );

// Reset form state when switching forms
watch( () => props.formSlug, () => {
	formSettings.value = {};
	form.value = null;
	fields.value = [];
	steps.value = [];
	selectedFieldId.value = null;
	activeStepId.value = null;
	activePanel.value = 'palette';
	showPreview.value = false;
	error.value = null;
	validationErrors.value = {};
} );

// Auto-save
const { isDirty, isSaving, lastSavedAt, saveError, markDirty, saveNow } = useAutoSave( {
	onSave: async () => {
		if ( !form.value ) {
			return;
		}

		const data = { ...formSettings.value };

		if ( Object.keys( data ).length > 0 ) {
			const savedKeys = Object.keys( data );
			await put( `/${form.value.slug}`, data );
			const prev = formSettings.value;
			const next = { ...prev };

			for ( const key of savedKeys ) {
				if ( prev[key as keyof UpdateFormRequest] === data[key as keyof UpdateFormRequest] ) {
					delete next[key as keyof UpdateFormRequest];
				}
			}

			formSettings.value = next;
		}
	},
	debounceMs: 2000,
} );

// Load form data
async function loadForm(): Promise<void> {
	isLoading.value = true;
	error.value = null;

	try {
		const response = await get<{ data: Form }>( `/${props.formSlug}` );
		const formData = response.data;
		form.value = formData;
		fields.value = formData.fields ?? [];
		steps.value = formData.steps ?? [];

		// Set active step to first step if multi-step
		if ( formData.is_multi_step && formData.steps && formData.steps.length > 0 ) {
			const sorted = [...formData.steps].sort( ( a, b ) => a.sort_order - b.sort_order );
			activeStepId.value = sorted[0].id;
		}
	} catch ( err ) {
		const message = err instanceof Error ? err.message : 'Failed to load form.';
		error.value = message;
	} finally {
		isLoading.value = false;
	}
}

// Watch formSlug to reload
watch( () => props.formSlug, () => {
	loadForm();
}, { immediate: true } );

// Update form settings
function updateFormSetting( updates: UpdateFormRequest ): void {
	formSettings.value = { ...formSettings.value, ...updates };
	form.value = form.value ? { ...form.value, ...updates } as Form : form.value;
	markDirty();
}

// Get fields for the active step (or all fields for single-step)
const currentFields = computed( () => {
	if ( !form.value?.is_multi_step ) {
		return [...fields.value].sort( ( a, b ) => a.sort_order - b.sort_order );
	}

	if ( null === activeStepId.value ) {
		// Show unassigned fields
		return fields.value
			.filter( ( f ) => null === f.step_id )
			.sort( ( a, b ) => a.sort_order - b.sort_order );
	}

	return fields.value
		.filter( ( f ) => f.step_id === activeStepId.value )
		.sort( ( a, b ) => a.sort_order - b.sort_order );
} );

const selectedField = computed(
	() => fields.value.find( ( f ) => f.id === selectedFieldId.value ) ?? null,
);

const sortedSteps = computed(
	() => [...steps.value].sort( ( a, b ) => a.sort_order - b.sort_order ),
);

// -----------------------------------------------------------------------
// Field CRUD
// -----------------------------------------------------------------------

async function addField( type: FieldType ): Promise<void> {
	if ( !form.value ) {
		return;
	}

	try {
		const request: StoreFieldRequest = {
			name: `${type}_${Date.now()}`,
			type,
			label: type.replace( '_', ' ' ).replace( /\b\w/g, ( c ) => c.toUpperCase() ),
			sort_order: currentFields.value.length,
			step_id: form.value.is_multi_step ? activeStepId.value : null,
		};

		const response = await post<{ data: FormField }>(
			`/${form.value.slug}/fields`,
			request,
		);

		fields.value = [...fields.value, response.data];
		selectedFieldId.value = response.data.id;
		activePanel.value = 'editor';
	} catch ( err ) {
		if ( err instanceof ApiValidationError ) {
			validationErrors.value = err.errors;
		} else {
			error.value = err instanceof Error ? err.message : 'Failed to add field.';
		}
	}
}

async function updateField( fieldId: number, data: UpdateFieldRequest ): Promise<void> {
	if ( !form.value ) {
		return;
	}

	const field = fields.value.find( ( f ) => f.id === fieldId );

	if ( !field ) {
		return;
	}

	// Optimistic update
	fields.value = fields.value.map( ( f ) =>
		f.id === fieldId ? { ...f, ...data } as FormField : f,
	);

	try {
		const response = await put<{ data: FormField }>(
			`/${form.value.slug}/fields/${fieldId}`,
			data,
		);
		fields.value = fields.value.map( ( f ) =>
			f.id === fieldId ? response.data : f,
		);
		validationErrors.value = {};
	} catch ( err ) {
		// Revert optimistic update
		fields.value = fields.value.map( ( f ) =>
			f.id === fieldId ? field : f,
		);

		if ( err instanceof ApiValidationError ) {
			validationErrors.value = err.errors;
		} else {
			error.value = err instanceof Error ? err.message : 'Failed to update field.';
		}
	}
}

async function deleteField( fieldId: number ): Promise<void> {
	if ( !form.value ) {
		return;
	}

	try {
		await del( `/${form.value.slug}/fields/${fieldId}` );
		fields.value = fields.value.filter( ( f ) => f.id !== fieldId );

		if ( selectedFieldId.value === fieldId ) {
			selectedFieldId.value = null;
			activePanel.value = 'palette';
		}
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : 'Failed to delete field.';
	}
}

async function duplicateField( fieldId: number ): Promise<void> {
	if ( !form.value ) {
		return;
	}

	const field = fields.value.find( ( f ) => f.id === fieldId );

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
			`/${form.value.slug}/fields`,
			request,
		);

		fields.value = [...fields.value, response.data];
		selectedFieldId.value = response.data.id;
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : 'Failed to duplicate field.';
	}
}

// -----------------------------------------------------------------------
// Field reordering (drag-and-drop)
// -----------------------------------------------------------------------

function handleDragStart( index: number ): void {
	dragState.value = { draggedIndex: index, overIndex: index };
}

function handleDragOver( index: number ): void {
	if ( dragState.value ) {
		dragState.value = { ...dragState.value, overIndex: index };
	}
}

async function handleDragEnd(): Promise<void> {
	if ( !form.value || !dragState.value || dragState.value.draggedIndex === dragState.value.overIndex ) {
		dragState.value = null;

		return;
	}

	const reordered = [...currentFields.value];
	const [moved] = reordered.splice( dragState.value.draggedIndex, 1 );
	reordered.splice( dragState.value.overIndex, 0, moved );

	// Update sort_order locally
	const updatedFields = reordered.map( ( f, i ) => ( { ...f, sort_order: i } ) );
	const unchanged = fields.value.filter( ( f ) => !updatedFields.some( ( u ) => u.id === f.id ) );
	fields.value = [...unchanged, ...updatedFields];

	dragState.value = null;

	// Persist to API
	try {
		const orderedUuids = updatedFields.map( ( f ) => f.uuid );
		await post( `/${form.value.slug}/fields/reorder`, {
			ordered_uuids: orderedUuids,
			step_id: form.value.is_multi_step ? activeStepId.value : null,
		} );
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : 'Failed to reorder fields.';
		await loadForm();
	}
}

// Handle drop from palette
function handleCanvasDrop( e: DragEvent ): void {
	e.preventDefault();
	const fieldType = e.dataTransfer?.getData( 'application/x-field-type' );

	if ( fieldType ) {
		addField( fieldType as FieldType );
	}
}

function handleCanvasDragOver( e: DragEvent ): void {
	e.preventDefault();

	if ( e.dataTransfer ) {
		e.dataTransfer.dropEffect = 'copy';
	}
}

// -----------------------------------------------------------------------
// Step management
// -----------------------------------------------------------------------

async function addStep(): Promise<void> {
	if ( !form.value ) {
		return;
	}

	try {
		const request: StoreStepRequest = {
			title: `Step ${steps.value.length + 1}`,
			sort_order: steps.value.length,
		};

		const response = await post<{ data: FormStep }>(
			`/${form.value.slug}/steps`,
			request,
		);

		steps.value = [...steps.value, response.data];
		activeStepId.value = response.data.id;
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : 'Failed to add step.';
	}
}

async function updateStep( stepId: number, data: Partial<FormStep> ): Promise<void> {
	if ( !form.value ) {
		return;
	}

	// Optimistic update
	const previousStep = steps.value.find( ( s ) => s.id === stepId );
	steps.value = steps.value.map( ( s ) =>
		s.id === stepId ? { ...s, ...data } as FormStep : s,
	);

	try {
		await put<{ data: FormStep }>(
			`/${form.value.slug}/steps/${stepId}`,
			data,
		);
	} catch ( err ) {
		// Rollback on failure
		if ( previousStep ) {
			steps.value = steps.value.map( ( s ) =>
				s.id === stepId ? previousStep : s,
			);
		}

		error.value = err instanceof Error ? err.message : 'Failed to update step.';
	}
}

async function deleteStep( stepId: number ): Promise<void> {
	if ( !form.value || steps.value.length <= 1 ) {
		return;
	}

	if ( !window.confirm( 'Are you sure you want to delete this step? Fields will be unassigned.' ) ) {
		return;
	}

	try {
		await del( `/${form.value.slug}/steps/${stepId}` );
		steps.value = steps.value.filter( ( s ) => s.id !== stepId );
		fields.value = fields.value.map( ( f ) =>
			f.step_id === stepId ? { ...f, step_id: null } : f,
		);

		if ( activeStepId.value === stepId ) {
			activeStepId.value = steps.value[0]?.id ?? null;
		}
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : 'Failed to delete step.';
	}
}

async function reorderSteps( stepId: number, direction: 'up' | 'down' ): Promise<void> {
	if ( !form.value ) {
		return;
	}

	const sorted = [...steps.value].sort( ( a, b ) => a.sort_order - b.sort_order );
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
	steps.value = updated;

	try {
		await post( `/${form.value.slug}/steps/reorder`, {
			ordered_ids: updated.map( ( s ) => s.id ),
		} );
	} catch ( err ) {
		// Rollback
		steps.value = sorted;
		error.value = err instanceof Error ? err.message : 'Failed to reorder steps.';
	}
}

async function enableMultiStep(): Promise<void> {
	if ( !form.value ) {
		return;
	}

	try {
		await put( `/${form.value.slug}`, { is_multi_step: true } );
		form.value = { ...form.value, is_multi_step: true };

		// Create first step if none exist
		if ( steps.value.length === 0 ) {
			const request = { title: 'Step 1', sort_order: 0 };
			const response = await post<{ data: FormStep }>( `/${form.value.slug}/steps`, request );
			steps.value = [...steps.value, response.data];
			activeStepId.value = response.data.id;
		}
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : 'Failed to enable multi-step.';
	}
}

async function disableMultiStep(): Promise<void> {
	if ( !form.value ) {
		return;
	}

	try {
		await put( `/${form.value.slug}`, { is_multi_step: false } );
		form.value = { ...form.value, is_multi_step: false };
		activeStepId.value = null;
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : 'Failed to disable multi-step.';
	}
}

// Helpers for field card classes
function fieldCardClass( field: FormField, index: number ): string {
	const classes = [
		'card card-compact bg-base-100 shadow-sm border cursor-pointer transition-all',
	];

	if ( selectedFieldId.value === field.id ) {
		classes.push( 'ring-2 ring-primary border-primary' );
	} else {
		classes.push( 'border-base-300 hover:border-base-content/30' );
	}

	if ( dragState.value?.overIndex === index && dragState.value.draggedIndex !== index ) {
		classes.push( 'border-t-2 border-t-primary' );
	}

	if ( 'full' === field.width ) {
		classes.push( 'w-full' );
	} else if ( 'half' === field.width ) {
		classes.push( 'w-1/2' );
	} else if ( 'third' === field.width ) {
		classes.push( 'w-1/3' );
	} else if ( 'two-thirds' === field.width ) {
		classes.push( 'w-2/3' );
	} else {
		classes.push( 'w-full' );
	}

	return classes.join( ' ' );
}

function selectField( fieldId: number ): void {
	selectedFieldId.value = fieldId;
	activePanel.value = 'editor';
}

function onFieldCardKeyDown( e: KeyboardEvent, fieldId: number ): void {
	if ( e.target !== e.currentTarget ) {
		return;
	}

	if ( 'Enter' === e.key || ' ' === e.key ) {
		e.preventDefault();
		selectField( fieldId );
	}
}

function confirmDeleteField( fieldId: number ): void {
	if ( window.confirm( 'Delete this field?' ) ) {
		deleteField( fieldId );
	}
}

function getPreviewInputType( fieldType: string ): string {
	return 'phone' === fieldType ? 'tel' : fieldType;
}
</script>

<template>
	<!-- Loading state -->
	<div
		v-if="isLoading"
		:class="['flex items-center justify-center gap-2 p-8', className]"
		role="status"
	>
		<Loading size="md" />
		<span>Loading form builder...</span>
	</div>

	<!-- Error state (no form loaded) -->
	<div
		v-else-if="error && !form"
		:class="['p-8', className]"
		role="alert"
	>
		<Alert color="error">
			<p>{{ error }}</p>
			<div class="flex gap-2">
				<Button size="sm" color="outline" @click="loadForm">Retry</Button>
				<Button size="sm" color="ghost" @click="emit( 'back' )">Back to Forms</Button>
			</div>
		</Alert>
	</div>

	<!-- Not found state -->
	<div
		v-else-if="!form"
		:class="['p-8 text-center text-base-content/60', className]"
	>
		Form not found.
	</div>

	<!-- Main builder -->
	<div
		v-else
		:class="['flex flex-col h-full', className]"
	>
		<!-- Top bar -->
		<div class="flex items-center justify-between bg-base-200 px-4 py-3 border-b border-base-300">
			<div class="flex items-center gap-3">
				<Button size="sm" color="ghost" @click="emit( 'back' )">
					&larr; Back
				</Button>
				<h1 class="text-lg font-bold">{{ form.name }}</h1>
				<Badge
					:color="form.is_active ? 'success' : 'warning'"
					:value="form.is_active ? 'Active' : 'Draft'"
				/>
			</div>
			<div class="flex items-center gap-3">
				<span v-if="isDirty" class="text-sm text-warning">Unsaved changes</span>
				<span
					v-if="isSaving"
					class="flex items-center gap-1 text-sm text-info"
				>
					<Loading size="xs" />
					Saving...
				</span>
				<span
					v-if="lastSavedAt && !isSaving && !isDirty"
					class="text-sm text-success"
				>
					Saved {{ lastSavedAt.toLocaleTimeString() }}
				</span>
				<span v-if="saveError" class="text-sm text-error">{{ saveError }}</span>
				<Button
					size="sm"
					color="outline"
					@click="showPreview = !showPreview"
				>
					{{ showPreview ? 'Edit' : 'Preview' }}
				</Button>
				<Button
					size="sm"
					color="primary"
					:disabled="isSaving"
					@click="saveNow"
				>
					{{ isSaving ? 'Saving...' : 'Save' }}
				</Button>
			</div>
		</div>

		<!-- Error banner -->
		<Alert
			v-if="error"
			color="error"
			class="mx-4 mt-3"
		>
			<span>{{ error }}</span>
			<Button size="sm" color="ghost" @click="error = null">Dismiss</Button>
		</Alert>

		<!-- Validation errors -->
		<Alert
			v-if="Object.keys( validationErrors ).length > 0"
			color="error"
			class="mx-4 mt-3"
		>
			<ul class="list-disc list-inside">
				<template
					v-for="( messages, key ) in validationErrors"
					:key="key"
				>
					<li
						v-for="( msg, i ) in messages"
						:key="`${key}-${i}`"
					>
						{{ msg }}
					</li>
				</template>
			</ul>
		</Alert>

		<!-- Main layout -->
		<div class="flex flex-1 overflow-hidden">
			<!-- Left sidebar -->
			<div class="w-64 shrink-0 bg-base-200 border-r border-base-300 flex flex-col overflow-hidden">
				<!-- Sidebar tabs -->
				<div class="tabs tabs-bordered px-2 pt-2">
					<button
						type="button"
						:class="['tab', activePanel === 'palette' ? 'tab-active' : '']"
						@click="activePanel = 'palette'"
					>
						Fields
					</button>
					<button
						type="button"
						:class="['tab', activePanel === 'settings' ? 'tab-active' : '']"
						@click="activePanel = 'settings'"
					>
						Settings
					</button>
				</div>

				<!-- Field palette -->
				<div class="flex-1 overflow-y-auto p-3">
					<FieldPalette
						v-if="activePanel === 'palette'"
						@add-field="addField"
					/>

					<!-- Form settings -->
					<div
						v-if="activePanel === 'settings'"
						class="space-y-4"
					>
						<h3 class="text-base font-semibold">Form Settings</h3>

						<Input
							id="form-name"
							label="Form Name"
							:value="form.name"
							@input="updateFormSetting( { name: ( $event.target as HTMLInputElement ).value } )"
						/>

						<Input
							id="form-slug"
							label="Slug"
							:value="form.slug ?? ''"
							hint="URL-friendly identifier for the form."
							@input="updateFormSetting( { slug: ( $event.target as HTMLInputElement ).value } )"
						/>

						<Textarea
							id="form-description"
							label="Description"
							:rows="3"
							:value="form.description ?? ''"
							@input="updateFormSetting( { description: ( $event.target as HTMLTextAreaElement ).value || null } )"
						/>

						<Input
							id="form-submit-text"
							label="Submit Button Text"
							:value="form.submit_button_text"
							@input="updateFormSetting( { submit_button_text: ( $event.target as HTMLInputElement ).value } )"
						/>

						<Textarea
							id="form-success-msg"
							label="Success Message"
							:rows="2"
							:value="form.success_message ?? ''"
							@input="updateFormSetting( { success_message: ( $event.target as HTMLTextAreaElement ).value || null } )"
						/>

						<Input
							id="form-redirect"
							label="Redirect URL"
							type="url"
							:value="form.redirect_url ?? ''"
							@input="updateFormSetting( { redirect_url: ( $event.target as HTMLInputElement ).value || null } )"
						/>

						<Checkbox
							label="Active (Published)"
							:checked="form.is_active"
							@change="updateFormSetting( { is_active: ( $event.target as HTMLInputElement ).checked } )"
						/>

						<Divider />
						<h4 class="text-sm font-semibold">Multi-Step</h4>

						<Checkbox
							label="Enable Multi-Step"
							:checked="form.is_multi_step"
							@change="( $event.target as HTMLInputElement ).checked ? enableMultiStep() : disableMultiStep()"
						/>

						<template v-if="form.is_multi_step">
							<Checkbox
								label="Show Progress Bar"
								:checked="form.show_progress_bar"
								@change="updateFormSetting( { show_progress_bar: ( $event.target as HTMLInputElement ).checked } )"
							/>
							<Checkbox
								label="Allow Step Navigation"
								:checked="form.allow_step_navigation"
								@change="updateFormSetting( { allow_step_navigation: ( $event.target as HTMLInputElement ).checked } )"
							/>
						</template>
					</div>
				</div>
			</div>

			<!-- Canvas -->
			<div class="flex-1 flex flex-col overflow-y-auto bg-base-100">
				<!-- Step tabs (multi-step only) -->
				<div
					v-if="form.is_multi_step"
					class="flex items-center gap-1 border-b border-base-300 px-4 pt-2"
				>
					<div class="tabs tabs-bordered">
						<div
							v-for="( step, index ) in sortedSteps"
							:key="step.id"
							class="flex items-center"
						>
							<button
								type="button"
								:class="['tab', step.id === activeStepId ? 'tab-active' : '']"
								@click="activeStepId = step.id"
							>
								<span>{{ step.title || `Step ${index + 1}` }}</span>
								<span class="ml-1 badge badge-sm badge-ghost">
									{{ fields.filter( ( f ) => f.step_id === step.id ).length }}
								</span>
							</button>
							<div class="flex flex-col -ml-1">
								<button
									v-if="index > 0"
									type="button"
									class="btn btn-ghost btn-xs px-0.5 py-0 h-auto min-h-0 text-xs"
									title="Move step left"
									:aria-label="`Move ${step.title || `Step ${index + 1}`} left`"
									@click="reorderSteps( step.id, 'up' )"
								>
									&#8249;
								</button>
								<button
									v-if="index < sortedSteps.length - 1"
									type="button"
									class="btn btn-ghost btn-xs px-0.5 py-0 h-auto min-h-0 text-xs"
									title="Move step right"
									:aria-label="`Move ${step.title || `Step ${index + 1}`} right`"
									@click="reorderSteps( step.id, 'down' )"
								>
									&#8250;
								</button>
							</div>
						</div>
						<button
							v-if="fields.some( ( f ) => null === f.step_id )"
							type="button"
							:class="['tab', null === activeStepId ? 'tab-active' : '']"
							@click="activeStepId = null"
						>
							Unassigned
							<span class="ml-1 badge badge-sm badge-warning">
								{{ fields.filter( ( f ) => null === f.step_id ).length }}
							</span>
						</button>
					</div>
					<Button
						size="sm"
						color="ghost"
						class="btn-circle"
						title="Add Step"
						@click="addStep"
					>
						+
					</Button>
				</div>

				<!-- Step settings -->
				<div
					v-if="form.is_multi_step && activeStepId"
					class="bg-base-200 px-4 py-3 border-b border-base-300"
				>
					<template
						v-for="step in sortedSteps.filter( ( s ) => s.id === activeStepId )"
						:key="step.id"
					>
						<div class="flex items-center gap-2">
							<Input
								:id="`step-title-${step.id}`"
								label="Step title"
								:value="step.title ?? ''"
								placeholder="Step title"
								class="flex-1"
								@input="updateStep( step.id, { title: ( $event.target as HTMLInputElement ).value } )"
							/>
							<Input
								:id="`step-desc-${step.id}`"
								label="Step description"
								:value="step.description ?? ''"
								placeholder="Step description (optional)"
								class="flex-1"
								@input="updateStep( step.id, { description: ( $event.target as HTMLInputElement ).value || null } )"
							/>
							<Button
								v-if="steps.length > 1"
								size="sm"
								color="error"
								title="Delete step"
								@click="deleteStep( step.id )"
							>
								Delete Step
							</Button>
						</div>
					</template>
				</div>

				<!-- Preview mode -->
				<div
					v-if="showPreview"
					class="flex-1 p-6 overflow-y-auto bg-base-100"
				>
					<div class="max-w-2xl mx-auto space-y-4">
						<h2 class="text-xl font-bold">{{ form.name }}</h2>
						<p
							v-if="form.description"
							class="text-base-content/70"
						>
							{{ form.description }}
						</p>
						<div
							v-for="field in currentFields"
							:key="field.id"
							class="form-control"
						>
							<label
								v-if="field.label && 'hidden' !== field.type"
								class="label"
							>
								<span class="label-text">
									{{ field.label }}
									<span v-if="field.is_required" class="text-error ml-1">*</span>
								</span>
							</label>
							<input
								v-if="['text', 'email', 'phone', 'number', 'url', 'date', 'time'].includes( field.type )"
								:type="getPreviewInputType( field.type )"
								class="input input-bordered"
								:placeholder="field.placeholder ?? ''"
								disabled
							/>
							<textarea
								v-if="'textarea' === field.type"
								class="textarea textarea-bordered"
								:placeholder="field.placeholder ?? ''"
								disabled
								:rows="3"
							/>
							<select
								v-if="'select' === field.type"
								class="select select-bordered"
								disabled
							>
								<option>{{ field.placeholder ?? 'Select...' }}</option>
							</select>
							<label
								v-if="'checkbox' === field.type"
								class="label cursor-pointer justify-start gap-2"
							>
								<input type="checkbox" class="checkbox" disabled />
								<span class="label-text">{{ field.label }}</span>
							</label>
							<input
								v-if="'file' === field.type"
								type="file"
								class="file-input file-input-bordered"
								disabled
							/>
							<h3
								v-if="'heading' === field.type"
								class="text-lg font-bold"
							>
								{{ field.default_value ?? field.label }}
							</h3>
							<p
								v-if="'paragraph' === field.type"
								class="text-base-content/70"
							>
								{{ field.default_value ?? '' }}
							</p>
							<div v-if="'divider' === field.type" class="divider" />
							<label v-if="field.help_text" class="label">
								<span class="label-text-alt">{{ field.help_text }}</span>
							</label>
						</div>
						<button class="btn btn-primary" disabled>{{ form.submit_button_text }}</button>
					</div>
				</div>

				<!-- Field canvas -->
				<div
					v-if="!showPreview"
					class="flex-1 p-4 min-h-48"
					@drop="handleCanvasDrop"
					@dragover="handleCanvasDragOver"
				>
					<div
						v-if="0 === currentFields.length"
						class="flex items-center justify-center h-full border-2 border-dashed border-base-300 rounded-lg p-8"
					>
						<p class="text-base-content/50">Drag fields here or click a field type to add it.</p>
					</div>

					<div class="space-y-2">
						<div
							v-for="( field, index ) in currentFields"
							:key="field.id"
							:class="fieldCardClass( field, index )"
							:tabindex="0"
							role="button"
							:aria-label="`Edit ${field.label || field.name} field`"
							draggable="true"
							@dragstart="handleDragStart( index )"
							@dragover.prevent="handleDragOver( index )"
							@dragend="handleDragEnd"
							@click="selectField( field.id )"
							@keydown="onFieldCardKeyDown( $event, field.id )"
						>
							<div class="card-body flex-row items-center gap-3">
								<div
									class="cursor-grab text-base-content/40 hover:text-base-content/70"
									title="Drag to reorder"
								>
									&#x2630;
								</div>
								<div class="flex flex-col flex-1 min-w-0">
									<Badge color="ghost" :value="field.type" class="badge-sm" />
									<span class="font-medium truncate">
										{{ field.label || field.name }}
										<span v-if="field.is_required" class="text-error ml-0.5">*</span>
									</span>
								</div>
								<div class="flex items-center gap-1">
									<Button
										size="xs"
										color="ghost"
										title="Duplicate"
										@click.stop="duplicateField( field.id )"
									>
										&#x2398;
									</Button>
									<Button
										size="xs"
										color="ghost"
										class="text-error"
										title="Delete"
										@click.stop="confirmDeleteField( field.id )"
									>
										&times;
									</Button>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Right sidebar (field editor) -->
			<div
				v-if="activePanel === 'editor' && selectedField"
				class="w-80 shrink-0 bg-base-200 border-l border-base-300 overflow-y-auto p-4"
			>
				<FieldEditor
					:field="selectedField"
					:all-fields="fields"
					@change="updateField"
					@delete="deleteField"
					@duplicate="duplicateField"
					@close="selectedFieldId = null; activePanel = 'palette'"
				/>
			</div>
		</div>
	</div>
</template>
