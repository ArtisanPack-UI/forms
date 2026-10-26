/**
 * Advanced field components.
 *
 * Renders file upload, date picker, date and time (since 1.6.0) and star
 * rating fields using @artisanpack-ui/react components.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import { useState } from 'react';
import { DatePicker, File as FileInput, Input } from '@artisanpack-ui/react/form';

import { getDateBounds, getDateLimits } from '../../../shared/dateConstraints';
import { getDateInputMode } from '../../../shared/dateTime';
import {
	allowsMultipleFiles,
	getFieldLayout,
	getFileAccept,
	getFileLimitsHint,
	getFileMaxCount,
	getInputLabelProps,
	getRatingSizeClass,
	getRatingSteps,
	getTimeStep,
	isReadOnlyField,
	ratingAllowsHalf,
	withInputNotes,
} from '../../../shared/fieldSettings';
import type { InputLabelProps } from '../../../shared/fieldSettings';
import type { FormField } from '../../../types/artisanpack-forms';
import { t } from '../../../shared/i18n';

import { DatePartsField } from './DateTimeParts';
import { FieldLayout } from './FieldLayout';
import type { FieldComponentProps } from './types';

/**
 * Renders a file upload field.
 *
 * A multi-file field (since 1.6.0) adds each file chosen or dropped to a
 * list with a remove button, up to its max files, and a polite live region
 * announces the changes. Files upload with the submission. A note under
 * the input lists the allowed types and size limits (since 1.6.0).
 */
export function FileField( props: FieldComponentProps ) {
	if ( allowsMultipleFiles( props.field ) ) {
		return <MultiFileField {...props} />;
	}

	return <SingleFileField {...props} />;
}

/**
 * Add the limits note's ID to a file input's `aria-describedby` (since 1.6.0).
 *
 * @returns The label props and the note, if the field sets limits.
 */
function withLimitsNote( props: InputLabelProps, field: FormField, error: string | undefined ): { props: InputLabelProps; note: React.ReactNode } {
	const hint = getFileLimitsHint( field );
	const id = `field-${field.name}-limits`;

	if ( hint === undefined ) {
		return { props, note: null };
	}

	return {
		props: withInputNotes( props, field, error, [id] ),
		note: <p id={id} className="fieldset-label mt-1">{hint}</p>,
	};
}

/**
 * Renders a multi-file upload field: a drag-and-drop zone around a keyboard
 * operable file input, and the list of files to upload.
 *
 * @since 1.6.0
 */
function MultiFileField( { field, error, onFileChange, displayConfig }: FieldComponentProps ) {
	const layout = getFieldLayout( field, displayConfig );
	const limits = withLimitsNote( getInputLabelProps( field, layout, displayConfig, error ), field, error );
	const { inline: _inline, ...labelProps } = limits.props;
	const maxFiles = getFileMaxCount( field );
	const [files, setFiles] = useState<File[]>( [] );
	const [announcement, setAnnouncement] = useState( '' );

	const update = ( next: File[], message: string ): void => {
		setFiles( next );
		setAnnouncement( message );
		onFileChange?.( next );
	};

	const add = ( fileList: FileList | null ): void => {
		const added = Array.from( fileList ?? [] );
		const room = maxFiles === undefined ? added.length : Math.max( 0, maxFiles - files.length );
		const accepted = added.slice( 0, room );

		if ( accepted.length === 0 && added.length > 0 ) {
			setAnnouncement( t( 'You may upload no more than :max files.', { max: maxFiles ?? 0 } ) );

			return;
		}

		update(
			[...files, ...accepted],
			accepted.length < added.length
				? t( 'You may upload no more than :max files.', { max: maxFiles ?? 0 } )
				: t( ':count files ready to upload.', { count: files.length + accepted.length } ),
		);
	};

	const remove = ( index: number ): void => {
		const removed = files[index];

		update( files.filter( ( _file, position ) => position !== index ), t( ':name removed.', { name: removed?.name ?? '' } ) );
	};

	return (
		<FieldLayout field={field} layout={layout}>
			<FileInput
				{...labelProps}
				name={field.name}
				accept={getFileAccept( field )}
				error={error}
				className={field.css_classes ?? undefined}
				multiple
				withDragDrop
				onFilesSelected={add}
			/>
			{limits.note}

			{files.length > 0 && (
				<ul className="mt-3 flex flex-col gap-2" aria-label={t( 'Files for :field', { field: field.label ?? field.name } )}>
					{files.map( ( file, index ) => (
						<li key={`${file.name}-${file.size}-${file.lastModified}-${index}`} className="flex items-center gap-3 rounded-box bg-base-200 p-2">
							<span className="min-w-0 grow truncate text-sm">{file.name}</span>
							<span className="shrink-0 text-xs text-base-content/60">{formatFileSize( file.size )}</span>
							<button
								type="button"
								className="btn btn-ghost btn-sm btn-square"
								aria-label={t( 'Remove :name', { name: file.name } )}
								onClick={() => remove( index )}
							>
								<span aria-hidden="true">✕</span>
							</button>
						</li>
					) )}
				</ul>
			)}

			<div className="sr-only" aria-live="polite" aria-atomic="true">{announcement}</div>
		</FieldLayout>
	);
}

/** Show a file size in KB or MB. */
function formatFileSize( bytes: number ): string {
	return bytes >= 1048576 ? `${( bytes / 1048576 ).toFixed( 1 )} MB` : `${Math.max( 1, Math.round( bytes / 1024 ) )} KB`;
}

/**
 * Renders a single-file upload field.
 */
function SingleFileField( { field, error, onFileChange, displayConfig }: FieldComponentProps ) {
	const allowedTypes = getFileAccept( field );
	const layout = getFieldLayout( field, displayConfig );
	const limits = withLimitsNote( getInputLabelProps( field, layout, displayConfig, error ), field, error );
	const { inline: _inline, ...labelProps } = limits.props;

	return (
		<FieldLayout field={field} layout={layout}>
			<FileInput
				{...labelProps}
				name={field.name}
				accept={allowedTypes}
				error={error}
				required={field.is_required}
				className={field.css_classes ?? undefined}
				withDragDrop
				onFilesSelected={( fileList ) => {
					if ( !onFileChange ) {
						return;
					}

					if ( !fileList || fileList.length === 0 ) {
						onFileChange( [] );

						return;
					}

					if ( fileList.length === 1 ) {
						onFileChange( fileList[0] );
					} else {
						onFileChange( Array.from( fileList ) );
					}
				}}
			/>
			{limits.note}
		</FieldLayout>
	);
}

/**
 * Renders a date and time field (since 1.6.0): a native `datetime-local`
 * input, bounded by the field's min and max dates from the start of the first
 * day to the end of the last.
 */
export function DateTimeField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const layout = getFieldLayout( field, displayConfig );
	const limits = getDateLimits( field );

	return (
		<FieldLayout field={field} layout={layout}>
			<Input
				{...getInputLabelProps( field, layout, displayConfig, error )}
				name={field.name}
				type="datetime-local"
				value={String( value ?? '' )}
				error={error}
				required={field.is_required}
				readOnly={isReadOnlyField( field )}
				className={field.css_classes ?? undefined}
				onChange={( e ) => onChange( e.target.value )}
				min={limits.min ? `${limits.min}T00:00` : undefined}
				max={limits.max ? `${limits.max}T23:59` : undefined}
				step={getTimeStep( field )}
			/>
		</FieldLayout>
	);
}

/**
 * Renders a date picker field, or month, day and year inputs in the
 * `dropdowns` and `fields` modes (since 1.6.0). The picker's `min` and `max`
 * follow the field's resolved limits and age limits.
 */
export function DateField( props: FieldComponentProps ) {
	const { field, value, error, onChange, displayConfig } = props;

	if ( getDateInputMode( field ) !== 'picker' ) {
		return <DatePartsField {...props} />;
	}

	const layout = getFieldLayout( field, displayConfig );
	const labelProps = getInputLabelProps( field, layout, displayConfig, error );
	const bounds = getDateBounds( field );

	// DatePicker has no read-only state (its calendar still sets the value),
	// so a read-only date is a plain readonly date input.
	if ( isReadOnlyField( field ) ) {
		return (
			<FieldLayout field={field} layout={layout}>
				<Input
					type="date"
					{...labelProps}
					name={field.name}
					value={String( value ?? '' )}
					error={error}
					required={field.is_required}
					className={field.css_classes ?? undefined}
					autoComplete={field.autocomplete ?? undefined}
					readOnly
					onChange={() => undefined}
				/>
			</FieldLayout>
		);
	}

	return (
		<FieldLayout field={field} layout={layout}>
			<DatePicker
				{...labelProps}
				name={field.name}
				value={String( value ?? '' )}
				error={error}
				required={field.is_required}
				className={field.css_classes ?? undefined}
				onChange={( e ) => onChange( e.target.value )}
				min={bounds.min}
				max={bounds.max}
				autoComplete={field.autocomplete ?? undefined}
			/>
		</FieldLayout>
	);
}

/** Focus ring shown on the star that has keyboard focus. */
const RATING_FOCUS_CLASSES = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';

/** Focus ring on the group while the hidden "No rating" radio has keyboard focus. */
const RATING_GROUP_FOCUS_CLASSES = 'has-[.rating-hidden:focus-visible]:outline has-[.rating-hidden:focus-visible]:outline-2 has-[.rating-hidden:focus-visible]:outline-offset-2 has-[.rating-hidden:focus-visible]:outline-primary';

/**
 * Renders a star rating field.
 *
 * Native radios in a `radiogroup`, so arrow keys move between stars. A hidden
 * "No rating" radio (value 0) clears the rating. Half stars split each star
 * into two radios when the field allows them. A read-only rating is marked
 * `aria-readonly` and its radios are disabled.
 *
 * @since 1.6.0
 */
export function RatingField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const steps = getRatingSteps( field );
	const allowHalf = ratingAllowsHalf( field );
	const readOnly = isReadOnlyField( field );
	const current = Number( value );
	const selected = Number.isFinite( current ) ? current : 0;

	const layout = getFieldLayout( field, displayConfig );
	const labelId = field.label ? ( layout.custom ? layout.labelId : `${field.name}-label` ) : undefined;
	const hintId = layout.custom
		? ( field.help_text ? layout.descriptionId : undefined )
		: ( field.help_text && !error ? `${field.name}-hint` : undefined );
	const errorId = error ? `${field.name}-error` : undefined;
	const describedBy = [hintId, errorId].filter( Boolean ).join( ' ' ) || undefined;
	const labelHidden = displayConfig.label_position === 'hidden';

	return (
		<FieldLayout field={field} layout={layout} group>
			<div className="fieldset">
				{!layout.custom && field.label && (
					<span id={labelId} className={`fieldset-legend ${labelHidden ? 'sr-only' : ''}`}>
						{field.label}
						{field.is_required && <span className="text-error ml-1">*</span>}
					</span>
				)}
				<div
					role="radiogroup"
					aria-labelledby={labelId}
					aria-label={labelId ? undefined : field.name}
					aria-describedby={describedBy}
					aria-required={field.is_required ? 'true' : undefined}
					aria-readonly={readOnly ? 'true' : undefined}
					aria-invalid={error ? true : undefined}
					className={`rating ${getRatingSizeClass( field )} ${allowHalf ? 'rating-half' : ''} ${RATING_GROUP_FOCUS_CLASSES} ${field.css_classes ?? ''}`}
				>
					<input
						type="radio"
						name={field.name}
						value="0"
						className="rating-hidden"
						aria-label={t( 'No rating' )}
						checked={selected === 0}
						disabled={readOnly}
						onChange={() => onChange( '0' )}
					/>
					{steps.map( ( step ) => {
						const halfClass = allowHalf ? ( Number.isInteger( step ) ? 'mask-half-2' : 'mask-half-1' ) : '';

						return (
							<input
								key={step}
								type="radio"
								name={field.name}
								value={String( step )}
								className={`mask mask-star-2 ${halfClass} bg-warning ${RATING_FOCUS_CLASSES}`}
								aria-label={step === 1 ? t( ':count star', { count: step } ) : t( ':count stars', { count: step } )}
								checked={selected === step}
								disabled={readOnly}
								onChange={() => onChange( String( step ) )}
							/>
						);
					} )}
				</div>
				{!layout.custom && hintId && (
					<p id={hintId} className="fieldset-label">{field.help_text}</p>
				)}
				{error && (
					<p id={errorId} className="fieldset-label text-error" role="alert">{error}</p>
				)}
			</div>
		</FieldLayout>
	);
}
