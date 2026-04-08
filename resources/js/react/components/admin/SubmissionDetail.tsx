/**
 * SubmissionDetail component.
 *
 * Displays a single submission with field values, admin notes,
 * file downloads, metadata, and navigation between submissions.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import React, { useCallback, useEffect, useState } from 'react';
import { Alert, Badge, Button, Card, Loading, Textarea } from '@artisanpack-ui/react';

import type {
	Form,
	FormSubmission,
	FormSubmissionValue,
	FormUpload,
	UpdateSubmissionRequest,
} from '../../../types/artisanpack-forms';
import { useApi } from '../../hooks/useApi';
import type { UseApiOptions } from '../../hooks/useApi';

/** Check whether a URL uses a safe protocol (http or https). */
function isSafeUrl( url: string ): boolean {
	try {
		const parsed = new URL( url );
		return parsed.protocol === 'http:' || parsed.protocol === 'https:';
	} catch {
		return false;
	}
}

/** Props for the SubmissionDetail component. */
export interface SubmissionDetailProps extends UseApiOptions {
	/** The form this submission belongs to. */
	form: Form;
	/** The submission ID to display. */
	submissionId: number;
	/** Callback to navigate back to the submissions list. */
	onBack?: () => void;
	/** Callback to navigate to next/previous submission. */
	onNavigate?: ( submissionId: number ) => void;
	/** Optional list of all submission IDs for prev/next navigation. */
	submissionIds?: number[];
	/** Optional CSS class name. */
	className?: string;
}

/**
 * Single submission detail view with field values and admin actions.
 *
 * @example
 * ```tsx
 * <SubmissionDetail
 *   baseUrl="/api/v1/forms"
 *   form={form}
 *   submissionId={123}
 *   onBack={() => navigate('/submissions')}
 * />
 * ```
 */
export function SubmissionDetail( {
	baseUrl,
	csrfToken,
	authorization,
	credentials,
	form,
	submissionId,
	onBack,
	onNavigate,
	submissionIds,
	className,
}: SubmissionDetailProps ): React.ReactElement {
	const { get, put, del, download } = useApi( { baseUrl, csrfToken, authorization, credentials } );

	const [submission, setSubmission] = useState<FormSubmission | null>( null );
	const [isLoading, setIsLoading] = useState( true );
	const [error, setError] = useState<string | null>( null );
	const [adminNotes, setAdminNotes] = useState( '' );
	const [isSavingNotes, setIsSavingNotes] = useState( false );
	const [isUpdating, setIsUpdating] = useState( false );

	// Load submission
	const loadSubmission = useCallback( async () => {
		setIsLoading( true );
		setError( null );
		setSubmission( null );
		setAdminNotes( '' );

		try {
			const response = await get<{ data: FormSubmission }>(
				`/${form.slug}/submissions/${submissionId}`,
			);
			setSubmission( response.data );
			setAdminNotes( response.data.admin_notes ?? '' );

			// Auto-mark as read after successful fetch
			if ( !response.data.is_read ) {
				try {
					await put( `/${form.slug}/submissions/${submissionId}`, { is_read: true } );
					setSubmission( ( prev ) => prev ? { ...prev, is_read: true } : prev );
				} catch {
					// Silently fail mark-as-read to avoid blocking the view
				}
			}
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to load submission.' );
			setSubmission( null );
			setAdminNotes( '' );
		} finally {
			setIsLoading( false );
		}
	}, [get, put, form.slug, submissionId] );

	useEffect( () => {
		loadSubmission();
	}, [loadSubmission] );

	// -----------------------------------------------------------------------
	// Actions
	// -----------------------------------------------------------------------

	const updateSubmission = useCallback( async ( data: UpdateSubmissionRequest ) => {
		try {
			const response = await put<{ data: FormSubmission }>(
				`/${form.slug}/submissions/${submissionId}`,
				data,
			);
			setSubmission( response.data );
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to update submission.' );
		}
	}, [put, form.slug, submissionId] );

	const toggleStar = useCallback( async () => {
		if ( !submission || isUpdating ) {
			return;
		}
		setIsUpdating( true );
		try {
			await updateSubmission( { is_starred: !submission.is_starred } );
		} finally {
			setIsUpdating( false );
		}
	}, [submission, updateSubmission, isUpdating] );

	const toggleSpam = useCallback( async () => {
		if ( !submission || isUpdating ) {
			return;
		}
		setIsUpdating( true );
		try {
			await updateSubmission( { is_spam: !submission.is_spam } );
		} finally {
			setIsUpdating( false );
		}
	}, [submission, updateSubmission, isUpdating] );

	const toggleRead = useCallback( async () => {
		if ( !submission || isUpdating ) {
			return;
		}
		setIsUpdating( true );
		try {
			await updateSubmission( { is_read: !submission.is_read } );
		} finally {
			setIsUpdating( false );
		}
	}, [submission, updateSubmission, isUpdating] );

	const saveNotes = useCallback( async () => {
		setIsSavingNotes( true );

		try {
			await updateSubmission( { admin_notes: adminNotes || null } );
		} finally {
			setIsSavingNotes( false );
		}
	}, [adminNotes, updateSubmission] );

	const deleteSubmission = useCallback( async () => {
		if ( !window.confirm( 'Are you sure you want to delete this submission?' ) ) {
			return;
		}

		try {
			await del( `/${form.slug}/submissions/${submissionId}` );
			onBack?.();
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to delete submission.' );
		}
	}, [del, form.slug, submissionId, onBack] );

	const downloadFile = useCallback( async ( upload: FormUpload ) => {
		try {
			await download(
				`/submissions/${submissionId}/uploads/${upload.id}/download`,
				upload.original_name,
			);
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to download file.' );
		}
	}, [download, submissionId] );

	// -----------------------------------------------------------------------
	// Navigation
	// -----------------------------------------------------------------------

	const currentIndex = submissionIds?.indexOf( submissionId ) ?? -1;
	const hasPrev = currentIndex > 0;
	const hasNext = currentIndex !== -1 && submissionIds ? currentIndex < submissionIds.length - 1 : false;

	const goToPrev = useCallback( () => {
		if ( hasPrev && submissionIds && currentIndex > 0 ) {
			onNavigate?.( submissionIds[currentIndex - 1] );
		}
	}, [hasPrev, submissionIds, currentIndex, onNavigate] );

	const goToNext = useCallback( () => {
		if ( hasNext && submissionIds && currentIndex !== -1 ) {
			onNavigate?.( submissionIds[currentIndex + 1] );
		}
	}, [hasNext, submissionIds, currentIndex, onNavigate] );

	// -----------------------------------------------------------------------
	// Render
	// -----------------------------------------------------------------------

	if ( isLoading ) {
		return (
			<div className={`flex items-center justify-center p-8 ${className ?? ''}`} role="status">
				<Loading size="md" />
				<span className="ml-2">Loading submission...</span>
			</div>
		);
	}

	if ( error && !submission ) {
		return (
			<div className={`space-y-4 ${className ?? ''}`} role="alert">
				<Alert color="error">{error}</Alert>
				<div className="flex gap-2">
					<Button color="outline" size="sm" onClick={loadSubmission}>Retry</Button>
					{onBack && <Button color="ghost" size="sm" onClick={onBack}>Back</Button>}
				</div>
			</div>
		);
	}

	if ( !submission ) {
		return (
			<div className={`text-center py-12 text-base-content/50 ${className ?? ''}`}>
				Submission not found.
			</div>
		);
	}

	return (
		<div className={`space-y-6 ${className ?? ''}`}>
			{/* Header */}
			<div className="flex flex-wrap items-center justify-between gap-4">
				<div className="flex items-center gap-3">
					{onBack && (
						<Button color="ghost" size="sm" onClick={onBack}>&larr; Back</Button>
					)}
					<h1 className="text-xl font-bold">Submission {submission.submission_number}</h1>
					{submission.is_spam && (
						<Badge color="error" value="Spam" />
					)}
				</div>
				<div>
					{onNavigate && submissionIds && submissionIds.length > 1 && (
						<div className="join">
							<Button size="sm" className="join-item" disabled={!hasPrev} onClick={goToPrev}>
								&larr; Prev
							</Button>
							<Button size="sm" className="join-item" disabled>
								{currentIndex === -1 ? '\u2014' : String( currentIndex + 1 )} of {submissionIds.length}
							</Button>
							<Button size="sm" className="join-item" disabled={!hasNext} onClick={goToNext}>
								Next &rarr;
							</Button>
						</div>
					)}
				</div>
			</div>

			{/* Error */}
			{error && (
				<Alert color="error" dismissible onDismiss={() => setError( null )}>
					{error}
				</Alert>
			)}

			{/* Actions bar */}
			<div className="flex flex-wrap gap-2">
				<Button
					color="ghost"
					size="sm"
					className={submission.is_starred ? 'text-yellow-500' : ''}
					onClick={toggleStar}
					disabled={isUpdating}
				>
					{submission.is_starred ? '\u2605 Starred' : '\u2606 Star'}
				</Button>
				<Button color="ghost" size="sm" onClick={toggleRead} disabled={isUpdating}>
					{submission.is_read ? 'Mark Unread' : 'Mark Read'}
				</Button>
				<Button color="ghost" size="sm" onClick={toggleSpam} disabled={isUpdating}>
					{submission.is_spam ? 'Not Spam' : 'Mark Spam'}
				</Button>
				<Button
					color="error"
					size="sm"
					onClick={deleteSubmission}
					disabled={isUpdating}
				>
					Delete
				</Button>
			</div>

			<div className="space-y-6">
				{/* Field values */}
				<Card title="Submitted Data" bordered>
					{submission.values && submission.values.length > 0 ? (
						<dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-3">
							{submission.values.map( ( value: FormSubmissionValue ) => (
								<React.Fragment key={value.id}>
									<dt className="font-medium text-base-content/70">{value.field_label || value.field_name}</dt>
									<dd>
										{value.upload_id && submission.uploads ? (
											<FileValue
												value={value}
												uploads={submission.uploads}
												onDownload={downloadFile}
											/>
										) : value.value_array ? (
											<ul className="list-disc list-inside">
												{value.value_array.map( ( v, i ) => (
													<li key={i}>{v}</li>
												) )}
											</ul>
										) : (
											<span>{value.display_value || '-'}</span>
										)}
									</dd>
								</React.Fragment>
							) )}
						</dl>
					) : (
						<p className="text-base-content/50">No submission data available.</p>
					)}
				</Card>

				{/* File uploads */}
				{submission.uploads && submission.uploads.length > 0 && (
					<Card title="File Uploads" bordered>
						<div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
							{submission.uploads.map( ( upload: FormUpload ) => (
								<div key={upload.id} className="card card-bordered bg-base-200">
									{upload.is_image && (
										<figure>
											<img
												src={upload.url}
												alt={upload.original_name}
												className="w-full h-40 object-cover"
											/>
										</figure>
									)}
									<div className="card-body p-3">
										<p className="font-medium text-sm truncate">{upload.original_name}</p>
										<p className="text-xs text-base-content/60">{upload.human_size}</p>
										<div className="card-actions justify-end mt-2">
											<Button
												color="outline"
												size="sm"
												onClick={() => downloadFile( upload )}
											>
												Download
											</Button>
										</div>
									</div>
								</div>
							) )}
						</div>
					</Card>
				)}

				{/* Admin notes */}
				<Card title="Admin Notes" bordered>
					<Textarea
						className="w-full"
						rows={4}
						value={adminNotes}
						onChange={( e ) => setAdminNotes( e.target.value )}
						placeholder="Add notes about this submission..."
					/>
					<div className="card-actions justify-end mt-2">
						<Button
							color="primary"
							size="sm"
							onClick={saveNotes}
							disabled={isSavingNotes || adminNotes === ( submission.admin_notes ?? '' )}
						>
							{isSavingNotes ? 'Saving...' : 'Save Notes'}
						</Button>
					</div>
				</Card>

				{/* Metadata */}
				<Card title="Submission Details" bordered>
					<dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-3">
						<dt className="font-medium text-base-content/70">Submitted</dt>
						<dd>{new Date( submission.created_at ).toLocaleString()}</dd>

						{submission.page_url && (
							<>
								<dt className="font-medium text-base-content/70">Page URL</dt>
								<dd>
									{isSafeUrl( submission.page_url ) ? (
										<a href={submission.page_url} target="_blank" rel="noopener noreferrer" className="link link-primary">
											{submission.page_url}
										</a>
									) : (
										<span>{submission.page_url}</span>
									)}
								</dd>
							</>
						)}
						{submission.referrer_url && (
							<>
								<dt className="font-medium text-base-content/70">Referrer</dt>
								<dd>{submission.referrer_url}</dd>
							</>
						)}
						{submission.ip_address && (
							<>
								<dt className="font-medium text-base-content/70">IP Address</dt>
								<dd>{submission.ip_address}</dd>
							</>
						)}
						{submission.user_agent && (
							<>
								<dt className="font-medium text-base-content/70">User Agent</dt>
								<dd className="text-sm break-all">{submission.user_agent}</dd>
							</>
						)}
					</dl>
				</Card>
			</div>
		</div>
	);
}

// ---------------------------------------------------------------------------
// FileValue sub-component
// ---------------------------------------------------------------------------

interface FileValueProps {
	value: FormSubmissionValue;
	uploads: FormUpload[];
	onDownload: ( upload: FormUpload ) => void;
}

function FileValue( { value, uploads, onDownload }: FileValueProps ): React.ReactElement {
	const upload = uploads.find( ( u ) => u.id === value.upload_id );

	if ( !upload ) {
		return <span>{value.display_value || 'File not found'}</span>;
	}

	return (
		<div className="flex items-center gap-3">
			{upload.is_image && (
				<img
					src={upload.url}
					alt={upload.original_name}
					className="w-10 h-10 rounded object-cover"
				/>
			)}
			<span className="text-sm">{upload.original_name} ({upload.human_size})</span>
			<Button color="ghost" size="xs" onClick={() => onDownload( upload )}>
				Download
			</Button>
		</div>
	);
}
