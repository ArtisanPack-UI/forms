/**
 * SubmissionDetail component.
 *
 * Displays a single submission with field values, admin notes,
 * file downloads, metadata, and navigation between submissions. Values of
 * fields masked in the admin arrive masked from the API; the form's
 * administrative fields are edited here. Since 1.6.0 an administrative file
 * field shows its stored files, each with a remove button that asks first,
 * and a file chosen for it replaces them when the fields are saved.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import React, { useCallback, useEffect, useState } from 'react';
import { Alert, Badge, Button, Card, Loading, Textarea } from '@artisanpack-ui/react';

import type {
	ConsentValue,
	DisplayConfig,
	Form,
	FormSubmission,
	FormField,
	FormSubmissionValue,
	FormUpload,
	UpdateSubmissionRequest,
} from '../../../types/artisanpack-forms';
import { getSubInputErrors } from '../../../shared/composite';
import { allowsMultipleFiles, getAdministrativeFormState, resolveOtherChoices } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import { validateFields } from '../../../shared/validation';
import { ApiValidationError, useApi } from '../../hooks/useApi';
import type { UseApiOptions } from '../../hooks/useApi';
import { FieldRenderer } from '../fields/FieldRenderer';

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
	const { get, post, put, del, download } = useApi( { baseUrl, csrfToken, authorization, credentials } );

	const [submission, setSubmission] = useState<FormSubmission | null>( null );
	const [isLoading, setIsLoading] = useState( true );
	const [error, setError] = useState<string | null>( null );
	const [adminNotes, setAdminNotes] = useState( '' );
	const [isSavingNotes, setIsSavingNotes] = useState( false );
	const [isUpdating, setIsUpdating] = useState( false );
	const [adminValues, setAdminValues] = useState<Record<string, unknown>>( {} );
	const [adminOtherValues, setAdminOtherValues] = useState<Record<string, string>>( {} );
	const [adminErrors, setAdminErrors] = useState<Record<string, string[]>>( {} );
	const [isSavingAdmin, setIsSavingAdmin] = useState( false );
	const [adminSaved, setAdminSaved] = useState( false );
	const [adminFiles, setAdminFiles] = useState<Record<string, File | File[]>>( {} );
	const [adminFileInputsKey, setAdminFileInputsKey] = useState( 0 );
	const [adminAnnouncement, setAdminAnnouncement] = useState( '' );

	/** Seeds the administrative field inputs from a loaded submission. */
	const seedAdministrativeFields = useCallback( ( loaded: FormSubmission ) => {
		const state = getAdministrativeFormState( loaded.administrative_fields ?? [], loaded.values ?? [] );

		setAdminValues( state.values );
		setAdminOtherValues( state.otherValues );
		setAdminErrors( {} );
		setAdminFiles( {} );
		setAdminFileInputsKey( ( key ) => key + 1 );
	}, [] );

	// Load submission
	const loadSubmission = useCallback( async () => {
		setIsLoading( true );
		setError( null );
		setSubmission( null );
		setAdminNotes( '' );

		try {
			const response = await get<{ data: FormSubmission }>(
				`/${encodeURIComponent( form.slug )}/submissions/${submissionId}`,
			);
			setSubmission( response.data );
			setAdminNotes( response.data.admin_notes ?? '' );
			seedAdministrativeFields( response.data );

			// Auto-mark as read after successful fetch
			if ( !response.data.is_read ) {
				try {
					await put( `/${encodeURIComponent( form.slug )}/submissions/${submissionId}`, { is_read: true } );
					setSubmission( ( prev ) => prev ? { ...prev, is_read: true } : prev );
				} catch {
					// Silently fail mark-as-read to avoid blocking the view
				}
			}
		} catch ( err ) {
			setError( err instanceof Error ? err.message : t( 'Failed to load submission.' ) );
			setSubmission( null );
			setAdminNotes( '' );
		} finally {
			setIsLoading( false );
		}
	}, [get, put, form.slug, submissionId, seedAdministrativeFields] );

	useEffect( () => {
		loadSubmission();
	}, [loadSubmission] );

	// -----------------------------------------------------------------------
	// Actions
	// -----------------------------------------------------------------------

	const updateSubmission = useCallback( async ( data: UpdateSubmissionRequest ) => {
		try {
			const response = await put<{ data: FormSubmission }>(
				`/${encodeURIComponent( form.slug )}/submissions/${submissionId}`,
				data,
			);
			setSubmission( response.data );
		} catch ( err ) {
			setError( err instanceof Error ? err.message : t( 'Failed to update submission.' ) );
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

	const saveAdministrativeFields = useCallback( async () => {
		// A file field's files are sent apart from the values (since 1.6.0)
		const fields = ( submission?.administrative_fields ?? [] ).filter( ( field ) => field.type !== 'file' );
		const values = resolveOtherChoices( fields, adminValues, adminOtherValues );
		const clientErrors = validateFields( fields, values, {} );

		setAdminSaved( false );

		if ( Object.keys( clientErrors ).length > 0 ) {
			setAdminErrors( clientErrors );

			return;
		}

		setIsSavingAdmin( true );
		setAdminErrors( {} );

		let uploadFailed = false;

		try {
			// Files go first in a multipart request, so a required file field is filled when the values are checked
			if ( Object.keys( adminFiles ).length > 0 ) {
				setAdminAnnouncement( t( 'Uploading files…' ) );

				let uploaded: { data: FormSubmission };

				try {
					uploaded = await post<{ data: FormSubmission }>( `/${encodeURIComponent( form.slug )}/submissions/${submissionId}`, buildAdminFilesBody( adminFiles ) );
				} catch ( uploadError ) {
					uploadFailed = true;

					throw uploadError;
				}

				// The files are saved now, whatever happens to the values, so show them and clear the inputs
				setSubmission( uploaded.data );
				setAdminFiles( {} );
				setAdminFileInputsKey( ( key ) => key + 1 );
				setAdminAnnouncement( t( 'Upload finished.' ) );
			}

			const response = await put<{ data: FormSubmission }>(
				`/${encodeURIComponent( form.slug )}/submissions/${submissionId}`,
				{ admin_values: values },
			);
			setSubmission( response.data );
			seedAdministrativeFields( response.data );
			setAdminSaved( true );
		} catch ( err ) {
			if ( err instanceof ApiValidationError ) {
				const serverErrors: Record<string, string[]> = {};

				for ( const [key, messages] of Object.entries( err.errors ) ) {
					const fileKey = key.match( /^admin_files\.([^.]+)/ );

					serverErrors[fileKey ? fileKey[1] : key.replace( /^admin_values\./, '' )] = messages;
				}

				setAdminErrors( serverErrors );
			} else {
				setError( err instanceof Error ? err.message : t( 'Failed to update submission.' ) );
			}

			if ( uploadFailed ) {
				setAdminAnnouncement( t( 'The upload failed.' ) );
			}
		} finally {
			setIsSavingAdmin( false );
		}
	}, [submission, adminValues, adminOtherValues, adminFiles, post, put, form.slug, submissionId, seedAdministrativeFields] );

	const removeAdministrativeUpload = useCallback( async ( upload: FormUpload ) => {
		if ( !window.confirm( t( 'Remove :name? The file will be deleted.', { name: upload.original_name } ) ) ) {
			return;
		}

		try {
			const response = await put<{ data: FormSubmission }>(
				`/${encodeURIComponent( form.slug )}/submissions/${submissionId}`,
				{ remove_admin_uploads: [upload.id] },
			);
			setSubmission( response.data );
			setAdminSaved( false );
			setAdminAnnouncement( t( ':name removed.', { name: upload.original_name } ) );
		} catch ( err ) {
			setError( err instanceof Error ? err.message : t( 'Failed to update submission.' ) );
		}
	}, [put, form.slug, submissionId] );

	const deleteSubmission = useCallback( async () => {
		if ( !window.confirm( t( 'Are you sure you want to delete this submission?' ) ) ) {
			return;
		}

		try {
			await del( `/${encodeURIComponent( form.slug )}/submissions/${submissionId}` );
			onBack?.();
		} catch ( err ) {
			setError( err instanceof Error ? err.message : t( 'Failed to delete submission.' ) );
		}
	}, [del, form.slug, submissionId, onBack] );

	const downloadFile = useCallback( async ( upload: FormUpload ) => {
		try {
			await download(
				`/submissions/${submissionId}/uploads/${upload.id}/download`,
				upload.original_name,
			);
		} catch ( err ) {
			setError( err instanceof Error ? err.message : t( 'Failed to download file.' ) );
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
				<span className="ml-2">{t( 'Loading submission...' )}</span>
			</div>
		);
	}

	if ( error && !submission ) {
		return (
			<div className={`space-y-4 ${className ?? ''}`} role="alert">
				<Alert color="error">{error}</Alert>
				<div className="flex gap-2">
					<Button color="outline" size="sm" onClick={loadSubmission}>{t( 'Retry' )}</Button>
					{onBack && <Button color="ghost" size="sm" onClick={onBack}>{t( 'Back' )}</Button>}
				</div>
			</div>
		);
	}

	if ( !submission ) {
		return (
			<div className={`text-center py-12 text-base-content/50 ${className ?? ''}`}>
				{t( 'Submission not found.' )}
			</div>
		);
	}

	return (
		<div className={`space-y-6 ${className ?? ''}`}>
			{/* Header */}
			<div className="flex flex-wrap items-center justify-between gap-4">
				<div className="flex items-center gap-3">
					{onBack && (
						<Button color="ghost" size="sm" onClick={onBack}>&larr; {t( 'Back' )}</Button>
					)}
					<h1 className="text-xl font-bold">{t( 'Submission :number', { number: submission.submission_number } )}</h1>
					{submission.is_spam && (
						<Badge color="error" value={t( 'Spam' )} />
					)}
				</div>
				<div>
					{onNavigate && submissionIds && submissionIds.length > 1 && (
						<div className="join">
							<Button size="sm" className="join-item" disabled={!hasPrev} onClick={goToPrev}>
								&larr; {t( 'Prev' )}
							</Button>
							<Button size="sm" className="join-item" disabled>
								{t( ':current of :total', {
									current: currentIndex === -1 ? '\u2014' : String( currentIndex + 1 ),
									total: submissionIds.length,
								} )}
							</Button>
							<Button size="sm" className="join-item" disabled={!hasNext} onClick={goToNext}>
								{t( 'Next' )} &rarr;
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
					{submission.is_starred ? `\u2605 ${t( 'Starred' )}` : `\u2606 ${t( 'Star' )}`}
				</Button>
				<Button color="ghost" size="sm" onClick={toggleRead} disabled={isUpdating}>
					{submission.is_read ? t( 'Mark Unread' ) : t( 'Mark Read' )}
				</Button>
				<Button color="ghost" size="sm" onClick={toggleSpam} disabled={isUpdating}>
					{submission.is_spam ? t( 'Not Spam' ) : t( 'Mark Spam' )}
				</Button>
				<Button
					color="error"
					size="sm"
					onClick={deleteSubmission}
					disabled={isUpdating}
				>
					{t( 'Delete' )}
				</Button>
			</div>

			<div className="space-y-6">
				{/* Field values */}
				<Card title={t( 'Submitted Data' )} bordered>
					{submission.values && submission.values.length > 0 ? (
						<dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-3">
							{submission.values.map( ( value: FormSubmissionValue ) => (
								<React.Fragment key={value.id}>
									<dt className="font-medium text-base-content/70">{value.field_admin_label || value.field_label || value.field_name}</dt>
									<dd>
										{value.is_masked ? (
											<span className="inline-flex items-center gap-2">
												<span className="font-mono">{value.display_value}</span>
												<Badge color="neutral" size="sm" value={t( 'Masked' )} />
											</span>
										) : value.is_redacted ? (
											<span className="italic text-base-content/60">{value.display_value}</span>
										) : value.display_html ? (
											// HTML the API escaped or sanitized with kses: rich text, a list's table, a ranking or a drawn signature (since 1.6.0).
											<div className="prose prose-sm max-w-none" dangerouslySetInnerHTML={{ __html: value.display_html }} />
										) : value.upload_id && submission.uploads ? (
											<FileValue
												value={value}
												uploads={submission.uploads}
												onDownload={downloadFile}
											/>
										) : value.field_type === 'consent' ? (
											<ConsentValueDetails value={value} />
										) : isCompositeValue( value.value_array ) ? (
											<div className="whitespace-pre-line">{value.display_value}</div>
										) : value.value_array ? (
											<ul className="list-disc list-inside">
												{( value.display_values ?? value.value_array ).map( ( v, i ) => (
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
						<p className="text-base-content/50">{t( 'No submission data available.' )}</p>
					)}
				</Card>

				{/* File uploads */}
				{submission.uploads && submission.uploads.length > 0 && (
					<Card title={t( 'File Uploads' )} bordered>
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
												{t( 'Download' )}
											</Button>
										</div>
									</div>
								</div>
							) )}
						</div>
					</Card>
				)}

				{/* Administrative fields */}
				{submission.administrative_fields && submission.administrative_fields.length > 0 && (
					<Card title={t( 'Administrative Fields' )} bordered>
						<p className="text-sm text-base-content/60 mb-4">{t( 'Internal fields that are not shown on the public form' )}</p>
						<form
							className="flex flex-wrap gap-4"
							onSubmit={( e ) => {
								e.preventDefault();
								saveAdministrativeFields();
							}}
						>
							{submission.administrative_fields.map( ( field ) => field.type === 'file' ? (
								<div key={field.id} className="w-full space-y-2">
									<AdministrativeFileList
										field={field}
										uploads={getFieldUploads( field, submission )}
										onDownload={downloadFile}
										onRemove={removeAdministrativeUpload}
									/>
									<FieldRenderer
										key={`${field.id}-${adminFileInputsKey}`}
										// A stored file is kept, so the input is only required while there is none
										field={getFieldUploads( field, submission ).length > 0 ? { ...field, is_required: false } : field}
										value={undefined}
										error={adminErrors[field.name]?.[0]}
										onChange={() => {}}
										onFileChange={( file ) => {
											setAdminFiles( ( prev ) => ( { ...prev, [field.name]: file } ) );
											setAdminSaved( false );
										}}
										displayConfig={ADMIN_FIELD_DISPLAY}
									/>
								</div>
							) : (
								<FieldRenderer
									key={field.id}
									field={field}
									value={adminValues[field.name]}
									error={adminErrors[field.name]?.[0]}
									subErrors={getSubInputErrors( adminErrors, field.name )}
									onChange={( value ) => {
										setAdminValues( ( prev ) => ( { ...prev, [field.name]: value } ) );
										setAdminSaved( false );
									}}
									otherValue={adminOtherValues[field.name] ?? ''}
									onOtherChange={( text ) => {
										setAdminOtherValues( ( prev ) => ( { ...prev, [field.name]: text } ) );
										setAdminSaved( false );
									}}
									displayConfig={ADMIN_FIELD_DISPLAY}
								/>
							) )}
							<div className="w-full flex items-center gap-3">
								<Button type="submit" color="primary" size="sm" disabled={isSavingAdmin}>
									{isSavingAdmin ? t( 'Saving...' ) : t( 'Save Administrative Fields' )}
								</Button>
								{adminSaved && <span className="text-sm text-success" role="status">{t( 'Saved.' )}</span>}
							</div>
							<div className="sr-only" aria-live="polite" aria-atomic="true">{adminAnnouncement}</div>
						</form>
					</Card>
				)}

				{/* Admin notes */}
				<Card title={t( 'Admin Notes' )} bordered>
					<Textarea
						className="w-full"
						rows={4}
						value={adminNotes}
						onChange={( e ) => setAdminNotes( e.target.value )}
						placeholder={t( 'Add notes about this submission...' )}
					/>
					<div className="card-actions justify-end mt-2">
						<Button
							color="primary"
							size="sm"
							onClick={saveNotes}
							disabled={isSavingNotes || adminNotes === ( submission.admin_notes ?? '' )}
						>
							{isSavingNotes ? t( 'Saving...' ) : t( 'Save Notes' )}
						</Button>
					</div>
				</Card>

				{/* Metadata */}
				<Card title={t( 'Submission Details' )} bordered>
					<dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-3">
						<dt className="font-medium text-base-content/70">{t( 'Submitted' )}</dt>
						<dd>{new Date( submission.created_at ).toLocaleString()}</dd>

						{submission.page_url && (
							<>
								<dt className="font-medium text-base-content/70">{t( 'Page URL' )}</dt>
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
								<dt className="font-medium text-base-content/70">{t( 'Referrer' )}</dt>
								<dd>{submission.referrer_url}</dd>
							</>
						)}
						{submission.ip_address && (
							<>
								<dt className="font-medium text-base-content/70">{t( 'IP Address' )}</dt>
								<dd>{submission.ip_address}</dd>
							</>
						)}
						{submission.user_agent && (
							<>
								<dt className="font-medium text-base-content/70">{t( 'User Agent' )}</dt>
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
	const valueUploads = getValueUploads( value, uploads );

	if ( valueUploads.length === 0 ) {
		return <span>{value.display_value || t( 'File not found' )}</span>;
	}

	return (
		<ul className="flex flex-col gap-2">
			{valueUploads.map( ( upload ) => (
				<li key={upload.id} className="flex items-center gap-3">
					{upload.is_image && (
						<img
							src={upload.url}
							alt={upload.original_name}
							className="w-10 h-10 rounded object-cover"
						/>
					)}
					<span className="text-sm">{upload.original_name} ({upload.human_size})</span>
					<Button color="ghost" size="xs" onClick={() => onDownload( upload )}>
						{t( 'Download' )}
					</Button>
				</li>
			) )}
		</ul>
	);
}

interface AdministrativeFileListProps {
	field: FormField;
	uploads: FormUpload[];
	onDownload: ( upload: FormUpload ) => void;
	onRemove: ( upload: FormUpload ) => void;
}

/**
 * Lists the stored files of an administrative file field, each with a
 * download button and a remove button named after the file (since 1.6.0).
 */
function AdministrativeFileList( { field, uploads, onDownload, onRemove }: AdministrativeFileListProps ): React.ReactElement | null {
	if ( uploads.length === 0 ) {
		return null;
	}

	const label = field.label || field.name;

	return (
		<div className="space-y-1">
			<ul className="flex flex-col gap-2" aria-label={t( 'Current files for :field', { field: label } )}>
				{uploads.map( ( upload ) => (
					<li key={upload.id} className="flex items-center gap-3 rounded-box bg-base-200 p-2">
						<span className="min-w-0 grow truncate text-sm">{upload.original_name} ({upload.human_size})</span>
						<Button color="ghost" size="xs" onClick={() => onDownload( upload )}>
							{t( 'Download' )}
						</Button>
						<Button
							color="ghost"
							size="xs"
							aria-label={t( 'Remove :name', { name: upload.original_name } )}
							onClick={() => onRemove( upload )}
						>
							<span aria-hidden="true">{t( 'Remove' )}</span>
						</Button>
					</li>
				) )}
			</ul>
			<p className="text-xs text-base-content/60">
				{allowsMultipleFiles( field ) ? t( 'Choosing new files replaces the current ones.' ) : t( 'Choosing a new file replaces the current one.' )}
			</p>
		</div>
	);
}

/**
 * Gets the stored files of an administrative file field (since 1.6.0).
 */
function getFieldUploads( field: FormField, submission: FormSubmission ): FormUpload[] {
	const value = submission.values?.find( ( item ) => item.field_id === field.id && !item.is_redacted );

	return value && submission.uploads ? getValueUploads( value, submission.uploads ) : [];
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

/**
 * Gets every upload a file value holds, in upload order: the `upload_ids` of
 * a multi-file value, or its single `upload_id` (since 1.6.0).
 */
function getValueUploads( value: FormSubmissionValue, uploads: FormUpload[] ): FormUpload[] {
	const ids = value.upload_ids && value.upload_ids.length > 0 ? value.upload_ids : [value.upload_id];

	return ids
		.map( ( id ) => uploads.find( ( u ) => u.id === id ) )
		.filter( ( upload ): upload is FormUpload => upload !== undefined );
}

/**
 * Whether a stored value array is a map of parts (a composite field such as
 * a name or an address) rather than a list (since 1.6.0).
 */
function isCompositeValue( valueArray: unknown ): boolean {
	return valueArray !== null && typeof valueArray === 'object' && !Array.isArray( valueArray );
}

interface ConsentValueDetailsProps {
	value: FormSubmissionValue;
}

/**
 * Shows a consent value and, when the visitor consented, the text agreed to
 * with its time and revision. The description is HTML the API sanitized
 * (`FormSubmissionValueResource`). Mirrors the Livewire submission detail.
 *
 * @since 1.6.0
 */
function ConsentValueDetails( { value }: ConsentValueDetailsProps ): React.ReactElement {
	const consent = ( isCompositeValue( value.value_array ) ? value.value_array : {} ) as unknown as Partial<ConsentValue>;
	const consented = consent.consented === '1';

	return (
		<>
			<span>{value.display_value}</span>
			{consented && (
				<details className="mt-2">
					<summary className="link link-primary cursor-pointer">{t( 'View consent text' )}</summary>
					<div className="mt-2 space-y-2 rounded-box bg-base-200 p-3">
						{consent.text && <p>{consent.text}</p>}
						{consent.description && (
							<div
								className="prose prose-sm max-w-none"
								// Sanitized with kses by the API (FormSubmissionValueResource).
								dangerouslySetInnerHTML={{ __html: consent.description }}
							/>
						)}
						{!consent.text && (
							<p className="italic opacity-60">{t( 'The consent text was not stored.' )}</p>
						)}
						<p className="text-xs opacity-60">
							{t( 'Consented at :time', { time: consent.consented_at ?? '—' } )}
							{' · '}
							{t( 'Revision :revision', { revision: String( consent.revision ?? '' ).slice( 0, 12 ) } )}
						</p>
					</div>
				</details>
			)}
		</>
	);
}
