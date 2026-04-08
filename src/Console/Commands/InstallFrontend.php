<?php

/**
 * Install frontend assets artisan command.
 *
 * Publishes React or Vue form components along with shared TypeScript
 * utilities and type definitions to the consuming application.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Console\Commands;

use Illuminate\Console\Command;

/**
 * Install frontend assets artisan command class.
 *
 * Publishes React or Vue form components along with shared TypeScript
 * utilities and type definitions to the consuming application.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class InstallFrontend extends Command
{

	/**
	 * The valid frontend stacks.
	 *
	 * @since 1.1.0
	 *
	 * @var array<int, string>
	 */
	protected const VALID_STACKS = ['react', 'vue'];

	/**
	 * The name and signature of the console command.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	protected $signature = 'forms:install-frontend
							{--stack= : The frontend stack to install (react or vue)}
							{--force : Overwrite existing files}';

	/**
	 * The console command description.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	protected $description = 'Install the React or Vue form components and shared TypeScript utilities';

	/**
	 * Executes the console command.
	 *
	 * @since 1.1.0
	 *
	 * @return int The command exit status code.
	 */
	public function handle(): int
	{
		$stack = $this->resolveStack();

		if ( null === $stack ) {
			return self::FAILURE;
		}

		$tag   = "forms-{$stack}";
		$force = $this->option( 'force' );

		$this->info( "Publishing {$stack} form components..." );

		$params = ['--tag' => $tag];

		if ( $force ) {
			$params['--force'] = true;
		}

		$this->call( 'vendor:publish', $params );

		$this->components->info( "ArtisanPack Forms {$stack} components installed successfully." );

		$this->newLine();
		$this->components->bulletList( [
			"Components published to: <comment>resources/js/vendor/artisanpack-forms/{$stack}/</comment>",
			'Shared utilities published to: <comment>resources/js/vendor/artisanpack-forms/shared/</comment>',
			'Type definitions published to: <comment>resources/js/vendor/artisanpack-forms/types/</comment>',
		] );

		$this->newLine();
		$this->line( '  <fg=gray>Next steps:</>' );
		$this->line( '  <fg=gray>1. Import components from</> <comment>resources/js/vendor/artisanpack-forms/' . $stack . '/</comment>' );
		$this->line( '  <fg=gray>2. Configure your build tool to compile TypeScript</>' );
		$this->line( '  <fg=gray>3. See the documentation for usage examples</>' );

		return self::SUCCESS;
	}

	/**
	 * Resolves the frontend stack from the option or by prompting the user.
	 *
	 * @since 1.1.0
	 *
	 * @return string|null The resolved stack name, or null if invalid.
	 */
	protected function resolveStack(): ?string
	{
		$stack = $this->option( 'stack' );

		if ( null === $stack ) {
			$stack = $this->choice(
				__( 'Which frontend stack would you like to install?' ),
				self::VALID_STACKS,
				0,
			);
		}

		$stack = strtolower( (string) $stack );

		if ( ! in_array( $stack, self::VALID_STACKS, true ) ) {
			$this->error( "Invalid stack: {$stack}. Valid options are: " . implode( ', ', self::VALID_STACKS ) );

			return null;
		}

		return $stack;
	}
}
