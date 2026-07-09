<?php

/**
 * SmartFieldValidator Livewire component.
 *
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.2.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Livewire\Ai;

use ArtisanPackUI\Ai\Contracts\FeatureRegistry;
use ArtisanPackUI\Ai\Exceptions\FeatureDisabledException;
use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Ai\Exceptions\MissingCredentialsException;
use ArtisanPackUI\Forms\Ai\Agents\SmartFieldValidationAgent;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Trigger UI for the {@see SmartFieldValidationAgent}.
 *
 * Mounts alongside a single form field to run an on-demand semantic check.
 * Emits `forms-ai-field-verdict` (payload: `[ 'fieldName' => string,
 * 'plausible' => bool, 'confidence' => float, 'reason' => string ]`).
 *
 * The submitted value and sibling context are held as `#[Locked]` public
 * properties so client-side tampering cannot swap them out mid-run. NOTE:
 * Livewire still serializes public properties into the DOM
 * `wire:snapshot` for state restoration, so callers rendering this
 * component in a multi-tenant admin should pass ONLY data the current
 * user is authorized to see.
 *
 *
 * @since      1.2.0
 */
class SmartFieldValidator extends Component
{
    public string $fieldName = '';

    public string $fieldLabel = '';

    public string $fieldKind = '';

    #[Locked]
    public string $value = '';

    /**
     * @var array<string, mixed>
     */
    #[Locked]
    public array $context = [];

    public bool $isLoading = false;

    public ?string $error = null;

    public ?bool $plausible = null;

    public ?float $confidence = null;

    public ?string $reason = null;

    public ?string $suggestion = null;

    /**
     * Mount the component with initial context from the containing surface.
     *
     * @since 1.2.0
     *
     * @param  string  $fieldName  Field name (for the emitted event).
     * @param  string  $fieldLabel  Human-readable label.
     * @param  string  $fieldKind  Field kind (address, company_name, etc.).
     * @param  string  $value  Current field value.
     * @param  array<string, mixed>  $context  Sibling fields for cross-checks.
     */
    public function mount(
        string $fieldName = '',
        string $fieldLabel = '',
        string $fieldKind = '',
        string $value = '',
        array $context = [],
    ): void {
        $this->fieldName = $fieldName;
        $this->fieldLabel = $fieldLabel;
        $this->fieldKind = $fieldKind;
        $this->value = $value;
        $this->context = $context;
    }

    /**
     * React to the parent surface updating the field context.
     *
     * @since 1.2.0
     *
     * @param  array{ field_name?: string, field_label?: string, field_kind?: string, value?: string, context?: array<string, mixed> }  $payload  New context.
     */
    #[On('forms-ai-field-context-updated')]
    public function contextUpdated(array $payload): void
    {
        if (isset($payload['field_name'])) {
            $this->fieldName = (string) $payload['field_name'];
        }

        if (isset($payload['field_label'])) {
            $this->fieldLabel = (string) $payload['field_label'];
        }

        if (isset($payload['field_kind'])) {
            $this->fieldKind = (string) $payload['field_kind'];
        }

        if (isset($payload['value'])) {
            $this->value = (string) $payload['value'];
        }

        if (isset($payload['context']) && is_array($payload['context'])) {
            $this->context = $payload['context'];
        }
    }

    /**
     * Run the agent and populate the verdict fields or `$error`.
     *
     * @since 1.2.0
     */
    public function validateField(): void
    {
        $this->error = null;
        $this->plausible = null;
        $this->confidence = null;
        $this->reason = null;
        $this->suggestion = null;
        $this->isLoading = true;

        try {
            $output = SmartFieldValidationAgent::for([
                'field_label' => $this->fieldLabel,
                'field_kind' => $this->fieldKind,
                'value' => $this->value,
                'context' => $this->context === [] ? null : $this->context,
            ])->run();

            $this->plausible = (bool) ($output['plausible'] ?? true);
            $this->confidence = (float) ($output['confidence'] ?? 0);
            $this->reason = (string) ($output['reason'] ?? '');
            $this->suggestion = isset($output['suggestion']) ? (string) $output['suggestion'] : null;

            $this->dispatch(
                'forms-ai-field-verdict',
                fieldName: $this->fieldName,
                plausible: $this->plausible,
                confidence: $this->confidence,
                reason: $this->reason,
            );
        } catch (FeatureDisabledException $exception) {
            $this->error = __('This AI feature is disabled.');
        } catch (MissingCredentialsException $exception) {
            $this->error = __('AI credentials are not configured.');
        } catch (FeatureError $exception) {
            $this->error = __('The AI agent could not validate the field.');
        } catch (Throwable $exception) {
            report($exception);
            $this->error = __('The AI agent could not complete this request.');
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
        $registry = app(FeatureRegistry::class);
        $key = 'forms.smart_validation';

        if ($registry->get($key) === null) {
            return false;
        }

        return $registry->isToggleOn($key);
    }

    /**
     * Render the component view.
     *
     * @since 1.2.0
     */
    public function render(): View
    {
        return view('forms::livewire.ai.smart-field-validator');
    }
}
