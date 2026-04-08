/**
 * NotificationEditor component.
 *
 * Manages email notifications for a form with recipient configuration,
 * conditional sending rules, message templates with placeholders, and
 * field inclusion settings.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Alert, Button, Checkbox, Input, Loading, Select, Textarea } from '@artisanpack-ui/react';

import type {
	Form,
	FormField,
	FormNotification,
	NotificationType,
	NotificationTypeInfo,
	StoreNotificationRequest,
	UpdateNotificationRequest,
} from '../../../types/artisanpack-forms';
import { useApi } from '../../hooks/useApi';
import type { UseApiOptions } from '../../hooks/useApi';
import { ApiValidationError } from '../../hooks/useApi';
import { ConditionalLogicEditor } from './ConditionalLogicEditor';

/** Notification type metadata. */
const NOTIFICATION_TYPES: NotificationTypeInfo[] = [
	{
		type: 'admin',
		label: 'Admin Notification',
		description: 'Send a notification to site administrators when a form is submitted.',
	},
	{
		type: 'autoresponder',
		label: 'Auto-Responder',
		description: 'Send an automatic reply to the person who submitted the form.',
	},
	{
		type: 'custom',
		label: 'Custom Notification',
		description: 'Send a notification to custom recipients with custom logic.',
	},
];

/** Props for the NotificationEditor component. */
export interface NotificationEditorProps extends UseApiOptions {
	/** The form being configured. */
	form: Form;
	/** All form fields (for placeholder and conditional logic references). */
	fields: FormField[];
	/** Optional CSS class name. */
	className?: string;
}

/**
 * Notification editor component for managing form email notifications.
 *
 * @example
 * ```tsx
 * <NotificationEditor
 *   baseUrl="/api/v1/forms"
 *   form={form}
 *   fields={fields}
 * />
 * ```
 */
export function NotificationEditor( {
	baseUrl,
	csrfToken,
	authorization,
	credentials,
	form,
	fields,
	className,
}: NotificationEditorProps ): React.ReactElement {
	const { get, post, put, del } = useApi( { baseUrl, csrfToken, authorization, credentials } );
	const messageRef = useRef<HTMLTextAreaElement>( null );
	const requestCounterRef = useRef<Map<number, number>>( new Map() );

	const [notifications, setNotifications] = useState<FormNotification[]>( [] );
	const [selectedId, setSelectedId] = useState<number | null>( null );
	const [isLoading, setIsLoading] = useState( true );
	const [error, setError] = useState<string | null>( null );
	const [validationErrors, setValidationErrors] = useState<Record<number, Record<string, string[]>>>( {} );

	// Load notifications
	const loadNotifications = useCallback( async () => {
		setIsLoading( true );

		try {
			const response = await get<{ data: FormNotification[] }>(
				`/${form.slug}/notifications`,
			);
			setNotifications( response.data );
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to load notifications.' );
		} finally {
			setIsLoading( false );
		}
	}, [get, form.slug] );

	useEffect( () => {
		loadNotifications();
	}, [loadNotifications] );

	const selectedNotification = useMemo(
		() => notifications.find( ( n ) => n.id === selectedId ) ?? null,
		[notifications, selectedId],
	);

	const currentErrors = selectedId ? validationErrors[selectedId] ?? {} : {};

	// Email fields for "to_field" and "reply_to_field" dropdowns
	const emailFields = useMemo(
		() => fields.filter( ( f ) => f.type === 'email' ),
		[fields],
	);

	// Available placeholders for message templates
	const placeholders = useMemo(
		() => fields
			.filter( ( f ) => !new Set( ['heading', 'paragraph', 'divider', 'html'] ).has( f.type ) )
			.map( ( f ) => ( { name: f.name, label: f.label || f.name } ) ),
		[fields],
	);

	// -----------------------------------------------------------------------
	// CRUD
	// -----------------------------------------------------------------------

	const addNotification = useCallback( async ( type: NotificationType ) => {
		try {
			const typeInfo = NOTIFICATION_TYPES.find( ( t ) => t.type === type );
			const request: StoreNotificationRequest = {
				type,
				name: typeInfo?.label ?? 'New Notification',
				subject: `New submission: ${form.name}`,
				message: 'A new form submission has been received.',
				is_active: true,
			};

			const response = await post<{ data: FormNotification }>(
				`/${form.slug}/notifications`,
				request,
			);

			setNotifications( ( prev ) => [...prev, response.data] );
			setSelectedId( response.data.id );
		} catch ( err ) {
			if ( err instanceof ApiValidationError ) {
				setValidationErrors( ( prev ) => ( { ...prev, [0]: err.errors } ) );
			} else {
				setError( err instanceof Error ? err.message : 'Failed to add notification.' );
			}
		}
	}, [post, form] );

	const updateNotification = useCallback(
		async ( id: number, data: UpdateNotificationRequest ) => {
			// Optimistic update
			setNotifications( ( prev ) => prev.map( ( n ) =>
				n.id === id ? { ...n, ...data } as FormNotification : n,
			) );

			const requestId = ( requestCounterRef.current.get( id ) ?? 0 ) + 1;
			requestCounterRef.current.set( id, requestId );

			try {
				const response = await put<{ data: FormNotification }>(
					`/${form.slug}/notifications/${id}`,
					data,
				);

				// Only apply if this is still the latest request
				if ( requestCounterRef.current.get( id ) === requestId ) {
					setNotifications( ( prev ) => prev.map( ( n ) =>
						n.id === id ? response.data : n,
					) );
					setValidationErrors( ( prev ) => {
						const next = { ...prev };
						delete next[id];
						return next;
					} );
				}
			} catch ( err ) {
				if ( requestCounterRef.current.get( id ) === requestId ) {
					// Revert on error
					await loadNotifications();

					if ( err instanceof ApiValidationError ) {
						setValidationErrors( ( prev ) => ( { ...prev, [id]: err.errors } ) );
					} else {
						setError( err instanceof Error ? err.message : 'Failed to update notification.' );
					}
				}
			}
		},
		[put, form.slug, loadNotifications],
	);

	const deleteNotification = useCallback( async ( id: number ) => {
		if ( !window.confirm( 'Are you sure you want to delete this notification?' ) ) {
			return;
		}

		try {
			await del( `/${form.slug}/notifications/${id}` );
			setNotifications( ( prev ) => prev.filter( ( n ) => n.id !== id ) );

			if ( selectedId === id ) {
				setSelectedId( null );
			}
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to delete notification.' );
		}
	}, [del, form.slug, selectedId] );

	const toggleActive = useCallback(
		async ( id: number ) => {
			const notification = notifications.find( ( n ) => n.id === id );

			if ( !notification ) {
				return;
			}

			await updateNotification( id, { is_active: !notification.is_active } );
		},
		[notifications, updateNotification],
	);

	// -----------------------------------------------------------------------
	// Render
	// -----------------------------------------------------------------------

	if ( isLoading ) {
		return (
			<div className={`flex items-center justify-center p-8 ${className ?? ''}`} role="status">
				<Loading size="md" />
				<span className="ml-2">Loading notifications...</span>
			</div>
		);
	}

	return (
		<div className={`space-y-4 ${className ?? ''}`}>
			{/* Header */}
			<div className="flex flex-wrap items-center justify-between gap-4">
				<h2 className="text-xl font-bold">Email Notifications</h2>
				<div className="flex flex-wrap gap-2">
					{NOTIFICATION_TYPES.map( ( typeInfo ) => (
						<Button
							key={typeInfo.type}
							color="outline"
							size="sm"
							onClick={() => addNotification( typeInfo.type )}
							title={typeInfo.description}
						>
							+ {typeInfo.label}
						</Button>
					) )}
				</div>
			</div>

			{/* Error */}
			{error && (
				<Alert color="error" dismissible onDismiss={() => setError( null )}>
					{error}
				</Alert>
			)}

			{/* Layout: list + detail */}
			<div className="flex gap-4">
				{/* Notification list */}
				<div className="w-64 shrink-0 space-y-2">
					{notifications.length === 0 && (
						<p className="text-sm text-base-content/50 p-4">
							No notifications configured. Add one to get started.
						</p>
					)}

					{notifications.map( ( notification ) => (
						<div
							key={notification.id}
							tabIndex={0}
							role="button"
							aria-pressed={selectedId === notification.id}
							className={`card card-bordered cursor-pointer p-3 transition-colors hover:bg-base-200 ${
								selectedId === notification.id ? 'bg-primary/10 border-primary' : ''
							} ${!notification.is_active ? 'opacity-50' : ''}`}
							onClick={( e ) => {
								if ( ( e.target as HTMLElement ).closest( 'button, [role="button"]' ) !== e.currentTarget ) {
									return;
								}
								setSelectedId( notification.id );
							}}
							onKeyDown={( e ) => {
								if ( ( e.target as HTMLElement ).closest( 'button, [role="button"]' ) !== e.currentTarget ) {
									return;
								}
								if ( e.key === 'Enter' || e.key === ' ' ) {
									e.preventDefault();
									setSelectedId( notification.id );
								}
							}}
						>
							<div className="flex items-center justify-between gap-2">
								<div className="min-w-0">
									<div className="font-medium truncate">
										{notification.name}
									</div>
									<div className="badge badge-sm badge-ghost mt-1">
										{notification.type}
									</div>
								</div>
								<div className="flex items-center gap-1 shrink-0">
									<Button
										color="ghost"
										size="xs"
										className={notification.is_active ? 'text-success' : 'text-base-content/40'}
										onClick={( e ) => {
											e.stopPropagation();
											toggleActive( notification.id );
										}}
										title={notification.is_active ? 'Disable' : 'Enable'}
									>
										{notification.is_active ? 'On' : 'Off'}
									</Button>
									<Button
										color="ghost"
										size="xs"
										className="text-error"
										onClick={( e ) => {
											e.stopPropagation();
											deleteNotification( notification.id );
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

				{/* Notification detail editor */}
				{selectedNotification && (
					<div className="flex-1 space-y-6">
						{/* Validation errors */}
						{Object.keys( currentErrors ).length > 0 && (
							<Alert color="warning">
								<ul className="list-disc list-inside text-sm">
									{Object.entries( currentErrors ).map( ( [key, messages] ) =>
										messages.map( ( msg, i ) => <li key={`${key}-${i}`}>{msg}</li> ),
									)}
								</ul>
							</Alert>
						)}

						{/* Basic settings */}
						<div className="space-y-4">
							<h3 className="text-lg font-semibold">Basic Settings</h3>

							<Input
								id="notif-name"
								label="Name"
								size="sm"
								value={selectedNotification.name}
								onChange={( e ) => updateNotification( selectedNotification.id, { name: e.target.value } )}
							/>

							<Checkbox
								label="Active"
								size="sm"
								checked={selectedNotification.is_active}
								onChange={( e ) => updateNotification( selectedNotification.id, { is_active: e.target.checked } )}
							/>
						</div>

						{/* Recipients */}
						<div className="space-y-4">
							<h3 className="text-lg font-semibold">Recipients</h3>

							{selectedNotification.type === 'autoresponder' ? (
								<Select
									id="notif-to-field"
									label="Send To (Email Field)"
									size="sm"
									value={selectedNotification.to_field ?? ''}
									onChange={( e ) => updateNotification( selectedNotification.id, {
										to_field: e.target.value || null,
									} )}
									options={[
										{ value: '', label: 'Select an email field...' },
										...emailFields.map( ( f ) => ( {
											value: f.name,
											label: f.label || f.name,
										} ) ),
									]}
									optionValue="value"
									optionLabel="label"
								/>
							) : (
								<Input
									id="notif-to-email"
									label="To Email(s)"
									size="sm"
									value={selectedNotification.to_email ?? ''}
									onChange={( e ) => updateNotification( selectedNotification.id, {
										to_email: e.target.value || null,
									} )}
									placeholder="email@example.com, admin@example.com"
									hint="Comma-separated email addresses"
								/>
							)}

							<Input
								id="notif-cc"
								label="CC Emails"
								size="sm"
								value={selectedNotification.cc_emails ?? ''}
								onChange={( e ) => updateNotification( selectedNotification.id, {
									cc_emails: e.target.value || null,
								} )}
								placeholder="cc@example.com"
							/>

							<Input
								id="notif-bcc"
								label="BCC Emails"
								size="sm"
								value={selectedNotification.bcc_emails ?? ''}
								onChange={( e ) => updateNotification( selectedNotification.id, {
									bcc_emails: e.target.value || null,
								} )}
								placeholder="bcc@example.com"
							/>
						</div>

						{/* Sender */}
						<div className="space-y-4">
							<h3 className="text-lg font-semibold">Sender</h3>

							<Input
								id="notif-from-name"
								label="From Name"
								size="sm"
								value={selectedNotification.from_name ?? ''}
								onChange={( e ) => updateNotification( selectedNotification.id, {
									from_name: e.target.value || null,
								} )}
							/>

							<Input
								id="notif-from-email"
								label="From Email"
								type="email"
								size="sm"
								value={selectedNotification.from_email ?? ''}
								onChange={( e ) => updateNotification( selectedNotification.id, {
									from_email: e.target.value || null,
								} )}
							/>

							<Input
								id="notif-reply-to"
								label="Reply-To Email"
								size="sm"
								value={selectedNotification.reply_to_email ?? ''}
								onChange={( e ) => updateNotification( selectedNotification.id, {
									reply_to_email: e.target.value || null,
								} )}
							/>

							{emailFields.length > 0 && (
								<Select
									id="notif-reply-to-field"
									label="Reply-To Field"
									size="sm"
									value={selectedNotification.reply_to_field ?? ''}
									onChange={( e ) => updateNotification( selectedNotification.id, {
										reply_to_field: e.target.value || null,
									} )}
									options={[
										{ value: '', label: 'None' },
										...emailFields.map( ( f ) => ( {
											value: f.name,
											label: f.label || f.name,
										} ) ),
									]}
									optionValue="value"
									optionLabel="label"
								/>
							)}
						</div>

						{/* Message */}
						<div className="space-y-4">
							<h3 className="text-lg font-semibold">Message</h3>

							<Input
								id="notif-subject"
								label="Subject"
								size="sm"
								value={selectedNotification.subject}
								onChange={( e ) => updateNotification( selectedNotification.id, {
									subject: e.target.value,
								} )}
							/>

							<Textarea
								id="notif-message"
								ref={messageRef}
								label="Message Body"
								rows={8}
								value={selectedNotification.message}
								onChange={( e ) => updateNotification( selectedNotification.id, {
									message: e.target.value,
								} )}
							/>

							{/* Placeholders */}
							{placeholders.length > 0 && (
								<div className="space-y-2">
									<label className="label">
										<span className="label-text font-medium">Available Placeholders:</span>
									</label>
									<div className="flex flex-wrap gap-1">
										{placeholders.map( ( p ) => (
											<Button
												key={p.name}
												color="ghost"
												size="xs"
												onClick={() => {
													const textarea = messageRef.current;

													if ( textarea ) {
														const start = textarea.selectionStart;
														const end = textarea.selectionEnd;
														const current = selectedNotification.message;
														const insert = `{${p.name}}`;
														const newMessage = current.substring( 0, start ) + insert + current.substring( end );
														updateNotification( selectedNotification.id, { message: newMessage } );
													}
												}}
												title={`Insert {${p.name}}`}
											>
												{`{${p.name}}`} - {p.label}
											</Button>
										) )}
									</div>
								</div>
							)}

							<Checkbox
								label="Include all submission data in email"
								size="sm"
								checked={selectedNotification.include_submission_data}
								onChange={( e ) => updateNotification( selectedNotification.id, {
									include_submission_data: e.target.checked,
								} )}
							/>
						</div>

						{/* Conditional sending */}
						<div className="space-y-4">
							<h3 className="text-lg font-semibold">Conditional Sending</h3>
							<p className="text-sm text-base-content/60">
								Only send this notification when certain conditions are met.
							</p>
							<ConditionalLogicEditor
								value={selectedNotification.conditional_logic}
								onChange={( logic ) => updateNotification( selectedNotification.id, {
									conditional_logic: logic,
								} )}
								fields={fields}
								actions={[
									{ value: 'send', label: 'Send' },
									{ value: 'skip', label: 'Skip' },
								]}
								actionLabel="this notification"
							/>
						</div>

						{/* Delete */}
						<div className="pt-4 border-t border-base-300">
							<Button
								color="error"
								size="sm"
								onClick={() => deleteNotification( selectedNotification.id )}
							>
								Delete Notification
							</Button>
						</div>
					</div>
				)}
			</div>
		</div>
	);
}
