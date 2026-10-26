/**
 * Translated labels for the built-in field types.
 *
 * Shared by the React and Vue builders for the field editor header and the
 * label a new field starts with, so both show the same text as the palette.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import { t } from './i18n';

/**
 * Get the translated label for a field type.
 *
 * Unknown (host-registered) types fall back to the type key in title case.
 *
 * @since 1.6.0
 *
 * @param type The field type key.
 *
 * @return The label.
 */
export function getFieldTypeLabel( type: string ): string {
	const labels: Record<string, string> = {
		text: t( 'Text' ),
		email: t( 'Email' ),
		phone: t( 'Phone' ),
		number: t( 'Number' ),
		url: t( 'URL' ),
		textarea: t( 'Textarea' ),
		hidden: t( 'Hidden' ),
		select: t( 'Select' ),
		radio: t( 'Radio' ),
		checkbox: t( 'Checkbox' ),
		toggle: t( 'Toggle' ),
		checkbox_group: t( 'Checkbox Group' ),
		select_multiple: t( 'Multi-Select' ),
		file: t( 'File Upload' ),
		date: t( 'Date' ),
		time: t( 'Time' ),
		datetime: t( 'Date & Time' ),
		date_range: t( 'Date Range' ),
		rating: t( 'Rating' ),
		slider: t( 'Slider' ),
		color: t( 'Color' ),
		nps: t( 'Net Promoter Score' ),
		matrix: t( 'Likert / Matrix' ),
		ranking: t( 'Ranking' ),
		name: t( 'Name' ),
		address: t( 'Address' ),
		list: t( 'List' ),
		calculation: t( 'Calculation' ),
		signature: t( 'Signature' ),
		consent: t( 'Consent' ),
		captcha: t( 'CAPTCHA' ),
		section: t( 'Section' ),
		heading: t( 'Heading' ),
		paragraph: t( 'Paragraph' ),
		divider: t( 'Divider' ),
		html: t( 'HTML' ),
	};

	return Object.prototype.hasOwnProperty.call( labels, type )
		? labels[type]
		: type.replace( /_/g, ' ' ).replace( /\b\w/g, ( c ) => c.toUpperCase() );
}
