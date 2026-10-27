<?php

declare( strict_types=1 );

namespace Tests\Support;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Runs the shared React/Vue helpers (`resources/js/shared/*.ts`) in Node, so
 * tests can check what they do rather than how their source reads.
 *
 * The helpers are copied to a temporary directory with their relative
 * imports given a `.ts` extension, and run with Node's TypeScript
 * transform (22.7+; some helpers use parameter properties, which plain type
 * stripping rejects). `run()` returns null when Node cannot run TypeScript,
 * so callers can skip.
 *
 * @since 1.6.0
 */
class SharedTypeScript
{
    /**
     * Runs a snippet with the shared helpers available.
     *
     * The snippet is the body of an async function of `( load, input )`:
     * `await load( 'validation' )` imports `shared/validation.ts`, and the
     * function's return value is JSON-encoded back to PHP.
     *
     * @since 1.6.0
     *
     * @param  string  $body  The function body.
     * @param  mixed  $input  A JSON-encodable value passed as `input`.
     *
     * @throws RuntimeException When Node runs TypeScript but not the snippet.
     *
     * @return mixed The snippet's return value, or null when Node cannot run TypeScript.
     */
    public static function run( string $body, mixed $input = null ): mixed
    {
        if ( ! self::nodeTransformsTypeScript() ) {
            return null;
        }

        $root = sys_get_temp_dir() . '/forms-shared-' . bin2hex( random_bytes( 6 ) );

        File::ensureDirectoryExists( $root . '/shared' );

        $sharedDir = __DIR__ . '/../../resources/js/shared';

        foreach ( File::files( $sharedDir ) as $file ) {
            $contents = $file->getContents();

            if ( 'ts' === $file->getExtension() ) {
                $contents = preg_replace_callback(
                    "/(from\\s+'|import\\(\\s*')(\\.\\.?\\/[^']+)'/",
                    static function ( array $match ) use ( $sharedDir ): string {
                        $target = $sharedDir . '/' . $match[2];

                        return file_exists( $target . '.ts' ) ? $match[1] . $match[2] . ".ts'" : $match[0];
                    },
                    $contents,
                );
            }

            file_put_contents( $root . '/shared/' . $file->getFilename(), $contents );
        }

        $script = 'const load = ( name ) => import( "file://" + process.argv[1] + "/shared/" + name + ".ts" );'
            . 'const input = JSON.parse( process.argv[2] );'
            . '( async ( load, input ) => { ' . $body . ' } )( load, input )'
            . '.then( ( result ) => process.stdout.write( JSON.stringify( result === undefined ? null : result ) ) )'
            . '.catch( ( error ) => { process.stderr.write( String( error && error.stack ? error.stack : error ) ); process.exitCode = 1; } );';

        try {
            $process = new Process( ['node', '--experimental-transform-types', '--no-warnings', '--input-type=module', '-e', $script, $root, json_encode( $input )] );
            $process->run();
        } finally {
            File::deleteDirectory( $root );
        }

        if ( ! $process->isSuccessful() ) {
            throw new RuntimeException( 'The shared helpers did not run: ' . $process->getErrorOutput() );
        }

        return json_decode( $process->getOutput(), true, flags: JSON_THROW_ON_ERROR );
    }

    /**
     * Checks whether Node accepts `--experimental-transform-types` (22.7+).
     *
     * @since 1.6.0
     *
     * @return bool True when it does.
     */
    public static function nodeTransformsTypeScript(): bool
    {
        static $available = null;

        if ( null === $available ) {
            try {
                $process = new Process( ['node', '--experimental-transform-types', '--no-warnings', '-e', 'process.stdout.write( "true" );'] );
                $process->run();

                $available = $process->isSuccessful() && 'true' === $process->getOutput();
            } catch ( Throwable ) {
                $available = false;
            }
        }

        return $available;
    }
}
