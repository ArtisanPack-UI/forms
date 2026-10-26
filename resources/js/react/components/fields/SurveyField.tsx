/**
 * Survey field components: slider, Net Promoter Score and Likert / matrix.
 *
 * Mirror the Blade `slider`, `nps` and `matrix` partials (since 1.6.0):
 *
 * - The slider is a native range input with its value shown beside it and
 *   given to screen readers as `aria-valuetext` ("$40").
 * - The NPS scale is a radio group of 0–10 shown as buttons, each radio
 *   visually hidden inside its button so arrow keys still move between
 *   scores; 0 and 10 are named with their end labels.
 * - A matrix is a table whose radios are named by their row and column
 *   headers, one radio group (shared name) per row; a single scale is one
 *   radio group.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { ReactNode } from 'react';

import { getFieldLayout, isReadOnlyField } from '../../../shared/fieldSettings';
import type { FieldLayout as FieldLayoutSettings } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';
import { getMatrixColumns, getMatrixRows, isSingleScale, toMatrixAnswers } from '../../../shared/matrix';
import { getNpsLabels, getNpsScoreLabel, NPS_MAX_SCORE, NPS_MIN_SCORE, NPS_SCORES } from '../../../shared/nps';
import { formatSliderValue, getInitialSliderValue, getSliderSettings } from '../../../shared/slider';
import type { FormField } from '../../../types/artisanpack-forms';

import { FieldLayout } from './FieldLayout';
import type { FieldComponentProps } from './types';

/** Focus ring shown on an NPS button while its hidden radio has keyboard focus. */
const SCORE_FOCUS_CLASSES = 'peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary';

/** The label, hint and error IDs of a field that renders its own label (see `RatingField`). */
interface GroupIds {
	labelId?: string;
	hintId?: string;
	errorId?: string;
	describedBy?: string;
}

/** Work out a field's label, hint and error IDs for its layout. */
function getGroupIds( field: FormField, layout: FieldLayoutSettings, error?: string ): GroupIds {
	const labelId = field.label ? ( layout.custom ? layout.labelId : `${field.name}-label` ) : undefined;
	const hintId = layout.custom
		? ( field.help_text ? layout.descriptionId : undefined )
		: ( field.help_text && !error ? `${field.name}-hint` : undefined );
	const errorId = error ? `${field.name}-error` : undefined;

	return { labelId, hintId, errorId, describedBy: [hintId, errorId].filter( Boolean ).join( ' ' ) || undefined };
}

/** Props for the label, hint and error wrapper shared by the slider, NPS and single scale. */
interface LabelledGroupProps {
	field: FormField;
	layout: FieldLayoutSettings;
	ids: GroupIds;
	error?: string;
	labelHidden: boolean;
	children: ReactNode;
}

/** Render a field's own label above its input and its hint and error below. */
function LabelledGroup( { field, layout, ids, error, labelHidden, children }: LabelledGroupProps ) {
	return (
		<FieldLayout field={field} layout={layout} group>
			<div className="fieldset">
				{!layout.custom && field.label && (
					<span id={ids.labelId} className={`fieldset-legend ${labelHidden ? 'sr-only' : ''}`}>
						{field.label}
						{field.is_required && <span className="text-error ml-1">*</span>}
					</span>
				)}
				{children}
				{!layout.custom && ids.hintId && (
					<p id={ids.hintId} className="fieldset-label">{field.help_text}</p>
				)}
				{error && (
					<p id={ids.errorId} className="fieldset-label text-error" role="alert">{error}</p>
				)}
			</div>
		</FieldLayout>
	);
}

/**
 * Renders a slider.
 */
export function SliderField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const settings = getSliderSettings( field );
	const current = getInitialSliderValue( field, value );
	const readOnly = isReadOnlyField( field );
	const layout = getFieldLayout( field, displayConfig );
	const ids = getGroupIds( field, layout, error );
	const inputId = layout.custom ? layout.inputId : `field-${field.name}`;
	const text = formatSliderValue( field, current );

	return (
		<LabelledGroup field={field} layout={layout} ids={ids} error={error} labelHidden={displayConfig.label_position === 'hidden'}>
			<div className="flex items-center gap-4">
				<input
					type="range"
					id={inputId}
					name={field.name}
					min={settings.min}
					max={settings.max}
					step={settings.step}
					value={current}
					className={`range range-primary flex-1 ${field.css_classes ?? ''}`}
					aria-labelledby={ids.labelId}
					aria-label={ids.labelId ? undefined : field.name}
					aria-valuetext={text}
					aria-describedby={ids.describedBy}
					aria-required={field.is_required ? 'true' : undefined}
					aria-readonly={readOnly ? 'true' : undefined}
					aria-invalid={error ? true : undefined}
					disabled={readOnly}
					onChange={( e ) => onChange( e.target.value )}
				/>
				{settings.show_value && (
					<output htmlFor={inputId} className="min-w-16 text-right font-medium tabular-nums" aria-hidden="true">{text}</output>
				)}
			</div>
			<div className="mt-1 flex justify-between text-xs text-base-content/60" aria-hidden="true">
				<span>{formatSliderValue( field, settings.min )}</span>
				<span>{formatSliderValue( field, settings.max )}</span>
			</div>
		</LabelledGroup>
	);
}

/**
 * Renders a Net Promoter Score scale.
 */
export function NpsField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const labels = getNpsLabels( field );
	const readOnly = isReadOnlyField( field );
	const layout = getFieldLayout( field, displayConfig );
	const ids = getGroupIds( field, layout, error );
	const endsId = `field-${field.name}-ends`;
	const selected = value === null || value === undefined || value === '' ? undefined : String( value );

	return (
		<LabelledGroup field={field} layout={layout} ids={ids} error={error} labelHidden={displayConfig.label_position === 'hidden'}>
			<div className={`flex flex-col gap-1 ${field.css_classes ?? ''}`}>
				<div
					role="radiogroup"
					className="grid grid-cols-6 gap-1 sm:grid-cols-11"
					aria-labelledby={ids.labelId}
					aria-label={ids.labelId ? undefined : field.name}
					aria-describedby={[endsId, ids.describedBy].filter( Boolean ).join( ' ' )}
					aria-required={field.is_required ? 'true' : undefined}
					aria-readonly={readOnly ? 'true' : undefined}
					aria-invalid={error ? true : undefined}
				>
					{NPS_SCORES.map( ( score ) => (
						<label key={score} className="cursor-pointer">
							<input
								type="radio"
								id={`field-${field.name}-${score}`}
								name={field.name}
								value={String( score )}
								className="peer sr-only"
								aria-label={getNpsScoreLabel( field, score )}
								checked={selected === String( score )}
								disabled={readOnly}
								onChange={() => onChange( String( score ) )}
							/>
							<span
								aria-hidden="true"
								className={`btn btn-sm btn-outline w-full tabular-nums peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-content peer-disabled:cursor-not-allowed ${SCORE_FOCUS_CLASSES}`}
							>
								{score}
							</span>
						</label>
					) )}
				</div>
				<div id={endsId} className="flex justify-between gap-4 text-xs text-base-content/70">
					<span>{t( ':score = :label', { score: NPS_MIN_SCORE, label: labels.low } )}</span>
					<span className="text-right">{t( ':score = :label', { score: NPS_MAX_SCORE, label: labels.high } )}</span>
				</div>
			</div>
		</LabelledGroup>
	);
}

/**
 * Renders a Likert / matrix field: a table of statements against a scale,
 * or a single scale.
 */
export function MatrixField( props: FieldComponentProps ) {
	if ( isSingleScale( props.field ) ) {
		return <SingleScaleField {...props} />;
	}

	const { field, value, error, subErrors = {}, onChange, displayConfig } = props;
	const columns = getMatrixColumns( field );
	const rows = getMatrixRows( field );
	const answers = toMatrixAnswers( field, value );
	const layout = getFieldLayout( field, displayConfig );
	const hiddenLabel = layout.label === 'hidden' || ( !layout.custom && displayConfig.label_position === 'hidden' );
	const rowErrorCount = Object.keys( subErrors ).length;
	const hasErrors = Boolean( error ) || rowErrorCount > 0;
	const hintId = field.help_text ? `${field.name}-hint` : undefined;
	const errorId = hasErrors ? `${field.name}-error` : undefined;
	const describedBy = [hintId, errorId].filter( Boolean ).join( ' ' ) || undefined;
	const hint = hintId ? <p id={hintId} className="fieldset-label">{field.help_text}</p> : null;
	const summary = error || ( rowErrorCount === 1
		? t( 'Please answer :count highlighted row in :field.', { count: rowErrorCount, field: field.label ?? field.name } )
		: t( 'Please answer :count highlighted rows in :field.', { count: rowErrorCount, field: field.label ?? field.name } ) );

	return (
		<fieldset id={`field-${field.name}`} className={`fieldset ${field.css_classes ?? ''}`} aria-describedby={describedBy}>
			{field.label && (
				<legend className={`fieldset-legend ${hiddenLabel ? 'sr-only' : ''}`}>
					{field.label}
					{field.is_required && <span className="text-error ml-1" aria-hidden="true">*</span>}
				</legend>
			)}

			{layout.description === 'above' && hint}

			<div className="overflow-x-auto">
				<table className="table table-sm">
					<thead>
						<tr>
							<td><span className="sr-only">{t( 'Statement' )}</span></td>
							{columns.map( ( column, index ) => (
								<th key={column.value} scope="col" id={`field-${field.name}-col-${index}`} className="text-center align-bottom font-medium">
									{column.label}
								</th>
							) )}
						</tr>
					</thead>
					<tbody>
						{rows.map( ( row ) => {
							const rowId = `field-${field.name}-row-${row.key}`;
							const rowError = subErrors[row.key];

							return (
								<tr key={row.key}>
									<th scope="row" className="min-w-40 font-normal">
										<span id={rowId}>{row.label}</span>
										{rowError && <span id={`${rowId}-error`} className="block text-xs text-error">{rowError}</span>}
									</th>
									{columns.map( ( column, index ) => (
										<td key={column.value} className="text-center">
											<input
												type="radio"
												id={`${rowId}-${index}`}
												name={`${field.name}[${row.key}]`}
												value={column.value}
												className="radio radio-primary"
												aria-labelledby={`${rowId} field-${field.name}-col-${index}`}
												aria-invalid={rowError ? true : undefined}
												aria-describedby={rowError ? `${rowId}-error` : undefined}
												checked={answers[row.key] === column.value}
												onChange={() => onChange( { ...answers, [row.key]: column.value } )}
											/>
										</td>
									) )}
								</tr>
							);
						} )}
					</tbody>
				</table>
			</div>

			{layout.description !== 'above' && hint}

			{hasErrors && (
				<p id={errorId} className="fieldset-label text-error" role="alert">{summary}</p>
			)}
		</fieldset>
	);
}

/**
 * Renders a matrix field in single mode: one radio group of the scale.
 */
function SingleScaleField( { field, value, error, onChange, displayConfig }: FieldComponentProps ) {
	const layout = getFieldLayout( field, displayConfig );
	const ids = getGroupIds( field, layout, error );
	const selected = typeof value === 'string' ? value : '';

	return (
		<LabelledGroup field={field} layout={layout} ids={ids} error={error} labelHidden={displayConfig.label_position === 'hidden'}>
			<div
				role="radiogroup"
				className={`flex flex-wrap gap-x-6 gap-y-2 ${field.css_classes ?? ''}`}
				aria-labelledby={ids.labelId}
				aria-label={ids.labelId ? undefined : field.name}
				aria-describedby={ids.describedBy}
				aria-required={field.is_required ? 'true' : undefined}
				aria-invalid={error ? true : undefined}
			>
				{getMatrixColumns( field ).map( ( column, index ) => (
					<label key={column.value} className="label cursor-pointer justify-start gap-2">
						<input
							type="radio"
							id={`field-${field.name}-${index}`}
							name={field.name}
							value={column.value}
							className="radio radio-primary"
							checked={selected === column.value}
							onChange={() => onChange( column.value )}
						/>
						<span className="label-text">{column.label}</span>
					</label>
				) )}
			</div>
		</LabelledGroup>
	);
}
