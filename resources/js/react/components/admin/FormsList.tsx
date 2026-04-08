/**
 * FormsList admin component.
 *
 * Displays a paginated list of forms with search, filtering, sorting,
 * and actions for creating, editing, duplicating, toggling publish
 * status, and deleting forms.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import React, { useCallback, useEffect, useMemo, useState } from 'react';

import { Alert, Badge, Button, Input, Loading, Pagination, Select } from '@artisanpack-ui/react';

import type {
	Form,
	FormStatusFilter,
	PaginatedResponse,
	SortDirection,
	StoreFormRequest,
} from '../../../types/artisanpack-forms';
import { useApi } from '../../hooks/useApi';
import type { UseApiOptions } from '../../hooks/useApi';

/** Props for the FormsList component. */
export interface FormsListProps extends UseApiOptions {
	/** Callback when a form is selected for editing. */
	onEditForm?: ( form: Form ) => void;
	/** Callback when the user clicks to create a new form. */
	onCreateForm?: () => void;
	/** Callback when a form is selected for viewing submissions. */
	onViewSubmissions?: ( form: Form ) => void;
	/** Optional CSS class name. */
	className?: string;
}

type SortColumn = 'name' | 'created_at';

/**
 * Admin forms list component with search, filter, sort, and CRUD actions.
 *
 * @example
 * ```tsx
 * <FormsList
 *   baseUrl="/api/v1/forms"
 *   onEditForm={(form) => navigate(`/forms/${form.slug}/edit`)}
 *   onCreateForm={() => navigate('/forms/create')}
 *   onViewSubmissions={(form) => navigate(`/forms/${form.slug}/submissions`)}
 * />
 * ```
 */
export function FormsList( {
	baseUrl,
	csrfToken,
	authorization,
	credentials,
	onEditForm,
	onCreateForm,
	onViewSubmissions,
	className,
}: FormsListProps ): React.ReactElement {
	const { get, post, put, del } = useApi( { baseUrl, csrfToken, authorization, credentials } );

	const [forms, setForms] = useState<PaginatedResponse<Form> | null>( null );
	const [isLoading, setIsLoading] = useState( true );
	const [error, setError] = useState<string | null>( null );

	// Filters and sorting
	const [search, setSearch] = useState( '' );
	const [statusFilter, setStatusFilter] = useState<FormStatusFilter>( 'all' );
	const [sortBy, setSortBy] = useState<SortColumn>( 'created_at' );
	const [sortDirection, setSortDirection] = useState<SortDirection>( 'desc' );
	const [currentPage, setCurrentPage] = useState( 1 );

	// Action states
	const [deletingId, setDeletingId] = useState<number | null>( null );
	const [duplicatingId, setDuplicatingId] = useState<number | null>( null );
	const [togglingId, setTogglingId] = useState<number | null>( null );

	const fetchForms = useCallback( async () => {
		setIsLoading( true );
		setError( null );

		try {
			const params: Record<string, string> = {
				page: String( currentPage ),
				sort_by: sortBy,
				sort_direction: sortDirection,
			};

			if ( search ) {
				params.search = search;
			}

			if ( statusFilter !== 'all' ) {
				params.status = statusFilter;
			}

			const data = await get<PaginatedResponse<Form>>( '/', params );
			setForms( data );
		} catch ( err ) {
			const message = err instanceof Error ? err.message : 'Failed to load forms.';
			setError( message );
		} finally {
			setIsLoading( false );
		}
	}, [get, currentPage, search, sortBy, sortDirection, statusFilter] );

	useEffect( () => {
		fetchForms();
	}, [fetchForms] );

	const handleSort = useCallback( ( column: SortColumn ) => {
		setSortBy( ( prev ) => {
			if ( prev === column ) {
				setSortDirection( ( d ) => ( d === 'asc' ? 'desc' : 'asc' ) );

				return prev;
			}

			setSortDirection( 'asc' );

			return column;
		} );
	}, [] );

	const handleDelete = useCallback( async ( form: Form ) => {
		if ( !window.confirm( `Are you sure you want to delete "${form.name}"?` ) ) {
			return;
		}

		setDeletingId( form.id );

		try {
			await del( `/${form.slug}` );
			await fetchForms();
		} catch ( err ) {
			const message = err instanceof Error ? err.message : 'Failed to delete form.';
			setError( message );
		} finally {
			setDeletingId( null );
		}
	}, [del, fetchForms] );

	// TODO: Backend duplicate endpoint would copy fields/steps/notifications.
	// Currently only copies form-level settings. A full duplicate API endpoint
	// should be added to the package for complete form cloning.
	const handleDuplicate = useCallback( async ( form: Form ) => {
		setDuplicatingId( form.id );

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
			setError( message );
		} finally {
			setDuplicatingId( null );
		}
	}, [post, fetchForms] );

	const handleTogglePublish = useCallback( async ( form: Form ) => {
		setTogglingId( form.id );

		try {
			await put( `/${form.slug}`, { is_active: !form.is_active } );
			await fetchForms();
		} catch ( err ) {
			const message = err instanceof Error ? err.message : 'Failed to update form status.';
			setError( message );
		} finally {
			setTogglingId( null );
		}
	}, [put, fetchForms] );

	const sortIndicator = useCallback( ( column: SortColumn ): string => {
		if ( sortBy !== column ) {
			return '';
		}

		return sortDirection === 'asc' ? ' \u2191' : ' \u2193';
	}, [sortBy, sortDirection] );

	const formsList = useMemo( () => forms?.data ?? [], [forms] );

	return (
		<div className={`space-y-6 ${className ?? ''}`.trim()}>
			{/* Header */}
			<div className="flex items-center justify-between">
				<h1 className="text-2xl font-bold">Forms</h1>
				{onCreateForm && (
					<Button
						color="primary"
						onClick={onCreateForm}
					>
						Create Form
					</Button>
				)}
			</div>

			{/* Filters */}
			<div className="flex flex-wrap items-center gap-3">
				<Input
					type="search"
					className="w-full max-w-xs"
					placeholder="Search forms..."
					value={search}
					onChange={( e ) => {
						setSearch( e.target.value );
						setCurrentPage( 1 );
					}}
				/>

				<Select
					value={statusFilter}
					options={[
						{ id: 'all', name: 'All Status' },
						{ id: 'active', name: 'Active' },
						{ id: 'inactive', name: 'Inactive' },
					]}
					optionValue="id"
					optionLabel="name"
					onChange={( e ) => {
						setStatusFilter( e.target.value as FormStatusFilter );
						setCurrentPage( 1 );
					}}
				/>
			</div>

			{/* Error */}
			{error && (
				<Alert color="error" dismissible onDismiss={() => setError( null )}>
					{error}
				</Alert>
			)}

			{/* Loading */}
			{isLoading && (
				<div className="flex items-center justify-center gap-3 py-12" role="status">
					<Loading size="md" />
					<span>Loading forms...</span>
				</div>
			)}

			{/* Table */}
			{!isLoading && formsList.length > 0 && (
				<div className="overflow-x-auto">
					<table className="table table-zebra">
						<thead>
							<tr>
								<th>
									<Button
										color="ghost"
										size="sm"
										onClick={() => handleSort( 'name' )}
									>
										Name{sortIndicator( 'name' )}
									</Button>
								</th>
								<th>Status</th>
								<th>Submissions</th>
								<th>
									<Button
										color="ghost"
										size="sm"
										onClick={() => handleSort( 'created_at' )}
									>
										Created{sortIndicator( 'created_at' )}
									</Button>
								</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							{formsList.map( ( form ) => (
								<tr key={form.id}>
									<td>
										<div className="flex flex-col">
											<button
												type="button"
												className="link link-hover font-medium text-left"
												onClick={() => onEditForm?.( form )}
											>
												{form.name}
											</button>
											{form.description && (
												<span className="text-sm text-base-content/60">
													{form.description}
												</span>
											)}
										</div>
									</td>
									<td>
										<Badge
											color={form.is_active ? 'success' : 'warning'}
											value={form.is_active ? 'Active' : 'Inactive'}
										/>
									</td>
									<td>
										<Button
											color="ghost"
											size="sm"
											onClick={() => onViewSubmissions?.( form )}
										>
											{form.total_submissions_count ?? 0}
											{( form.unread_submissions_count ?? 0 ) > 0 && (
												<Badge
													color="info"
													size="sm"
													className="ml-1"
													value={`${form.unread_submissions_count} new`}
												/>
											)}
										</Button>
									</td>
									<td>
										{new Date( form.created_at ).toLocaleDateString()}
									</td>
									<td>
										<div className="flex items-center gap-1">
											<Button
												color="ghost"
												size="sm"
												onClick={() => onEditForm?.( form )}
												title="Edit"
											>
												Edit
											</Button>
											<Button
												color="ghost"
												size="sm"
												onClick={() => handleDuplicate( form )}
												disabled={duplicatingId === form.id}
												title="Duplicate"
											>
												{duplicatingId === form.id ? 'Duplicating...' : 'Duplicate'}
											</Button>
											<Button
												color="outline"
												size="sm"
												onClick={() => handleTogglePublish( form )}
												disabled={togglingId === form.id}
												title={form.is_active ? 'Unpublish' : 'Publish'}
											>
												{togglingId === form.id
													? 'Updating...'
													: form.is_active
														? 'Unpublish'
														: 'Publish'}
											</Button>
											<Button
												color="error"
												size="sm"
												onClick={() => handleDelete( form )}
												disabled={deletingId === form.id}
												title="Delete"
											>
												{deletingId === form.id ? 'Deleting...' : 'Delete'}
											</Button>
										</div>
									</td>
								</tr>
							) )}
						</tbody>
					</table>
				</div>
			)}

			{/* Empty state */}
			{!isLoading && formsList.length === 0 && !error && (
				<div className="flex flex-col items-center justify-center gap-4 py-16 text-base-content/60">
					<p className="text-lg">No forms found.</p>
					{onCreateForm && (
						<Button
							color="primary"
							onClick={onCreateForm}
						>
							Create your first form
						</Button>
					)}
				</div>
			)}

			{/* Pagination */}
			{forms && forms.meta.last_page > 1 && (
				<Pagination
					currentPage={forms.meta.current_page}
					totalPages={forms.meta.last_page}
					onChange={setCurrentPage}
				/>
			)}
		</div>
	);
}
