<!--
  SubmissionDetail component.

  Displays a single submission with field values, admin notes,
  file downloads, metadata, and navigation between submissions.

  @package    ArtisanPack_UI
  @subpackage Forms

  @since      1.1.0
-->
<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Alert, Badge, Button, Card, Loading, Textarea } from '@artisanpack-ui/vue';

import type {
	Form,
	FormSubmission,
	FormSubmissionValue,
	FormUpload,
	UpdateSubmissionRequest,
} from '../../../types/artisanpack-forms';
import { useApi } from '../../composables/useApi';
import type { UseApiOptions } from '../../composables/useApi';
import { t } from '../../../shared/i18n';

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

const { get, put, del, download } = useApi( {
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

/** Find the upload associated with a submission value. */
function findUpload( value: FormSubmissionValue ): FormUpload | undefined {
	return submission.value?.uploads?.find( ( u ) => u.id === value.upload_id );
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
							{{ value.field_label || value.field_name }}
						</dt>
						<dd>
							<!-- File value -->
							<template v-if="value.upload_id && submission.uploads">
								<template v-if="findUpload( value )">
									<div class="flex items-center gap-3">
										<img
											v-if="findUpload( value )!.is_image"
											:src="findUpload( value )!.url"
											:alt="findUpload( value )!.original_name"
											class="w-10 h-10 rounded object-cover"
										/>
										<span class="text-sm">
											{{ findUpload( value )!.original_name }} ({{ findUpload( value )!.human_size }})
										</span>
										<Button
											color="ghost"
											size="xs"
											@click="downloadFile( findUpload( value )! )"
										>
											{{ t( 'Download' ) }}
										</Button>
									</div>
								</template>
								<span v-else>{{ value.display_value || t( 'File not found' ) }}</span>
							</template>
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
