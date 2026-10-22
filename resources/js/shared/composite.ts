/**
 * Composite (multi-input) field helpers.
 *
 * Client-side mirror of `CompositeFieldType` and the composite methods on
 * `FormField`. A composite field's value is a map of sub-input keys, bound
 * per input and submitted as `data[{field}][{key}]`; its errors are keyed
 * `{field}.{key}`.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type {
	CompositeFieldConfig,
	FormField,
	SubInputDefinition,
	SubLabelPlacement,
	UpdateFieldRequest,
} from '../types/artisanpack-forms';

/** The longest text one sub-input accepts, mirroring `CompositeFieldType::SUB_INPUT_MAX_LENGTH`. */
export const SUB_INPUT_MAX_LENGTH = 255;

/** The sub-input settings a field may change, mirroring `CompositeFieldType::SUB_INPUT_SETTINGS`. */
export type SubInputSetting = 'enabled' | 'required' | 'label' | 'placeholder';

/** Whether a field collects several inputs stored together. */
export function isCompositeField( field: FormField ): boolean {
	return Array.isArray( field.sub_inputs ) && field.sub_inputs.length > 0;
}

/** Get a composite field's enabled sub-inputs, in definition order. */
export function getEnabledSubInputs( field: FormField ): SubInputDefinition[] {
	return ( field.sub_inputs ?? [] ).filter( ( subInput ) => subInput.enabled );
}

/**
 * Get the keys of the sub-inputs a submission must fill in. Mirrors
 * `FormField::getRequiredSubInputKeys()`: a required field requires the
 * marked inputs, or every enabled one when none is marked.
 */
export function getRequiredSubInputKeys( field: FormField ): string[] {
	if ( Array.isArray( field.required_sub_inputs ) ) {
		return field.required_sub_inputs;
	}

	if ( !field.is_required ) {
		return [];
	}

	const enabled = getEnabledSubInputs( field );
	const marked = enabled.filter( ( subInput ) => subInput.required ).map( ( subInput ) => subInput.key );

	return marked.length > 0 ? marked : enabled.map( ( subInput ) => subInput.key );
}

/** Get where a composite field shows its sub-input labels. */
export function getSubLabelPlacement( field: FormField ): SubLabelPlacement {
	const placement = field.sub_label_placement ?? ( field.field_config as CompositeFieldConfig | null )?.sub_label_placement;

	return placement === 'above' || placement === 'hidden' ? placement : 'below';
}

/** Get the value a composite field starts with: a blank string per enabled sub-input. */
export function getEmptyCompositeValue( field: FormField ): Record<string, string> {
	return Object.fromEntries( getEnabledSubInputs( field ).map( ( subInput ) => [subInput.key, ''] ) );
}

/** Read a composite value as a map of strings, whatever shape it arrived in. */
export function toCompositeValue( value: unknown ): Record<string, string> {
	if ( !value || typeof value !== 'object' || Array.isArray( value ) ) {
		return {};
	}

	return Object.fromEntries(
		Object.entries( value as Record<string, unknown> ).map( ( [key, part] ) => [key, part === null || part === undefined ? '' : String( part )] ),
	);
}

/** Get the ID of a sub-input's input. */
export function getSubInputId( field: FormField, key: string ): string {
	return `field-${field.name}-${key}`;
}

/**
 * Pick a composite field's errors out of a form's error map, keyed by
 * sub-input. Server errors arrive keyed `{field}.{key}` once the `data.` or
 * `formData.` prefix is stripped.
 */
export function getSubInputErrors( errors: Record<string, string[] | undefined>, fieldName: string ): Record<string, string> {
	const prefix = `${fieldName}.`;
	const subErrors: Record<string, string> = {};

	for ( const [key, messages] of Object.entries( errors ) ) {
		if ( key.startsWith( prefix ) && messages && messages.length > 0 ) {
			subErrors[key.slice( prefix.length )] = messages[0];
		}
	}

	return subErrors;
}

/** Whether a value is a composite field's map of sub-input values. */
function isPartsMap( value: unknown ): value is Record<string, unknown> {
	return value !== null && typeof value === 'object' && !Array.isArray( value );
}

/**
 * Drop the errors a field's new value may have fixed: the field's own error,
 * and for a composite field only the sub-inputs whose part changed, so the
 * other parts keep their errors and the field-level summary.
 */
export function clearChangedFieldErrors(
	errors: Record<string, string[]>,
	name: string,
	previous: unknown,
	value: unknown,
): Record<string, string[]> {
	const prefix = `${name}.`;
	const next: Record<string, string[]> = {};

	for ( const [key, messages] of Object.entries( errors ) ) {
		if ( key === name ) {
			continue;
		}

		if ( key.startsWith( prefix ) ) {
			const part = key.slice( prefix.length );

			if ( !isPartsMap( value ) || !isPartsMap( previous ) || value[part] !== previous[part] ) {
				continue;
			}
		}

		next[key] = messages;
	}

	return next;
}

/**
 * Format a composite value for a conditional logic rule on the whole field:
 * its filled parts joined with `, `, as `CompositeFieldType::format()` joins
 * them by default. Other values are returned unchanged.
 */
export function formatCompositeForCondition( value: unknown ): unknown {
	if ( !isPartsMap( value ) ) {
		return value;
	}

	return Object.values( value )
		.filter( ( part ) => ( typeof part === 'string' || typeof part === 'number' ) && String( part ).trim() !== '' )
		.map( ( part ) => String( part ).trim() )
		.join( ', ' );
}

/** Get the field name an error key belongs to (`address.city` → `address`). */
export function getErrorFieldName( key: string ): string {
	return key.split( '.' )[0];
}

/**
 * Build the update that changes one of a composite field's sub-input
 * settings, or null when the change is not allowed (an unknown input or
 * setting, or turning off the last enabled input). Mirrors
 * `CompositeFieldType::updateSubInputConfig()`.
 */
export function buildSubInputUpdate(
	field: FormField,
	key: string,
	setting: SubInputSetting,
	value: unknown,
): UpdateFieldRequest | null {
	const subInputs = field.sub_inputs ?? [];

	if ( !subInputs.some( ( subInput ) => subInput.key === key ) ) {
		return null;
	}

	const config: Record<string, unknown> = { ...( field.field_config ?? {} ) };
	const configured: Record<string, Record<string, unknown>> = { ...( ( config.sub_inputs as Record<string, Record<string, unknown>> | undefined ) ?? {} ) };
	const entry: Record<string, unknown> = { ...( configured[key] ?? {} ) };

	if ( setting === 'enabled' || setting === 'required' ) {
		const flag = value === true || ['1', 'true', 'on', 'yes'].includes( String( value ).toLowerCase() );
		const enabled = getEnabledSubInputs( field ).map( ( subInput ) => subInput.key );

		if ( setting === 'enabled' && !flag && enabled.length === 1 && enabled[0] === key ) {
			return null;
		}

		entry[setting] = flag;
	} else {
		const text = typeof value === 'string' || typeof value === 'number' ? String( value ).trim().slice( 0, 255 ) : '';

		if ( text === '' ) {
			delete entry[setting];
		} else {
			entry[setting] = text;
		}
	}

	configured[key] = entry;
	config.sub_inputs = configured;

	return { field_config: config as FormField['field_config'] };
}

/**
 * Apply a sub-input settings change to the field's `sub_inputs` locally, so
 * the builder shows it before the server answers.
 */
export function applySubInputUpdate( field: FormField, key: string, setting: SubInputSetting, value: unknown ): FormField {
	return {
		...field,
		sub_inputs: ( field.sub_inputs ?? [] ).map( ( subInput ) => {
			if ( subInput.key !== key ) {
				return subInput;
			}

			if ( setting === 'enabled' || setting === 'required' ) {
				return { ...subInput, [setting]: Boolean( value ) };
			}

			const text = String( value ?? '' ).trim();

			return setting === 'label'
				? { ...subInput, label: text !== '' ? text : subInput.default_label }
				: { ...subInput, placeholder: text !== '' ? text : null };
		} ),
	};
}

/** A conditional logic target: a field, or one of a composite field's sub-inputs. */
export interface ConditionTarget {
	/** The rule's `field` reference: the field's UUID, followed by `.{key}` for a sub-input. */
	ref: string;
	/** The field name (`{field}.{key}` for a sub-input). */
	name: string;
	label: string;
	type: FormField['type'];
	/** The field, for its options and operators. */
	field: FormField;
	/** The sub-input key, when the target is a sub-input. */
	subKey?: string;
}

/**
 * Get the targets a conditional logic rule may test on a field: the field,
 * or each enabled sub-input of a composite field. Mirrors
 * `FormField::getConditionTargets()`.
 */
export function getConditionTargets( field: FormField ): ConditionTarget[] {
	if ( !isCompositeField( field ) ) {
		return [{ ref: field.uuid, name: field.name, label: field.label ?? field.name, type: field.type, field }];
	}

	return getEnabledSubInputs( field ).map( ( subInput ) => ( {
		ref: `${field.uuid}.${subInput.key}`,
		name: `${field.name}.${subInput.key}`,
		label: `${field.label ?? field.name}: ${subInput.label}`,
		type: 'text',
		field,
		subKey: subInput.key,
	} ) );
}
