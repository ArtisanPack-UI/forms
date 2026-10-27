<?php

declare( strict_types=1 );

/**
 * Guards issues #134, #135 and #136: the shared JS helpers give the same
 * results as the server for calculation formulas, hex colors and image
 * choice URLs.
 *
 * Each helper is copied to a temporary directory with its relative imports
 * replaced by small stubs and run in Node with its types stripped, so these
 * tests are skipped when no Node with `--experimental-strip-types` (22.6+)
 * is on the PATH.
 */

use ArtisanPackUI\Forms\FieldTypes\ColorFieldType;
use ArtisanPackUI\Forms\Support\Calculation;
use ArtisanPackUI\Forms\Support\ChoiceImages;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Runs a function exported by a shared helper on each input.
 *
 * @param  string  $helper  The helper's file name in `resources/js/shared`.
 * @param  string  $call  A JS expression of `input` (and the helper's exports) giving the result.
 * @param  array<int, mixed>  $inputs  The inputs.
 *
 * @throws RuntimeException When Node runs TypeScript but not the helper.
 *
 * @return array<int, mixed>|null The results, or null when Node cannot run TypeScript.
 */
$runHelper = static function ( string $helper, string $call, array $inputs ): ?array {
    if ( ! nodeRunsTypeScript() ) {
        return null;
    }

    $source = file_get_contents( __DIR__ . '/../../resources/js/shared/' . $helper );
    $stubs  = <<<'JS'
const t = ( key, replace = {} ) => key.replace( /:(\w+)/g, ( match, name ) => ( name in replace ? String( replace[name] ) : match ) );
const getHiddenFields = () => ( {} );
const getNumberFormat = () => undefined;
const normalizeFormattedNumber = ( value ) => value;

JS;
    $source = $stubs . preg_replace( "/^import [^;]+;\n/m", '', $source );
    $dir    = sys_get_temp_dir() . '/forms-parity-' . bin2hex( random_bytes( 6 ) );

    File::ensureDirectoryExists( $dir );
    file_put_contents( $dir . '/helper.ts', $source );

    $script = 'import( process.argv[1] ).then( ( helper ) => { const results = JSON.parse( process.argv[2] ).map( ( input ) => ( ' . $call . ' ) ); process.stdout.write( JSON.stringify( results ) ); } );';

    try {
        $process = new Process( ['node', '--experimental-strip-types', '--no-warnings', '--input-type=module', '-e', $script, 'file://' . $dir . '/helper.ts', json_encode( $inputs )] );
        $process->run();
    } finally {
        File::deleteDirectory( $dir );
    }

    if ( ! $process->isSuccessful() ) {
        throw new RuntimeException( $helper . ' did not run: ' . $process->getErrorOutput() );
    }

    return json_decode( $process->getOutput(), true, flags: JSON_THROW_ON_ERROR );
};

it( 'evaluates and formats formulas like Calculation', function () use ( $runHelper ): void {
    $values = ['quantity' => '3', 'price' => '19.99', 'tags' => ['1', '2.5'], 'blank' => '', 'text' => 'abc', 'on' => true, 'neg' => '-4', 'exp' => '1e2', 'nbsp' => "\u{00A0}5"];
    $cases  = [
        ['{quantity} * {price}', 2],
        ['2 + 3 * 4', 0],
        ['(2 + 3) * 4 / 5', 2],
        ['-{quantity} + 10', 2],
        ['- - 3', 0],
        ['10 / 4', 3],
        ['1 / 0', 2],
        ['{tags} * 2', 2],
        ['{blank} + {text} + {on} + {missing}', 2],
        ['{neg} * {exp}', 2],
        ['2 *', 2],
        ['(1 + 2', 2],
        ['1 + 2)', 2],
        ['alert(1)', 2],
        ['.5 + .5', 1],
        ['{price} / 3', 4],
        ['0 - 0.001', 2],
        ['2.5', 0],
        [str_repeat( '(', 40 ) . '1' . str_repeat( ')', 40 ), 2],
        ["{quantity}\u{00A0}*\u{00A0}{price}", 2],
        ["{quantity} * {price}\u{2009}", 2],
        ["{quantity}\t*\n{price}\r", 2],
        ['{nbsp} + 1', 2],
    ];

    $results = $runHelper(
        'calculation.ts',
        'helper.formatCalculation( helper.evaluateFormula( input[0], ( name ) => ( ' . json_encode( $values ) . ' )[name] ), input[1] )',
        $cases,
    );

    if ( null === $results ) {
        $this->markTestSkipped( 'Node 22.6+ is needed to run calculation.ts.' );
    }

    $expected = array_map(
        fn ( array $case ): string => Calculation::format( Calculation::evaluate( $case[0], fn ( string $name ): mixed => $values[ $name ] ?? null ), $case[1] ),
        $cases,
    );

    expect( $results )->toBe( $expected )
        ->and( $expected[0] )->toBe( '59.97' )
        ->and( $expected[6] )->toBe( '' )
        ->and( $expected[16] )->toBe( '0.00' )
        ->and( $expected[19] )->toBe( '' )
        ->and( $expected[21] )->toBe( '59.97' )
        ->and( $expected[22] )->toBe( '1.00' );
} );

it( 'checks formulas like Calculation::check()', function () use ( $runHelper ): void {
    $cases = ['', '{a} * {b}', '{a} / 0', '{a} +', '{a} ^ 2', '{c} + 1', str_repeat( '1+', 600 ) . '1', "{a}\u{00A0}*\u{00A0}{b}", "\u{00A0}"];

    $results = $runHelper( 'calculation.ts', "helper.checkFormula( input, ['a', 'b'] ) ?? null", $cases );

    if ( null === $results ) {
        $this->markTestSkipped( 'Node 22.6+ is needed to run calculation.ts.' );
    }

    expect( $results )->toBe( array_map( fn ( string $formula ): ?string => Calculation::check( $formula, ['a', 'b'] ), $cases ) );
} );

it( 'normalizes colors like ColorFieldType', function () use ( $runHelper ): void {
    $cases = ['#FF8800', 'ff8800', '#F80', 'f80c', ' #FF8800CC ', '  ', ' orange ', '#ff880', '#gg8800'];

    $results = $runHelper( 'color.ts', '[ helper.normalizeColor( input ), helper.getColorPickerValue( input ) ]', $cases );

    if ( null === $results ) {
        $this->markTestSkipped( 'Node 22.6+ is needed to run color.ts.' );
    }

    expect( $results )->toBe( array_map( fn ( string $color ): array => [ColorFieldType::normalizeValue( $color ), ColorFieldType::pickerValue( $color )], $cases ) );
} );

it( 'accepts the same image choice URLs as ChoiceImages', function () use ( $runHelper ): void {
    $cases = [
        'https://example.com/a.png',
        'http://example.com/a.png',
        '/storage/forms/a.png',
        '//evil.example/a.png',
        'javascript:alert(1)',
        'data:image/png;base64,AAAA',
        'https://example.com/a b.png',
        'https://example.com/"onerror="x',
        'relative/a.png',
        '',
        'https:///a.png',
    ];

    $results = $runHelper( 'choices.ts', 'helper.getChoiceImageUrl( { label: "", value: "", image: input } ) ?? null', $cases );

    if ( null === $results ) {
        $this->markTestSkipped( 'Node 22.6+ is needed to run choices.ts.' );
    }

    expect( $results )->toBe( array_map( fn ( string $url ): ?string => ChoiceImages::url( ['image' => $url] ), $cases ) )
        ->and( array_filter( $results ) )->toBe( [0 => 'https://example.com/a.png', 1 => 'http://example.com/a.png', 2 => '/storage/forms/a.png'] );
} );
