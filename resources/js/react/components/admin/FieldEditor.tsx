/**
 * FieldEditor component for per-field settings.
 *
 * The settings every field shares (label, name, placeholder, help text,
 * default value, required, width, CSS classes, options, conditional logic)
 * are built in. Type-specific settings are rendered from the field's
 * `settings_schema`, which the server builds from the field type's handler,
 * so a new type's settings need no editor changes.
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
	FieldSettingDefinition,
	FieldType,
	FieldWidth,
	FormField,
	PredefinedChoiceList,
	UpdateFieldRequest,
} from '../../../types/artisanpack-forms';

import {
	buildLayoutContentUpdate,
	buildSettingUpdate,
	getLayoutContent,
	getSettingInput,
	getSettingsForTab,
	isDraftStored,
} from '../../../shared/fieldSettings';
import { applySubInputUpdate, isCompositeField } from '../../../shared/composite';
import { MERGE_TAGS } from '../../../shared/dynamicPopulation';
import { getFieldTypeLabel } from '../../../shared/fieldTypeLabels';
import { t } from '../../../shared/i18n';
import { resolveFieldSettings } from '../fields/registry';
import type { CustomFieldSettingsEntry, FieldSettingsRegistration } from '../fields/registry';
import { ConditionalLogicEditor } from './ConditionalLogicEditor';
import { OptionsEditor } from './OptionsEditor';
import { SubInputsEditor } from './SubInputsEditor';

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

/** Field types without a placeholder. */
const NO_PLACEHOLDER_TYPES: Set<FieldType> = new Set( ['checkbox', 'toggle', 'radio', 'rating', 'consent', 'captcha'] );

/**
 * Field types without a default value: a consent box is never pre-checked
 * and a CAPTCHA token comes from the provider (since 1.6.0).
 */
const NO_DEFAULT_VALUE_TYPES: Set<FieldType> = new Set( ['consent', 'captcha'] );

/** Get the width options, translated at render. */
function getWidthOptions(): Array<{ value: FieldWidth; label: string }> {
	return [
		{ value: 'full', label: t( 'Full Width' ) },
		{ value: 'half', label: t( 'Half (1/2)' ) },
		{ value: 'third', label: t( 'Third (1/3)' ) },
		{ value: 'two-thirds', label: t( 'Two-Thirds (2/3)' ) },
	];
}

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
	/**
	 * Host-supplied settings panels keyed by field type. An entry is either a
	 * component (rendered last in the General tab) or `{ component, position }`
	 * where `position: 'before-basics'` renders it above Width/CSS Classes.
	 * Takes precedence over the module-level registry (see
	 * `registerFieldSettings`) for the same type.
	 */
	customSettings?: Record<string, CustomFieldSettingsEntry>;
	/**
	 * The lists the options editor's bulk editor can insert (since 1.6.0;
	 * from the `fields/predefined-choices` endpoint).
	 */
	predefinedChoices?: PredefinedChoiceList[];
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
	customSettings,
	predefinedChoices,
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

	// Host-supplied settings panel for this field type, if any. The per-instance
	// prop wins over a module-level registration; the resolver guards the plain
	// object so a type such as `constructor` cannot resolve a prototype member.
	const customSettingsPanel = useMemo(
		() => resolveFieldSettings( customSettings, field.type ),
		[customSettings, field.type],
	);

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

	const handleSetOptions = useCallback(
		( newOptions: FieldOption[] ) => {
			updateField( { field_config: { ...( field.field_config ?? {} ), options: newOptions } } );
		},
		[field.field_config, updateField],
	);

	// Show a sub-input change at once; the server rebuilds `sub_inputs` on save.
	const patchField = useCallback( ( patch: Partial<FormField> ) => {
		setField( ( prev ) => ( { ...prev, ...patch } ) );
	}, [] );

	const validationSettings = useMemo( () => getSettingsForTab( field, 'validation' ), [field] );
	const advancedSettings = useMemo( () => getSettingsForTab( field, 'advanced' ), [field] );

	// Build tabs array — Tabs component uses `name` (not `id`) as the key
	const tabs = useMemo( () => {
		const tabItems = [
			{
				name: 'general',
				label: t( 'General' ),
				content: (
					<GeneralTabContent
						field={field}
						isLayoutField={isLayoutField}
						hasOptions={hasOptions}
						options={options}
						updateField={updateField}
						patchField={patchField}
						setOptions={handleSetOptions}
						predefinedChoices={predefinedChoices}
						customSettings={customSettingsPanel}
						allFields={allFields}
					/>
				),
			},
		];

		if ( validationSettings.length > 0 ) {
			tabItems.push( {
				name: 'validation',
				label: t( 'Validation' ),
				content: (
					<SettingsTabContent
						field={field}
						settings={validationSettings}
						updateField={updateField}
					/>
				),
			} );
		}

		if ( !isLayoutField ) {
			tabItems.push( {
				name: 'conditional',
				label: t( 'Conditional' ),
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

		if ( advancedSettings.length > 0 ) {
			tabItems.push( {
				name: 'advanced',
				label: t( 'Advanced' ),
				content: (
					<SettingsTabContent
						field={field}
						settings={advancedSettings}
						updateField={updateField}
					/>
				),
			} );
		}

		return tabItems;
	}, [
		field, isLayoutField, hasOptions, options, validationSettings, advancedSettings,
		allFields, updateField, patchField, handleSetOptions, predefinedChoices,
		customSettingsPanel,
	] );

	// Fall back to the General tab when the active tab does not apply to the field.
	const resolvedTab = tabs.some( ( tab ) => tab.name === activeTab ) ? activeTab : 'general';

	return (
		<div className={`flex flex-col gap-0 ${className ?? ''}`}>
			{/* Header */}
			<div className="flex items-center justify-between p-3 border-b border-base-300">
				<h3 className="text-sm font-semibold">
					{t( 'Edit :type Field', { type: getFieldTypeLabel( field.type ) } )}
				</h3>
				<Button
					color="ghost"
					size="sm"
					className="btn-circle"
					onClick={onClose}
					aria-label={t( 'Close field editor' )}
				>
					&times;
				</Button>
			</div>

			{/* Tabs */}
			<Tabs
				variant="bordered"
				tabs={tabs}
				activeTab={resolvedTab}
				onChange={setActiveTab}
			/>

			{/* Footer actions */}
			<div className="flex gap-2 justify-end p-3 border-t border-base-300">
				<Button
					color="ghost"
					size="sm"
					onClick={() => onDuplicate( field.id )}
				>
					{t( 'Duplicate' )}
				</Button>
				<Button
					color="error"
					size="sm"
					onClick={() => {
						if ( window.confirm( t( 'Are you sure you want to delete this field?' ) ) ) {
							onDelete( field.id );
						}
					}}
				>
					{t( 'Delete' )}
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
	updateField: ( data: UpdateFieldRequest ) => void;
	patchField: ( patch: Partial<FormField> ) => void;
	setOptions: ( options: FieldOption[] ) => void;
	predefinedChoices?: PredefinedChoiceList[];
	customSettings?: FieldSettingsRegistration;
	allFields: FormField[];
}

function GeneralTabContent( {
	field,
	isLayoutField,
	hasOptions,
	options,
	updateField,
	patchField,
	setOptions,
	predefinedChoices,
	customSettings,
	allFields,
}: GeneralTabContentProps ): React.ReactElement {
	const isComposite = isCompositeField( field );
	const CustomSettings = customSettings?.component;
	const customSettingsPanel = CustomSettings ? (
		<CustomSettings field={field} allFields={allFields} updateField={updateField} />
	) : null;
	const rendersBeforeBasics = 'before-basics' === customSettings?.position;

	return (
		<div className="space-y-4 p-4">
			{/* Label (a heading's label is its text) */}
			{field.type === 'heading' ? (
				<Input
					label={t( 'Heading Text' )}
					value={getLayoutContent( field )}
					onChange={( e ) => updateField( buildLayoutContentUpdate( field, e.target.value ) )}
				/>
			) : (
				<Input
					label={t( 'Label' )}
					value={field.label ?? ''}
					onChange={( e ) => updateField( { label: e.target.value } )}
				/>
			)}

			{/* Name (machine name) */}
			<Input
				label={t( 'Name' )}
				value={field.name}
				onChange={( e ) => updateField( { name: e.target.value } )}
				hint={t( 'Machine-readable name. Used as the form data key.' )}
			/>

			{/* Placeholder (non-layout only; a composite field's inputs have their own) */}
			{!isLayoutField && !isComposite && !NO_PLACEHOLDER_TYPES.has( field.type ) && (
				<Input
					label={t( 'Placeholder' )}
					value={field.placeholder ?? ''}
					onChange={( e ) => updateField( { placeholder: e.target.value || null } )}
				/>
			)}

			{/* A composite field's sub-inputs */}
			{isComposite && (
				<SubInputsEditor
					field={field}
					onChange={( data, key, setting, value ) => {
						updateField( data );
						patchField( { sub_inputs: applySubInputUpdate( field, key, setting, value ).sub_inputs } );
					}}
				/>
			)}

			{/* Help text */}
			{!isLayoutField && (
				<Input
					label={t( 'Help Text' )}
					value={field.help_text ?? ''}
					onChange={( e ) => updateField( { help_text: e.target.value || null } )}
				/>
			)}

			{/* Default value */}
			{!isLayoutField && !isComposite && field.type !== 'file' && ( !NO_DEFAULT_VALUE_TYPES.has( field.type ) && (
				<Input
					label={t( 'Default Value' )}
					value={field.default_value ?? ''}
					onChange={( e ) => updateField( { default_value: e.target.value || null } )}
					hint={t( 'Merge tags: :tags', { tags: MERGE_TAGS.join( ', ' ) } )}
				/>
			) )}

			{/* Required toggle */}
			{!isLayoutField && (
				<Checkbox
					label={t( 'Required' )}
					checked={field.is_required}
					onChange={( e ) => updateField( { is_required: e.target.checked } )}
				/>
			)}

			{/* Host-supplied settings panel placed above the generic basics */}
			{rendersBeforeBasics && customSettingsPanel && (
				<>
					{customSettingsPanel}
					<div className="divider text-xs text-base-content/50">{t( 'Field Basics' )}</div>
				</>
			)}

			{/* Width */}
			<Select
				label={t( 'Width' )}
				value={field.width}
				options={getWidthOptions()}
				optionValue="value"
				optionLabel="label"
				onChange={( e ) => updateField( { width: e.target.value as FieldWidth } )}
			/>

			{/* CSS Classes */}
			<Input
				label={t( 'CSS Classes' )}
				value={field.css_classes ?? ''}
				onChange={( e ) => updateField( { css_classes: e.target.value || null } )}
			/>

			{/* HTML Content (for html field type) */}
			{field.type === 'html' && (
				<Textarea
					label={t( 'HTML Content' )}
					rows={6}
					value={getLayoutContent( field )}
					onChange={( e ) => updateField( buildLayoutContentUpdate( field, e.target.value ) )}
				/>
			)}

			{/* Paragraph content */}
			{field.type === 'paragraph' && (
				<Textarea
					label={t( 'Content' )}
					rows={4}
					value={getLayoutContent( field )}
					onChange={( e ) => updateField( buildLayoutContentUpdate( field, e.target.value ) )}
				/>
			)}

			{/* Type-specific settings from the field type's settings schema */}
			{getSettingsForTab( field, 'general' ).map( ( setting ) => (
				<SettingControl
					key={setting.key}
					field={field}
					setting={setting}
					updateField={updateField}
				/>
			) )}

			{/* Options editor for choice fields */}
			{hasOptions && (
				<OptionsEditor
					key={field.id}
					field={field}
					options={options}
					onChange={setOptions}
					predefinedChoices={predefinedChoices}
				/>
			)}

			{/* Host-supplied settings panel for a custom field type */}
			{!rendersBeforeBasics && customSettingsPanel}
		</div>
	);
}

// ---------------------------------------------------------------------------
// SettingsTabContent (inline sub-component)
// ---------------------------------------------------------------------------

interface SettingsTabContentProps {
	field: FormField;
	settings: FieldSettingDefinition[];
	updateField: ( data: UpdateFieldRequest ) => void;
}

/** Renders a tab made only of schema settings (Validation, Advanced). */
function SettingsTabContent( {
	field,
	settings,
	updateField,
}: SettingsTabContentProps ): React.ReactElement {
	return (
		<div className="space-y-4 p-4">
			{settings.map( ( setting ) => (
				<SettingControl
					key={setting.key}
					field={field}
					setting={setting}
					updateField={updateField}
				/>
			) )}
		</div>
	);
}

// ---------------------------------------------------------------------------
// SettingControl (inline sub-component)
// ---------------------------------------------------------------------------

interface SettingControlProps {
	field: FormField;
	setting: FieldSettingDefinition;
	updateField: ( data: UpdateFieldRequest ) => void;
}

/** Map a schema input to the HTML input type that edits it. */
function htmlInputType( input: FieldSettingDefinition['input'] ): string {
	switch ( input ) {
		case 'integer':
		case 'number':
			return 'number';
		case 'date':
			return 'date';
		case 'time':
			return 'time';
		default:
			return 'text';
	}
}

/**
 * Renders one setting from a field type's settings schema.
 *
 * Toggles and selects save as they change. Typed inputs and textareas
 * (since 1.6.0) keep a local draft
 * of exactly what was typed — a trailing comma in a list, a space between
 * words, `0.` in a number — and save the parsed value, so parsing never
 * rewrites the text under the cursor. The draft resets when another field
 * or setting is shown.
 */
function SettingControl( { field, setting, updateField }: SettingControlProps ): React.ReactElement {
	const stored = getSettingInput( field, setting );
	const [draft, setDraft] = useState<string>( typeof stored === 'string' ? stored : '' );

	useEffect( () => {
		const current = getSettingInput( field, setting );

		setDraft( typeof current === 'string' ? current : '' );
	// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [field.id, setting.key] );

	// Follow the stored value when it moves away from the draft (a rejected
	// update was reverted, or the server stored something else).
	const storedText = typeof stored === 'string' ? stored : '';

	useEffect( () => {
		setDraft( ( current ) => ( isDraftStored( field, setting, current ) ? current : storedText ) );
	// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [storedText] );

	if ( setting.input === 'toggle' ) {
		return (
			<Checkbox
				label={setting.label}
				hint={setting.hint}
				checked={stored === true}
				onChange={( e ) => updateField( buildSettingUpdate( field, setting, e.target.checked ) )}
			/>
		);
	}

	if ( setting.input === 'select' ) {
		return (
			<Select
				label={setting.label}
				hint={setting.hint}
				value={typeof stored === 'string' ? stored : ''}
				options={setting.options ?? []}
				optionValue="value"
				optionLabel="label"
				onChange={( e ) => updateField( buildSettingUpdate( field, setting, e.target.value ) )}
			/>
		);
	}

	if ( setting.input === 'textarea' ) {
		return (
			<Textarea
				label={setting.label}
				hint={setting.hint}
				value={draft}
				placeholder={setting.placeholder}
				rows={4}
				maxLength={setting.max_length}
				className="textarea textarea-sm textarea-bordered w-full"
				onChange={( e ) => {
					setDraft( e.target.value );
					updateField( buildSettingUpdate( field, setting, e.target.value ) );
				}}
			/>
		);
	}

	const isNumeric = setting.input === 'integer' || setting.input === 'number';

	return (
		<Input
			label={setting.label}
			hint={setting.hint}
			type={htmlInputType( setting.input )}
			value={draft}
			placeholder={setting.placeholder}
			min={isNumeric ? setting.min : undefined}
			max={isNumeric ? setting.max : undefined}
			step={isNumeric ? ( setting.input === 'integer' ? 1 : ( setting.step ?? 'any' ) ) : undefined}
			className={setting.monospace ? 'font-mono' : undefined}
			onChange={( e ) => {
				setDraft( e.target.value );
				updateField( buildSettingUpdate( field, setting, e.target.value ) );
			}}
		/>
	);
}
