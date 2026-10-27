<?php

declare( strict_types=1 );

/**
 * Guards issue #157 in the React and Vue admin: administrative file fields
 * show their stored files with remove buttons, and chosen files are uploaded
 * in a multipart update.
 */
$resource = static fn ( string $path ): string => file_get_contents( __DIR__ . '/../../resources/js/' . $path );

describe( 'TypeScript definitions', function () use ( $resource ): void {
    it( 'describe removing administrative files', function () use ( $resource ): void {
        expect( $resource( 'types/artisanpack-forms.d.ts' ) )->toContain( 'remove_admin_uploads?: number[];' );
    } );
} );

describe( 'React and Vue submission detail', function () use ( $resource ): void {
    it( 'send a file field\'s files apart from the values', function ( string $path ): void {
        expect( file_get_contents( __DIR__ . '/../../resources/js/' . $path ) )
            ->toContain( ".filter( ( field ) => field.type !== 'file' )" )
            ->toContain( "body.append( '_method', 'PUT' );" )
            ->toContain( 'body.append( `admin_files[${name}][]`, item )' )
            ->toContain( 'body.append( `admin_files[${name}]`, file )' )
            ->toContain( 'buildAdminFilesBody(' );
    } )->with( ['react/components/admin/SubmissionDetail.tsx', 'vue/components/admin/SubmissionDetail.vue'] );

    it( 'map file errors back to the field', function ( string $path ): void {
        expect( file_get_contents( __DIR__ . '/../../resources/js/' . $path ) )
            ->toContain( 'key.match( /^admin_files\.([^.]+)/ )' );
    } )->with( ['react/components/admin/SubmissionDetail.tsx', 'vue/components/admin/SubmissionDetail.vue'] );

    it( 'confirm before removing a file and name it on the remove button', function ( string $path ): void {
        expect( file_get_contents( __DIR__ . '/../../resources/js/' . $path ) )
            ->toContain( "window.confirm( t( 'Remove :name? The file will be deleted.', { name: upload.original_name } ) )" )
            ->toContain( '{ remove_admin_uploads: [upload.id] }' )
            ->toContain( "t( 'Remove :name', { name: upload.original_name } )" )
            ->toContain( "t( 'Current files for :field', { field:" );
    } )->with( ['react/components/admin/SubmissionDetail.tsx', 'vue/components/admin/SubmissionDetail.vue'] );

    it( 'announce uploads and removals', function ( string $path ): void {
        expect( file_get_contents( __DIR__ . '/../../resources/js/' . $path ) )
            ->toContain( 'aria-live="polite"' )
            ->toContain( "t( 'Uploading files…' )" )
            ->toContain( "t( 'Upload finished.' )" )
            ->toContain( "t( ':name removed.', { name: upload.original_name } )" );
    } )->with( ['react/components/admin/SubmissionDetail.tsx', 'vue/components/admin/SubmissionDetail.vue'] );

    it( 'use the labelled file input of the field renderer', function () use ( $resource ): void {
        expect( $resource( 'react/components/admin/SubmissionDetail.tsx' ) )
            ->toContain( 'onFileChange={( file ) => {' )
            ->toContain( 'key={`${field.id}-${adminFileInputsKey}`}' );

        expect( $resource( 'vue/components/admin/SubmissionDetail.vue' ) )
            ->toContain( '@file-change="setAdminFile( field.name, $event )"' )
            ->toContain( ':key="`${field.id}-${adminFileInputsKey}`"' );
    } );

    it( 'only require a file input while the field has no stored file', function () use ( $resource ): void {
        expect( $resource( 'react/components/admin/SubmissionDetail.tsx' ) )
            ->toContain( 'field={getFieldUploads( field, submission ).length > 0 ? { ...field, is_required: false } : field}' );

        expect( $resource( 'vue/components/admin/SubmissionDetail.vue' ) )
            ->toContain( ':field="getFieldUploads( field ).length > 0 ? { ...field, is_required: false } : field"' );
    } );

    it( 'list every new admin string for translation', function () use ( $resource ): void {
        expect( json_decode( $resource( 'shared/translation-keys.json' ), true ) )
            ->toContain( 'Remove :name? The file will be deleted.', 'Current files for :field', 'Uploading files…', 'Upload finished.', 'The upload failed.' );
    } );
} );
