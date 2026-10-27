/**
 * Searchable select.
 *
 * An editable combobox with a listbox popup, following the WAI-ARIA 1.2
 * combobox pattern: typing filters the options by label and a polite status
 * announces how many match; arrow keys move the active option
 * (`aria-activedescendant`), Enter chooses it, Escape closes the list and Tab
 * leaves. A multi-select keeps the list open, toggles options and shows each
 * choice as a removable button; Backspace in an empty input removes the last.
 * Mirrors the Livewire `forms::components.combobox` partial.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import { useEffect, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';

import { filterChoices } from '../../../shared/choices';
import { t } from '../../../shared/i18n';
import type { FieldOption } from '../../../types/artisanpack-forms';

/** Props for the Combobox component. */
export interface ComboboxProps {
	/** The input's ID; the listbox and options derive theirs from it. */
	id: string;
	/** The options, in the order to list them. */
	options: FieldOption[];
	/** The chosen value, or values for a multi-select. */
	value: unknown;
	/** Whether any number of options may be chosen. */
	multiple?: boolean;
	onChange: ( value: string | string[] ) => void;
	placeholder?: string;
	required?: boolean;
	readOnly?: boolean;
	invalid?: boolean;
	/** The `aria-describedby` value for the input. */
	describedBy?: string;
	/** The `aria-labelledby` value for the input, when no `<label for>` names it. */
	labelledBy?: string;
	/** The listbox's accessible name. */
	listLabel?: string;
	className?: string;
}

/**
 * Renders a searchable single or multiple select.
 */
export function Combobox( {
	id,
	options,
	value,
	multiple = false,
	onChange,
	placeholder,
	required = false,
	readOnly = false,
	invalid = false,
	describedBy,
	labelledBy,
	listLabel,
	className,
}: ComboboxProps ) {
	const [query, setQuery] = useState( '' );
	const [typed, setTyped] = useState( false );
	const [open, setOpen] = useState( false );
	const [active, setActive] = useState( -1 );
	const rootRef = useRef<HTMLDivElement>( null );
	const inputRef = useRef<HTMLInputElement>( null );
	const listboxId = `${id}-listbox`;

	const selected = useMemo( (): string[] => {
		if ( multiple ) {
			return Array.isArray( value ) ? value.map( String ) : [];
		}

		return value === null || value === undefined || value === '' ? [] : [String( value )];
	}, [value, multiple] );

	const filtered = useMemo( () => ( typed ? filterChoices( options, query ) : options ), [options, query, typed] );
	const labelFor = ( item: string ): string => options.find( ( option ) => option.value === item )?.label ?? item;
	const optionId = ( option: FieldOption ): string => `${id}-option-${options.indexOf( option )}`;
	const activeOption = open && active >= 0 ? filtered[active] : undefined;
	const status = !open ? '' : filtered.length === 0
		? t( 'No results found.' )
		: filtered.length === 1 ? t( '1 result available.' ) : t( ':count results available.', { count: filtered.length } );
	const inputValue = !multiple && !open ? ( selected.length > 0 ? labelFor( selected[0] ) : '' ) : query;

	const show = (): void => {
		if ( readOnly || open ) {
			return;
		}

		setOpen( true );
		setTyped( false );
		setQuery( multiple || selected.length === 0 ? '' : labelFor( selected[0] ) );
		setActive( multiple ? 0 : Math.max( 0, options.findIndex( ( option ) => selected.includes( option.value ) ) ) );
	};

	const close = (): void => {
		setOpen( false );
		setTyped( false );
		setActive( -1 );
		setQuery( '' );
	};

	const choose = ( option: FieldOption | undefined ): void => {
		if ( !option || readOnly ) {
			return;
		}

		if ( multiple ) {
			onChange( selected.includes( option.value )
				? selected.filter( ( item ) => item !== option.value )
				: [...selected, option.value] );

			return;
		}

		onChange( option.value );
		close();
	};

	const remove = ( item: string ): void => {
		if ( !readOnly ) {
			onChange( selected.filter( ( entry ) => entry !== item ) );
			inputRef.current?.focus();
		}
	};

	// Close when focus or a click leaves the widget.
	useEffect( () => {
		if ( !open ) {
			return undefined;
		}

		const handlePointer = ( event: MouseEvent ): void => {
			if ( rootRef.current && !rootRef.current.contains( event.target as Node ) ) {
				close();
			}
		};

		document.addEventListener( 'mousedown', handlePointer );

		return () => document.removeEventListener( 'mousedown', handlePointer );
	}, [open] );

	// Keep the active option in view.
	useEffect( () => {
		if ( activeOption ) {
			document.getElementById( optionId( activeOption ) )?.scrollIntoView?.( { block: 'nearest' } );
		}
	}, [activeOption] );

	const move = ( step: number ): void => {
		if ( !open ) {
			show();

			return;
		}

		if ( filtered.length > 0 ) {
			setActive( ( current ) => ( current + step + filtered.length ) % filtered.length );
		}
	};

	const handleKeyDown = ( event: KeyboardEvent<HTMLInputElement> ): void => {
		switch ( event.key ) {
			case 'ArrowDown':
				event.preventDefault();
				move( 1 );
				break;
			case 'ArrowUp':
				event.preventDefault();
				move( -1 );
				break;
			case 'Home':
				if ( open && filtered.length > 0 ) {
					setActive( 0 );
				}
				break;
			case 'End':
				if ( open && filtered.length > 0 ) {
					setActive( filtered.length - 1 );
				}
				break;
			case 'Enter':
				if ( activeOption ) {
					event.preventDefault();
					choose( activeOption );
				}
				break;
			case 'Escape':
				if ( open ) {
					event.preventDefault();
					event.stopPropagation();
					close();
				}
				break;
			case 'Backspace':
				if ( multiple && query === '' && selected.length > 0 ) {
					remove( selected[selected.length - 1] );
				}
				break;
			case 'Tab':
				close();
				break;
			default:
				break;
		}
	};

	return (
		<div ref={rootRef} className={`relative ${className ?? ''}`}>
			{multiple && selected.length > 0 && (
				<ul className="mb-2 flex flex-wrap gap-2" aria-label={t( 'Selected options' )}>
					{selected.map( ( item ) => (
						<li key={item} className="badge badge-primary badge-outline gap-1 py-3">
							<span>{labelFor( item )}</span>
							<button
								type="button"
								className="btn btn-ghost btn-xs btn-circle"
								aria-label={t( 'Remove :label', { label: labelFor( item ) } )}
								disabled={readOnly}
								onClick={() => remove( item )}
							>
								<span aria-hidden="true">&times;</span>
							</button>
						</li>
					) )}
				</ul>
			)}

			<div className="relative">
				<input
					ref={inputRef}
					id={id}
					type="text"
					role="combobox"
					aria-autocomplete="list"
					aria-controls={listboxId}
					aria-expanded={open}
					aria-activedescendant={activeOption ? optionId( activeOption ) : undefined}
					aria-required={required ? 'true' : undefined}
					aria-invalid={invalid ? true : undefined}
					aria-describedby={describedBy}
					aria-labelledby={labelledBy}
					aria-readonly={readOnly ? 'true' : undefined}
					autoComplete="off"
					readOnly={readOnly}
					className={`input input-bordered w-full pr-10 ${invalid ? 'input-error' : ''}`}
					placeholder={placeholder ?? ( multiple ? t( 'Search options...' ) : t( 'Search...' ) )}
					value={inputValue}
					onChange={( event ) => {
						if ( readOnly ) {
							return;
						}

						setQuery( event.target.value );
						setTyped( true );
						setOpen( true );
						setActive( filterChoices( options, event.target.value ).length > 0 ? 0 : -1 );
					}}
					onClick={show}
					onKeyDown={handleKeyDown}
				/>
				<button
					type="button"
					tabIndex={-1}
					className="btn btn-ghost btn-sm btn-square absolute right-1 top-1/2 -translate-y-1/2"
					aria-label={t( 'Show options' )}
					aria-expanded={open}
					aria-controls={listboxId}
					disabled={readOnly}
					onClick={() => {
						if ( open ) {
							close();
						} else {
							show();
							inputRef.current?.focus();
						}
					}}
				>
					<svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
						<path fillRule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clipRule="evenodd" />
					</svg>
				</button>
			</div>

			<ul
				id={listboxId}
				role="listbox"
				aria-multiselectable={multiple ? 'true' : undefined}
				aria-label={listLabel}
				hidden={!open || filtered.length === 0}
				className="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-box border border-base-300 bg-base-100 p-1 shadow-lg"
			>
				{filtered.map( ( option, index ) => (
					<li
						key={option.value}
						id={optionId( option )}
						role="option"
						aria-selected={selected.includes( option.value )}
						className={`flex cursor-pointer items-center gap-2 rounded-field px-3 py-2 ${index === active ? 'bg-base-200' : ''}`}
						onMouseDown={( event ) => {
							event.preventDefault();
							choose( option );
						}}
						onMouseEnter={() => setActive( index )}
					>
						<span className="w-4 shrink-0" aria-hidden="true">{selected.includes( option.value ) ? '✓' : ''}</span>
						<span>{option.label}</span>
					</li>
				) )}
			</ul>

			{open && filtered.length === 0 && (
				<div className="absolute z-20 mt-1 w-full rounded-box border border-base-300 bg-base-100 px-3 py-2 text-sm text-base-content/60 shadow-lg">
					{t( 'No results found.' )}
				</div>
			)}

			<div className="sr-only" role="status" aria-live="polite" aria-atomic="true">{status}</div>
		</div>
	);
}
