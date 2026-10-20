<!--
  SubmissionsList admin component.

  Displays a paginated list of form submissions with filtering,
  sorting, bulk actions, and CSV export.

  @package    ArtisanPack_UI
  @subpackage Forms

  @since      1.1.0
-->
<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Alert, Badge, Button, Input, Loading, Pagination, Select } from '@artisanpack-ui/vue';

import type {
	BulkSubmissionRequest,
	BulkSubmissionResponse,
	Form,
	FormSubmission,
	PaginatedResponse,
	SortDirection,
	SubmissionDateRange,
	SubmissionStatusFilter,
} from '../../../types/artisanpack-forms';
import { useApi } from '../../composables/useApi';
import type { UseApiOptions } from '../../composables/useApi';
import { t } from '../../../shared/i18n';

type SortColumn = 'submission_number' | 'created_at';

const props = withDefaults( defineProps<UseApiOptions & {
	/** The form to show submissions for. */
	form: Form;
	/** Optional CSS class name. */
	className?: string;
}>(), {
	csrfToken: undefined,
	authorization: undefined,
	credentials: undefined,
	className: undefined,
} );

const emit = defineEmits<{
	/** Emitted when a submission is selected for viewing. */
	'view-submission': [submission: FormSubmission];
	/** Emitted to navigate back. */
	'back': [];
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

const submissions = ref<PaginatedResponse<FormSubmission> | null>( null );
const isLoading   = ref( true );
const error       = ref<string | null>( null );

// Filters
const searchInput   = ref( '' );
const search        = ref( '' );
let searchTimer: ReturnType<typeof setTimeout> | null = null;
const statusFilter  = ref<SubmissionStatusFilter>( 'all' );
const dateRange     = ref<SubmissionDateRange>( 'all' );
const sortBy        = ref<SortColumn>( 'created_at' );
const sortDirection = ref<SortDirection>( 'desc' );
const currentPage   = ref( 1 );

// Selection
const selected = ref<Set<number>>( new Set() );

// Action states
const isBulkProcessing = ref( false );
const isExporting      = ref( false );
const pendingRowIds    = ref<Set<number>>( new Set() );

// ---------------------------------------------------------------------------
// Derived state
// ---------------------------------------------------------------------------

const submissionsList = computed( () => submissions.value?.data ?? [] );

const selectAll = computed(
	() => submissionsList.value.length > 0
		&& submissionsList.value.every( ( s ) => selected.value.has( s.id ) ),
);

const statusOptions: SubmissionStatusFilter[] = ['all', 'unread', 'read', 'starred', 'spam'];

const dateRangeOptions = computed( () => [
	{ value: 'all', label: t( 'All Time' ) },
	{ value: 'today', label: t( 'Today' ) },
	{ value: 'week', label: t( 'This Week' ) },
	{ value: 'month', label: t( 'This Month' ) },
	{ value: 'year', label: t( 'This Year' ) },
] );

// ---------------------------------------------------------------------------
// Pending row helpers
// ---------------------------------------------------------------------------

function addPendingRow( id: number ): void {
	const next = new Set( pendingRowIds.value );
	next.add( id );
	pendingRowIds.value = next;
}

function removePendingRow( id: number ): void {
	const next = new Set( pendingRowIds.value );
	next.delete( id );
	pendingRowIds.value = next;
}

// ---------------------------------------------------------------------------
// Fetch
// ---------------------------------------------------------------------------

async function fetchSubmissions(): Promise<void> {
	isLoading.value = true;
	error.value     = null;

	try {
		const params: Record<string, string> = {
			page: String( currentPage.value ),
			sort_by: sortBy.value,
			sort_direction: sortDirection.value,
		};

		if ( search.value ) {
			params.search = search.value;
		}

		if ( statusFilter.value === 'unread' ) {
			params.is_read = '0';
		} else if ( statusFilter.value === 'read' ) {
			params.is_read = '1';
		} else if ( statusFilter.value === 'spam' ) {
			params.is_spam = '1';
		} else if ( statusFilter.value === 'starred' ) {
			params.is_starred = '1';
		}

		if ( dateRange.value !== 'all' ) {
			params.date_range = dateRange.value;
		}

		const data = await get<PaginatedResponse<FormSubmission>>(
			`/${props.form.slug}/submissions`,
			params,
		);
		submissions.value = data;
	} catch ( err ) {
		submissions.value = null;
		error.value       = err instanceof Error ? err.message : t( 'Failed to load submissions.' );
	} finally {
		isLoading.value = false;
	}
}

// ---------------------------------------------------------------------------
// Watchers
// ---------------------------------------------------------------------------

watch(
	[search, statusFilter, dateRange, sortBy, sortDirection, currentPage],
	() => {
		fetchSubmissions();
	},
);

watch( currentPage, () => {
	selected.value = new Set();
} );

onMounted( () => {
	fetchSubmissions();
} );

// ---------------------------------------------------------------------------
// Selection
// ---------------------------------------------------------------------------

function handleSelectAll( checked: boolean ): void {
	if ( checked ) {
		selected.value = new Set( submissionsList.value.map( ( s ) => s.id ) );
	} else {
		selected.value = new Set();
	}
}

function handleSelectOne( id: number, checked: boolean ): void {
	const next = new Set( selected.value );

	if ( checked ) {
		next.add( id );
	} else {
		next.delete( id );
	}

	selected.value = next;
}

// ---------------------------------------------------------------------------
// Individual actions
// ---------------------------------------------------------------------------

async function markAsRead( id: number ): Promise<void> {
	addPendingRow( id );
	try {
		await put( `/${props.form.slug}/submissions/${id}`, { is_read: true } );
		await fetchSubmissions();
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : t( 'Failed to update submission.' );
	} finally {
		removePendingRow( id );
	}
}

async function markAsUnread( id: number ): Promise<void> {
	addPendingRow( id );
	try {
		await put( `/${props.form.slug}/submissions/${id}`, { is_read: false } );
		await fetchSubmissions();
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : t( 'Failed to update submission.' );
	} finally {
		removePendingRow( id );
	}
}

async function toggleStar( submission: FormSubmission ): Promise<void> {
	addPendingRow( submission.id );
	try {
		await put( `/${props.form.slug}/submissions/${submission.id}`, {
			is_starred: !submission.is_starred,
		} );
		await fetchSubmissions();
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : t( 'Failed to update submission.' );
	} finally {
		removePendingRow( submission.id );
	}
}

async function toggleSpam( submission: FormSubmission ): Promise<void> {
	addPendingRow( submission.id );
	try {
		await put( `/${props.form.slug}/submissions/${submission.id}`, {
			is_spam: !submission.is_spam,
		} );
		await fetchSubmissions();
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : t( 'Failed to update submission.' );
	} finally {
		removePendingRow( submission.id );
	}
}

async function deleteSubmission( id: number ): Promise<void> {
	if ( !window.confirm( t( 'Are you sure you want to delete this submission?' ) ) ) {
		return;
	}

	addPendingRow( id );
	try {
		await del( `/${props.form.slug}/submissions/${id}` );
		await fetchSubmissions();
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : t( 'Failed to delete submission.' );
	} finally {
		removePendingRow( id );
	}
}

// ---------------------------------------------------------------------------
// Bulk actions
// ---------------------------------------------------------------------------

async function executeBulkAction( action: BulkSubmissionRequest['action'] ): Promise<void> {
	if ( selected.value.size === 0 ) {
		return;
	}

	const confirmMessage = action === 'delete'
		? t( 'Are you sure you want to delete :count submission(s)?', { count: selected.value.size } )
		: null;

	if ( confirmMessage && !window.confirm( confirmMessage ) ) {
		return;
	}

	isBulkProcessing.value = true;

	try {
		await post<BulkSubmissionResponse>(
			`/${props.form.slug}/submissions/bulk`,
			{ action, ids: Array.from( selected.value ) },
		);
		selected.value = new Set();
		await fetchSubmissions();
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : t( 'Bulk action failed.' );
	} finally {
		isBulkProcessing.value = false;
	}
}

// ---------------------------------------------------------------------------
// Export
// ---------------------------------------------------------------------------

async function handleExport(): Promise<void> {
	isExporting.value = true;

	try {
		await download(
			`/${props.form.slug}/submissions/export`,
			`${props.form.slug}-submissions.csv`,
		);
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : t( 'Export failed.' );
	} finally {
		isExporting.value = false;
	}
}

// ---------------------------------------------------------------------------
// Sort
// ---------------------------------------------------------------------------

function handleSort( column: SortColumn ): void {
	if ( sortBy.value === column ) {
		sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
	} else {
		sortBy.value        = column;
		sortDirection.value = 'desc';
	}
}

function sortIndicator( column: SortColumn ): string {
	if ( sortBy.value !== column ) {
		return '';
	}

	return sortDirection.value === 'asc' ? ' \u2191' : ' \u2193';
}

function getAriaSortValue( column: SortColumn ): 'ascending' | 'descending' | 'none' {
	if ( sortBy.value !== column ) {
		return 'none';
	}

	return sortDirection.value === 'asc' ? 'ascending' : 'descending';
}

// ---------------------------------------------------------------------------
// Filter helpers
// ---------------------------------------------------------------------------

function handleSearchInput( e: Event ): void {
	searchInput.value = ( e.target as HTMLInputElement ).value;

	if ( searchTimer ) {
		clearTimeout( searchTimer );
	}

	searchTimer = setTimeout( () => {
		search.value      = searchInput.value;
		currentPage.value = 1;
		selected.value    = new Set();
	}, 300 );
}

function handleStatusChange( status: SubmissionStatusFilter ): void {
	statusFilter.value = status;
	currentPage.value  = 1;
	selected.value     = new Set();
}

function handleDateRangeChange( value: SubmissionDateRange ): void {
	dateRange.value   = value;
	currentPage.value = 1;
	selected.value    = new Set();
}

function handlePageChange( page: number ): void {
	currentPage.value = page;
	selected.value    = new Set();
}

function statusLabel( status: SubmissionStatusFilter ): string {
	switch ( status ) {
		case 'unread':
			return t( 'Unread' );
		case 'read':
			return t( 'Read' );
		case 'starred':
			return t( 'Starred' );
		case 'spam':
			return t( 'Spam' );
		default:
			return t( 'All' );
	}
}

function isRowDisabled( submissionId: number ): boolean {
	return pendingRowIds.value.has( submissionId ) || isBulkProcessing.value;
}
</script>

<template>
	<div :class="['space-y-4', className]">
		<!-- Header -->
		<div class="flex flex-wrap items-center justify-between gap-4">
			<div class="flex items-center gap-3">
				<Button
					color="ghost"
					size="sm"
					@click="emit( 'back' )"
				>
					&larr; {{ t( 'Back' ) }}
				</Button>
				<h1 class="text-xl font-bold">{{ t( 'Submissions: :form', { form: form.name } ) }}</h1>
			</div>
			<div>
				<Button
					color="outline"
					size="sm"
					:disabled="isExporting"
					@click="handleExport"
				>
					{{ isExporting ? t( 'Exporting...' ) : t( 'Export CSV' ) }}
				</Button>
			</div>
		</div>

		<!-- Filters -->
		<div class="flex flex-wrap items-center gap-3">
			<Input
				type="search"
				size="sm"
				class="w-64"
				:placeholder="t( 'Search submissions...' )"
				:model-value="searchInput"
				@input="handleSearchInput"
			/>

			<!-- Status filter buttons -->
			<div class="tabs tabs-bordered" role="group" :aria-label="t( 'Submission status filter' )">
				<button
					v-for="status in statusOptions"
					:key="status"
					type="button"
					:aria-pressed="statusFilter === status"
					:class="['tab', { 'tab-active': statusFilter === status }]"
					@click="handleStatusChange( status )"
				>
					{{ statusLabel( status ) }}
				</button>
			</div>

			<Select
				size="sm"
				:model-value="dateRange"
				:options="dateRangeOptions"
				option-value="value"
				option-label="label"
				@update:model-value="handleDateRangeChange"
			/>
		</div>

		<!-- Bulk actions -->
		<div
			v-if="selected.size > 0"
			class="bg-base-200 p-3 rounded-lg flex flex-wrap gap-2 items-center"
		>
			<span class="text-sm font-medium">{{ t( ':count selected', { count: selected.size } ) }}</span>
			<Button
				color="ghost"
				size="sm"
				:disabled="isBulkProcessing"
				@click="executeBulkAction( 'mark_read' )"
			>
				{{ t( 'Mark Read' ) }}
			</Button>
			<Button
				color="ghost"
				size="sm"
				:disabled="isBulkProcessing"
				@click="executeBulkAction( 'mark_unread' )"
			>
				{{ t( 'Mark Unread' ) }}
			</Button>
			<Button
				color="ghost"
				size="sm"
				:disabled="isBulkProcessing"
				@click="executeBulkAction( 'mark_spam' )"
			>
				{{ t( 'Mark Spam' ) }}
			</Button>
			<Button
				color="ghost"
				size="sm"
				:disabled="isBulkProcessing"
				@click="executeBulkAction( 'mark_not_spam' )"
			>
				{{ t( 'Not Spam' ) }}
			</Button>
			<Button
				color="error"
				size="sm"
				:disabled="isBulkProcessing"
				@click="executeBulkAction( 'delete' )"
			>
				{{ t( 'Delete' ) }}
			</Button>
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

		<!-- Loading -->
		<div v-if="isLoading" class="flex items-center justify-center p-8" role="status">
			<Loading size="md" />
			<span class="ml-2">{{ t( 'Loading submissions...' ) }}</span>
		</div>

		<!-- Table -->
		<div v-if="!isLoading && submissionsList.length > 0" class="overflow-x-auto">
			<table class="table table-zebra">
				<thead>
					<tr>
						<th>
							<input
								type="checkbox"
								class="checkbox checkbox-sm"
								:checked="selectAll"
								:aria-label="t( 'Select all submissions' )"
								@change="handleSelectAll( ( $event.target as HTMLInputElement ).checked )"
							/>
						</th>
						<th>{{ t( 'Star' ) }}</th>
						<th :aria-sort="getAriaSortValue( 'submission_number' )">
							<button
								type="button"
								class="link link-hover"
								@click="handleSort( 'submission_number' )"
							>
								#{{ sortIndicator( 'submission_number' ) }}
							</button>
						</th>
						<th>{{ t( 'Status' ) }}</th>
						<th>{{ t( 'Summary' ) }}</th>
						<th :aria-sort="getAriaSortValue( 'created_at' )">
							<button
								type="button"
								class="link link-hover"
								@click="handleSort( 'created_at' )"
							>
								{{ t( 'Date' ) }}{{ sortIndicator( 'created_at' ) }}
							</button>
						</th>
						<th>{{ t( 'Actions' ) }}</th>
					</tr>
				</thead>
				<tbody>
					<tr
						v-for="submission in submissionsList"
						:key="submission.id"
						:class="{
							'font-semibold': !submission.is_read,
							'opacity-60': submission.is_spam,
						}"
					>
						<td>
							<input
								type="checkbox"
								class="checkbox checkbox-sm"
								:checked="selected.has( submission.id )"
								:aria-label="t( 'Select submission :number', { number: submission.submission_number } )"
								@change="handleSelectOne( submission.id, ( $event.target as HTMLInputElement ).checked )"
							/>
						</td>
						<td>
							<Button
								color="ghost"
								size="sm"
								:class="['btn-circle', submission.is_starred ? 'text-yellow-500' : 'text-base-content/30']"
								:aria-label="submission.is_starred ? t( 'Unstar' ) : t( 'Star' )"
								:disabled="isRowDisabled( submission.id )"
								@click="toggleStar( submission )"
							>
								{{ submission.is_starred ? '\u2605' : '\u2606' }}
							</Button>
						</td>
						<td>
							<button
								type="button"
								class="link link-hover link-primary"
								@click="emit( 'view-submission', submission )"
							>
								{{ submission.submission_number }}
							</button>
						</td>
						<td>
							<Badge
								v-if="submission.is_spam"
								color="error"
								size="sm"
								:value="t( 'Spam' )"
							/>
							<Badge
								v-if="!submission.is_read && !submission.is_spam"
								color="info"
								size="sm"
								:value="t( 'New' )"
							/>
						</td>
						<td>
							<span
								v-if="submission.values && submission.values.length > 0"
								class="text-sm text-base-content/70"
							>
								{{ submission.values.slice( 0, 2 ).map( ( v ) => v.display_value ).join( ' | ' ) }}
							</span>
						</td>
						<td class="text-sm">
							{{ new Date( submission.created_at ).toLocaleString() }}
						</td>
						<td>
							<div class="flex gap-1">
								<Button
									color="ghost"
									size="xs"
									:title="t( 'View' )"
									:disabled="isRowDisabled( submission.id )"
									@click="emit( 'view-submission', submission )"
								>
									{{ t( 'View' ) }}
								</Button>
								<Button
									v-if="submission.is_read"
									color="ghost"
									size="xs"
									:title="t( 'Mark as unread' )"
									:disabled="isRowDisabled( submission.id )"
									@click="markAsUnread( submission.id )"
								>
									{{ t( 'Unread' ) }}
								</Button>
								<Button
									v-else
									color="ghost"
									size="xs"
									:title="t( 'Mark as read' )"
									:disabled="isRowDisabled( submission.id )"
									@click="markAsRead( submission.id )"
								>
									{{ t( 'Read' ) }}
								</Button>
								<Button
									color="ghost"
									size="xs"
									:title="submission.is_spam ? t( 'Not spam' ) : t( 'Mark as spam' )"
									:disabled="isRowDisabled( submission.id )"
									@click="toggleSpam( submission )"
								>
									{{ submission.is_spam ? t( 'Not Spam' ) : t( 'Spam' ) }}
								</Button>
								<Button
									color="ghost"
									size="xs"
									class="text-error"
									:title="t( 'Delete' )"
									:disabled="isRowDisabled( submission.id )"
									@click="deleteSubmission( submission.id )"
								>
									{{ t( 'Delete' ) }}
								</Button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Empty state -->
		<div
			v-if="!isLoading && submissionsList.length === 0 && !error"
			class="text-center py-12 text-base-content/50"
		>
			<p>{{ t( 'No submissions found.' ) }}</p>
		</div>

		<!-- Pagination -->
		<div
			v-if="submissions && submissions.meta.last_page > 1"
			class="flex items-center justify-center gap-2"
		>
			<Pagination
				:current-page="submissions.meta.current_page"
				:total-pages="submissions.meta.last_page"
				@change="handlePageChange"
			/>
		</div>
	</div>
</template>
