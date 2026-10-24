/**
 * Sub-input editor for composite fields.
 *
 * A table that turns each of a composite field's sub-inputs on or off,
 * relabels it, sets its placeholder, default value and population parameter
 * (since 1.6.0), and whether a required field requires it. A select
 * sub-input's default is one of its options. The last shown input cannot be
 * turned off. Mirrors the Livewire builder's `sub-inputs-editor` partial.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import React from 'react';

import { buildSubInputUpdate, getEnabledSubInputs, sanitizeSubInputParameter } from '../../../shared/composite';
import type { SubInputSetting } from '../../../shared/composite';
import { t } from '../../../shared/i18n';
import type { FormField, UpdateFieldRequest } from '../../../types/artisanpack-forms';

/** Props for the SubInputsEditor component. */
export interface SubInputsEditorProps {
	/** The composite field being edited. */
	field: FormField;
	/**
	 * Called with the field update for one change, and the change itself so
	 * the editor can show it before the server answers.
	 */
	onChange: ( data: UpdateFieldRequest, key: string, setting: SubInputSetting, value: unknown ) => void;
}

/**
 * Renders the sub-input table.
 */
export function SubInputsEditor( { field, onChange }: SubInputsEditorProps ): React.ReactElement {
	const enabledCount = getEnabledSubInputs( field ).length;
	const labelId = `sub-inputs-label-${field.id}`;
	const hintId = `sub-inputs-hint-${field.id}`;

	const change = ( key: string, setting: SubInputSetting, value: unknown ): void => {
		const update = buildSubInputUpdate( field, key, setting, value );

		if ( update ) {
			onChange( update, key, setting, value );
		}
	};

	return (
		<div className="space-y-1">
			<span id={labelId} className="label-text font-medium">{t( 'Inputs' )}</span>
			<div className="overflow-x-auto">
				<table className="table table-xs" aria-labelledby={labelId} aria-describedby={hintId}>
					<thead>
						<tr>
							<th scope="col">{t( 'Show' )}</th>
							<th scope="col">{t( 'Sub-Label' )}</th>
							<th scope="col">{t( 'Placeholder' )}</th>
							<th scope="col">{t( 'Default' )}</th>
							<th scope="col">{t( 'Parameter' )}</th>
							<th scope="col">{t( 'Required' )}</th>
						</tr>
					</thead>
					<tbody>
						{( field.sub_inputs ?? [] ).map( ( subInput ) => (
							<tr key={`${field.id}-${subInput.key}`}>
								<td>
									<input
										type="checkbox"
										className="checkbox checkbox-xs checkbox-primary"
										aria-label={t( 'Show :input', { input: subInput.default_label } )}
										checked={subInput.enabled}
										disabled={subInput.enabled && enabledCount === 1}
										onChange={( e ) => change( subInput.key, 'enabled', e.target.checked )}
									/>
								</td>
								<td>
									<input
										type="text"
										className="input input-xs input-bordered w-full"
										aria-label={t( ':input sub-label', { input: subInput.default_label } )}
										placeholder={subInput.default_label}
										defaultValue={subInput.label === subInput.default_label ? '' : subInput.label}
										maxLength={255}
										onBlur={( e ) => change( subInput.key, 'label', e.target.value )}
									/>
								</td>
								<td>
									<input
										type="text"
										className="input input-xs input-bordered w-full"
										aria-label={t( ':input placeholder', { input: subInput.default_label } )}
										defaultValue={subInput.placeholder ?? ''}
										maxLength={255}
										onBlur={( e ) => change( subInput.key, 'placeholder', e.target.value )}
									/>
								</td>
								<td>
									{subInput.type === 'select' ? (
										<select
											className="select select-xs select-bordered w-full"
											aria-label={t( ':input default value', { input: subInput.default_label } )}
											defaultValue={subInput.default ?? ''}
											onChange={( e ) => change( subInput.key, 'default', e.target.value )}
										>
											<option value="">{t( 'None' )}</option>
											{( subInput.options ?? [] ).map( ( option ) => (
												<option key={option.value} value={option.value}>{option.label}</option>
											) )}
										</select>
									) : (
										<input
											type="text"
											className="input input-xs input-bordered w-full"
											aria-label={t( ':input default value', { input: subInput.default_label } )}
											defaultValue={subInput.default ?? ''}
											maxLength={255}
											onBlur={( e ) => change( subInput.key, 'default', e.target.value )}
										/>
									)}
								</td>
								<td>
									<input
										type="text"
										className="input input-xs input-bordered w-full font-mono"
										aria-label={t( ':input population parameter', { input: subInput.default_label } )}
										defaultValue={subInput.parameter ?? ''}
										maxLength={100}
										onBlur={( e ) => {
											// Show the parameter as it is saved: letters, digits, `_`, `.` and `-`.
											e.target.value = sanitizeSubInputParameter( e.target.value.trim() );
											change( subInput.key, 'parameter', e.target.value );
										}}
									/>
								</td>
								<td>
									<input
										type="checkbox"
										className="checkbox checkbox-xs checkbox-primary"
										aria-label={t( 'Require :input', { input: subInput.default_label } )}
										checked={subInput.required}
										onChange={( e ) => change( subInput.key, 'required', e.target.checked )}
									/>
								</td>
							</tr>
						) )}
					</tbody>
				</table>
			</div>
			<p id={hintId} className="text-xs text-base-content/60">
				{t( 'Required inputs apply when the field is required. With none checked, every shown input is required.' )}
				{' '}
				{t( 'A parameter fills the input from the renderer prefill or the query string.' )}
			</p>
		</div>
	);
}
