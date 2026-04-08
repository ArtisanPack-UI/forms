<!--
  NotificationEditor component.

  Manages email notifications for a form with recipient configuration,
  conditional sending rules, message templates with placeholders, and
  field inclusion settings.

  @package    ArtisanPack_UI
  @subpackage Forms
  @since      1.1.0
-->
<script setup lang="ts">
import { computed, nextTick, ref, watch, type Ref } from 'vue';
import { Alert, Button, Checkbox, Input, Loading, Select, Textarea } from '@artisanpack-ui/vue';

import type {
	Form,
	FormField,
	FormNotification,
	NotificationType,
	NotificationTypeInfo,
	StoreNotificationRequest,
	UpdateNotificationRequest,
} from '../../../types/artisanpack-forms';
import { useApi, ApiValidationError } from '../../composables/useApi';
import type { UseApiOptions } from '../../composables/useApi';
import ConditionalLogicEditor from './ConditionalLogicEditor.vue';

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

const props = defineProps<{
	/** Base URL for the forms API (e.g. "/api/v1/forms"). */
	baseUrl: string;
	/** Optional CSRF token for non-API middleware. */
	csrfToken?: string;
	/** Optional authorization header value (e.g. "Bearer token"). */
	authorization?: string;
	/** Fetch credentials mode. */
	credentials?: RequestCredentials;
	/** The form being configured. */
	form: Form;
	/** All form fields (for placeholder and conditional logic references). */
	fields: FormField[];
}>();

const { get, post, put, del } = useApi( {
	baseUrl: props.baseUrl,
	csrfToken: props.csrfToken,
	authorization: props.authorization,
	credentials: props.credentials,
} as UseApiOptions );

const messageRef: Ref<HTMLTextAreaElement | null> = ref( null );
const requestCounters = ref<Map<number, number>>( new Map() );

const notifications = ref<FormNotification[]>( [] );
const selectedId = ref<number | null>( null );
const isLoading = ref( true );
const error = ref<string | null>( null );
const validationErrors = ref<Record<number, Record<string, string[]>>>( {} );

// -----------------------------------------------------------------------
// Derived state
// -----------------------------------------------------------------------

const selectedNotification = computed( () =>
	notifications.value.find( ( n ) => n.id === selectedId.value ) ?? null,
);

const currentErrors = computed( () =>
	selectedId.value ? validationErrors.value[selectedId.value] ?? {} : {},
);

const emailFields = computed( () =>
	props.fields.filter( ( f ) => f.type === 'email' ),
);

const placeholders = computed( () =>
	props.fields
		.filter( ( f ) => !new Set( ['heading', 'paragraph', 'divider', 'html'] ).has( f.type ) )
		.map( ( f ) => ( { name: f.name, label: f.label || f.name } ) ),
);

// -----------------------------------------------------------------------
// API
// -----------------------------------------------------------------------

async function loadNotifications(): Promise<void> {
	isLoading.value = true;

	try {
		const response = await get<{ data: FormNotification[] }>(
			`/${props.form.slug}/notifications`,
		);
		notifications.value = response.data;
	} catch ( err ) {
		notifications.value = [];
		error.value = err instanceof Error ? err.message : 'Failed to load notifications.';
	} finally {
		isLoading.value = false;
	}
}

// Load on mount
loadNotifications();

// Reset editor state when form changes
watch( () => props.form.slug, () => {
	selectedId.value = null;
	validationErrors.value = {};
	error.value = null;
	loadNotifications();
} );

// -----------------------------------------------------------------------
// CRUD
// -----------------------------------------------------------------------

async function addNotification( type: NotificationType ): Promise<void> {
	try {
		const typeInfo = NOTIFICATION_TYPES.find( ( t ) => t.type === type );
		const request: StoreNotificationRequest = {
			type,
			name: typeInfo?.label ?? 'New Notification',
			subject: `New submission: ${props.form.name}`,
			message: 'A new form submission has been received.',
			is_active: true,
		};

		const response = await post<{ data: FormNotification }>(
			`/${props.form.slug}/notifications`,
			request,
		);

		notifications.value = [...notifications.value, response.data];
		selectedId.value = response.data.id;
	} catch ( err ) {
		if ( err instanceof ApiValidationError ) {
			const messages = Object.values( err.errors ).flat();
			error.value = messages.join( ' ' );
		} else {
			error.value = err instanceof Error ? err.message : 'Failed to add notification.';
		}
	}
}

async function updateNotification( id: number, data: UpdateNotificationRequest ): Promise<void> {
	// Optimistic update
	notifications.value = notifications.value.map( ( n ) =>
		n.id === id ? { ...n, ...data } as FormNotification : n,
	);

	const requestId = ( requestCounters.value.get( id ) ?? 0 ) + 1;
	requestCounters.value.set( id, requestId );

	try {
		const response = await put<{ data: FormNotification }>(
			`/${props.form.slug}/notifications/${id}`,
			data,
		);

		// Only apply if this is still the latest request
		if ( requestCounters.value.get( id ) === requestId ) {
			notifications.value = notifications.value.map( ( n ) =>
				n.id === id ? response.data : n,
			);
			const next = { ...validationErrors.value };
			delete next[id];
			validationErrors.value = next;
		}
	} catch ( err ) {
		if ( requestCounters.value.get( id ) === requestId ) {
			if ( err instanceof ApiValidationError ) {
				// Don't reload -- keep optimistic state, just show errors
				validationErrors.value = { ...validationErrors.value, [id]: err.errors };
			} else {
				// Revert to server state on non-validation errors
				await loadNotifications();
				error.value = err instanceof Error ? err.message : 'Failed to update notification.';
			}
		}
	}
}

async function deleteNotification( id: number ): Promise<void> {
	if ( !window.confirm( 'Are you sure you want to delete this notification?' ) ) {
		return;
	}

	try {
		await del( `/${props.form.slug}/notifications/${id}` );
		notifications.value = notifications.value.filter( ( n ) => n.id !== id );

		if ( selectedId.value === id ) {
			selectedId.value = null;
		}
	} catch ( err ) {
		error.value = err instanceof Error ? err.message : 'Failed to delete notification.';
	}
}

async function toggleActive( id: number ): Promise<void> {
	const notification = notifications.value.find( ( n ) => n.id === id );

	if ( !notification ) {
		return;
	}

	await updateNotification( id, { is_active: !notification.is_active } );
}

// -----------------------------------------------------------------------
// Placeholder insertion
// -----------------------------------------------------------------------

async function insertPlaceholder( name: string ): Promise<void> {
	const textarea = messageRef.value;

	if ( !textarea || !selectedNotification.value ) {
		return;
	}

	const start = textarea.selectionStart;
	const end = textarea.selectionEnd;
	const current = selectedNotification.value.message;
	const insert = `{${name}}`;
	const newMessage = current.substring( 0, start ) + insert + current.substring( end );
	const newCursorPos = start + insert.length;

	updateNotification( selectedNotification.value.id, { message: newMessage } );

	await nextTick();

	if ( messageRef.value ) {
		messageRef.value.selectionStart = newCursorPos;
		messageRef.value.selectionEnd = newCursorPos;
		messageRef.value.focus();
	}
}

// -----------------------------------------------------------------------
// Event bubbling guard
// -----------------------------------------------------------------------

function isNestedButton( event: Event ): boolean {
	const target = event.target as HTMLElement;
	const currentTarget = event.currentTarget as HTMLElement;

	return target.closest( 'button, [role="button"]' ) !== currentTarget;
}
</script>

<template>
	<!-- Loading state -->
	<div
		v-if="isLoading"
		class="flex items-center justify-center p-8"
		role="status"
	>
		<Loading size="md" />
		<span class="ml-2">Loading notifications...</span>
	</div>

	<!-- Main content -->
	<div
		v-else
		class="space-y-4"
	>
		<!-- Header -->
		<div class="flex flex-wrap items-center justify-between gap-4">
			<h2 class="text-xl font-bold">Email Notifications</h2>
			<div class="flex flex-wrap gap-2">
				<Button
					v-for="typeInfo in NOTIFICATION_TYPES"
					:key="typeInfo.type"
					color="outline"
					size="sm"
					:title="typeInfo.description"
					@click="addNotification( typeInfo.type )"
				>
					+ {{ typeInfo.label }}
				</Button>
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

		<!-- Layout: list + detail -->
		<div class="flex gap-4">
			<!-- Notification list -->
			<div class="w-64 shrink-0 space-y-2">
				<p
					v-if="notifications.length === 0"
					class="text-sm text-base-content/50 p-4"
				>
					No notifications configured. Add one to get started.
				</p>

				<div
					v-for="notification in notifications"
					:key="notification.id"
					tabindex="0"
					role="button"
					:aria-pressed="selectedId === notification.id"
					:class="[
						'card card-bordered cursor-pointer p-3 transition-colors hover:bg-base-200',
						selectedId === notification.id ? 'bg-primary/10 border-primary' : '',
						!notification.is_active ? 'opacity-50' : '',
					]"
					@click="( e: MouseEvent ) => {
						if ( isNestedButton( e ) ) { return; }
						selectedId = notification.id;
					}"
					@keydown="( e: KeyboardEvent ) => {
						if ( isNestedButton( e ) ) { return; }
						if ( e.key === 'Enter' || e.key === ' ' ) {
							e.preventDefault();
							selectedId = notification.id;
						}
					}"
				>
					<div class="flex items-center justify-between gap-2">
						<div class="min-w-0">
							<div class="font-medium truncate">
								{{ notification.name }}
							</div>
							<div class="badge badge-sm badge-ghost mt-1">
								{{ notification.type }}
							</div>
						</div>
						<div class="flex items-center gap-1 shrink-0">
							<Button
								color="ghost"
								size="xs"
								:class="notification.is_active ? 'text-success' : 'text-base-content/40'"
								:title="notification.is_active ? 'Disable' : 'Enable'"
								:aria-label="`${ notification.is_active ? 'Disable' : 'Enable' } notification ${ notification.name }`"
								@click.stop="toggleActive( notification.id )"
							>
								{{ notification.is_active ? 'On' : 'Off' }}
							</Button>
							<Button
								color="ghost"
								size="xs"
								class="text-error"
								title="Delete"
								:aria-label="`Delete notification ${ notification.name }`"
								@click.stop="deleteNotification( notification.id )"
							>
								&times;
							</Button>
						</div>
					</div>
				</div>
			</div>

			<!-- Notification detail editor -->
			<div
				v-if="selectedNotification"
				class="flex-1 space-y-6"
			>
				<!-- Validation errors -->
				<Alert
					v-if="Object.keys( currentErrors ).length > 0"
					color="warning"
				>
					<ul class="list-disc list-inside text-sm">
						<template
							v-for="( messages, key ) in currentErrors"
							:key="key"
						>
							<li
								v-for="( msg, i ) in messages"
								:key="`${ key }-${ i }`"
							>
								{{ msg }}
							</li>
						</template>
					</ul>
				</Alert>

				<!-- Basic settings -->
				<div class="space-y-4">
					<h3 class="text-lg font-semibold">Basic Settings</h3>

					<Input
						id="notif-name"
						label="Name"
						size="sm"
						:model-value="selectedNotification.name"
						@update:model-value="updateNotification( selectedNotification!.id, { name: $event } )"
					/>

					<Checkbox
						label="Active"
						size="sm"
						:model-value="selectedNotification.is_active"
						@update:model-value="updateNotification( selectedNotification!.id, { is_active: $event } )"
					/>
				</div>

				<!-- Recipients -->
				<div class="space-y-4">
					<h3 class="text-lg font-semibold">Recipients</h3>

					<Select
						v-if="selectedNotification.type === 'autoresponder'"
						id="notif-to-field"
						label="Send To (Email Field)"
						size="sm"
						:model-value="selectedNotification.to_field ?? ''"
						:options="[
							{ value: '', label: 'Select an email field...' },
							...emailFields.map( ( f ) => ( {
								value: f.name,
								label: f.label || f.name,
							} ) ),
						]"
						option-value="value"
						option-label="label"
						@update:model-value="updateNotification( selectedNotification!.id, {
							to_field: ( $event as string ) || null,
						} )"
					/>
					<Input
						v-else
						id="notif-to-email"
						label="To Email(s)"
						size="sm"
						:model-value="selectedNotification.to_email ?? ''"
						placeholder="email@example.com, admin@example.com"
						hint="Comma-separated email addresses"
						@update:model-value="updateNotification( selectedNotification!.id, {
							to_email: $event || null,
						} )"
					/>

					<Input
						id="notif-cc"
						label="CC Emails"
						size="sm"
						:model-value="selectedNotification.cc_emails ?? ''"
						placeholder="cc@example.com"
						@update:model-value="updateNotification( selectedNotification!.id, {
							cc_emails: $event || null,
						} )"
					/>

					<Input
						id="notif-bcc"
						label="BCC Emails"
						size="sm"
						:model-value="selectedNotification.bcc_emails ?? ''"
						placeholder="bcc@example.com"
						@update:model-value="updateNotification( selectedNotification!.id, {
							bcc_emails: $event || null,
						} )"
					/>
				</div>

				<!-- Sender -->
				<div class="space-y-4">
					<h3 class="text-lg font-semibold">Sender</h3>

					<Input
						id="notif-from-name"
						label="From Name"
						size="sm"
						:model-value="selectedNotification.from_name ?? ''"
						@update:model-value="updateNotification( selectedNotification!.id, {
							from_name: $event || null,
						} )"
					/>

					<Input
						id="notif-from-email"
						label="From Email"
						type="email"
						size="sm"
						:model-value="selectedNotification.from_email ?? ''"
						@update:model-value="updateNotification( selectedNotification!.id, {
							from_email: $event || null,
						} )"
					/>

					<Input
						id="notif-reply-to"
						label="Reply-To Email"
						size="sm"
						:model-value="selectedNotification.reply_to_email ?? ''"
						@update:model-value="updateNotification( selectedNotification!.id, {
							reply_to_email: $event || null,
						} )"
					/>

					<Select
						v-if="emailFields.length > 0"
						id="notif-reply-to-field"
						label="Reply-To Field"
						size="sm"
						:model-value="selectedNotification.reply_to_field ?? ''"
						:options="[
							{ value: '', label: 'None' },
							...emailFields.map( ( f ) => ( {
								value: f.name,
								label: f.label || f.name,
							} ) ),
						]"
						option-value="value"
						option-label="label"
						@update:model-value="updateNotification( selectedNotification!.id, {
							reply_to_field: ( $event as string ) || null,
						} )"
					/>
				</div>

				<!-- Message -->
				<div class="space-y-4">
					<h3 class="text-lg font-semibold">Message</h3>

					<Input
						id="notif-subject"
						label="Subject"
						size="sm"
						:model-value="selectedNotification.subject"
						@update:model-value="updateNotification( selectedNotification!.id, {
							subject: $event,
						} )"
					/>

					<Textarea
						id="notif-message"
						ref="messageRef"
						label="Message Body"
						:rows="8"
						:model-value="selectedNotification.message"
						@update:model-value="updateNotification( selectedNotification!.id, {
							message: $event,
						} )"
					/>

					<!-- Placeholders -->
					<div
						v-if="placeholders.length > 0"
						class="space-y-2"
					>
						<label class="label">
							<span class="label-text font-medium">Available Placeholders:</span>
						</label>
						<div class="flex flex-wrap gap-1">
							<Button
								v-for="p in placeholders"
								:key="p.name"
								color="ghost"
								size="xs"
								:title="'Insert {' + p.name + '}'"
								@click="insertPlaceholder( p.name )"
							>
								{{ '{' + p.name + '}' }} - {{ p.label }}
							</Button>
						</div>
					</div>

					<Checkbox
						label="Include all submission data in email"
						size="sm"
						:model-value="selectedNotification.include_submission_data"
						@update:model-value="updateNotification( selectedNotification!.id, {
							include_submission_data: $event,
						} )"
					/>
				</div>

				<!-- Conditional sending -->
				<div class="space-y-4">
					<h3 class="text-lg font-semibold">Conditional Sending</h3>
					<p class="text-sm text-base-content/60">
						Only send this notification when certain conditions are met.
					</p>
					<ConditionalLogicEditor
						:value="selectedNotification.conditional_logic"
						:fields="fields"
						:actions="[
							{ value: 'send', label: 'Send' },
							{ value: 'skip', label: 'Skip' },
						]"
						action-label="this notification"
						@update:value="updateNotification( selectedNotification!.id, {
							conditional_logic: $event,
						} )"
					/>
				</div>

				<!-- Delete -->
				<div class="pt-4 border-t border-base-300">
					<Button
						color="error"
						size="sm"
						@click="deleteNotification( selectedNotification!.id )"
					>
						Delete Notification
					</Button>
				</div>
			</div>
		</div>
	</div>
</template>
