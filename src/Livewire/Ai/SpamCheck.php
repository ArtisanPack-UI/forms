<?php

/**
 * SpamCheck Livewire component.
 *
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Livewire\Ai;

use ArtisanPackUI\Ai\Contracts\FeatureRegistry;
use ArtisanPackUI\Ai\Exceptions\FeatureDisabledException;
use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Ai\Exceptions\MissingCredentialsException;
use ArtisanPackUI\Forms\Ai\Agents\SpamDetectionAgent;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Trigger UI for the {@see SpamDetectionAgent}.
 *
 * Mounts inside the submissions admin surface to score a single submission
 * on demand. Emits `forms-ai-spam-verdict` (payload:
 * `[ 'submissionId' => int, 'verdict' => string, 'spamScore' => int ]`)
 * when a run completes so the parent list can update its filters.
 *
 * The submitted fields and metadata are held as `#[Locked]` public
 * properties so client-side tampering cannot swap them out mid-run.
 * NOTE: Livewire still serializes public properties into the DOM
 * `wire:snapshot` for state restoration, so callers rendering this
 * component in a multi-tenant admin should pass ONLY data the current
 * user is authorized to see (typically their own tenant's submissions).
 *
 *
 * @since      1.2.0
 */
class SpamCheck extends Component
{
    public int $submissionId = 0;

    /**
     * @var array<string, mixed>
     */
    #[Locked]
    public array $fields = [];

    /**
     * @var array<string, mixed>
     */
    #[Locked]
    public array $meta = [];

    public bool $isLoading = false;

    public ?string $error = null;

    public ?int $spamScore = null;

    public ?string $verdict = null;

    /**
     * @var array<int, string>
     */
    public array $reasons = [];

    /**
     * Mount the component with initial context from the containing surface.
     *
     * @since 1.2.0
     *
     * @param  int  $submissionId  Submission id (for the emitted event).
     * @param  array<string, mixed>  $fields  Submitted field values.
     * @param  array<string, mixed>  $meta  Submission metadata (ip_country, etc.).
     */
    public function mount( int $submissionId = 0, array $fields = [], array $meta = [] ): void
    {
        $this->submissionId = $submissionId;
        $this->fields       = $fields;
        $this->meta         = $meta;
    }

    /**
     * React to the parent surface updating the submission context.
     *
     * @since 1.2.0
     *
     * @param  array{ submission_id?: int, fields?: array<string, mixed>, meta?: array<string, mixed> }  $payload  New context.
     */
    #[On( 'forms-ai-submission-updated' )]
    public function submissionUpdated( array $payload ): void
    {
        if ( isset( $payload['submission_id'] ) ) {
            $this->submissionId = (int) $payload['submission_id'];
        }

        if ( isset( $payload['fields'] ) && is_array( $payload['fields'] ) ) {
            $this->fields = $payload['fields'];
        }

        if ( isset( $payload['meta'] ) && is_array( $payload['meta'] ) ) {
            $this->meta = $payload['meta'];
        }
    }

    /**
     * Run the agent and populate the verdict fields or `$error`.
     *
     * @since 1.2.0
     */
    public function check(): void
    {
        $this->error     = null;
        $this->spamScore = null;
        $this->verdict   = null;
        $this->reasons   = [];
        $this->isLoading = true;

        try {
            $output = SpamDetectionAgent::for( [
                'fields' => $this->fields,
                'meta'   => $this->meta,
            ] )->run();

            $this->spamScore = (int) ( $output['spam_score'] ?? 0 );
            $this->verdict   = (string) ( $output['verdict'] ?? 'ham' );
            $this->reasons   = is_array( $output['reasons'] ?? null ) ? $output['reasons'] : [];

            $this->dispatch(
                'forms-ai-spam-verdict',
                submissionId: $this->submissionId,
                verdict: $this->verdict,
                spamScore: $this->spamScore,
            );
        } catch ( FeatureDisabledException $exception ) {
            $this->error = __( 'This AI feature is disabled.' );
        } catch ( MissingCredentialsException $exception ) {
            $this->error = __( 'AI credentials are not configured.' );
        } catch ( FeatureError $exception ) {
            $this->error = __( 'The AI agent could not validate the submission.' );
        } catch ( Throwable $exception ) {
            report( $exception );
            $this->error = __( 'The AI agent could not complete this request.' );
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * Determine whether this feature is enabled in the registry.
     *
     * @since 1.2.0
     */
    public function getIsEnabledProperty(): bool
    {
        $registry = app( FeatureRegistry::class );
        $key      = 'forms.spam_detection';

        if ( null === $registry->get( $key ) ) {
            return false;
        }

        return $registry->isToggleOn( $key );
    }

    /**
     * Render the component view.
     *
     * @since 1.2.0
     */
    public function render(): View
    {
        return view( 'forms::livewire.ai.spam-check');
    }
}
