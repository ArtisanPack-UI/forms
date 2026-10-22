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

import { getConditionTargets } from '../../../shared/composite';
import { getConditionOptions } from '../../../shared/fieldSettings';
import { t } from '../../../shared/i18n';

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

/** Operators that compare against no value. */
const VALUELESS_OPERATORS: Set<ConditionalOperator> = new Set( ['is_empty', 'is_not_empty', 'checked', 'unchecked'] );

/** Get the operator options, translated at render. */
function getOperatorOptions(): Array<{ value: ConditionalOperator; label: string }> {
	return [
		{ value: 'equals', label: t( 'Equals' ) },
		{ value: 'not_equals', label: t( 'Does not equal' ) },
		{ value: 'contains', label: t( 'Contains' ) },
		{ value: 'not_contains', label: t( 'Does not contain' ) },
		{ value: 'starts_with', label: t( 'Starts with' ) },
		{ value: 'ends_with', label: t( 'Ends with' ) },
		{ value: 'is_empty', label: t( 'Is empty' ) },
		{ value: 'is_not_empty', label: t( 'Is not empty' ) },
		{ value: 'greater_than', label: t( 'Greater than' ) },
		{ value: 'less_than', label: t( 'Less than' ) },
		{ value: 'greater_or_equal', label: t( 'Greater or equal' ) },
		{ value: 'less_or_equal', label: t( 'Less or equal' ) },
		{ value: 'in', label: t( 'Is one of' ) },
		{ value: 'not_in', label: t( 'Is not one of' ) },
		{ value: 'checked', label: t( 'Is checked' ) },
		{ value: 'unchecked', label: t( 'Is unchecked' ) },
		{ value: 'includes', label: t( 'Includes' ) },
		{ value: 'not_includes', label: t( 'Does not include' ) },
	];
}

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
	/** Label for the action dropdown. Defaults to "this field", translated. */
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
	actionLabel,
	className,
}: ConditionalLogicEditorProps ): React.ReactElement {
	const availableActions = actions ?? [
		{ value: 'show', label: t( 'Show' ) },
		{ value: 'hide', label: t( 'Hide' ) },
	];
	const subject = actionLabel ?? t( 'this field' );

	const availableFields = useMemo(
		() => fields.filter( ( f ) =>
			( excludeFieldId === undefined || f.id !== excludeFieldId ) &&
			!LAYOUT_TYPES.has( f.type ),
		),
		[fields, excludeFieldId],
	);

	// A composite field offers each of its enabled sub-inputs instead of itself.
	const availableTargets = useMemo( () => availableFields.flatMap( getConditionTargets ), [availableFields] );

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
		const firstTarget = availableTargets[0];

		if ( !firstTarget ) {
			return;
		}

		const newRule: ConditionalLogicRule = {
			field: firstTarget.ref,
			operator: 'equals',
			value: '',
		};

		onChange( { ...logic, rules: [...logic.rules, newRule] } );
	}, [availableTargets, logic, onChange] );

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

	const needsValue = useCallback(
		( operator: ConditionalOperator ): boolean => !VALUELESS_OPERATORS.has( operator ),
		[],
	);

	// Get options for a referenced field (for value dropdown); a sub-input takes free text
	const getFieldOptions = useCallback(
		( fieldRef: string ) => {
			const target = availableTargets.find( ( entry ) => entry.ref === fieldRef );

			if ( target?.subKey ) {
				return null;
			}

			const field = target?.field ?? fields.find( ( f ) => f.uuid === fieldRef );

			return field ? getConditionOptions( field ) : null;
		},
		[availableTargets, fields],
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
							className="select-sm"
							options={availableActions}
							optionValue="value"
							optionLabel="label"
						/>
						<span className="text-base-content">{t( ':subject when', { subject } )}</span>
						<Select
							value={logic.logic}
							onChange={( e ) => handleLogicTypeChange( e.target.value )}
							className="select-sm"
							options={[
								{ value: 'all', label: t( 'all' ) },
								{ value: 'any', label: t( 'any' ) },
							]}
							optionValue="value"
							optionLabel="label"
						/>
						<span className="text-base-content">{t( 'of the following rules match:' )}</span>
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
										className="select-sm"
										options={availableTargets.map( ( target ) => ( {
											value: target.ref,
											label: target.label,
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
										className="select-sm"
										options={getOperatorOptions()}
										optionValue="value"
										optionLabel="label"
									/>

									{/* Value input */}
									{needsValue( rule.operator ) && (
										fieldOptions ? (
											<Select
												value={String( rule.value ?? '' )}
												onChange={( e ) => handleUpdateRule( index, { value: e.target.value } )}
												className="select-sm"
												options={[
													{ value: '', label: t( 'Select...' ) },
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
												placeholder={t( 'Value' )}
												className="input-sm"
											/>
										)
									)}

									{/* Remove button */}
									<Button
										color="ghost"
										size="sm"
										className="btn-circle"
										onClick={() => handleRemoveRule( index )}
										title={t( 'Remove rule' )}
										aria-label={t( 'Remove rule' )}
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
					{t( '+ Add Rule' )}
				</Button>
				{logic.rules.length > 0 && (
					<Button
						color="outline"
						size="sm"
						onClick={handleClearAll}
					>
						{t( 'Clear All Rules' )}
					</Button>
				)}
			</div>

			{availableFields.length === 0 && (
				<p className="text-sm text-base-content/50">
					{t( 'Add more non-layout fields to the form to create conditional rules.' )}
				</p>
			)}
		</div>
	);
}
