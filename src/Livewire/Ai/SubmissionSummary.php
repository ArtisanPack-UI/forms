<?php

/**
 * SubmissionSummary Livewire component.
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
use ArtisanPackUI\Forms\Ai\Agents\SubmissionSummaryAgent;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Trigger UI for the {@see SubmissionSummaryAgent}.
 *
 * Mounts inside the forms admin surface to generate an on-demand summary of
 * submissions for a chosen window. Emits `forms-ai-summary-ready` when a run
 * completes so a parent can offer a "Send digest email" action against the
 * generated summary.
 *
 * The submissions payload is held as a `#[Locked]` public property so
 * client-side tampering cannot swap it out mid-run. NOTE: Livewire still
 * serializes public properties into the DOM `wire:snapshot` for state
 * restoration, so callers rendering this component in a multi-tenant admin
 * should pass ONLY submissions the current user is authorized to see.
 *
 *
 * @since      1.2.0
 */
class SubmissionSummary extends Component
{
    public string $formName = '';

    public string $window = 'weekly';

    /**
     * @var array<int, array<string, mixed>>
     */
    #[Locked]
    public array $submissions = [];

    public bool $isLoading = false;

    public ?string $error = null;

    public ?string $headline = null;

    public int $totalCount = 0;

    public int $sampleCount = 0;

    /**
     * @var array<int, array{ title: string, count: int, examples: array<int, string> }>
     */
    public array $themes = [];

    /**
     * @var array<int, string>
     */
    public array $notable = [];

    /**
     * @var array<int, string>
     */
    public array $suggestions = [];

    /**
     * Mount the component with initial context from the containing surface.
     *
     * @since 1.2.0
     *
     * @param  string  $formName  Form name.
     * @param  string  $window  Reporting window (daily/weekly/range).
     * @param  array<int, array<string, mixed>>  $submissions  Normalized submissions.
     */
    public function mount( string $formName = '', string $window = 'weekly', array $submissions = [] ): void
    {
        $this->formName    = $formName;
        $this->window      = '' === $window ? 'weekly' : $window;
        $this->submissions = $submissions;
    }

    /**
     * React to the parent surface updating the summary context.
     *
     * @since 1.2.0
     *
     * @param  array{ form_name?: string, window?: string, submissions?: array<int, array<string, mixed>> }  $payload  New context.
     */
    #[On( 'forms-ai-summary-context-updated' )]
    public function contextUpdated( array $payload ): void
    {
        if ( isset( $payload['form_name'] ) ) {
            $this->formName = (string) $payload['form_name'];
        }

        if ( isset( $payload['window'] ) && '' !== trim( (string) $payload['window'] ) ) {
            $this->window = (string) $payload['window'];
        }

        if ( isset( $payload['submissions'] ) && is_array( $payload['submissions'] ) ) {
            $this->submissions = $payload['submissions'];
        }
    }

    /**
     * Run the agent and populate the summary fields or `$error`.
     *
     * @since 1.2.0
     */
    public function summarize(): void
    {
        $this->error       = null;
        $this->headline    = null;
        $this->totalCount  = 0;
        $this->sampleCount = 0;
        $this->themes      = [];
        $this->notable     = [];
        $this->suggestions = [];
        $this->isLoading   = true;

        try {
            $output = SubmissionSummaryAgent::for( [
                'form_name'   => $this->formName,
                'window'      => $this->window,
                'submissions' => $this->submissions,
            ] )->run();

            $this->headline    = (string) ( $output['headline'] ?? '' );
            $this->totalCount  = (int) ( $output['total_count'] ?? 0 );
            $this->sampleCount = (int) ( $output['sample_count'] ?? 0 );
            $this->themes      = is_array( $output['themes'] ?? null ) ? $output['themes'] : [];
            $this->notable     = is_array( $output['notable'] ?? null ) ? $output['notable'] : [];
            $this->suggestions = is_array( $output['suggestions'] ?? null ) ? $output['suggestions'] : [];

            $this->dispatch(
                'forms-ai-summary-ready',
                formName: $this->formName,
                window: $this->window,
                headline: $this->headline,
                totalCount: $this->totalCount,
                sampleCount: $this->sampleCount,
            );
        } catch ( FeatureDisabledException $exception ) {
            $this->error = __( 'This AI feature is disabled.' );
        } catch ( MissingCredentialsException $exception ) {
            $this->error = __( 'AI credentials are not configured.' );
        } catch ( FeatureError $exception ) {
            $this->error = __( 'The AI agent could not summarize the submissions.' );
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
        $key      = 'forms.submission_summary';

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
        return view( 'forms::livewire.ai.submission-summary' );
    }
}
