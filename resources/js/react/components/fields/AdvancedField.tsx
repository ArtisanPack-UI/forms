/**
 * Advanced field components.
 *
 * Renders file upload, date picker and star rating fields
 * using @artisanpack-ui/react components.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import { DatePicker, File as FileInput, Input } from '@artisanpack-ui/react/form';

import {
	getFileAccept,
	getRatingSizeClass,
	getRatingSteps,
	isReadOnlyField,
	ratingAllowsHalf,
} from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';

import type { FieldComponentProps } from './types';

/**
 * Renders a file upload field.
 */
export function FileField( { field, error, onFileChange, displayConfig }: FieldComponentProps ) {
	const allowedTypes = getFileAccept( field );

	return (
		<FileInput
			label={displayConfig.label_position !== 'hidden' ? ( field.label ?? undefined ) : undefined}
			aria-label={displayConfig.label_position === 'hidden' ? ( field.label ?? field.name ) : undefined}
			name={field.name}
			accept={allowedTypes}
			hint={field.help_text ?? undefined}
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
	);
}

/**
 * Renders a date picker field.
 */
export function DateField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	// DatePicker has no read-only state (its calendar still sets the value),
	// so a read-only date is a plain readonly date input.
	if ( isReadOnlyField( field ) ) {
		return (
			<Input
				type="date"
				label={displayConfig.label_position !== 'hidden' ? ( field.label ?? undefined ) : undefined}
				aria-label={displayConfig.label_position === 'hidden' ? ( field.label ?? field.name ) : undefined}
				name={field.name}
				value={String( value ?? '' )}
				hint={field.help_text ?? undefined}
				error={error}
				required={field.is_required}
				className={field.css_classes ?? undefined}
				autoComplete={field.autocomplete ?? undefined}
				readOnly
				onChange={() => undefined}
				inline={displayConfig.label_position === 'beside'}
			/>
		);
	}

	return (
		<DatePicker
			label={displayConfig.label_position !== 'hidden' ? ( field.label ?? undefined ) : undefined}
			aria-label={displayConfig.label_position === 'hidden' ? ( field.label ?? field.name ) : undefined}
			name={field.name}
			value={String( value ?? '' )}
			hint={field.help_text ?? undefined}
			error={error}
			required={field.is_required}
			className={field.css_classes ?? undefined}
			onChange={( e ) => onChange( e.target.value )}
			min={field.validation_rules?.min_date ?? undefined}
			max={field.validation_rules?.max_date ?? undefined}
			autoComplete={field.autocomplete ?? undefined}
			inline={displayConfig.label_position === 'beside'}
		/>
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

	const labelId = field.label ? `${field.name}-label` : undefined;
	const hintId = field.help_text && !error ? `${field.name}-hint` : undefined;
	const errorId = error ? `${field.name}-error` : undefined;
	const describedBy = [hintId, errorId].filter( Boolean ).join( ' ' ) || undefined;
	const labelHidden = displayConfig.label_position === 'hidden';

	return (
		<div className="fieldset">
			{field.label && (
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
			{field.help_text && !error && (
				<p id={hintId} className="fieldset-label">{field.help_text}</p>
			)}
			{error && (
				<p id={errorId} className="fieldset-label text-error" role="alert">{error}</p>
			)}
		</div>
	);
}
