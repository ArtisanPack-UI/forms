/**
 * ConditionalLogicEditor component.
 *
 * Standalone visual rule builder for field/step visibility conditions.
 * Can be used independently or embedded within the FieldEditor or
 * NotificationEditor components.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import React, { useCallback, useMemo } from 'react';
import { Button, Input, Select } from '@artisanpack-ui/react';

import type {
	ConditionalLogicRule,
	ConditionalLogicType,
	ConditionalOperator,
	FormField,
} from '../../../types/artisanpack-forms';

/** Generic conditional logic shape that accepts any action string. */
export interface GenericConditionalLogic {
	action: string;
	logic: ConditionalLogicType;
	rules: ConditionalLogicRule[];
}

/** Operator definitions with metadata. */
const OPERATOR_DEFINITIONS: Array<{
	value: ConditionalOperator;
	label: string;
	needsValue: boolean;
}> = [
	{ value: 'equals', label: 'Equals', needsValue: true },
	{ value: 'not_equals', label: 'Does not equal', needsValue: true },
	{ value: 'contains', label: 'Contains', needsValue: true },
	{ value: 'not_contains', label: 'Does not contain', needsValue: true },
	{ value: 'starts_with', label: 'Starts with', needsValue: true },
	{ value: 'ends_with', label: 'Ends with', needsValue: true },
	{ value: 'is_empty', label: 'Is empty', needsValue: false },
	{ value: 'is_not_empty', label: 'Is not empty', needsValue: false },
	{ value: 'greater_than', label: 'Greater than', needsValue: true },
	{ value: 'less_than', label: 'Less than', needsValue: true },
	{ value: 'greater_or_equal', label: 'Greater or equal', needsValue: true },
	{ value: 'less_or_equal', label: 'Less or equal', needsValue: true },
	{ value: 'in', label: 'Is one of', needsValue: true },
	{ value: 'not_in', label: 'Is not one of', needsValue: true },
	{ value: 'checked', label: 'Is checked', needsValue: false },
	{ value: 'unchecked', label: 'Is unchecked', needsValue: false },
	{ value: 'includes', label: 'Includes', needsValue: true },
	{ value: 'not_includes', label: 'Does not include', needsValue: true },
];

/** Layout field types excluded from condition targets. */
const LAYOUT_TYPES = new Set( ['heading', 'paragraph', 'divider', 'html'] );

/** Props for the ConditionalLogicEditor component. */
export interface ConditionalLogicEditorProps {
	/** Current conditional logic configuration, or null. */
	value: GenericConditionalLogic | null;
	/** Callback when the logic changes. Pass null to clear. */
	onChange: ( logic: GenericConditionalLogic | null ) => void;
	/** All fields available as condition targets. */
	fields: FormField[];
	/** ID of the current field (excluded from targets). */
	excludeFieldId?: number;
	/** Available actions. Defaults to ['show', 'hide']. */
	actions?: Array<{ value: string; label: string }>;
	/** Label for the action dropdown. Defaults to 'this field'. */
	actionLabel?: string;
	/** Optional CSS class name. */
	className?: string;
}

/**
 * Visual conditional logic rule builder.
 *
 * @example
 * ```tsx
 * <ConditionalLogicEditor
 *   value={field.conditional_logic}
 *   onChange={(logic) => updateField(field.id, { conditional_logic: logic })}
 *   fields={allFields}
 *   excludeFieldId={field.id}
 * />
 * ```
 */
export function ConditionalLogicEditor( {
	value,
	onChange,
	fields,
	excludeFieldId,
	actions,
	actionLabel = 'this field',
	className,
}: ConditionalLogicEditorProps ): React.ReactElement {
	const availableActions = actions ?? [
		{ value: 'show', label: 'Show' },
		{ value: 'hide', label: 'Hide' },
	];

	const availableFields = useMemo(
		() => fields.filter( ( f ) =>
			( excludeFieldId === undefined || f.id !== excludeFieldId ) &&
			!LAYOUT_TYPES.has( f.type ),
		),
		[fields, excludeFieldId],
	);

	const defaultLogic = useMemo( () => ( {
		action: availableActions[0].value,
		logic: 'all' as ConditionalLogicType,
		rules: [],
	} ), [availableActions] );

	const logic: GenericConditionalLogic = value ?? defaultLogic;

	const handleActionChange = useCallback(
		( action: string ) => {
			onChange( { ...logic, action } );
		},
		[logic, onChange],
	);

	const handleLogicTypeChange = useCallback(
		( logicType: string ) => {
			onChange( { ...logic, logic: logicType as ConditionalLogicType } );
		},
		[logic, onChange],
	);

	const handleAddRule = useCallback( () => {
		const firstField = availableFields[0];

		if ( !firstField ) {
			return;
		}

		const newRule: ConditionalLogicRule = {
			field: firstField.uuid,
			operator: 'equals',
			value: '',
		};

		onChange( { ...logic, rules: [...logic.rules, newRule] } );
	}, [availableFields, logic, onChange] );

	const handleUpdateRule = useCallback(
		( index: number, updates: Partial<ConditionalLogicRule> ) => {
			const newRules = logic.rules.map( ( rule, i ) =>
				i === index ? { ...rule, ...updates } : rule,
			);
			onChange( { ...logic, rules: newRules } );
		},
		[logic, onChange],
	);

	const handleRemoveRule = useCallback(
		( index: number ) => {
			const newRules = logic.rules.filter( ( _, i ) => i !== index );

			if ( newRules.length === 0 ) {
				onChange( null );
			} else {
				onChange( { ...logic, rules: newRules } );
			}
		},
		[logic, onChange],
	);

	const handleClearAll = useCallback( () => {
		onChange( null );
	}, [onChange] );

	const needsValue = useCallback( ( operator: ConditionalOperator ): boolean => {
		const def = OPERATOR_DEFINITIONS.find( ( d ) => d.value === operator );

		return def?.needsValue ?? true;
	}, [] );

	// Get options for a referenced field (for value dropdown)
	const getFieldOptions = useCallback(
		( fieldUuid: string ) => {
			const field = fields.find( ( f ) => f.uuid === fieldUuid );

			if ( !field ) {
				return null;
			}

			const options = field.options ??
				( field.field_config as { options?: Array<{ label: string; value: string }> } )?.options;

			return options && options.length > 0 ? options : null;
		},
		[fields],
	);

	return (
		<div className={`space-y-3 ${className ?? ''}`}>
			{logic.rules.length > 0 && (
				<>
					{/* Action and logic type config */}
					<div className="flex flex-wrap items-center gap-2 text-sm">
						<Select
							value={logic.action}
							onChange={( e ) => handleActionChange( e.target.value )}
							size="sm"
							options={availableActions}
							optionValue="value"
							optionLabel="label"
						/>
						<span className="text-base-content">{actionLabel} when</span>
						<Select
							value={logic.logic}
							onChange={( e ) => handleLogicTypeChange( e.target.value )}
							size="sm"
							options={[
								{ value: 'all', label: 'all' },
								{ value: 'any', label: 'any' },
							]}
							optionValue="value"
							optionLabel="label"
						/>
						<span className="text-base-content">of the following rules match:</span>
					</div>

					{/* Rules */}
					<div className="space-y-2">
						{logic.rules.map( ( rule, index ) => {
							const fieldOptions = getFieldOptions( rule.field );

							return (
								<div key={index} className="flex flex-wrap items-center gap-2">
									{/* Field selector */}
									<Select
										value={rule.field}
										onChange={( e ) => handleUpdateRule( index, { field: e.target.value } )}
										size="sm"
										options={availableFields.map( ( f ) => ( {
											value: f.uuid,
											label: f.label || f.name,
										} ) )}
										optionValue="value"
										optionLabel="label"
									/>

									{/* Operator selector */}
									<Select
										value={rule.operator}
										onChange={( e ) => handleUpdateRule( index, {
											operator: e.target.value as ConditionalOperator,
										} )}
										size="sm"
										options={OPERATOR_DEFINITIONS.map( ( op ) => ( {
											value: op.value,
											label: op.label,
										} ) )}
										optionValue="value"
										optionLabel="label"
									/>

									{/* Value input */}
									{needsValue( rule.operator ) && (
										fieldOptions ? (
											<Select
												value={String( rule.value ?? '' )}
												onChange={( e ) => handleUpdateRule( index, { value: e.target.value } )}
												size="sm"
												options={[
													{ value: '', label: 'Select...' },
													...fieldOptions.map( ( opt ) => ( {
														value: opt.value,
														label: opt.label,
													} ) ),
												]}
												optionValue="value"
												optionLabel="label"
											/>
										) : (
											<Input
												type="text"
												value={String( rule.value ?? '' )}
												onChange={( e ) => handleUpdateRule( index, { value: e.target.value } )}
												placeholder="Value"
												size="sm"
											/>
										)
									)}

									{/* Remove button */}
									<Button
										color="ghost"
										size="sm"
										className="btn-circle"
										onClick={() => handleRemoveRule( index )}
										title="Remove rule"
										aria-label="Remove rule"
									>
										&times;
									</Button>
								</div>
							);
						} )}
					</div>
				</>
			)}

			{/* Actions */}
			<div className="flex items-center gap-2">
				<Button
					color="ghost"
					size="sm"
					onClick={handleAddRule}
					disabled={availableFields.length === 0}
				>
					+ Add Rule
				</Button>
				{logic.rules.length > 0 && (
					<Button
						color="outline"
						size="sm"
						onClick={handleClearAll}
					>
						Clear All Rules
					</Button>
				)}
			</div>

			{availableFields.length === 0 && (
				<p className="text-sm text-base-content/50">
					Add more non-layout fields to the form to create conditional rules.
				</p>
			)}
		</div>
	);
}
