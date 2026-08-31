/**
 * SubmissionsList admin component.
 *
 * Displays a paginated list of form submissions with filtering,
 * sorting, bulk actions, and CSV export.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Alert, Badge, Button, Input, Loading, Pagination, Select } from '@artisanpack-ui/react';

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
import { useApi } from '../../hooks/useApi';
import type { UseApiOptions } from '../../hooks/useApi';

/** Props for the SubmissionsList component. */
export interface SubmissionsListProps extends UseApiOptions {
	/** The form to show submissions for. */
	form: Form;
	/** Callback when a submission is selected for viewing. */
	onViewSubmission?: ( submission: FormSubmission ) => void;
	/** Callback to navigate back. */
	onBack?: () => void;
	/** Optional CSS class name. */
	className?: string;
}

type SortColumn = 'submission_number' | 'created_at';

/**
 * Submissions list with filtering, sorting, bulk actions, and CSV export.
 *
 * @example
 * ```tsx
 * <SubmissionsList
 *   baseUrl="/api/v1/forms"
 *   form={form}
 *   onViewSubmission={(sub) => navigate(`/submissions/${sub.id}`)}
 *   onBack={() => navigate('/forms')}
 * />
 * ```
 */
export function SubmissionsList( {
	baseUrl,
	csrfToken,
	authorization,
	credentials,
	form,
	onViewSubmission,
	onBack,
	className,
}: SubmissionsListProps ): React.ReactElement {
	const { get, post, put, del, download } = useApi( { baseUrl, csrfToken, authorization, credentials } );

	const [submissions, setSubmissions] = useState<PaginatedResponse<FormSubmission> | null>( null );
	const [isLoading, setIsLoading] = useState( true );
	const [error, setError] = useState<string | null>( null );

	// Filters
	const [search, setSearch] = useState( '' );
	const [statusFilter, setStatusFilter] = useState<SubmissionStatusFilter>( 'all' );
	const [dateRange, setDateRange] = useState<SubmissionDateRange>( 'all' );
	const [sortBy, setSortBy] = useState<SortColumn>( 'created_at' );
	const [sortDirection, setSortDirection] = useState<SortDirection>( 'desc' );
	const [currentPage, setCurrentPage] = useState( 1 );

	// Selection
	const [selected, setSelected] = useState<Set<number>>( new Set() );

	// Action states
	const [isBulkProcessing, setIsBulkProcessing] = useState( false );
	const [isExporting, setIsExporting] = useState( false );
	const [pendingRowIds, setPendingRowIds] = useState<Set<number>>( new Set() );

	const addPendingRow = useCallback( ( id: number ) => {
		setPendingRowIds( ( prev ) => new Set( prev ).add( id ) );
	}, [] );

	const removePendingRow = useCallback( ( id: number ) => {
		setPendingRowIds( ( prev ) => {
			const next = new Set( prev );
			next.delete( id );
			return next;
		} );
	}, [] );

	const fetchSubmissions = useCallback( async () => {
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

			if ( statusFilter === 'unread' ) {
				params.is_read = '0';
			} else if ( statusFilter === 'read' ) {
				params.is_read = '1';
			} else if ( statusFilter === 'spam' ) {
				params.is_spam = '1';
			} else if ( statusFilter === 'starred' ) {
				params.is_starred = '1';
			}

			if ( dateRange !== 'all' ) {
				params.date_range = dateRange;
			}

			const data = await get<PaginatedResponse<FormSubmission>>(
				`/${form.id}/submissions`,
				params,
			);
			setSubmissions( data );
		} catch ( err ) {
			setSubmissions( null );
			setError( err instanceof Error ? err.message : 'Failed to load submissions.' );
		} finally {
			setIsLoading( false );
		}
	}, [get, form.id, currentPage, search, sortBy, sortDirection, statusFilter, dateRange] );

	useEffect( () => {
		fetchSubmissions();
	}, [fetchSubmissions] );

	const submissionsList = useMemo( () => submissions?.data ?? [], [submissions] );

	// Derive selectAll by checking every visible row is in the selected set
	const selectAll = submissionsList.length > 0 && submissionsList.every( ( s ) => selected.has( s.id ) );

	// -----------------------------------------------------------------------
	// Selection
	// -----------------------------------------------------------------------

	const handleSelectAll = useCallback( ( checked: boolean ) => {
		if ( checked ) {
			setSelected( new Set( submissionsList.map( ( s ) => s.id ) ) );
		} else {
			setSelected( new Set() );
		}
	}, [submissionsList] );

	const handleSelectOne = useCallback( ( id: number, checked: boolean ) => {
		setSelected( ( prev ) => {
			const next = new Set( prev );

			if ( checked ) {
				next.add( id );
			} else {
				next.delete( id );
			}

			return next;
		} );
	}, [] );

	// -----------------------------------------------------------------------
	// Individual actions
	// -----------------------------------------------------------------------

	const markAsRead = useCallback( async ( id: number ) => {
		addPendingRow( id );
		try {
			await put( `/${form.id}/submissions/${id}`, { is_read: true } );
			await fetchSubmissions();
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to update submission.' );
		} finally {
			removePendingRow( id );
		}
	}, [put, form.id, fetchSubmissions, addPendingRow, removePendingRow] );

	const markAsUnread = useCallback( async ( id: number ) => {
		addPendingRow( id );
		try {
			await put( `/${form.id}/submissions/${id}`, { is_read: false } );
			await fetchSubmissions();
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to update submission.' );
		} finally {
			removePendingRow( id );
		}
	}, [put, form.id, fetchSubmissions, addPendingRow, removePendingRow] );

	const toggleStar = useCallback( async ( submission: FormSubmission ) => {
		addPendingRow( submission.id );
		try {
			await put( `/${form.id}/submissions/${submission.id}`, {
				is_starred: !submission.is_starred,
			} );
			await fetchSubmissions();
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to update submission.' );
		} finally {
			removePendingRow( submission.id );
		}
	}, [put, form.id, fetchSubmissions, addPendingRow, removePendingRow] );

	const toggleSpam = useCallback( async ( submission: FormSubmission ) => {
		addPendingRow( submission.id );
		try {
			await put( `/${form.id}/submissions/${submission.id}`, {
				is_spam: !submission.is_spam,
			} );
			await fetchSubmissions();
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to update submission.' );
		} finally {
			removePendingRow( submission.id );
		}
	}, [put, form.id, fetchSubmissions, addPendingRow, removePendingRow] );

	const deleteSubmission = useCallback( async ( id: number ) => {
		if ( !window.confirm( 'Are you sure you want to delete this submission?' ) ) {
			return;
		}

		addPendingRow( id );
		try {
			await del( `/${form.id}/submissions/${id}` );
			await fetchSubmissions();
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to delete submission.' );
		} finally {
			removePendingRow( id );
		}
	}, [del, form.id, fetchSubmissions, addPendingRow, removePendingRow] );

	// -----------------------------------------------------------------------
	// Bulk actions
	// -----------------------------------------------------------------------

	const executeBulkAction = useCallback( async ( action: BulkSubmissionRequest['action'] ) => {
		if ( selected.size === 0 ) {
			return;
		}

		const confirmMessage = action === 'delete'
			? `Are you sure you want to delete ${selected.size} submission(s)?`
			: null;

		if ( confirmMessage && !window.confirm( confirmMessage ) ) {
			return;
		}

		setIsBulkProcessing( true );

		try {
			await post<BulkSubmissionResponse>(
				`/${form.id}/submissions/bulk`,
				{ action, ids: Array.from( selected ) },
			);
			setSelected( new Set() );
			await fetchSubmissions();
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Bulk action failed.' );
		} finally {
			setIsBulkProcessing( false );
		}
	}, [post, form.id, selected, fetchSubmissions] );

	// -----------------------------------------------------------------------
	// Export
	// -----------------------------------------------------------------------

	const handleExport = useCallback( async () => {
		setIsExporting( true );

		try {
			await download(
				`/${form.id}/submissions/export`,
				`${form.id}-submissions.csv`,
			);
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Export failed.' );
		} finally {
			setIsExporting( false );
		}
	}, [download, form.id] );

	// -----------------------------------------------------------------------
	// Sort
	// -----------------------------------------------------------------------

	const handleSort = useCallback( ( column: SortColumn ) => {
		if ( sortBy === column ) {
			setSortDirection( ( d ) => ( d === 'asc' ? 'desc' : 'asc' ) );
		} else {
			setSortBy( column );
			setSortDirection( 'desc' );
		}
	}, [sortBy] );

	const sortIndicator = useCallback( ( column: SortColumn ): string => {
		if ( sortBy !== column ) {
			return '';
		}

		return sortDirection === 'asc' ? ' \u2191' : ' \u2193';
	}, [sortBy, sortDirection] );

	const getAriaSortValue = useCallback( ( column: SortColumn ): 'ascending' | 'descending' | 'none' => {
		if ( sortBy !== column ) {
			return 'none';
		}

		return sortDirection === 'asc' ? 'ascending' : 'descending';
	}, [sortBy, sortDirection] );

	return (
		<div className={`space-y-4 ${className ?? ''}`}>
			{/* Header */}
			<div className="flex flex-wrap items-center justify-between gap-4">
				<div className="flex items-center gap-3">
					{onBack && (
						<Button color="ghost" size="sm" onClick={onBack}>&larr; Back</Button>
					)}
					<h1 className="text-xl font-bold">Submissions: {form.name}</h1>
				</div>
				<div>
					<Button
						color="outline"
						size="sm"
						onClick={handleExport}
						disabled={isExporting}
					>
						{isExporting ? 'Exporting...' : 'Export CSV'}
					</Button>
				</div>
			</div>

			{/* Filters */}
			<div className="flex flex-wrap items-center gap-3">
				<Input
					type="search"
					className="input-sm w-64"
					placeholder="Search submissions..."
					value={search}
					onChange={( e ) => {
						setSearch( e.target.value );
						setCurrentPage( 1 );
						setSelected( new Set() );
					}}
				/>

				{/* Status filter buttons */}
				<div className="tabs tabs-bordered" role="group" aria-label="Submission status filter">
					{( ['all', 'unread', 'read', 'starred', 'spam'] as SubmissionStatusFilter[] ).map( ( status ) => (
						<button
							key={status}
							type="button"
							aria-pressed={statusFilter === status}
							className={`tab ${statusFilter === status ? 'tab-active' : ''}`}
							onClick={() => {
									setStatusFilter( status );
									setCurrentPage( 1 );
									setSelected( new Set() );
								}}
						>
							{status.charAt( 0 ).toUpperCase() + status.slice( 1 )}
						</button>
					) )}
				</div>

				<Select
					className="select-sm"
					value={dateRange}
					onChange={( e ) => {
						setDateRange( e.target.value as SubmissionDateRange );
						setCurrentPage( 1 );
						setSelected( new Set() );
					}}
					options={[
						{ value: 'all', label: 'All Time' },
						{ value: 'today', label: 'Today' },
						{ value: 'week', label: 'This Week' },
						{ value: 'month', label: 'This Month' },
						{ value: 'year', label: 'This Year' },
					]}
					optionValue="value"
					optionLabel="label"
				/>
			</div>

			{/* Bulk actions */}
			{selected.size > 0 && (
				<div className="bg-base-200 p-3 rounded-lg flex flex-wrap gap-2 items-center">
					<span className="text-sm font-medium">{selected.size} selected</span>
					<Button
						color="ghost"
						size="sm"
						onClick={() => executeBulkAction( 'mark_read' )}
						disabled={isBulkProcessing}
					>
						Mark Read
					</Button>
					<Button
						color="ghost"
						size="sm"
						onClick={() => executeBulkAction( 'mark_unread' )}
						disabled={isBulkProcessing}
					>
						Mark Unread
					</Button>
					<Button
						color="ghost"
						size="sm"
						onClick={() => executeBulkAction( 'mark_spam' )}
						disabled={isBulkProcessing}
					>
						Mark Spam
					</Button>
					<Button
						color="ghost"
						size="sm"
						onClick={() => executeBulkAction( 'mark_not_spam' )}
						disabled={isBulkProcessing}
					>
						Not Spam
					</Button>
					<Button
						color="error"
						size="sm"
						onClick={() => executeBulkAction( 'delete' )}
						disabled={isBulkProcessing}
					>
						Delete
					</Button>
				</div>
			)}

			{/* Error */}
			{error && (
				<Alert color="error" dismissible onDismiss={() => setError( null )}>
					{error}
				</Alert>
			)}

			{/* Loading */}
			{isLoading && (
				<div className="flex items-center justify-center p-8" role="status">
					<Loading size="md" />
					<span className="ml-2">Loading submissions...</span>
				</div>
			)}

			{/* Table */}
			{!isLoading && submissionsList.length > 0 && (
				<div className="overflow-x-auto">
					<table className="table table-zebra">
						<thead>
							<tr>
								<th>
									<input
										type="checkbox"
										className="checkbox checkbox-sm"
										checked={selectAll}
										onChange={( e ) => handleSelectAll( e.target.checked )}
										aria-label="Select all submissions"
									/>
								</th>
								<th>Star</th>
								<th aria-sort={getAriaSortValue( 'submission_number' )}>
									<button type="button" className="link link-hover" onClick={() => handleSort( 'submission_number' )}>
										#{sortIndicator( 'submission_number' )}
									</button>
								</th>
								<th>Status</th>
								<th>Summary</th>
								<th aria-sort={getAriaSortValue( 'created_at' )}>
									<button type="button" className="link link-hover" onClick={() => handleSort( 'created_at' )}>
										Date{sortIndicator( 'created_at' )}
									</button>
								</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							{submissionsList.map( ( submission ) => (
								<tr
									key={submission.id}
									className={`${!submission.is_read ? 'font-semibold' : ''} ${submission.is_spam ? 'opacity-60' : ''}`}
								>
									<td>
										<input
											type="checkbox"
											className="checkbox checkbox-sm"
											checked={selected.has( submission.id )}
											onChange={( e ) => handleSelectOne( submission.id, e.target.checked )}
											aria-label={`Select submission ${submission.submission_number}`}
										/>
									</td>
									<td>
										<Button
											color="ghost"
											size="sm"
											className={`btn-circle ${submission.is_starred ? 'text-yellow-500' : 'text-base-content/30'}`}
											onClick={() => toggleStar( submission )}
											aria-label={submission.is_starred ? 'Unstar' : 'Star'}
											disabled={pendingRowIds.has( submission.id ) || isBulkProcessing}
										>
											{submission.is_starred ? '\u2605' : '\u2606'}
										</Button>
									</td>
									<td>
										{onViewSubmission ? (
											<button
												type="button"
												className="link link-hover link-primary"
												onClick={() => onViewSubmission( submission )}
											>
												{submission.submission_number}
											</button>
										) : (
											<span>{submission.submission_number}</span>
										)}
									</td>
									<td>
										{submission.is_spam && (
											<Badge color="error" size="sm" value="Spam" />
										)}
										{!submission.is_read && !submission.is_spam && (
											<Badge color="info" size="sm" value="New" />
										)}
									</td>
									<td>
										{submission.values && submission.values.length > 0 && (
											<span className="text-sm text-base-content/70">
												{submission.values.slice( 0, 2 ).map( ( v ) => v.display_value ).join( ' | ' )}
											</span>
										)}
									</td>
									<td className="text-sm">{new Date( submission.created_at ).toLocaleString()}</td>
									<td>
										<div className="flex gap-1">
											{onViewSubmission && (
												<Button
													color="ghost"
													size="xs"
													onClick={() => onViewSubmission( submission )}
													title="View"
													disabled={pendingRowIds.has( submission.id ) || isBulkProcessing}
												>
													View
												</Button>
											)}
											{submission.is_read ? (
												<Button
													color="ghost"
													size="xs"
													onClick={() => markAsUnread( submission.id )}
													title="Mark as unread"
													disabled={pendingRowIds.has( submission.id ) || isBulkProcessing}
												>
													Unread
												</Button>
											) : (
												<Button
													color="ghost"
													size="xs"
													onClick={() => markAsRead( submission.id )}
													title="Mark as read"
													disabled={pendingRowIds.has( submission.id ) || isBulkProcessing}
												>
													Read
												</Button>
											)}
											<Button
												color="ghost"
												size="xs"
												onClick={() => toggleSpam( submission )}
												title={submission.is_spam ? 'Not spam' : 'Mark as spam'}
												disabled={pendingRowIds.has( submission.id ) || isBulkProcessing}
											>
												{submission.is_spam ? 'Not Spam' : 'Spam'}
											</Button>
											<Button
												color="ghost"
												size="xs"
												className="text-error"
												onClick={() => deleteSubmission( submission.id )}
												title="Delete"
												disabled={pendingRowIds.has( submission.id ) || isBulkProcessing}
											>
												Delete
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
			{!isLoading && submissionsList.length === 0 && !error && (
				<div className="text-center py-12 text-base-content/50">
					<p>No submissions found.</p>
				</div>
			)}

			{/* Pagination */}
			{submissions && submissions.meta.last_page > 1 && (
				<div className="flex items-center justify-center gap-2">
					<Pagination
						currentPage={submissions.meta.current_page}
						totalPages={submissions.meta.last_page}
						onChange={( page ) => { setCurrentPage( page ); setSelected( new Set() ); }}
					/>
				</div>
			)}
		</div>
	);
}
