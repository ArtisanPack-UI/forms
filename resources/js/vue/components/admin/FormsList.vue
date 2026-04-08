<!--
  FormsList admin component.

  Displays a paginated list of forms with search, filtering, sorting,
  and actions for creating, editing, duplicating, toggling publish
  status, and deleting forms.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.1.0
-->
<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';

import { Alert, Badge, Button, Input, Loading, Pagination, Select } from '@artisanpack-ui/vue';

import type {
	Form,
	FormStatusFilter,
	PaginatedResponse,
	SortDirection,
	StoreFormRequest,
} from '../../../types/artisanpack-forms';
import { useApi } from '../../composables/useApi';
import type { UseApiOptions } from '../../composables/useApi';

type SortColumn = 'name' | 'created_at';

const props = withDefaults( defineProps<UseApiOptions & {
	/** Optional CSS class name. */
	className?: string;
	/** Whether to show clickable submission counts (requires view-submissions listener). */
	showSubmissions?: boolean;
}>(), {
	csrfToken: undefined,
	authorization: undefined,
	credentials: undefined,
	className: undefined,
	showSubmissions: false,
} );

const emit = defineEmits<{
	'edit-form': [form: Form];
	'create-form': [];
	'view-submissions': [form: Form];
}>();

const { get, post, put, del } = useApi( {
	baseUrl: props.baseUrl,
	csrfToken: props.csrfToken,
	authorization: props.authorization,
	credentials: props.credentials,
} );

// Data state
const forms = ref<PaginatedResponse<Form> | null>( null );
const isLoading = ref( true );
const error = ref<string | null>( null );

// Filters and sorting
const search = ref( '' );
const debouncedSearch = ref( '' );
const statusFilter = ref<FormStatusFilter>( 'all' );
const sortBy = ref<SortColumn>( 'created_at' );
const sortDirection = ref<SortDirection>( 'desc' );
const currentPage = ref( 1 );

// Debounce search
let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch( search, ( value ) => {
	if ( searchTimer ) {
		clearTimeout( searchTimer );
	}

	searchTimer = setTimeout( () => {
		debouncedSearch.value = value;
	}, 300 );
} );

onUnmounted( () => {
	if ( searchTimer ) {
		clearTimeout( searchTimer );
	}
} );

// Action states
const deletingId = ref<number | null>( null );
const duplicatingId = ref<number | null>( null );
const togglingId = ref<number | null>( null );

// Computed
const formsList = computed( () => forms.value?.data ?? [] );

const statusOptions = [
	{ id: 'all', name: 'All Status' },
	{ id: 'active', name: 'Active' },
	{ id: 'inactive', name: 'Inactive' },
];

// Fetch forms
async function fetchForms(): Promise<void> {
	isLoading.value = true;
	error.value = null;

	try {
		const params: Record<string, string> = {
			page: String( currentPage.value ),
			sort_by: sortBy.value,
			sort_direction: sortDirection.value,
		};

		if ( debouncedSearch.value ) {
			params.search = debouncedSearch.value;
		}

		if ( statusFilter.value !== 'all' ) {
			params.status = statusFilter.value;
		}

		const data = await get<PaginatedResponse<Form>>( '/', params );
		forms.value = data;
	} catch ( err ) {
		const message = err instanceof Error ? err.message : 'Failed to load forms.';
		error.value = message;
	} finally {
		isLoading.value = false;
	}
}

// Watchers that trigger re-fetch
watch(
	[debouncedSearch, statusFilter, sortBy, sortDirection, currentPage],
	() => {
		fetchForms();
	},
	{ immediate: true },
);

// Sort handler
function handleSort( column: SortColumn ): void {
	if ( sortBy.value === column ) {
		sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
	} else {
		sortBy.value = column;
		sortDirection.value = 'asc';
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

// Action handlers
async function handleDelete( form: Form ): Promise<void> {
	if ( !window.confirm( `Are you sure you want to delete "${form.name}"?` ) ) {
		return;
	}

	deletingId.value = form.id;

	try {
		await del( `/${form.slug}` );
		await fetchForms();
	} catch ( err ) {
		const message = err instanceof Error ? err.message : 'Failed to delete form.';
		error.value = message;
	} finally {
		deletingId.value = null;
	}
}

// TODO: Backend duplicate endpoint would copy fields/steps/notifications.
// Currently only copies form-level settings. A full duplicate API endpoint
// should be added to the package for complete form cloning.
async function handleDuplicate( form: Form ): Promise<void> {
	duplicatingId.value = form.id;

	try {
		const duplicateData: StoreFormRequest = {
			name: `${form.name} (Copy)`,
			description: form.description,
			submit_button_text: form.submit_button_text,
			success_message: form.success_message,
			redirect_url: form.redirect_url,
			is_active: false,
			is_multi_step: form.is_multi_step,
			show_progress_bar: form.show_progress_bar,
			allow_step_navigation: form.allow_step_navigation,
		};

		await post<{ data: Form }>( '/', duplicateData );
		await fetchForms();
	} catch ( err ) {
		const message = err instanceof Error ? err.message : 'Failed to duplicate form.';
		error.value = message;
	} finally {
		duplicatingId.value = null;
	}
}

async function handleTogglePublish( form: Form ): Promise<void> {
	togglingId.value = form.id;

	try {
		await put( `/${form.slug}`, { is_active: !form.is_active } );
		await fetchForms();
	} catch ( err ) {
		const message = err instanceof Error ? err.message : 'Failed to update form status.';
		error.value = message;
	} finally {
		togglingId.value = null;
	}
}

function onSearchInput( e: Event ): void {
	search.value = ( e.target as HTMLInputElement ).value;
	currentPage.value = 1;
}

function onStatusChange( e: Event ): void {
	statusFilter.value = ( e.target as HTMLSelectElement ).value as FormStatusFilter;
	currentPage.value = 1;
}

function formatDate( dateString: string ): string {
	return new Intl.DateTimeFormat( 'en-US', {
		year: 'numeric',
		month: 'short',
		day: 'numeric',
	} ).format( new Date( dateString ) );
}

function isRowProcessing( form: Form ): boolean {
	return duplicatingId.value === form.id
		|| togglingId.value === form.id
		|| deletingId.value === form.id;
}
</script>

<template>
	<div :class="['space-y-6', className]">
		<!-- Header -->
		<div class="flex items-center justify-between">
			<h1 class="text-2xl font-bold">Forms</h1>
			<Button
				color="primary"
				@click="emit( 'create-form' )"
			>
				Create Form
			</Button>
		</div>

		<!-- Filters -->
		<div class="flex flex-wrap items-center gap-3">
			<Input
				type="search"
				class="w-full max-w-xs"
				placeholder="Search forms..."
				:value="search"
				@input="onSearchInput"
			/>

			<Select
				:value="statusFilter"
				:options="statusOptions"
				option-value="id"
				option-label="name"
				@change="onStatusChange"
			/>
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
		<div
			v-if="isLoading"
			class="flex items-center justify-center gap-3 py-12"
			role="status"
		>
			<Loading size="md" />
			<span>Loading forms...</span>
		</div>

		<!-- Table -->
		<div
			v-if="!isLoading && formsList.length > 0"
			class="overflow-x-auto"
		>
			<table class="table table-zebra">
				<thead>
					<tr>
						<th :aria-sort="getAriaSortValue( 'name' )">
							<Button
								color="ghost"
								size="sm"
								@click="handleSort( 'name' )"
							>
								Name{{ sortIndicator( 'name' ) }}
							</Button>
						</th>
						<th>Status</th>
						<th>Submissions</th>
						<th :aria-sort="getAriaSortValue( 'created_at' )">
							<Button
								color="ghost"
								size="sm"
								@click="handleSort( 'created_at' )"
							>
								Created{{ sortIndicator( 'created_at' ) }}
							</Button>
						</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<tr
						v-for="form in formsList"
						:key="form.id"
					>
						<td>
							<div class="flex flex-col">
								<button
									type="button"
									class="link link-hover font-medium text-left"
									@click="emit( 'edit-form', form )"
								>
									{{ form.name }}
								</button>
								<span
									v-if="form.description"
									class="text-sm text-base-content/60"
								>
									{{ form.description }}
								</span>
							</div>
						</td>
						<td>
							<Badge
								:color="form.is_active ? 'success' : 'warning'"
								:value="form.is_active ? 'Active' : 'Inactive'"
							/>
						</td>
						<td>
							<Button
								v-if="showSubmissions"
								color="ghost"
								size="sm"
								@click="emit( 'view-submissions', form )"
							>
								{{ form.total_submissions_count ?? 0 }}
								<Badge
									v-if="( form.unread_submissions_count ?? 0 ) > 0"
									color="info"
									size="sm"
									class="ml-1"
									:value="`${form.unread_submissions_count} new`"
								/>
							</Button>
							<span v-else>
								{{ form.total_submissions_count ?? 0 }}
								<Badge
									v-if="( form.unread_submissions_count ?? 0 ) > 0"
									color="info"
									size="sm"
									class="ml-1"
									:value="`${form.unread_submissions_count} new`"
								/>
							</span>
						</td>
						<td>
							{{ formatDate( form.created_at ) }}
						</td>
						<td>
							<div class="flex items-center gap-1">
								<Button
									color="ghost"
									size="sm"
									title="Edit"
									:disabled="isRowProcessing( form )"
									@click="emit( 'edit-form', form )"
								>
									Edit
								</Button>
								<Button
									color="ghost"
									size="sm"
									title="Duplicate"
									:disabled="isRowProcessing( form )"
									@click="handleDuplicate( form )"
								>
									{{ duplicatingId === form.id ? 'Duplicating...' : 'Duplicate' }}
								</Button>
								<Button
									color="outline"
									size="sm"
									:title="form.is_active ? 'Unpublish' : 'Publish'"
									:disabled="isRowProcessing( form )"
									@click="handleTogglePublish( form )"
								>
									{{
										togglingId === form.id
											? 'Updating...'
											: form.is_active
												? 'Unpublish'
												: 'Publish'
									}}
								</Button>
								<Button
									color="error"
									size="sm"
									title="Delete"
									:disabled="isRowProcessing( form )"
									@click="handleDelete( form )"
								>
									{{ deletingId === form.id ? 'Deleting...' : 'Delete' }}
								</Button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Empty state -->
		<div
			v-if="!isLoading && formsList.length === 0 && !error"
			class="flex flex-col items-center justify-center gap-4 py-16 text-base-content/60"
		>
			<p class="text-lg">No forms found.</p>
			<Button
				color="primary"
				@click="emit( 'create-form' )"
			>
				Create your first form
			</Button>
		</div>

		<!-- Pagination -->
		<Pagination
			v-if="forms && forms.meta.last_page > 1"
			:current-page="forms.meta.current_page"
			:total-pages="forms.meta.last_page"
			@change="currentPage = $event"
		/>
	</div>
</template>
