/**
 * FieldEditor component for per-field settings.
 *
 * Adapts its UI based on the selected field type, showing relevant
 * configuration options like validation rules, options editor for
 * choice fields, file upload settings, and conditional logic.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import { Button, Checkbox, Input, Select, Tabs, Textarea } from '@artisanpack-ui/react';

import type {
	ConditionalLogic,
	FieldOption,
	FieldType,
	FieldWidth,
	FormField,
	UpdateFieldRequest,
	ValidationRules,
} from '../../../types/artisanpack-forms';

import { ConditionalLogicEditor } from './ConditionalLogicEditor';

/** Safely parse a numeric input value, returning undefined for empty or NaN. */
function parseNumericInput( value: string ): number | undefined {
	if ( !value ) {
		return undefined;
	}

	const num = Number( value );

	return Number.isNaN( num ) ? undefined : num;
}

/** Field types that support an options list. */
const OPTION_FIELD_TYPES: Set<FieldType> = new Set( [
	'select',
	'radio',
	'checkbox_group',
	'select_multiple',
] );

/** Field types that don't collect user data. */
const LAYOUT_FIELD_TYPES: Set<FieldType> = new Set( [
	'heading',
	'paragraph',
	'divider',
	'html',
] );

/** Available width options. */
const WIDTH_OPTIONS: Array<{ value: FieldWidth; label: string }> = [
	{ value: 'full', label: 'Full Width' },
	{ value: 'half', label: 'Half (1/2)' },
	{ value: 'third', label: 'Third (1/3)' },
	{ value: 'two-thirds', label: 'Two-Thirds (2/3)' },
];

/** Props for the FieldEditor component. */
export interface FieldEditorProps {
	/** The field being edited. */
	field: FormField;
	/** All fields in the form (for conditional logic field references). */
	allFields: FormField[];
	/** Callback when a field property changes. */
	onChange: ( fieldId: number, data: UpdateFieldRequest ) => void;
	/** Callback to delete the field. */
	onDelete: ( fieldId: number ) => void;
	/** Callback to duplicate the field. */
	onDuplicate: ( fieldId: number ) => void;
	/** Callback to close the editor panel. */
	onClose: () => void;
	/** Optional CSS class name. */
	className?: string;
}

/**
 * Per-field settings editor panel.
 *
 * @example
 * ```tsx
 * <FieldEditor
 *   field={selectedField}
 *   allFields={form.fields}
 *   onChange={(id, data) => updateField(id, data)}
 *   onDelete={(id) => deleteField(id)}
 *   onDuplicate={(id) => duplicateField(id)}
 *   onClose={() => setSelectedField(null)}
 * />
 * ```
 */
export function FieldEditor( {
	field: serverField,
	allFields,
	onChange,
	onDelete,
	onDuplicate,
	onClose,
	className,
}: FieldEditorProps ): React.ReactElement {
	const [activeTab, setActiveTab] = useState<string>( 'general' );

	// Mirror the field locally so every keystroke is reflected immediately
	// — the parent re-fetches on the debounced save, so binding inputs to
	// the prop directly would race the API response and swallow characters
	// the user typed during that window. Reset the mirror when the user
	// selects a different field.
	const [field, setField] = useState<FormField>( serverField );

	useEffect( () => {
		setField( serverField );
	}, [serverField.id] );

	const isLayoutField = LAYOUT_FIELD_TYPES.has( field.type );
	const hasOptions = OPTION_FIELD_TYPES.has( field.type );

	const updateTimerRef = useRef<ReturnType<typeof setTimeout> | null>( null );
	const pendingUpdatesRef = useRef<UpdateFieldRequest>( {} );

	const updateField = useCallback(
		( data: UpdateFieldRequest ) => {
			setField( ( prev ) => ( { ...prev, ...data } as FormField ) );
			pendingUpdatesRef.current = { ...pendingUpdatesRef.current, ...data };

			if ( updateTimerRef.current ) {
				clearTimeout( updateTimerRef.current );
			}

			updateTimerRef.current = setTimeout( () => {
				onChange( serverField.id, pendingUpdatesRef.current );
				pendingUpdatesRef.current = {};
			}, 500 );
		},
		[serverField.id, onChange],
	);

	useEffect( () => {
		return () => {
			if ( updateTimerRef.current ) {
				clearTimeout( updateTimerRef.current );

				if ( Object.keys( pendingUpdatesRef.current ).length > 0 ) {
					onChange( serverField.id, pendingUpdatesRef.current );
					pendingUpdatesRef.current = {};
				}
			}
		};
	}, [serverField.id, onChange] );

	// Options editor for choice fields
	const options = useMemo( (): FieldOption[] => {
		if ( !hasOptions ) {
			return [];
		}

		return ( field.field_config as { options?: FieldOption[] } )?.options ?? field.options ?? [];
	}, [field, hasOptions] );

	const handleAddOption = useCallback( () => {
		const newOptions = [
			...options,
			{ label: `Option ${options.length + 1}`, value: `option_${options.length + 1}` },
		];
		updateField( { field_config: { options: newOptions } } );
	}, [options, updateField] );

	const handleUpdateOption = useCallback(
		( index: number, key: 'label' | 'value', value: string ) => {
			const newOptions = options.map( ( opt, i ) =>
				i === index ? { ...opt, [key]: value } : opt,
			);
			updateField( { field_config: { options: newOptions } } );
		},
		[options, updateField],
	);

	const handleRemoveOption = useCallback(
		( index: number ) => {
			const newOptions = options.filter( ( _, i ) => i !== index );
			updateField( { field_config: { options: newOptions } } );
		},
		[options, updateField],
	);

	// Validation rules
	const validationRules = useMemo(
		(): ValidationRules => field.validation_rules ?? {},
		[field.validation_rules],
	);

	const updateValidation = useCallback(
		( rules: Partial<ValidationRules> ) => {
			updateField( { validation_rules: { ...validationRules, ...rules } } );
		},
		[validationRules, updateField],
	);

	// Build tabs array — Tabs component uses `name` (not `id`) as the key
	const tabs = useMemo( () => {
		const tabItems = [
			{
				name: 'general',
				label: 'General',
				content: (
					<GeneralTabContent
						field={field}
						isLayoutField={isLayoutField}
						hasOptions={hasOptions}
						options={options}
						validationRules={validationRules}
						updateField={updateField}
						updateValidation={updateValidation}
						handleAddOption={handleAddOption}
						handleUpdateOption={handleUpdateOption}
						handleRemoveOption={handleRemoveOption}
					/>
				),
			},
		];

		if ( !isLayoutField ) {
			tabItems.push( {
				name: 'validation',
				label: 'Validation',
				content: (
					<ValidationTabContent
						field={field}
						validationRules={validationRules}
						updateValidation={updateValidation}
					/>
				),
			} );
			tabItems.push( {
				name: 'conditional',
				label: 'Conditional',
				content: (
					<ConditionalLogicEditor
						value={field.conditional_logic}
						onChange={( logic ) => updateField( {
							conditional_logic: logic as ConditionalLogic | null,
						} )}
						fields={allFields}
						excludeFieldId={field.id}
					/>
				),
			} );
		}

		return tabItems;
	}, [
		field, isLayoutField, hasOptions, options, validationRules,
		allFields, updateField, updateValidation,
		handleAddOption, handleUpdateOption, handleRemoveOption,
	] );

	return (
		<div className={`flex flex-col gap-0 ${className ?? ''}`}>
			{/* Header */}
			<div className="flex items-center justify-between p-3 border-b border-base-300">
				<h3 className="text-sm font-semibold">
					Edit {field.type.replace( '_', ' ' )} Field
				</h3>
				<Button
					color="ghost"
					size="sm"
					className="btn-circle"
					onClick={onClose}
					aria-label="Close field editor"
				>
					&times;
				</Button>
			</div>

			{/* Tabs */}
			<Tabs
				variant="bordered"
				tabs={tabs}
				activeTab={activeTab}
				onChange={setActiveTab}
			/>

			{/* Footer actions */}
			<div className="flex gap-2 justify-end p-3 border-t border-base-300">
				<Button
					color="ghost"
					size="sm"
					onClick={() => onDuplicate( field.id )}
				>
					Duplicate
				</Button>
				<Button
					color="error"
					size="sm"
					onClick={() => {
						if ( window.confirm( 'Are you sure you want to delete this field?' ) ) {
							onDelete( field.id );
						}
					}}
				>
					Delete
				</Button>
			</div>
		</div>
	);
}

// ---------------------------------------------------------------------------
// GeneralTabContent (inline sub-component)
// ---------------------------------------------------------------------------

interface GeneralTabContentProps {
	field: FormField;
	isLayoutField: boolean;
	hasOptions: boolean;
	options: FieldOption[];
	validationRules: ValidationRules;
	updateField: ( data: UpdateFieldRequest ) => void;
	updateValidation: ( rules: Partial<ValidationRules> ) => void;
	handleAddOption: () => void;
	handleUpdateOption: ( index: number, key: 'label' | 'value', value: string ) => void;
	handleRemoveOption: ( index: number ) => void;
}

function GeneralTabContent( {
	field,
	isLayoutField,
	hasOptions,
	options,
	validationRules,
	updateField,
	updateValidation,
	handleAddOption,
	handleUpdateOption,
	handleRemoveOption,
}: GeneralTabContentProps ): React.ReactElement {
	return (
		<div className="space-y-4 p-4">
			{/* Label */}
			<Input
				label="Label"
				value={field.label ?? ''}
				onChange={( e ) => updateField( { label: e.target.value } )}
			/>

			{/* Name (machine name) */}
			<Input
				label="Name"
				value={field.name}
				onChange={( e ) => updateField( { name: e.target.value } )}
				hint="Machine-readable name. Used as the form data key."
			/>

			{/* Placeholder (non-layout only) */}
			{!isLayoutField && field.type !== 'checkbox' && field.type !== 'radio' && (
				<Input
					label="Placeholder"
					value={field.placeholder ?? ''}
					onChange={( e ) => updateField( { placeholder: e.target.value || null } )}
				/>
			)}

			{/* Help text */}
			{!isLayoutField && (
				<Input
					label="Help Text"
					value={field.help_text ?? ''}
					onChange={( e ) => updateField( { help_text: e.target.value || null } )}
				/>
			)}

			{/* Default value */}
			{!isLayoutField && field.type !== 'file' && (
				<Input
					label="Default Value"
					value={field.default_value ?? ''}
					onChange={( e ) => updateField( { default_value: e.target.value || null } )}
				/>
			)}

			{/* Required toggle */}
			{!isLayoutField && (
				<Checkbox
					label="Required"
					checked={field.is_required}
					onChange={( e ) => updateField( { is_required: e.target.checked } )}
				/>
			)}

			{/* Width */}
			<Select
				label="Width"
				value={field.width}
				options={WIDTH_OPTIONS}
				optionValue="value"
				optionLabel="label"
				onChange={( e ) => updateField( { width: e.target.value as FieldWidth } )}
			/>

			{/* CSS Classes */}
			<Input
				label="CSS Classes"
				value={field.css_classes ?? ''}
				onChange={( e ) => updateField( { css_classes: e.target.value || null } )}
			/>

			{/* HTML Content (for html field type) */}
			{field.type === 'html' && (
				<Textarea
					label="HTML Content"
					rows={6}
					value={field.default_value ?? ''}
					onChange={( e ) => updateField( { default_value: e.target.value } )}
				/>
			)}

			{/* Heading/Paragraph content */}
			{( field.type === 'heading' || field.type === 'paragraph' ) && (
				<>
					{field.type === 'heading' ? (
						<Input
							label="Content"
							value={field.default_value ?? ''}
							onChange={( e ) => updateField( { default_value: e.target.value } )}
						/>
					) : (
						<Textarea
							label="Content"
							rows={4}
							value={field.default_value ?? ''}
							onChange={( e ) => updateField( { default_value: e.target.value } )}
						/>
					)}
				</>
			)}

			{/* Options editor for choice fields */}
			{hasOptions && (
				<div className="space-y-2">
					<label className="label">
						<span className="label-text font-medium">Options</span>
					</label>
					{options.map( ( option, index ) => (
						<div key={index} className="flex items-center gap-2">
							<Input
								placeholder="Label"
								value={option.label}
								onChange={( e ) => handleUpdateOption( index, 'label', e.target.value )}
								className="flex-1"
							/>
							<Input
								placeholder="Value"
								value={option.value}
								onChange={( e ) => handleUpdateOption( index, 'value', e.target.value )}
								className="flex-1"
							/>
							<Button
								color="ghost"
								size="sm"
								className="btn-circle"
								onClick={() => handleRemoveOption( index )}
								title="Remove option"
							>
								&times;
							</Button>
						</div>
					) )}
					<Button
						color="ghost"
						size="sm"
						onClick={handleAddOption}
					>
						Add Option
					</Button>
				</div>
			)}

			{/* File field config */}
			{field.type === 'file' && (
				<div className="space-y-4">
					<Input
						label="Max File Size (KB)"
						type="number"
						min={0}
						value={validationRules.max_size ?? ''}
						onChange={( e ) => updateValidation( {
							max_size: parseNumericInput( e.target.value ),
						} )}
					/>
					<Input
						label="Allowed File Types (comma-separated MIME types)"
						value={validationRules.allowed_types?.join( ', ' ) ?? ''}
						onChange={( e ) => updateValidation( {
							allowed_types: e.target.value
								? e.target.value.split( ',' ).map( ( s ) => s.trim() )
								: undefined,
						} )}
					/>
				</div>
			)}
		</div>
	);
}

// ---------------------------------------------------------------------------
// ValidationTabContent (inline sub-component)
// ---------------------------------------------------------------------------

interface ValidationTabContentProps {
	field: FormField;
	validationRules: ValidationRules;
	updateValidation: ( rules: Partial<ValidationRules> ) => void;
}

function ValidationTabContent( {
	field,
	validationRules,
	updateValidation,
}: ValidationTabContentProps ): React.ReactElement {
	return (
		<div className="space-y-4 p-4">
			{/* Min/Max for text fields */}
			{( field.type === 'text' || field.type === 'email' || field.type === 'url' ||
				field.type === 'textarea' || field.type === 'phone' ) && (
				<>
					<Input
						label="Min Length"
						type="number"
						min={0}
						value={validationRules.min ?? ''}
						onChange={( e ) => updateValidation( {
							min: parseNumericInput( e.target.value ),
						} )}
					/>
					<Input
						label="Max Length"
						type="number"
						min={0}
						value={validationRules.max ?? ''}
						onChange={( e ) => updateValidation( {
							max: parseNumericInput( e.target.value ),
						} )}
					/>
				</>
			)}

			{/* Min/Max/Step for number */}
			{field.type === 'number' && (
				<>
					<Input
						label="Minimum"
						type="number"
						value={validationRules.min ?? ''}
						onChange={( e ) => updateValidation( {
							min: parseNumericInput( e.target.value ),
						} )}
					/>
					<Input
						label="Maximum"
						type="number"
						value={validationRules.max ?? ''}
						onChange={( e ) => updateValidation( {
							max: parseNumericInput( e.target.value ),
						} )}
					/>
					<Input
						label="Step"
						type="number"
						min={0}
						value={validationRules.step ?? ''}
						onChange={( e ) => updateValidation( {
							step: parseNumericInput( e.target.value ),
						} )}
					/>
				</>
			)}

			{/* Date range */}
			{field.type === 'date' && (
				<>
					<Input
						label="Min Date"
						type="date"
						value={validationRules.min_date ?? ''}
						onChange={( e ) => updateValidation( { min_date: e.target.value || undefined } )}
					/>
					<Input
						label="Max Date"
						type="date"
						value={validationRules.max_date ?? ''}
						onChange={( e ) => updateValidation( { max_date: e.target.value || undefined } )}
					/>
				</>
			)}

			{/* Time range */}
			{field.type === 'time' && (
				<>
					<Input
						label="Min Time"
						type="time"
						value={validationRules.min_time ?? ''}
						onChange={( e ) => updateValidation( { min_time: e.target.value || undefined } )}
					/>
					<Input
						label="Max Time"
						type="time"
						value={validationRules.max_time ?? ''}
						onChange={( e ) => updateValidation( { max_time: e.target.value || undefined } )}
					/>
				</>
			)}

			{/* Pattern */}
			{( field.type === 'text' || field.type === 'phone' ) && (
				<Input
					label="Pattern (RegExp)"
					value={validationRules.pattern ?? ''}
					onChange={( e ) => updateValidation( { pattern: e.target.value || undefined } )}
					placeholder="e.g. ^[A-Z]{2}[0-9]{4}$"
					hint="Regular expression pattern for input validation."
				/>
			)}
		</div>
	);
}

