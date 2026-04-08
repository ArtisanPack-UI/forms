/**
 * Advanced field components.
 *
 * Renders file upload and date picker fields
 * using @artisanpack-ui/react components.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */

import { DatePicker, File as FileInput } from '@artisanpack-ui/react/form';

import type { FieldComponentProps } from './types';

/**
 * Renders a file upload field.
 */
export function FileField( { field, error, onFileChange, displayConfig }: FieldComponentProps ) {
	const rules = field.validation_rules;
	const allowedTypes = rules?.allowed_types?.join( ',' ) ?? undefined;

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
			inline={displayConfig.label_position === 'beside'}
		/>
	);
}
