<!--
  SubmissionDetail component.

  Displays a single submission with field values, admin notes,
  file downloads, metadata, and navigation between submissions. A consent
  value shows the text agreed to, and a composite value (name, address) its
  formatted lines (since 1.6.0). An administrative file field shows its
  stored files, each with a remove button that asks first, and a file chosen
  for it replaces them when the fields are saved (since 1.6.0). Mirrors the
  Livewire `submission-detail`.

  @package    ArtisanPack_UI
  @subpackage Forms

  @since      1.1.0
-->
<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Alert, Badge, Button, Card, Loading, Textarea } from '@artisanpack-ui/vue';

import type {
	ConsentValue,
	DisplayConfig,
	Form,
	FormField,
	FormSubmission,
	FormSubmissionValue,
	FormUpload,
	UpdateSubmissionRequest,
} from '../../../types/artisanpack-forms';
import { ApiValidationError, useApi } from '../../composables/useApi';
import type { UseApiOptions } from '../../composables/useApi';
import { getSubInputErrors } from '../../../shared/composite';
import { allowsMultipleFiles, getAdministrativeFormState, resolveOtherChoices } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import { validateFields } from '../../../shared/validation';
import FieldRenderer from '../fields/FieldRenderer.vue';

/** Display settings for the administrative field inputs. */
const ADMIN_FIELD_DISPLAY: DisplayConfig = {
	label_position: 'above',
	show_required_indicator: true,
	required_indicator: '*',
	error_display: 'below',
};

/** Check whether a URL uses a safe protocol (http or https). */
function isSafeUrl( url: string ): boolean {
	try {
		const parsed = new URL( url );
		return parsed.protocol === 'http:' || parsed.protocol === 'https:';
	} catch {
		return false;
	}
}

const props = withDefaults( defineProps<UseApiOptions & {
	/** The form this submission belongs to. */
	form: Form;
	/** The submission ID to display. */
	submissionId: number;
	/** Optional list of all submission IDs for prev/next navigation. */
	submissionIds?: number[];
	/** Optional CSS class name. */
	className?: string;
}>(), {
	csrfToken: undefined,
	authorization: undefined,
	credentials: undefined,
	submissionIds: undefined,
	className: undefined,
} );

const emit = defineEmits<{
	/** Emitted to navigate back to the submissions list. */
	'back': [];
	/** Emitted to navigate to next/previous submission. */
	'navigate': [submissionId: number];
}>();

const { get, post, put, del, download } = useApi( {
	baseUrl: props.baseUrl,
	csrfToken: props.csrfToken,
	authorization: props.authorization,
	credentials: props.credentials,
} );

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------

const submission  = ref<FormSubmission | null>( null );
const isLoading   = ref( true );
const error       = ref<string | null>( null );
const adminNotes  = ref( '' );
const isSavingNotes = ref( false );
const isUpdating  = ref( false );

/** Values of the form's administrative fields, keyed by field name. */
const adminValues = ref<Record<string, unknown>>( {} );
/** Text typed into an administrative field's "Other" choice. */
const adminOtherValues = ref<Record<string, string>>( {} );
const adminErrors = ref<Record<string, string[]>>( {} );
const isSavingAdmin = ref( false );
const adminSaved = ref( false );
/** Files chosen for the administrative file fields, keyed by field name (since 1.6.0). */
const adminFiles = ref<Record<string, File | File[]>>( {} );
/** Bumped to clear the administrative file inputs (since 1.6.0). */
const adminFileInputsKey = ref( 0 );
/** Announces administrative upload progress and removals to screen readers (since 1.6.0). */
const adminAnnouncement = ref( '' );

/** Seeds the administrative field inputs from a loaded submission. */
function seedAdministrativeFields( loaded: FormSubmission ): void {
	const state = getAdministrativeFormState( loaded.administrative_fields ?? [], loaded.values ?? [] );

	adminValues.value = state.values;
	adminOtherValues.value = state.otherValues;
	adminErrors.value = {};
	adminFiles.value = {};
	adminFileInputsKey.value++;
}

// ---------------------------------------------------------------------------
// Load submission
// ---------------------------------------------------------------------------

async function loadSubmission(): Promise<void> {
	isLoading.value  = true;
	error.value      = null;
	submission.value = null;
	adminNotes.value = '';

	const currentId = props.submissionId;

	try {
		const response = await get<{ data: FormSubmission }>(
			`/${props.form.slug}/submissions/${currentId}`,
		);

		if ( props.submissionId !== currentId ) {
			return;
		}

		submission.value = response.data;
		adminNotes.value = response.data.admin_notes ?? '';
		seedAdministrativeFields( response.data );

		// Auto-mark as read after successful fetch
		if ( !response.data.is_read ) {
			try {
				await put( `/${props.form.slug}/submissions/${currentId}`, { is_read: true } );

				if ( props.submissionId !== currentId ) {
					return;
				}

				if ( submission.value ) {
					submission.value = { ...submission.value, is_read: true };
				}
			} catch {
				// Silently fail mark-as-read to avoid blocking the view
			}
		}
	} catch ( err ) {
		if ( props.submissionId !== currentId ) {
			return;
		}

		error.value      = err instanceof Error ? err.message : t( 'Failed to load submission.' );
		submission.value = null;
		adminNotes.value = '';
	} finally {
		isLoading.value = false;
	}
}

watch( () => props.submissionId, () => {
	loadSubmission();
} );

onMounted( () => {
	loadSubmission();
} );

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------

async function updateSubmission( data: UpdateSubmissionRequest ): Promise<void> {
	try {
		const response = await put<{ data: FormSubmission }>(
			`/${props.form.slug}/submissions/${props.submissionId}`,
			data,
		);
		submission.value = response.data;
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : t( 'Failed to update submission.' );
	}
}

async function toggleStar(): Promise<void> {
	if ( !submission.value || isUpdating.value ) {
		return;
	}
	isUpdating.value = true;
	try {
		await updateSubmission( { is_starred: !submission.value.is_starred } );
	} finally {
		isUpdating.value = false;
	}
}

async function toggleSpam(): Promise<void> {
	if ( !submission.value || isUpdating.value ) {
		return;
	}
	isUpdating.value = true;
	try {
		await updateSubmission( { is_spam: !submission.value.is_spam } );
	} finally {
		isUpdating.value = false;
	}
}

async function toggleRead(): Promise<void> {
	if ( !submission.value || isUpdating.value ) {
		return;
	}
	isUpdating.value = true;
	try {
		await updateSubmission( { is_read: !submission.value.is_read } );
	} finally {
		isUpdating.value = false;
	}
}

async function saveNotes(): Promise<void> {
	isSavingNotes.value = true;

	try {
		await updateSubmission( { admin_notes: adminNotes.value || null } );
	} finally {
		isSavingNotes.value = false;
	}
}

async function saveAdministrativeFields(): Promise<void> {
	// A file field's files are sent apart from the values (since 1.6.0)
	const fields = ( submission.value?.administrative_fields ?? [] ).filter( ( field ) => field.type !== 'file' );
	const values = resolveOtherChoices( fields, adminValues.value, adminOtherValues.value );
	const clientErrors = validateFields( fields, values, {} );

	adminSaved.value = false;

	if ( Object.keys( clientErrors ).length > 0 ) {
		adminErrors.value = clientErrors;

		return;
	}

	isSavingAdmin.value = true;
	adminErrors.value = {};

	let uploadFailed = false;

	try {
		// Files go first in a multipart request, so a required file field is filled when the values are checked
		if ( Object.keys( adminFiles.value ).length > 0 ) {
			adminAnnouncement.value = t( 'Uploading files…' );

			let uploaded: { data: FormSubmission };

			try {
				uploaded = await post<{ data: FormSubmission }>( `/${props.form.slug}/submissions/${props.submissionId}`, buildAdminFilesBody( adminFiles.value ) );
			} catch ( uploadError ) {
				uploadFailed = true;

				throw uploadError;
			}

			// The files are saved now, whatever happens to the values, so show them and clear the inputs
			submission.value = uploaded.data;
			adminFiles.value = {};
			adminFileInputsKey.value++;
			adminAnnouncement.value = t( 'Upload finished.' );
		}

		const response = await put<{ data: FormSubmission }>(
			`/${props.form.slug}/submissions/${props.submissionId}`,
			{ admin_values: values },
		);
		submission.value = response.data;
		seedAdministrativeFields( response.data );
		adminSaved.value = true;
	} catch ( err ) {
		if ( err instanceof ApiValidationError ) {
			const serverErrors: Record<string, string[]> = {};

			for ( const [key, messages] of Object.entries( err.errors ) ) {
				const fileKey = key.match( /^admin_files\.([^.]+)/ );

				serverErrors[fileKey ? fileKey[1] : key.replace( /^admin_values\./, '' )] = messages;
			}

			adminErrors.value = serverErrors;
		} else {
			error.value = err instanceof Error ? err.message : t( 'Failed to update submission.' );
		}

		if ( uploadFailed ) {
			adminAnnouncement.value = t( 'The upload failed.' );
		}
	} finally {
		isSavingAdmin.value = false;
	}
}

function setAdminFile( name: string, file: File | File[] ): void {
	adminFiles.value = { ...adminFiles.value, [name]: file };
	adminSaved.value = false;
}

/** Deletes one file of an administrative file field, after asking (since 1.6.0). */
async function removeAdministrativeUpload( upload: FormUpload ): Promise<void> {
	if ( !window.confirm( t( 'Remove :name? The file will be deleted.', { name: upload.original_name } ) ) ) {
		return;
	}

	try {
		const response = await put<{ data: FormSubmission }>(
			`/${props.form.slug}/submissions/${props.submissionId}`,
			{ remove_admin_uploads: [upload.id] },
		);
		submission.value = response.data;
		adminSaved.value = false;
		adminAnnouncement.value = t( ':name removed.', { name: upload.original_name } );
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : t( 'Failed to update submission.' );
	}
}

/** Gets the stored files of an administrative file field (since 1.6.0). */
function getFieldUploads( field: FormField ): FormUpload[] {
	const value = submission.value?.values?.find( ( item ) => item.field_id === field.id && !item.is_redacted );

	return value ? findUploads( value ) : [];
}

/**
 * Builds the multipart body that replaces administrative file fields' files.
 * The update route is PUT, which PHP only parses as multipart through a POST
 * with `_method` (since 1.6.0).
 */
function buildAdminFilesBody( files: Record<string, File | File[]> ): FormData {
	const body = new FormData();

	body.append( '_method', 'PUT' );

	for ( const [name, file] of Object.entries( files ) ) {
		if ( Array.isArray( file ) ) {
			file.forEach( ( item ) => body.append( `admin_files[${name}][]`, item ) );
		} else {
			body.append( `admin_files[${name}]`, file );
		}
	}

	return body;
}

function setAdminValue( name: string, value: unknown ): void {
	adminValues.value = { ...adminValues.value, [name]: value };
	adminSaved.value = false;
}

function setAdminOtherValue( name: string, text: string ): void {
	adminOtherValues.value = { ...adminOtherValues.value, [name]: text };
	adminSaved.value = false;
}

async function deleteSubmission(): Promise<void> {
	if ( !window.confirm( t( 'Are you sure you want to delete this submission?' ) ) ) {
		return;
	}

	try {
		await del( `/${props.form.slug}/submissions/${props.submissionId}` );
		emit( 'back' );
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : t( 'Failed to delete submission.' );
	}
}

async function downloadFile( upload: FormUpload ): Promise<void> {
	try {
		await download(
			`/submissions/${props.submissionId}/uploads/${upload.id}/download`,
			upload.original_name,
		);
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : t( 'Failed to download file.' );
	}
}

// ---------------------------------------------------------------------------
// Navigation
// ---------------------------------------------------------------------------

const currentIndex = computed( () => props.submissionIds?.indexOf( props.submissionId ) ?? -1 );

const hasPrev = computed( () => currentIndex.value > 0 );

const hasNext = computed(
	() => currentIndex.value !== -1
		&& props.submissionIds
			? currentIndex.value < props.submissionIds.length - 1
			: false,
);

function goToPrev(): void {
	if ( hasPrev.value && props.submissionIds && currentIndex.value > 0 ) {
		emit( 'navigate', props.submissionIds[currentIndex.value - 1] );
	}
}

function goToNext(): void {
	if ( hasNext.value && props.submissionIds && currentIndex.value !== -1 ) {
		emit( 'navigate', props.submissionIds[currentIndex.value + 1] );
	}
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const isNotesDirty = computed(
	() => adminNotes.value !== ( submission.value?.admin_notes ?? '' ),
);

/**
 * Find every upload a submission value holds, in upload order: the
 * `upload_ids` of a multi-file value, or its single `upload_id`.
 */
function findUploads( value: FormSubmissionValue ): FormUpload[] {
	const ids = value.upload_ids && value.upload_ids.length > 0 ? value.upload_ids : [value.upload_id];

	return ids
		.map( ( id ) => submission.value?.uploads?.find( ( u ) => u.id === id ) )
		.filter( ( upload ): upload is FormUpload => upload !== undefined );
}

/**
 * Whether a value's `value_array` is a map rather than a list: a composite
 * value (name, address) or a consent record (since 1.6.0).
 */
function isKeyedValue( value: FormSubmissionValue ): boolean {
	const stored: unknown = value.value_array;

	return stored !== null && typeof stored === 'object' && !Array.isArray( stored );
}

/** A consent value's stored record, or an empty one (since 1.6.0). */
function getConsentValue( value: FormSubmissionValue ): Partial<ConsentValue> {
	return isKeyedValue( value ) ? ( value.value_array as unknown as Partial<ConsentValue> ) : {};
}

/** Whether a consent value records that the visitor consented (since 1.6.0). */
function isConsented( value: FormSubmissionValue ): boolean {
	return getConsentValue( value ).consented === '1';
}
</script>

<template>
	<!-- Loading state -->
	<div
		v-if="isLoading"
		:class="['flex items-center justify-center p-8', className]"
		role="status"
	>
		<Loading size="md" />
		<span class="ml-2">{{ t( 'Loading submission...' ) }}</span>
	</div>

	<!-- Error without submission -->
	<div
		v-else-if="error && !submission"
		:class="['space-y-4', className]"
		role="alert"
	>
		<Alert color="error">{{ error }}</Alert>
		<div class="flex gap-2">
			<Button color="outline" size="sm" @click="loadSubmission">{{ t( 'Retry' ) }}</Button>
			<Button color="ghost" size="sm" @click="emit( 'back' )">{{ t( 'Back' ) }}</Button>
		</div>
	</div>

	<!-- Submission not found -->
	<div
		v-else-if="!submission"
		:class="['text-center py-12 text-base-content/50', className]"
	>
		{{ t( 'Submission not found.' ) }}
	</div>

	<!-- Submission detail -->
	<div v-else :class="['space-y-6', className]">
		<!-- Header -->
		<div class="flex flex-wrap items-center justify-between gap-4">
			<div class="flex items-center gap-3">
				<Button color="ghost" size="sm" @click="emit( 'back' )">
					&larr; {{ t( 'Back' ) }}
				</Button>
				<h1 class="text-xl font-bold">{{ t( 'Submission :number', { number: submission.submission_number } ) }}</h1>
				<Badge
					v-if="submission.is_spam"
					color="error"
					:value="t( 'Spam' )"
				/>
			</div>
			<div>
				<div
					v-if="submissionIds && submissionIds.length > 1"
					class="join"
				>
					<Button
						size="sm"
						class="join-item"
						:disabled="!hasPrev"
						@click="goToPrev"
					>
						&larr; {{ t( 'Prev' ) }}
					</Button>
					<Button size="sm" class="join-item" disabled>
						{{ t( ':current of :total', { current: currentIndex === -1 ? '\u2014' : String( currentIndex + 1 ), total: submissionIds.length } ) }}
					</Button>
					<Button
						size="sm"
						class="join-item"
						:disabled="!hasNext"
						@click="goToNext"
					>
						{{ t( 'Next' ) }} &rarr;
					</Button>
				</div>
			</div>
		</div>

		<!-- Error -->
		<Alert
			v-if="error"
			color="error"
			dismissible
			@dismiss="error = null"
		>
			{{ error }}
		</Alert>

		<!-- Actions bar -->
		<div class="flex flex-wrap gap-2">
			<Button
				color="ghost"
				size="sm"
				:class="{ 'text-yellow-500': submission.is_starred }"
				:disabled="isUpdating"
				@click="toggleStar"
			>
				{{ submission.is_starred ? '\u2605 ' + t( 'Starred' ) : '\u2606 ' + t( 'Star' ) }}
			</Button>
			<Button
				color="ghost"
				size="sm"
				:disabled="isUpdating"
				@click="toggleRead"
			>
				{{ submission.is_read ? t( 'Mark Unread' ) : t( 'Mark Read' ) }}
			</Button>
			<Button
				color="ghost"
				size="sm"
				:disabled="isUpdating"
				@click="toggleSpam"
			>
				{{ submission.is_spam ? t( 'Not Spam' ) : t( 'Mark Spam' ) }}
			</Button>
			<Button
				color="error"
				size="sm"
				:disabled="isUpdating"
				@click="deleteSubmission"
			>
				{{ t( 'Delete' ) }}
			</Button>
		</div>

		<div class="space-y-6">
			<!-- Field values -->
			<Card :title="t( 'Submitted Data' )" bordered>
				<dl
					v-if="submission.values && submission.values.length > 0"
					class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-3"
				>
					<template v-for="value in submission.values" :key="value.id">
						<dt class="font-medium text-base-content/70">
							{{ value.field_admin_label || value.field_label || value.field_name }}
						</dt>
						<dd>
							<!-- Masked value: the API sends the masked text -->
							<span v-if="value.is_masked" class="inline-flex items-center gap-2">
								<span class="font-mono">{{ value.display_value }}</span>
								<Badge color="neutral" size="sm" :value="t( 'Masked' )" />
							</span>
							<!-- Redacted value -->
							<span v-else-if="value.is_redacted" class="italic text-base-content/60">{{ value.display_value }}</span>
							<!-- HTML the API escaped or sanitized with kses: rich text, a list's table, a ranking or a drawn signature (since 1.6.0) -->
							<div v-else-if="value.display_html" class="prose prose-sm max-w-none" v-html="value.display_html" />
							<!-- File value -->
							<template v-else-if="value.upload_id && submission.uploads">
								<ul v-if="findUploads( value ).length > 0" class="flex flex-col gap-2">
									<li v-for="upload in findUploads( value )" :key="upload.id" class="flex items-center gap-3">
										<img
											v-if="upload.is_image"
											:src="upload.url"
											:alt="upload.original_name"
											class="w-10 h-10 rounded object-cover"
										/>
										<span class="text-sm">
											{{ upload.original_name }} ({{ upload.human_size }})
										</span>
										<Button
											color="ghost"
											size="xs"
											@click="downloadFile( upload )"
										>
											{{ t( 'Download' ) }}
										</Button>
									</li>
								</ul>
								<span v-else>{{ value.display_value || t( 'File not found' ) }}</span>
							</template>
							<!-- Consent, with the text agreed to (since 1.6.0) -->
							<template v-else-if="value.field_type === 'consent'">
								<span>{{ value.display_value }}</span>
								<details v-if="isConsented( value )" class="mt-2">
									<summary class="link link-primary cursor-pointer">{{ t( 'View consent text' ) }}</summary>
									<div class="mt-2 space-y-2 rounded-box bg-base-200 p-3">
										<p v-if="getConsentValue( value ).text">{{ getConsentValue( value ).text }}</p>
										<!-- Sanitized with kses by the API (`FormSubmissionValueResource`). -->
										<div
											v-if="getConsentValue( value ).description"
											class="prose prose-sm max-w-none"
											v-html="getConsentValue( value ).description"
										/>
										<p v-if="!getConsentValue( value ).text" class="italic opacity-60">
											{{ t( 'The consent text was not stored.' ) }}
										</p>
										<p class="text-xs opacity-60">
											{{ t( 'Consented at :time', { time: getConsentValue( value ).consented_at ?? '—' } ) }}
											· {{ t( 'Revision :revision', { revision: String( getConsentValue( value ).revision ?? '' ).slice( 0, 12 ) } ) }}
										</p>
									</div>
								</details>
							</template>
							<!-- Composite value (name, address), formatted (since 1.6.0) -->
							<div v-else-if="isKeyedValue( value )" class="whitespace-pre-line">{{ value.display_value }}</div>
							<!-- Array value -->
							<ul v-else-if="value.value_array" class="list-disc list-inside">
								<li v-for="( v, i ) in ( value.display_values ?? value.value_array )" :key="i">{{ v }}</li>
							</ul>
							<!-- Simple value -->
							<span v-else>{{ value.display_value || '-' }}</span>
						</dd>
					</template>
				</dl>
				<p v-else class="text-base-content/50">{{ t( 'No submission data available.' ) }}</p>
			</Card>

			<!-- File uploads -->
			<Card
				v-if="submission.uploads && submission.uploads.length > 0"
				:title="t( 'File Uploads' )"
				bordered
			>
				<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
					<div
						v-for="upload in submission.uploads"
						:key="upload.id"
						class="card card-bordered bg-base-200"
					>
						<figure v-if="upload.is_image">
							<img
								:src="upload.url"
								:alt="upload.original_name"
								class="w-full h-40 object-cover"
							/>
						</figure>
						<div class="card-body p-3">
							<p class="font-medium text-sm truncate">{{ upload.original_name }}</p>
							<p class="text-xs text-base-content/60">{{ upload.human_size }}</p>
							<div class="card-actions justify-end mt-2">
								<Button
									color="outline"
									size="sm"
									@click="downloadFile( upload )"
								>
									{{ t( 'Download' ) }}
								</Button>
							</div>
						</div>
					</div>
				</div>
			</Card>

			<!-- Administrative fields -->
			<Card
				v-if="submission.administrative_fields && submission.administrative_fields.length > 0"
				:title="t( 'Administrative Fields' )"
				bordered
			>
				<p class="text-sm text-base-content/60 mb-4">{{ t( 'Internal fields that are not shown on the public form' ) }}</p>
				<form class="flex flex-wrap gap-4" @submit.prevent="saveAdministrativeFields">
					<template v-for="field in submission.administrative_fields" :key="field.id">
						<div v-if="field.type === 'file'" class="w-full space-y-2">
							<div v-if="getFieldUploads( field ).length > 0" class="space-y-1">
								<ul class="flex flex-col gap-2" :aria-label="t( 'Current files for :field', { field: field.label || field.name } )">
									<li
										v-for="upload in getFieldUploads( field )"
										:key="upload.id"
										class="flex items-center gap-3 rounded-box bg-base-200 p-2"
									>
										<span class="min-w-0 grow truncate text-sm">{{ upload.original_name }} ({{ upload.human_size }})</span>
										<Button color="ghost" size="xs" @click="downloadFile( upload )">
											{{ t( 'Download' ) }}
										</Button>
										<Button
											color="ghost"
											size="xs"
											:aria-label="t( 'Remove :name', { name: upload.original_name } )"
											@click="removeAdministrativeUpload( upload )"
										>
											<span aria-hidden="true">{{ t( 'Remove' ) }}</span>
										</Button>
									</li>
								</ul>
								<p class="text-xs text-base-content/60">
									{{ allowsMultipleFiles( field ) ? t( 'Choosing new files replaces the current ones.' ) : t( 'Choosing a new file replaces the current one.' ) }}
								</p>
							</div>
							<!-- A stored file is kept, so the input is only required while there is none -->
							<FieldRenderer
								:key="`${field.id}-${adminFileInputsKey}`"
								:field="getFieldUploads( field ).length > 0 ? { ...field, is_required: false } : field"
								:model-value="undefined"
								:error="adminErrors[field.name]?.[0]"
								:display-config="ADMIN_FIELD_DISPLAY"
								@file-change="setAdminFile( field.name, $event )"
							/>
						</div>
						<FieldRenderer
							v-else
							:field="field"
							:model-value="adminValues[field.name]"
							:error="adminErrors[field.name]?.[0]"
							:display-config="ADMIN_FIELD_DISPLAY"
							:other-value="adminOtherValues[field.name] ?? ''"
							:sub-errors="getSubInputErrors( adminErrors, field.name )"
							@update:model-value="setAdminValue( field.name, $event )"
							@update:other-value="setAdminOtherValue( field.name, $event )"
						/>
					</template>
					<div class="w-full flex items-center gap-3">
						<Button type="submit" color="primary" size="sm" :disabled="isSavingAdmin">
							{{ isSavingAdmin ? t( 'Saving...' ) : t( 'Save Administrative Fields' ) }}
						</Button>
						<span v-if="adminSaved" class="text-sm text-success" role="status">{{ t( 'Saved.' ) }}</span>
					</div>
					<div class="sr-only" aria-live="polite" aria-atomic="true">{{ adminAnnouncement }}</div>
				</form>
			</Card>

			<!-- Admin notes -->
			<Card :title="t( 'Admin Notes' )" bordered>
				<Textarea
					class="w-full"
					:rows="4"
					:model-value="adminNotes"
					:placeholder="t( 'Add notes about this submission...' )"
					@input="adminNotes = ( $event.target as HTMLTextAreaElement ).value"
				/>
				<div class="card-actions justify-end mt-2">
					<Button
						color="primary"
						size="sm"
						:disabled="isSavingNotes || !isNotesDirty"
						@click="saveNotes"
					>
						{{ isSavingNotes ? t( 'Saving...' ) : t( 'Save Notes' ) }}
					</Button>
				</div>
			</Card>

			<!-- Metadata -->
			<Card :title="t( 'Submission Details' )" bordered>
				<dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-3">
					<dt class="font-medium text-base-content/70">{{ t( 'Submitted' ) }}</dt>
					<dd>{{ new Date( submission.created_at ).toLocaleString() }}</dd>

					<template v-if="submission.page_url">
						<dt class="font-medium text-base-content/70">{{ t( 'Page URL' ) }}</dt>
						<dd>
							<a
								v-if="isSafeUrl( submission.page_url )"
								:href="submission.page_url"
								target="_blank"
								rel="noopener noreferrer"
								class="link link-primary"
							>
								{{ submission.page_url }}
							</a>
							<span v-else>{{ submission.page_url }}</span>
						</dd>
					</template>

					<template v-if="submission.referrer_url">
						<dt class="font-medium text-base-content/70">{{ t( 'Referrer' ) }}</dt>
						<dd>{{ submission.referrer_url }}</dd>
					</template>

					<template v-if="submission.ip_address">
						<dt class="font-medium text-base-content/70">{{ t( 'IP Address' ) }}</dt>
						<dd>{{ submission.ip_address }}</dd>
					</template>

					<template v-if="submission.user_agent">
						<dt class="font-medium text-base-content/70">{{ t( 'User Agent' ) }}</dt>
						<dd class="text-sm break-all">{{ submission.user_agent }}</dd>
					</template>
				</dl>
			</Card>
		</div>
	</div>
</template>
