<?php

declare( strict_types=1 );

use Illuminate\Support\ServiceProvider;

describe( 'TypeScript Type Definitions', function (): void {
    describe( 'publishing', function (): void {
        it( 'registers forms-types as a publishable tag', function (): void {
            $publishGroups = ServiceProvider::$publishGroups;

            expect( $publishGroups )->toHaveKey( 'forms-types' );
        } );

        it( 'maps the source type definitions file to the correct destination', function (): void {
            $publishGroups = ServiceProvider::$publishGroups;
            $paths         = $publishGroups['forms-types'] ?? [];

            // Find the source path key that ends with the expected file
            $matchingKeys = array_filter(
                array_keys( $paths ),
                fn ( $key ) => str_ends_with( $key, 'resources/types/artisanpack-forms.d.ts' ),
            );

            expect( $matchingKeys )->not->toBeEmpty();

            $sourcePath  = array_values( $matchingKeys )[0];
            $destination = $paths[ $sourcePath ];
            expect( $destination )->toContain( 'types' )
                ->and( $destination )->toEndWith( 'artisanpack-forms.d.ts' );
        } );

        it( 'has the source type definitions file present', function (): void {
            $sourcePath = __DIR__ . '/../../resources/types/artisanpack-forms.d.ts';

            expect( file_exists( $sourcePath ) )->toBeTrue();
        } );
    } );

    describe( 'type definitions content', function (): void {
        beforeEach( function (): void {
            $this->content = file_get_contents(
                __DIR__ . '/../../resources/types/artisanpack-forms.d.ts',
            );
        } );

        it( 'exports the Form interface', function (): void {
            expect( $this->content )->toContain( 'export interface Form {' );
        } );

        it( 'exports the FormField interface', function (): void {
            expect( $this->content )->toContain( 'export interface FormField {' );
        } );

        it( 'exports the FormStep interface', function (): void {
            expect( $this->content )->toContain( 'export interface FormStep {' );
        } );

        it( 'exports the FormSubmission interface', function (): void {
            expect( $this->content )->toContain( 'export interface FormSubmission {' );
        } );

        it( 'exports the FormSubmissionValue interface', function (): void {
            expect( $this->content )->toContain( 'export interface FormSubmissionValue {' );
        } );

        it( 'exports the FormUpload interface', function (): void {
            expect( $this->content )->toContain( 'export interface FormUpload {' );
        } );

        it( 'exports the FormNotification interface', function (): void {
            expect( $this->content )->toContain( 'export interface FormNotification {' );
        } );

        it( 'exports the FormRenderData interface', function (): void {
            expect( $this->content )->toContain( 'export interface FormRenderData {' );
        } );

        it( 'exports the FieldType union type with all field types', function (): void {
            $fieldTypes = [
                "'text'",
                "'email'",
                "'phone'",
                "'number'",
                "'url'",
                "'textarea'",
                "'hidden'",
                "'select'",
                "'radio'",
                "'checkbox'",
                "'checkbox_group'",
                "'select_multiple'",
                "'file'",
                "'date'",
                "'time'",
                "'heading'",
                "'paragraph'",
                "'divider'",
                "'html'",
            ];

            foreach ( $fieldTypes as $type ) {
                expect( $this->content )->toContain( $type );
            }
        } );

        it( 'exports the ConditionalOperator union type with all operators', function (): void {
            $operators = [
                "'equals'",
                "'not_equals'",
                "'contains'",
                "'not_contains'",
                "'starts_with'",
                "'ends_with'",
                "'is_empty'",
                "'is_not_empty'",
                "'greater_than'",
                "'less_than'",
                "'greater_or_equal'",
                "'less_or_equal'",
                "'in'",
                "'not_in'",
                "'checked'",
                "'unchecked'",
                "'includes'",
                "'not_includes'",
            ];

            foreach ( $operators as $operator ) {
                expect( $this->content )->toContain( $operator );
            }
        } );

        it( 'exports the NotificationType union type', function (): void {
            expect( $this->content )->toContain( "export type NotificationType = 'admin' | 'autoresponder' | 'custom'" );
        } );

        it( 'exports the ConditionalLogic interface', function (): void {
            expect( $this->content )->toContain( 'export interface ConditionalLogic {' )
                ->and( $this->content )->toContain( 'export interface ConditionalLogicRule {' );
        } );

        it( 'exports request and response types', function (): void {
            expect( $this->content )->toContain( 'export interface SubmitFormRequest {' )
                ->and( $this->content )->toContain( 'export interface SubmitFormResponse {' )
                ->and( $this->content )->toContain( 'export interface StoreFormRequest {' )
                ->and( $this->content )->toContain( 'export interface UpdateFormRequest' )
                ->and( $this->content )->toContain( 'export interface StoreFieldRequest {' )
                ->and( $this->content )->toContain( 'export interface StoreNotificationRequest {' );
        } );

        it( 'exports the PaginatedResponse generic type', function (): void {
            expect( $this->content )->toContain( 'export interface PaginatedResponse<T>' );
        } );

        it( 'exports error response types', function (): void {
            expect( $this->content )->toContain( 'export interface ValidationErrorResponse {' )
                ->and( $this->content )->toContain( 'export interface ErrorResponse {' );
        } );

        it( 'exports field configuration types', function (): void {
            expect( $this->content )->toContain( 'export interface FieldOption {' )
                ->and( $this->content )->toContain( 'export interface ValidationRules {' )
                ->and( $this->content )->toContain( 'export interface OptionsFieldConfig' )
                ->and( $this->content )->toContain( 'export interface FileFieldConfig' )
                ->and( $this->content )->toContain( 'export type FieldConfig' );
        } );

        it( 'exports display and honeypot config types', function (): void {
            expect( $this->content )->toContain( 'export interface HoneypotConfig {' )
                ->and( $this->content )->toContain( 'export interface DisplayConfig {' );
        } );

        it( 'exports the FieldTypeMetadata interface', function (): void {
            expect( $this->content )->toContain( 'export interface FieldTypeMetadata {' )
                ->and( $this->content )->toContain( 'export type FieldTypeMap' );
        } );
    } );
} );
