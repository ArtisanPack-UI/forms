/**
 * Options editor for choice fields.
 *
 * Edits a choice field's options: label, value and whether each starts
 * selected (one for a radio or select, any number for a checkbox group or
 * multi-select), plus a bulk editor that takes one `value|label` per line and
 * can insert a predefined list. A field that loads its choices from a source
 * shows them read-only instead. With image choices on, each option also
 * takes an image URL, an upload and alt text (since 1.6.0).
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import React, { useRef, useState } from 'react';

import { Button, Input, Select, Textarea } from '@artisanpack-ui/react';

import {
	applyBulkChoices,
	getChoiceImageUrl,
	getChoicesSource,
	isMultipleChoiceField,
	parseBulkChoices,
	setOptionDefault,
	showsImageChoices,
	toBulkChoices,
} from '../../../shared/choices';
import { t } from '../../../shared/i18n';
import type { FieldOption, FormField, PredefinedChoiceList } from '../../../types/artisanpack-forms';

/** Props for the OptionsEditor component. */
export interface OptionsEditorProps {
	/** The choice field being edited. */
	field: FormField;
	/** The field's stored options. */
	options: FieldOption[];
	/** Called with the new options list. */
	onChange: ( options: FieldOption[] ) => void;
	/** The lists the bulk editor can insert (see the `fields/predefined-choices` endpoint). */
	predefinedChoices?: PredefinedChoiceList[];
	/** Stores an image choice's image and resolves to its URL (since 1.6.0). */
	uploadImage?: ( file: File ) => Promise<string>;
}

/**
 * Renders the options editor.
 */
export function OptionsEditor( { field, options, onChange, predefinedChoices = [], uploadImage }: OptionsEditorProps ): React.ReactElement {
	const [bulkOpen, setBulkOpen] = useState( false );
	const [uploading, setUploading] = useState<number | null>( null );
	const [uploadError, setUploadError] = useState( '' );
	// The latest options, so an upload that finishes later keeps edits made while it ran
	const latestOptions = useRef( options );
	latestOptions.current = options;
	const showsImages = showsImageChoices( field );
	const [bulkText, setBulkText] = useState( '' );
	const [selectedList, setSelectedList] = useState( '' );
	const multiple = isMultipleChoiceField( field );
	// A ranking's options are its choices to order, none selected (since 1.6.0).
	const hasDefaults = field.type !== 'ranking';
	const source = getChoicesSource( field );
	const bulkId = `bulk-choices-${field.id}`;

	if ( source ) {
		const sourceLabel = field.settings_schema
			?.find( ( setting ) => setting.key === 'choices_source' )
			?.options?.find( ( option ) => option.value === source )?.label ?? source;

		return (
			<div className="space-y-2">
				<span className="label-text font-medium">{t( 'Options' )}</span>
				<div className="rounded-lg bg-base-200 p-3 text-xs text-base-content/70">
					{t( 'Choices are loaded from ":source" when the form is shown. Change "Load Choices From" to edit the options list.', { source: sourceLabel } )}
				</div>
				{field.options && field.options.length > 0 && (
					<ul className="max-h-48 space-y-1 overflow-auto text-xs" aria-label={t( 'Loaded choices' )}>
						{field.options.map( ( option ) => (
							<li key={option.value} className="rounded bg-base-100 px-2 py-1">{option.label}</li>
						) )}
					</ul>
				)}
			</div>
		);
	}

	const updateOption = ( index: number, key: 'label' | 'value' | 'image' | 'image_alt', value: string ): void => {
		onChange( options.map( ( option, i ) => ( i === index ? { ...option, [key]: value } : option ) ) );
	};

	const removeImage = ( index: number ): void => {
		onChange( options.map( ( option, i ) => {
			if ( i !== index ) {
				return option;
			}

			const { image: _image, image_alt: _alt, ...rest } = option;

			return rest as FieldOption;
		} ) );
	};

	const handleUpload = async ( index: number, file: File | undefined ): Promise<void> => {
		if ( !file || !uploadImage ) {
			return;
		}

		setUploading( index );
		setUploadError( '' );

		try {
			const url = await uploadImage( file );

			onChange( latestOptions.current.map( ( option, i ) => ( i === index ? { ...option, image: url } : option ) ) );
		} catch ( err ) {
			setUploadError( err instanceof Error ? err.message : t( 'The image could not be stored.' ) );
		} finally {
			setUploading( null );
		}
	};

	const openBulk = (): void => {
		setBulkText( toBulkChoices( options ) );
		setBulkOpen( true );
	};

	const applyBulk = ( mode: 'replace' | 'append' ): void => {
		onChange( applyBulkChoices( options, parseBulkChoices( bulkText ), mode ) );
		setBulkOpen( false );
	};

	return (
		<div className="space-y-2">
			<span className="label-text font-medium">{t( 'Options' )}</span>
			{options.map( ( option, index ) => (
				<div key={index} className={showsImages ? 'space-y-2 rounded-lg bg-base-200 p-2' : undefined}>
				<div className="flex items-center gap-2">
					{hasDefaults && (
						<input
							type="checkbox"
							className="checkbox checkbox-xs checkbox-primary"
							checked={option.default === true}
							title={t( 'Selected by default' )}
							aria-label={t( 'Selected by default: :label', { label: option.label || option.value } )}
							onChange={( e ) => onChange( setOptionDefault( options, index, e.target.checked, multiple ) )}
						/>
					)}
					<Input
						placeholder={t( 'Label' )}
						aria-label={t( 'Option :number label', { number: index + 1 } )}
						value={option.label}
						onChange={( e ) => updateOption( index, 'label', e.target.value )}
						className="flex-1"
					/>
					<Input
						placeholder={t( 'Value' )}
						aria-label={t( 'Option :number value', { number: index + 1 } )}
						value={option.value}
						onChange={( e ) => updateOption( index, 'value', e.target.value )}
						className="flex-1"
					/>
					<Button
						color="ghost"
						size="sm"
						className="btn-circle"
						onClick={() => onChange( options.filter( ( _, i ) => i !== index ) )}
						title={t( 'Remove option' )}
						aria-label={t( 'Remove option' )}
					>
						&times;
					</Button>
				</div>
				{showsImages && (
					<div className="flex items-start gap-2">
						<div className="h-12 w-12 shrink-0 overflow-hidden rounded bg-base-300">
							{getChoiceImageUrl( option ) && <img src={getChoiceImageUrl( option )} alt="" className="h-full w-full object-cover" />}
						</div>
						<div className="flex-1 space-y-1">
							<Input
								type="url"
								placeholder="https://"
								aria-label={t( 'Image URL: :label', { label: option.label || option.value } )}
								value={typeof option.image === 'string' ? option.image : ''}
								onChange={( e ) => updateOption( index, 'image', e.target.value )}
							/>
							<Input
								placeholder={t( 'Describe the image' )}
								aria-label={t( 'Image alt text: :label', { label: option.label || option.value } )}
								value={typeof option.image_alt === 'string' ? option.image_alt : ''}
								onChange={( e ) => updateOption( index, 'image_alt', e.target.value )}
							/>
							<div className="flex flex-wrap gap-1">
								{uploadImage && (
									<label className={`btn btn-ghost btn-xs focus-within:outline focus-within:outline-2 focus-within:outline-primary ${uploading === index ? 'btn-disabled' : ''}`}>
										{uploading === index ? t( 'Uploading…' ) : t( 'Upload' )}
										<input
											type="file"
											className="sr-only"
											accept="image/jpeg,image/png,image/gif,image/webp"
											aria-label={t( 'Upload an image: :label', { label: option.label || option.value } )}
											disabled={uploading !== null}
											onChange={( e ) => {
												void handleUpload( index, e.target.files?.[0] );
												e.target.value = '';
											}}
										/>
									</label>
								)}
								{typeof option.image === 'string' && option.image !== '' && (
									<Button color="ghost" size="sm" className="text-error" onClick={() => removeImage( index )}>
										{t( 'Remove image' )}
									</Button>
								)}
							</div>
						</div>
					</div>
				)}
				</div>
			) )}
			{showsImages && uploadError && (
				<p className="text-xs text-error" role="alert">{uploadError}</p>
			)}
			{showsImages && (
				<p className="text-xs text-base-content/60">
					{t( 'The option label stays the choice\'s name for screen readers; the alt text describes what the image shows.' )}
				</p>
			)}
			{hasDefaults && options.length > 0 && (
				<p className="text-xs text-base-content/60">
					{multiple ? t( 'Check the options that start selected.' ) : t( 'Check the option that starts selected.' )}
				</p>
			)}
			<div className="flex flex-wrap gap-2">
				<Button
					color="ghost"
					size="sm"
					onClick={() => {
						// The highest `option_N` suffix plus one, so a removal cannot cause a duplicate value.
						const next = options.reduce( ( max, option ) => {
							const match = /^option_(\d+)$/.exec( option.value );

							return match ? Math.max( max, Number( match[1] ) ) : max;
						}, 0 ) + 1;

						onChange( [...options, { label: t( 'Option :number', { number: next } ), value: `option_${next}` }] );
					}}
				>
					{t( 'Add Option' )}
				</Button>
				<Button
					color="ghost"
					size="sm"
					aria-expanded={bulkOpen}
					aria-controls={bulkId}
					onClick={() => ( bulkOpen ? setBulkOpen( false ) : openBulk() )}
				>
					{t( 'Bulk Add / Predefined Choices' )}
				</Button>
			</div>

			{bulkOpen && (
				<div id={bulkId} className="space-y-2 rounded-lg bg-base-200 p-3">
					{predefinedChoices.length > 0 && (
						<div className="flex items-end gap-2">
							<Select
								label={t( 'Predefined Choices' )}
								value={selectedList}
								placeholder={t( 'Choose a list...' )}
								options={predefinedChoices.map( ( list ) => ( { value: list.key, label: list.label } ) )}
								optionValue="value"
								optionLabel="label"
								onChange={( e ) => setSelectedList( e.target.value )}
								className="flex-1"
							/>
							<Button
								size="sm"
								disabled={!selectedList}
								onClick={() => {
									const list = predefinedChoices.find( ( entry ) => entry.key === selectedList );

									if ( list ) {
										setBulkText( toBulkChoices( list.choices ) );
									}
								}}
							>
								{t( 'Insert' )}
							</Button>
						</div>
					)}
					<Textarea
						label={t( 'Choices' )}
						rows={8}
						className="font-mono text-xs"
						value={bulkText}
						onChange={( e ) => setBulkText( e.target.value )}
						hint={t( 'One choice per line. Use value|label to store a value that differs from its label.' )}
					/>
					<div className="flex flex-wrap gap-2">
						<Button color="primary" size="sm" onClick={() => applyBulk( 'replace' )}>{t( 'Replace Options' )}</Button>
						<Button size="sm" onClick={() => applyBulk( 'append' )}>{t( 'Add to Options' )}</Button>
						<Button color="ghost" size="sm" onClick={() => setBulkOpen( false )}>{t( 'Cancel' )}</Button>
					</div>
				</div>
			)}
		</div>
	);
}
