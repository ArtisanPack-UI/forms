<?php

/**
 * ResponseClassifier Livewire component.
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
use ArtisanPackUI\Forms\Ai\Agents\ResponseClassificationAgent;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Trigger UI for the {@see ResponseClassificationAgent}.
 *
 * Mounts inside the submissions admin surface. Emits
 * `forms-ai-category-selected` (payload: `[ 'submissionId' => int,
 * 'category' => string, 'confidence' => float, 'suggestedNew' => string|null ]`)
 * when the user accepts a suggestion.
 *
 * The submitted fields are held as a `#[Locked]` public property so
 * client-side tampering cannot swap them out mid-run. NOTE: Livewire still
 * serializes public properties into the DOM `wire:snapshot` for state
 * restoration, so callers rendering this component in a multi-tenant admin
 * should pass ONLY submissions the current user is authorized to see.
 *
 *
 * @since      1.2.0
 */
class ResponseClassifier extends Component
{
    public int $submissionId = 0;

    /**
     * @var array<string, mixed>
     */
    #[Locked]
    public array $fields = [];

    /**
     * @var array<int, string>
     */
    #[Locked]
    public array $availableCategories = [];

    public bool $isLoading = false;

    public ?string $error = null;

    public ?string $category = null;

    public ?float $confidence = null;

    public ?string $suggestedNew = null;

    /**
     * Mount the component with initial context from the containing surface.
     *
     * @since 1.2.0
     *
     * @param  int  $submissionId  Submission id (for the emitted event).
     * @param  array<string, mixed>  $fields  Submitted field values.
     * @param  array<int, string>  $availableCategories  Category labels to choose from.
     */
    public function mount( int $submissionId = 0, array $fields = [], array $availableCategories = [] ): void
    {
        $this->submissionId        = $submissionId;
        $this->fields              = $fields;
        $this->availableCategories = $availableCategories;
    }

    /**
     * React to the parent surface updating the submission context.
     *
     * @since 1.2.0
     *
     * @param  array{ submission_id?: int, fields?: array<string, mixed>, available_categories?: array<int, string> }  $payload  New context.
     */
    #[On( 'forms-ai-classifier-context-updated' )]
    public function contextUpdated( array $payload ): void
    {
        if ( isset( $payload['submission_id'] ) ) {
            $this->submissionId = (int) $payload['submission_id'];
        }

        if ( isset( $payload['fields'] ) && is_array( $payload['fields'] ) ) {
            $this->fields = $payload['fields'];
        }

        if ( isset( $payload['available_categories'] ) && is_array( $payload['available_categories'] ) ) {
            $this->availableCategories = $payload['available_categories'];
        }
    }

    /**
     * Run the agent and populate the classification fields or `$error`.
     *
     * @since 1.2.0
     */
    public function classify(): void
    {
        $this->error        = null;
        $this->category     = null;
        $this->confidence   = null;
        $this->suggestedNew = null;
        $this->isLoading    = true;

        try {
            $output = ResponseClassificationAgent::for( [
                'fields'               => $this->fields,
                'available_categories' => $this->availableCategories,
            ] )->run();

            $this->category     = (string) ( $output['category'] ?? '' );
            $this->confidence   = (float) ( $output['confidence'] ?? 0 );
            $this->suggestedNew = isset( $output['suggested_new'] ) ? (string) $output['suggested_new'] : null;
        } catch ( FeatureDisabledException $exception ) {
            $this->error = __( 'This AI feature is disabled.' );
        } catch ( MissingCredentialsException $exception ) {
            $this->error = __( 'AI credentials are not configured.' );
        } catch ( FeatureError $exception ) {
            $this->error = __( 'The AI agent could not classify the submission.' );
        } catch ( Throwable $exception ) {
            report( $exception );
            $this->error = __( 'The AI agent could not complete this request.' );
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * Accept the suggestion and emit it back to the parent surface.
     *
     * @since 1.2.0
     */
    public function accept(): void
    {
        if ( null === $this->category ) {
            return;
        }

        $this->dispatch(
            'forms-ai-category-selected',
            submissionId: $this->submissionId,
            category: $this->category,
            confidence: $this->confidence ?? 0.0,
            suggestedNew: $this->suggestedNew,
        );
    }

    /**
     * Determine whether this feature is enabled in the registry.
     *
     * @since 1.2.0
     */
    public function getIsEnabledProperty(): bool
    {
        $registry = app( FeatureRegistry::class );
        $key      = 'forms.response_classification';

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
        return view( 'forms::livewire.ai.response-classifier' );
    }
}
