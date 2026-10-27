/**
 * Net Promoter Score field helpers.
 *
 * Client-side mirror of `NpsFieldType`: a score from 0 to 10, with the end
 * labels the server sends as `field.nps`.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */

import type { FormField } from '../types/artisanpack-forms';
import { t } from './i18n';

/** The lowest score, mirroring `NpsFieldType::MIN_SCORE`. */
export const NPS_MIN_SCORE = 0;

/** The highest score, mirroring `NpsFieldType::MAX_SCORE`. */
export const NPS_MAX_SCORE = 10;

/** The scores in order, 0 to 10. */
export const NPS_SCORES: readonly number[] = Array.from( { length: NPS_MAX_SCORE - NPS_MIN_SCORE + 1 }, ( _, index ) => NPS_MIN_SCORE + index );

/** Get the labels shown at the ends of the scale. */
export function getNpsLabels( field: FormField ): { low: string; high: string } {
	return {
		low: field.nps?.low || t( 'Not likely' ),
		high: field.nps?.high || t( 'Extremely likely' ),
	};
}

/**
 * Get a score's accessible name: the number, with the end label for 0 and 10
 * ("0, Not likely").
 */
export function getNpsScoreLabel( field: FormField, score: number ): string {
	const labels = getNpsLabels( field );

	if ( score === NPS_MIN_SCORE ) {
		return t( ':score, :label', { score, label: labels.low } );
	}

	if ( score === NPS_MAX_SCORE ) {
		return t( ':score, :label', { score, label: labels.high } );
	}

	return String( score );
}

/**
 * Validate a score, mirroring `NpsFieldType::typeRules()`: a whole number
 * from 0 to 10.
 *
 * @returns An array of error messages (empty if valid).
 */
export function validateNps( field: FormField, value: unknown ): string[] {
	const score = Number( value );

	if ( !Number.isInteger( score ) || score < NPS_MIN_SCORE || score > NPS_MAX_SCORE ) {
		return [t( 'Please choose a score from :min to :max for :field.', { min: NPS_MIN_SCORE, max: NPS_MAX_SCORE, field: field.label ?? field.name } )];
	}

	return [];
}
