<?php

declare( strict_types=1 );

/**
 * Guards the React, Vue and shared JS side of issues #129 (signature), #130
 * (slider), #131 (Likert / matrix), #132 (Net Promoter Score) and #133
 * (ranking). The surfaces are validated by source content (the package ships
 * no JS build or test harness of its own), matching
 * SectionListDateRangeFrontendTest.
 */

$resource = static fn ( string $path ): string => file_get_contents( __DIR__ . '/../../resources/js/' . $path );

/**
 * Returns the part of `$haystack` between the first `$start` and the next
 * `$end` after it, so an assertion can be scoped to one block of code.
 */
$between = static function ( string $haystack, string $start, string $end ): string {
    $from = strpos( $haystack, $start );
    expect( $from )->not->toBeFalse();

    $to = strpos( $haystack, $end, $from + strlen( $start ) );
    expect( $to )->not->toBeFalse();

    return substr( $haystack, $from, $to - $from );
};

describe( 'shared helpers', function () use ( $resource, $between ): void {
    it( 'mirrors the slider limits, value label and step check', function () use ( $resource, $between ): void {
        $slider = $resource( 'shared/slider.ts' );

        expect( $between( $slider, 'export function getInitialSliderValue(', "\n}\n" ) )
            ->toContain( 'value >= min && value <= max ? value : min' )
            ->and( $between( $slider, 'export function formatSliderValue(', "\n}\n" ) )
            ->toContain( '/[\p{L}\p{N}]$/u.test( prefix )' )
            ->and( $between( $slider, 'export function isOnStep(', "\n}\n" ) )
            ->toContain( 'Math.abs( steps - Math.round( steps ) ) < 1e-9 * Math.max( 1, Math.abs( steps ) )' )
            ->and( $between( $slider, 'export function validateSlider(', "\n}\n" ) )
            ->toContain( "t( 'The :field must be between :min and :max.'" )
            ->toContain( "t( 'The :field must be in steps of :step.'" );
    } );

    it( 'mirrors the NPS scale and its end labels', function () use ( $resource, $between ): void {
        $nps = $resource( 'shared/nps.ts' );

        expect( $nps )->toContain( 'export const NPS_MIN_SCORE = 0;' )
            ->toContain( 'export const NPS_MAX_SCORE = 10;' )
            ->and( $between( $nps, 'export function getNpsScoreLabel(', "\n}\n" ) )
            ->toContain( "t( ':score, :label', { score, label: labels.low } )" )
            ->and( $between( $nps, 'export function validateNps(', "\n}\n" ) )
            ->toContain( '!Number.isInteger( score ) || score < NPS_MIN_SCORE || score > NPS_MAX_SCORE' );
    } );

    it( 'validates matrix rows like MatrixFieldType', function () use ( $resource, $between ): void {
        $matrix = $resource( 'shared/matrix.ts' );

        expect( $between( $matrix, 'export function validateMatrixField(', "\n}\n" ) )
            ->toContain( "errors[`\${field.name}.\${row.key}`] = [field.required_message ?? t( ':field: please answer \":row\".', replacements )]" )
            ->toContain( "t( ':field: please choose a valid answer for \":row\".', replacements )" )
            ->toContain( 'if ( isSingleScale( field ) ) {' )
            ->and( $between( $matrix, 'export function getInitialMatrixValue(', "\n}\n" ) )
            ->toContain( 'return toMatrixAnswers( field, source );' );
    } );

    it( 'orders, moves and validates rankings like RankingFieldType', function () use ( $resource, $between ): void {
        $ranking = $resource( 'shared/ranking.ts' );

        expect( $between( $ranking, 'export function getRankingOrder(', "\n}\n" ) )
            ->toContain( 'return [...order, ...choices.filter( ( choice ) => !order.includes( choice ) )];' )
            ->and( $between( $ranking, 'export function moveRankingItem(', "\n}\n" ) )
            ->toContain( 'next.splice( to, 0, moved );' )
            ->and( $between( $ranking, 'export function validateRanking(', "\n}\n" ) )
            ->toContain( 'new Set( items ).size === items.length' );
    } );

    it( 'checks signatures and draws on the pad', function () use ( $resource, $between ): void {
        $signature = $resource( 'shared/signature.ts' );

        expect( $signature )->toContain( "export const SIGNATURE_DATA_URL_PREFIX = 'data:image/png;base64,';" )
            ->toContain( 'export const SIGNATURE_TYPED_MAX_LENGTH = 255;' )
            ->toContain( 'export const SIGNATURE_MAX_BYTES = 512 * 1024;' )
            ->and( $between( $signature, 'export function validateSignature(', "\n}\n" ) )
            ->toContain( 'encoded.length <= Math.ceil( maxBytes / 3 ) * 4' )
            ->toContain( '!/[\x00-\x1F\x7F]/.test( value )' )
            ->and( $signature )->toContain( 'export class SignaturePad {' )
            ->toContain( "canvas.addEventListener( 'pointerdown', this.start );" )
            ->toContain( 'this.observer = new ResizeObserver( () => this.resize() );' )
            ->toContain( "return this.canvas.toDataURL( 'image/png' );" );
    } );

    it( 'validates the new types and starts them with their own values', function () use ( $resource ): void {
        expect( $resource( 'shared/validation.ts' ) )
            ->toContain( "field.type === 'checkbox_group' || field.type === 'select_multiple' || field.type === 'ranking'" )
            ->toContain( "slider: validateSlider,\n\tnps: validateNps,\n\tranking: validateRanking,\n\tsignature: validateSignature," )
            ->toContain( 'Object.assign( errors, validateMatrixField( field, formData[field.name] ) );' )
            ->and( $resource( 'shared/fieldSettings.ts' ) )
            ->toContain( 'return getInitialMatrixValue( field, field.resolved_default_value );' )
            ->toContain( 'return getRankingOrder( field, field.resolved_default_value );' )
            ->toContain( 'return getInitialSliderValue( field, source );' );
    } );

    it( 'labels the new types', function () use ( $resource ): void {
        expect( $resource( 'shared/fieldTypeLabels.ts' ) )
            ->toContain( "slider: t( 'Slider' )," )
            ->toContain( "nps: t( 'Net Promoter Score' )," )
            ->toContain( "matrix: t( 'Likert / Matrix' )," )
            ->toContain( "ranking: t( 'Ranking' )," )
            ->toContain( "signature: t( 'Signature' )," );
    } );
} );

describe( 'types', function () use ( $resource ): void {
    it( 'declares the new types, configs and API payloads', function () use ( $resource ): void {
        expect( $resource( 'types/artisanpack-forms.d.ts' ) )
            ->toContain( "| 'slider'" )
            ->toContain( "| 'nps'" )
            ->toContain( "| 'matrix'" )
            ->toContain( "| 'ranking'" )
            ->toContain( "| 'signature'" )
            ->toContain( 'export interface SliderFieldConfig extends CommonFieldConfig {' )
            ->toContain( 'export interface SliderSettings {' )
            ->toContain( 'export interface NpsFieldConfig extends CommonFieldConfig {' )
            ->toContain( 'export interface MatrixFieldConfig extends CommonFieldConfig {' )
            ->toContain( 'export interface MatrixRow {' )
            ->toContain( 'export interface MatrixColumn {' )
            ->toContain( 'export interface RankingFieldConfig extends CommonFieldConfig {' )
            ->toContain( 'export interface SignatureFieldConfig extends CommonFieldConfig {' )
            ->toContain( 'slider?: SliderSettings;' )
            ->toContain( 'nps?: { low: string; high: string };' )
            ->toContain( 'matrix?: { single: boolean; rows: MatrixRow[]; columns: MatrixColumn[] };' )
            ->toContain( 'signature?: { pad_height: number; max_bytes: number };' );
    } );
} );

describe( 'React', function () use ( $resource, $between ): void {
    it( 'renders the new types', function () use ( $resource ): void {
        expect( $resource( 'react/components/fields/FieldRenderer.tsx' ) )
            ->toContain( "slider: SliderField,\n\tnps: NpsField,\n\tmatrix: MatrixField,\n\tranking: RankingField," )
            ->toContain( 'signature: SignatureField,' );
    } );

    it( 'gives the slider a value text and the NPS scores their names', function () use ( $resource, $between ): void {
        $survey = $resource( 'react/components/fields/SurveyField.tsx' );

        expect( $between( $survey, 'export function SliderField(', "\n}\n" ) )
            ->toContain( 'type="range"' )
            ->toContain( 'aria-valuetext={text}' )
            ->toContain( '<output htmlFor={inputId}' )
            ->and( $between( $survey, 'export function NpsField(', "\n}\n" ) )
            ->toContain( 'role="radiogroup"' )
            ->toContain( 'className="peer sr-only"' )
            ->toContain( 'aria-label={getNpsScoreLabel( field, score )}' )
            ->toContain( "aria-describedby={[endsId, ids.describedBy].filter( Boolean ).join( ' ' )}" );
    } );

    it( 'names each matrix radio by its row and column', function () use ( $resource, $between ): void {
        $matrix = $between( $resource( 'react/components/fields/SurveyField.tsx' ), 'export function MatrixField(', "\n}\n" );

        expect( $matrix )->toContain( '<th key={column.value} scope="col" id={`field-${fieldIdKey( field )}-col-${index}`}' )
            ->toContain( '<th scope="row"' )
            ->toContain( 'aria-labelledby={`${rowId} field-${fieldIdKey( field )}-col-${index}`}' )
            ->toContain( 'name={`${field.name}[${row.key}]`}' )
            ->toContain( 'const rowError = subErrors[row.key];' );
    } );

    it( 'reorders rankings with buttons and drag and drop, keeping focus and announcing', function () use ( $resource ): void {
        expect( $resource( 'react/components/fields/RankingField.tsx' ) )
            ->toContain( "aria-label={t( 'Move :choice up', { choice: label } )}" )
            ->toContain( "aria-label={t( 'Move :choice down', { choice: label } )}" )
            ->toContain( 'draggable={!readOnly}' )
            ->toContain( 'onDrop={( e ) => {' )
            ->toContain( "t( ':choice moved to position :position of :total.'" )
            ->toContain( 'button = document.getElementById( `${idPrefix}${focusTarget.position}-${other}` )' )
            ->toContain( 'aria-live="polite"' );
    } );

    it( 'offers a typed alternative to drawing a signature', function () use ( $resource ): void {
        expect( $resource( 'react/components/fields/SignatureField.tsx' ) )
            ->toContain( 'new SignaturePad( canvas.current, padHeight' )
            ->toContain( 'role="img"' )
            ->toContain( "aria-pressed={mode === 'type'}" )
            ->toContain( "t( 'Type your full name' )" )
            ->toContain( 'autoComplete="name"' )
            ->toContain( "t( 'Clear signature' )" )
            ->toContain( 'return () => signaturePad.destroy();' );
    } );

    it( 'exports the components and helpers', function () use ( $resource ): void {
        expect( $resource( 'react/index.ts' ) )
            ->toContain( "export { MatrixField, NpsField, SliderField } from './components/fields/SurveyField';" )
            ->toContain( "export { RankingField } from './components/fields/RankingField';" )
            ->toContain( "export { SignatureField } from './components/fields/SignatureField';" )
            ->toContain( "export { isDrawnSignature, SignaturePad, validateSignature } from '../shared/signature';" );
    } );

    it( 'lists the new types in the builder', function () use ( $resource ): void {
        expect( $resource( 'react/components/admin/FieldPalette.tsx' ) )
            ->toContain( "{ type: 'slider', label: t( 'Slider' ), icon: 'slider', category: 'advanced' }," )
            ->toContain( "{ type: 'signature', label: t( 'Signature' ), icon: 'signature', category: 'advanced' }," )
            ->and( $resource( 'react/components/admin/FieldEditor.tsx' ) )
            ->toContain( "\t'select_multiple',\n\t'ranking',\n] );" )
            ->toContain( "new Set( ['consent', 'captcha', 'list', 'matrix', 'ranking', 'signature', 'calculation'] )" )
            ->and( $resource( 'react/components/admin/OptionsEditor.tsx' ) )
            ->toContain( "const hasDefaults = field.type !== 'ranking';" );
    } );

    it( 'shows field HTML before uploads in the submission detail', function () use ( $resource ): void {
        $detail = $resource( 'react/components/admin/SubmissionDetail.tsx' );

        expect( strpos( $detail, ') : value.display_html ? (' ) )->toBeLessThan( strpos( $detail, ') : value.upload_id && submission.uploads ? (' ) );
    } );
} );

describe( 'Vue', function () use ( $resource ): void {
    it( 'renders the new types', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/FieldRenderer.vue' ) )
            ->toContain( "const SURVEY_TYPES = new Set( ['slider', 'nps', 'matrix', 'ranking', 'signature'] );" )
            ->toContain( "v-else-if=\"componentGroup === 'matrix'\"" )
            ->toContain( ':sub-errors="subErrors"' )
            ->toContain( "v-else-if=\"componentGroup === 'signature'\"" );
    } );

    it( 'gives the slider a value text and the NPS scores their names', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/SliderField.vue' ) )
            ->toContain( 'type="range"' )
            ->toContain( ':aria-valuetext="valueText"' )
            ->and( $resource( 'vue/components/fields/NpsField.vue' ) )
            ->toContain( 'class="peer sr-only"' )
            ->toContain( ':aria-label="getNpsScoreLabel( field, score )"' );
    } );

    it( 'names each matrix radio by its row and column', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/MatrixField.vue' ) )
            ->toContain( 'scope="col"' )
            ->toContain( 'scope="row"' )
            ->toContain( ':aria-labelledby="`${rowId( row.key )} field-${fieldIdKey( field )}-col-${index}`"' )
            ->toContain( ':name="`${field.name}[${row.key}]`"' );
    } );

    it( 'reorders rankings and offers a typed signature', function () use ( $resource ): void {
        expect( $resource( 'vue/components/fields/RankingField.vue' ) )
            ->toContain( ":aria-label=\"t( 'Move :choice up', { choice: labels[choice] ?? choice } )\"" )
            ->toContain( '@drop.prevent="drop( index )"' )
            ->toContain( "t( ':choice moved to position :position of :total.'" )
            ->toContain( 'aria-live="polite"' )
            ->and( $resource( 'vue/components/fields/SignatureField.vue' ) )
            ->toContain( 'new SignaturePad( canvas.value, padHeight.value' )
            ->toContain( 'role="img"' )
            ->toContain( "t( 'Type your full name' )" )
            ->toContain( 'onBeforeUnmount( () => pad?.destroy() );' );
    } );

    it( 'exports the components and lists them in the builder', function () use ( $resource ): void {
        expect( $resource( 'vue/index.ts' ) )
            ->toContain( "export { default as SliderField } from './components/fields/SliderField.vue';" )
            ->toContain( "export { default as SignatureField } from './components/fields/SignatureField.vue';" )
            ->and( $resource( 'vue/components/admin/FieldPalette.vue' ) )
            ->toContain( "{ type: 'ranking', label: t( 'Ranking' ), icon: 'ranking', category: 'advanced' }," )
            ->and( $resource( 'vue/components/admin/FieldEditor.vue' ) )
            ->toContain( "new Set<FieldType>( ['consent', 'captcha', 'list', 'matrix', 'ranking', 'signature', 'calculation'] )" )
            ->and( $resource( 'vue/components/admin/OptionsEditor.vue' ) )
            ->toContain( "const hasDefaults = computed( () => props.field.type !== 'ranking' );" );
    } );

    it( 'shows field HTML before uploads in the submission detail', function () use ( $resource ): void {
        $detail = $resource( 'vue/components/admin/SubmissionDetail.vue' );

        expect( strpos( $detail, '<div v-else-if="value.display_html"' ) )->toBeLessThan( strpos( $detail, '<template v-else-if="value.upload_id && submission.uploads">' ) );
    } );
} );
