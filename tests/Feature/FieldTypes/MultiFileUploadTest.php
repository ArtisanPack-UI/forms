<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormSubmissionValueResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormUpload;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use ArtisanPackUI\Forms\Support\SubmissionRules;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Guards issue #114: multi-file uploads, total size, image dimensions and
 * listing every file.
 */
beforeEach( function (): void {
    Storage::fake( 'form-uploads' );

    $this->form  = Form::factory()->create( ['is_active' => true] );
    $this->field = FormField::factory()->for( $this->form )->create( [
        'type'         => 'file',
        'name'         => 'docs',
        'label'        => 'Documents',
        'is_required'  => false,
        'field_config' => [
            'multiple'       => true,
            'max_files'      => 3,
            'max_size'       => 200,
            'max_total_size' => 300,
            'allowed_types'  => ['pdf', 'png'],
        ],
    ] );
} );

describe( 'settings', function (): void {
    it( 'offers the multi-file and image settings', function (): void {
        expect( array_column( FieldSettings::schema( $this->field ), 'key' ) )
            ->toContain( 'multiple', 'max_files', 'max_total_size', 'min_width', 'max_width', 'min_height', 'max_height' );
    } );

    it( 'reads the limits only when multiple is on', function (): void {
        $single = FormField::factory()->make( ['type' => 'file', 'name' => 'f', 'field_config' => ['max_files' => 3, 'max_total_size' => 10]] );

        expect( $this->field->allowsMultipleFiles() )->toBeTrue()
            ->and( $this->field->getFileMaxCount() )->toBe( 3 )
            ->and( $this->field->getFileMaxTotalSize() )->toBe( 300 )
            ->and( $single->allowsMultipleFiles() )->toBeFalse()
            ->and( $single->getFileMaxCount() )->toBeNull()
            ->and( $single->getFileMaxTotalSize() )->toBeNull();
    } );
} );

describe( 'validation rules', function (): void {
    it( 'checks the list and then each file', function (): void {
        expect( $this->field->buildKeyedValidationRules() )->toBe( [
            ''  => ['nullable', 'array', 'max:3', 'forms_total_size:300'],
            '*' => ['file', 'mimes:pdf,png', 'max:200'],
        ] );
    } );

    it( 'keys the messages for each file under *', function (): void {
        $messages = $this->field->buildValidationMessages();

        expect( $messages['max'] )->toBe( 'You may upload no more than 3 files for Documents.' )
            ->and( $messages['forms_total_size'] )->toBe( 'The files for Documents must not exceed 300 KB in total.' )
            ->and( $messages['*.max'] )->toBe( 'The Documents must not exceed the maximum file size.' )
            ->and( $messages['*.mimes'] )->toBe( 'The Documents must be a file of the allowed types.' )
            ->and( $messages )->not->toHaveKey( 'mimes' );
    } );

    it( 'adds the image dimension rule when bounds are set', function (): void {
        $this->field->update( ['field_config' => ['multiple' => false, 'min_width' => 100, 'max_height' => 500]] );

        expect( $this->field->fresh()->buildValidationRules() )->toContain( 'forms_image_dimensions:100,,,500' );
    } );

    it( 'checks image dimensions and lets other files through', function (): void {
        expect( SubmissionRules::withinImageDimensions( UploadedFile::fake()->image( 'a.png', 150, 80 ), ['100', '', '', '100'] ) )->toBeTrue()
            ->and( SubmissionRules::withinImageDimensions( UploadedFile::fake()->image( 'a.png', 50, 80 ), ['100', '', '', ''] ) )->toBeFalse()
            ->and( SubmissionRules::withinImageDimensions( UploadedFile::fake()->image( 'a.png', 150, 800 ), ['', '', '', '500'] ) )->toBeFalse()
            ->and( SubmissionRules::withinImageDimensions( UploadedFile::fake()->create( 'a.pdf', 10, 'application/pdf' ), ['100', '', '', ''] ) )->toBeTrue();
    } );

    it( 'checks the total size of a list of files', function (): void {
        $files = [UploadedFile::fake()->create( 'a.pdf', 200 ), UploadedFile::fake()->create( 'b.pdf', 150 )];

        expect( SubmissionRules::withinTotalSize( $files, ['300'] ) )->toBeFalse()
            ->and( SubmissionRules::withinTotalSize( $files, ['400'] ) )->toBeTrue()
            ->and( SubmissionRules::withinTotalSize( 'not files', ['1'] ) )->toBeTrue();
    } );
} );

describe( 'Livewire uploads', function (): void {
    it( 'stores one upload per file and one value listing them in order', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->afterSpamDelay()
            ->set( 'files.docs.fa', UploadedFile::fake()->create( 'first.pdf', 50, 'application/pdf' ) )
            ->set( 'files.docs.fb', UploadedFile::fake()->create( 'second.pdf', 50, 'application/pdf' ) )
            ->call( 'submit' )
            ->assertHasNoErrors()
            ->assertSet( 'isSubmitted', true );

        $submission = FormSubmission::with( 'uploads' )->firstOrFail();
        $value      = $submission->values()->firstOrFail();

        expect( $submission->uploads )->toHaveCount( 2 )
            ->and( $submission->values()->count() )->toBe( 1 )
            ->and( $value->upload_id )->toBe( $submission->uploads->first()->id )
            ->and( $value->value_array )->toBe( $submission->uploads->pluck( 'id' )->all() )
            ->and( array_map( fn ( FormUpload $upload ): string => $upload->original_name, $value->getUploads() ) )->toBe( ['first.pdf', 'second.pdf'] )
            ->and( $value->display_value )->toBe( 'first.pdf, second.pdf' )
            ->and( $value->display_values )->toBe( ['first.pdf', 'second.pdf'] )
            ->and( $value->getRawValue() )->toBe( 'first.pdf, second.pdf' )
            ->and( $value->is_array_value )->toBeFalse();
    } );

    it( 'removes one file before submitting', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->afterSpamDelay()
            ->set( 'files.docs.fa', UploadedFile::fake()->create( 'first.pdf', 50, 'application/pdf' ) )
            ->set( 'files.docs.fb', UploadedFile::fake()->create( 'second.pdf', 50, 'application/pdf' ) )
            ->call( 'removeFile', 'docs', 'fa' )
            ->call( 'submit' )
            ->assertHasNoErrors();

        expect( FormUpload::query()->pluck( 'original_name' )->all() )->toBe( ['second.pdf'] );
    } );

    it( 'rejects too many files, too large a total and a disallowed file', function (): void {
        $component = Livewire::test( FormRenderer::class, ['form' => $this->form] )->afterSpamDelay();

        foreach ( ['a', 'b', 'c', 'd'] as $key ) {
            $component->set( "files.docs.{$key}", UploadedFile::fake()->create( "{$key}.pdf", 10, 'application/pdf' ) );
        }

        $component->call( 'submit' )->assertHasErrors( ['files.docs' => 'max'] );

        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->afterSpamDelay()
            ->set( 'files.docs.a', UploadedFile::fake()->create( 'a.pdf', 180, 'application/pdf' ) )
            ->set( 'files.docs.b', UploadedFile::fake()->create( 'b.pdf', 180, 'application/pdf' ) )
            ->call( 'submit' )
            ->assertHasErrors( ['files.docs' => 'forms_total_size'] );

        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->afterSpamDelay()
            ->set( 'files.docs.a', UploadedFile::fake()->create( 'a.exe', 10 ) )
            ->call( 'submit' )
            ->assertHasErrors( ['files.docs.a' => 'mimes'] )
            ->assertSee( 'The Documents must be a file of the allowed types.' );

        expect( FormSubmission::count() )->toBe( 0 );
    } );

    it( 'requires at least one file when required', function (): void {
        $this->field->update( ['is_required' => true] );

        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->afterSpamDelay()
            ->call( 'submit' )
            ->assertHasErrors( ['files.docs' => 'required'] );
    } );
} );

describe( 'hardening', function (): void {
    it( 'does not store files uploaded to a field conditional logic hides', function (): void {
        FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'show_docs', 'sort_order' => 0] );
        $this->field->update( ['conditional_logic' => [
            'action'    => 'show',
            'logic'     => 'all',
            'rules'     => [['field' => 'show_docs', 'operator' => 'equals', 'value' => 'yes']],
        ]] );

        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->afterSpamDelay()
            ->set( 'formData.show_docs', 'no' )
            ->set( 'files.docs.a', UploadedFile::fake()->create( 'too-big.pdf', 500, 'application/pdf' ) )
            ->call( 'submit' )
            ->assertHasNoErrors()
            ->assertSet( 'isSubmitted', true );

        expect( FormUpload::count() )->toBe( 0 );
    } );

    it( 'only returns uploads of the value\'s own submission', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->afterSpamDelay()
            ->set( 'files.docs.a', UploadedFile::fake()->create( 'mine.pdf', 10, 'application/pdf' ) )
            ->call( 'submit' );

        $other              = FormUpload::factory()->create( ['original_name' => 'theirs.pdf'] );
        $value              = FormSubmission::firstOrFail()->values()->firstOrFail();
        $value->value_array = [...$value->value_array, $other->id];

        expect( $value->display_values )->toBe( ['mine.pdf'] );
    } );
} );

describe( 'API uploads', function (): void {
    it( 'stores every file sent as files[name][]', function (): void {
        $this->post( route( 'api.forms.submit', $this->form ), [
            'data'            => [],
            'files'           => ['docs' => [
                UploadedFile::fake()->create( 'one.pdf', 20, 'application/pdf' ),
                UploadedFile::fake()->create( 'two.pdf', 20, 'application/pdf' ),
            ]],
            '_form_loaded_at' => time() - 10,
        ], ['Accept' => 'application/json'] )->assertCreated();

        $value = FormSubmission::firstOrFail()->values()->firstOrFail();

        expect( $value->display_value )->toBe( 'one.pdf, two.pdf' )
            ->and( ( new FormSubmissionValueResource( $value ) )->toArray( Request::create( '/' ) )['upload_ids'] )->toBe( $value->value_array );
    } );

    it( 'rejects too many files through the API', function (): void {
        $this->post( route( 'api.forms.submit', $this->form ), [
            'data'            => [],
            'files'           => ['docs' => array_map( fn ( int $i ): UploadedFile => UploadedFile::fake()->create( "{$i}.pdf", 1, 'application/pdf' ), range( 1, 4 ) )],
            '_form_loaded_at' => time() - 10,
        ], ['Accept' => 'application/json'] )->assertUnprocessable()->assertJsonValidationErrors( 'files.docs' );
    } );
} );

describe( 'single files', function (): void {
    it( 'still stores a single file with no value array', function (): void {
        $this->field->update( ['field_config' => ['allowed_types' => ['pdf']]] );

        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->afterSpamDelay()
            ->set( 'files.docs', UploadedFile::fake()->create( 'only.pdf', 10, 'application/pdf' ) )
            ->call( 'submit' )
            ->assertHasNoErrors();

        $value = FormSubmission::firstOrFail()->values()->firstOrFail();

        expect( $value->value_array )->toBeNull()
            ->and( $value->display_value )->toBe( 'only.pdf' )
            ->and( ( new FormSubmissionValueResource( $value ) )->toArray( Request::create( '/' ) )['upload_ids'] )->toBe( [$value->upload_id] );
    } );
} );

describe( 'single-file fields given several files (issue #146)', function (): void {
    beforeEach( function (): void {
        $this->field->update( ['field_config' => ['allowed_types' => ['pdf']]] );
    } );

    it( 'rejects a list of files on a single-file field in Livewire instead of dropping them', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->afterSpamDelay()
            ->set( 'files.docs.fa', UploadedFile::fake()->create( 'first.pdf', 10, 'application/pdf' ) )
            ->set( 'files.docs.fb', UploadedFile::fake()->create( 'second.pdf', 10, 'application/pdf' ) )
            ->call( 'submit' )
            ->assertHasErrors( ['files.docs' => 'file'] );

        expect( FormSubmission::count() )->toBe( 0 )
            ->and( FormUpload::count() )->toBe( 0 );
    } );

    it( 'rejects files[name][] on a single-file field through the API', function (): void {
        $this->post( route( 'api.forms.submit', $this->form ), [
            'data'            => [],
            'files'           => ['docs' => [
                UploadedFile::fake()->create( 'one.pdf', 20, 'application/pdf' ),
                UploadedFile::fake()->create( 'two.pdf', 20, 'application/pdf' ),
            ]],
            '_form_loaded_at' => time() - 10,
        ], ['Accept' => 'application/json'] )->assertUnprocessable()->assertJsonValidationErrors( 'files.docs' );

        expect( FormSubmission::count() )->toBe( 0 );
    } );

    it( 'keeps only the first dropped file in the React and Vue single-file inputs', function (): void {
        $react = file_get_contents( __DIR__ . '/../../../resources/js/react/components/fields/AdvancedField.tsx' );
        $vue   = file_get_contents( __DIR__ . '/../../../resources/js/vue/components/fields/AdvancedField.vue' );

        $single = substr( $react, strpos( $react, 'function SingleFileField(' ) );
        $single = substr( $single, 0, strpos( $single, "\n}\n" ) );

        $handler = substr( $vue, strpos( $vue, 'function handleFilesSelected(' ) );
        $handler = substr( $handler, 0, strpos( $handler, "\n}\n" ) );

        expect( $single )->toContain( 'onFileChange( fileList[0] );' )
            ->not->toContain( 'Array.from( fileList )' )
            ->and( $handler )->toContain( "emit( 'fileChange', fileList[0] );" )
            ->not->toContain( 'Array.from( fileList )' );
    } );
} );

describe( 'rendering', function (): void {
    it( 'renders a drop zone around a keyboard-operable multiple file input', function (): void {
        $html = FieldRenderer::render( $this->field, null );

        expect( $html )->toContain( 'x-on:drop.prevent=' )
            ->toContain( 'type="file"' )
            ->toContain( 'multiple' )
            ->toContain( 'id="field-docs"' )
            ->toContain( 'this.$wire.upload(' )
            ->toContain( 'this.$wire.removeFile(this.field, item.key)' )
            ->toContain( '<progress' )
            ->toContain( 'aria-live="polite"' )
            ->toContain( 'Up to 3 files' )
            ->toContain( 'Maximum total size: 300 KB' )
            ->not->toContain( 'wire:model="files.docs"' );
    } );

    it( 'shows the first error of any one file', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->afterSpamDelay()
            ->set( 'files.docs.zz', UploadedFile::fake()->create( 'big.pdf', 250, 'application/pdf' ) )
            ->call( 'submit' )
            ->assertSee( 'The Documents must not exceed the maximum file size.' );
    } );
} );
