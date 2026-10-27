<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormFieldResource;
use ArtisanPackUI\Forms\Livewire\FormBuilder;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\ChoiceImages;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;
use Tests\Models\User as TestUser;

/**
 * Guards issue #134: image choices for radio and checkbox groups.
 */
beforeEach( function (): void {
    $this->form         = Form::factory()->create( ['is_active' => true] );
    $this->shirtOptions = [
        ['value' => 'red', 'label' => 'Red shirt', 'image' => 'https://example.com/red.png', 'image_alt' => 'A red T-shirt, folded'],
        ['value' => 'blue', 'label' => 'Blue shirt', 'image' => '/storage/forms/blue.png', 'image_alt' => ''],
        ['value' => 'green', 'label' => 'Green shirt', 'image' => 'javascript:alert(1)', 'image_alt' => 'Green'],
        ['value' => 'plain', 'label' => 'Plain shirt'],
    ];
    $this->radio = FormField::factory()->for( $this->form )->create( [
        'type'         => 'radio',
        'name'         => 'shirt',
        'label'        => 'Shirt',
        'help_text'    => null,
        'field_config' => ['display' => 'images', 'options' => $this->shirtOptions],
    ] );
} );

describe( 'setting', function (): void {
    it( 'is offered by radio and checkbox groups only', function (): void {
        expect( FieldSettings::find( $this->radio, 'display' ) )->toMatchArray( ['input' => 'select', 'default' => 'list'] )
            ->and( array_column( FieldSettings::find( $this->radio, 'display' )['options'], 'value' ) )->toBe( ['list', 'images'] )
            ->and( FieldSettings::find( FormField::factory()->make( ['type' => 'checkbox_group'] ), 'display' ) )->not->toBeNull()
            ->and( FieldSettings::find( FormField::factory()->make( ['type' => 'select'] ), 'display' ) )->toBeNull();
    } );

    it( 'shows image choices only when the setting is on and the type offers it', function (): void {
        expect( $this->radio->showsImageChoices() )->toBeTrue()
            ->and( FormField::factory()->make( ['type' => 'radio', 'field_config' => ['display' => 'list']] )->showsImageChoices() )->toBeFalse()
            ->and( FormField::factory()->make( ['type' => 'select', 'field_config' => ['display' => 'images']] )->showsImageChoices() )->toBeFalse();
    } );
} );

describe( 'image URLs', function (): void {
    it( 'keeps only http, https and site path URLs', function ( mixed $url, ?string $expected ): void {
        expect( ChoiceImages::url( ['image' => $url] ) )->toBe( $expected );
    } )->with( [
        'https'               => ['https://example.com/a.png', 'https://example.com/a.png'],
        'http'                => ['http://example.com/a.png', 'http://example.com/a.png'],
        'site path'           => ['  /storage/a.png ', '/storage/a.png'],
        'protocol relative'   => ['//evil.example/a.png', null],
        'javascript'          => ['javascript:alert(1)', null],
        'data'                => ['data:image/png;base64,AAAA', null],
        'quote'               => ['https://example.com/"onerror="x', null],
        'space'               => ['https://example.com/a b.png', null],
        'relative'            => ['a.png', null],
        'empty host'          => ['https:///a.png', null],
        'not a string'        => [['x'], null],
    ] );

    it( 'sanitizes the options sent to the React and Vue renderers', function (): void {
        $resource = ( new FormFieldResource( $this->radio ) )->withResolvedDefault()->toArray( Request::create( '/' ) );

        expect( $resource['options'][0] )->toMatchArray( ['image' => 'https://example.com/red.png', 'image_alt' => 'A red T-shirt, folded'] )
            ->and( $resource['options'][1] )->toMatchArray( ['image' => '/storage/forms/blue.png', 'image_alt' => ''] )
            ->and( $resource['options'][2] )->toBe( ['value' => 'green', 'label' => 'Green shirt'] )
            ->and( $resource['options'][3] )->toBe( ['value' => 'plain', 'label' => 'Plain shirt'] )
            ->and( $resource['field_config']['options'][2] )->not->toHaveKey( 'image' );
    } );

    it( 'sends the builders the options as stored, so an edit keeps them', function (): void {
        $resource = ( new FormFieldResource( $this->radio ) )->toArray( Request::create( '/' ) );

        expect( $resource['options'] )->toBe( $this->radio->options )
            ->and( $resource['field_config']['options'] )->toBe( $this->radio->field_config['options'] );
    } );
} );

describe( 'rendering', function (): void {
    beforeEach( function (): void {
        view()->share( 'errors', new ViewErrorBag() );
    } );

    it( 'renders each radio as an image card named by its label', function (): void {
        $html = FieldRenderer::render( $this->radio, 'red' );

        expect( $html )->toContain( '<img src="https://example.com/red.png" alt="A red T-shirt, folded"' )
            ->toContain( '<img src="/storage/forms/blue.png" alt=""' )
            ->not->toContain( 'javascript:' )
            ->toContain( 'aria-labelledby="field-shirt-0-label"' )
            ->toContain( '<span id="field-shirt-0-label" class="label-text">Red shirt</span>' )
            ->toContain( 'has-[:checked]:border-primary' )
            ->toContain( 'role="radiogroup"' );
    } );

    it( 'shows a placeholder for an option without an image', function (): void {
        $html = FieldRenderer::render( $this->radio, '' );

        expect( substr_count( $html, 'block aspect-square w-full bg-base-200' ) )->toBe( 2 );
    } );

    it( 'renders a checkbox group as image cards', function (): void {
        $group = FormField::factory()->for( $this->form )->create( [
            'type'         => 'checkbox_group',
            'name'         => 'toppings',
            'label'        => 'Toppings',
            'help_text'    => null,
            'field_config' => ['display' => 'images', 'options' => [['value' => 'olive', 'label' => 'Olives', 'image' => 'https://example.com/olive.png', 'image_alt' => 'Black olives']]],
        ] );

        $html = FieldRenderer::render( $group, ['olive'] );

        expect( $html )->toContain( 'type="checkbox"' )
            ->toContain( 'aria-labelledby="field-toppings-0-label"' )
            ->toContain( 'alt="Black olives"' );
    } );

    it( 'keeps the plain list when the setting is off', function (): void {
        $this->radio->update( ['field_config' => ['options' => $this->shirtOptions]] );

        expect( FieldRenderer::render( $this->radio->fresh(), '' ) )->not->toContain( '<img' )
            ->not->toContain( 'aria-labelledby="field-shirt-0-label"' );
    } );

    it( 'keeps the "Other" choice as a plain radio', function (): void {
        $this->radio->update( ['field_config' => array_merge( $this->radio->field_config, ['enable_other' => true] )] );

        expect( FieldRenderer::render( $this->radio->fresh(), '' ) )->toContain( 'id="field-shirt-other"' )
            ->not->toContain( 'field-shirt-other-label' );
    } );

    it( 'submits the chosen value from the Livewire renderer', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->afterSpamDelay()
            ->set( 'formData.shirt', 'blue' )
            ->call( 'submit' )
            ->assertHasNoErrors();

        expect( $this->form->submissions()->first()->values->first()->value )->toBe( 'blue' );
    } );
} );

describe( 'uploads', function (): void {
    beforeEach( function (): void {
        Storage::fake( 'public' );

        $this->user = ( new class extends User {
            protected $table = 'users';

            protected $fillable = ['name', 'email', 'password'];
        } )->create( ['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt( 'password' )] );
    } );

    it( 'stores an uploaded image on the public disk and returns its URL', function (): void {
        $response = $this->actingAs( $this->user )->postJson( route( 'api.forms.fields.choice-images', $this->form ), [
            'image' => UploadedFile::fake()->image( 'shirt.png', 40, 40 ),
        ] );

        $response->assertCreated();

        $files = Storage::disk( 'public' )->allFiles( 'forms/choice-images/' . $this->form->id );

        expect( $files )->toHaveCount( 1 )
            ->and( $response->json( 'data.url' ) )->toBe( Storage::disk( 'public' )->url( $files[0] ) );
    } );

    it( 'accepts only JPEG, PNG, GIF and WebP images', function ( string $name ): void {
        $file = str_ends_with( $name, '.svg' )
            ? UploadedFile::fake()->createWithContent( $name, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>' )
            : UploadedFile::fake()->create( $name, 10 );

        $this->actingAs( $this->user )
            ->postJson( route( 'api.forms.fields.choice-images', $this->form ), ['image' => $file] )
            ->assertUnprocessable()
            ->assertJsonValidationErrors( 'image' );
    } )->with( ['shirt.svg', 'notes.pdf', 'page.html'] );

    it( 'rejects images over the size limit', function (): void {
        config( ['artisanpack.forms.uploads.choice_images.max_size' => 1] );

        $this->actingAs( $this->user )
            ->postJson( route( 'api.forms.fields.choice-images', $this->form ), ['image' => UploadedFile::fake()->image( 'big.png' )->size( 5 )] )
            ->assertUnprocessable();
    } );

    it( 'requires a signed-in user', function (): void {
        $this->postJson( route( 'api.forms.fields.choice-images', $this->form ), ['image' => UploadedFile::fake()->image( 'shirt.png' )] )
            ->assertUnauthorized();
    } );

    it( 'stores the image with the media library when it takes it', function (): void {
        $this->app->instance( ChoiceImages::class, new class extends ChoiceImages {
            protected function mediaLibraryInstalled(): bool
            {
                return true;
            }

            protected function storeInMediaLibrary( UploadedFile $file ): ?string
            {
                return 'https://cdn.example.com/media/' . $file->getClientOriginalName();
            }
        } );

        $this->actingAs( $this->user )
            ->postJson( route( 'api.forms.fields.choice-images', $this->form ), ['image' => UploadedFile::fake()->image( 'shirt.png' )] )
            ->assertCreated()
            ->assertJsonPath( 'data.url', 'https://cdn.example.com/media/shirt.png' );

        expect( Storage::disk( 'public' )->allFiles() )->toBe( [] );
    } );

    it( 'does not fall back to the public disk when the media library refuses an image', function (): void {
        $this->app->instance( ChoiceImages::class, new class extends ChoiceImages {
            protected function mediaLibraryInstalled(): bool
            {
                return true;
            }

            protected function storeInMediaLibrary( UploadedFile $file ): ?string
            {
                return null;
            }
        } );

        $this->actingAs( $this->user )
            ->postJson( route( 'api.forms.fields.choice-images', $this->form ), ['image' => UploadedFile::fake()->image( 'shirt.png' )] )
            ->assertServerError();

        expect( Storage::disk( 'public' )->allFiles() )->toBe( [] );
    } );

    it( 'requires permission to update the form to store an image in the Livewire builder', function (): void {
        $builder = Livewire::actingAs( $this->user )->test( FormBuilder::class, ['form' => $this->form] );

        // Signed out after the builder loaded
        $this->app['auth']->forgetGuards();

        $builder->set( 'choiceImage', UploadedFile::fake()->image( 'shirt.jpg' ) )
            ->call( 'storeChoiceImage' )
            ->assertForbidden();

        expect( Storage::disk( 'public' )->allFiles() )->toBe( [] );
    } );

    it( 'stores an image uploaded in the Livewire builder', function (): void {
        $url = Livewire::actingAs( $this->user )->test( FormBuilder::class, ['form' => $this->form] )
            ->set( 'choiceImage', UploadedFile::fake()->image( 'shirt.jpg' ) )
            ->call( 'storeChoiceImage' )
            ->assertHasNoErrors()
            ->assertSet( 'choiceImage', null )
            ->effects['returns'][0] ?? null;

        expect( $url )->toStartWith( Storage::disk( 'public' )->url( 'forms/choice-images/' . $this->form->id . '/' ) );
    } );

    it( 'rejects a file that is not an image in the Livewire builder', function (): void {
        Livewire::actingAs( $this->user )->test( FormBuilder::class, ['form' => $this->form] )
            ->set( 'choiceImage', UploadedFile::fake()->create( 'notes.pdf', 10 ) )
            ->call( 'storeChoiceImage' )
            ->assertHasErrors( 'choiceImage' );
    } );
} );

describe( 'builder', function (): void {
    it( 'adds image URL, alt text and upload inputs to the options editor', function (): void {
        Livewire::actingAs( TestUser::factory()->create() )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $this->radio->uuid )
            ->assertSeeHtml( 'x-model="option.image"' )
            ->assertSeeHtml( 'x-model="option.image_alt"' )
            ->assertSeeHtml( 'accept="image/jpeg,image/png,image/gif,image/webp"' )
            ->assertDontSeeHtml( '@click="pickImage(index)"' );
    } );

    it( 'leaves the image inputs out while the choices are plain labels', function (): void {
        $this->radio->update( ['field_config' => ['options' => $this->shirtOptions]] );

        Livewire::actingAs( TestUser::factory()->create() )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $this->radio->uuid )
            ->assertDontSeeHtml( 'x-model="option.image"' );
    } );
} );
