<?php

/**
 * Smart field validation agent.
 *
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Ai\Agents;

use ArtisanPackUI\Ai\Agents\ArtisanPackAgent;
use ArtisanPackUI\Ai\Contracts\AgentPrompter;
use ArtisanPackUI\Ai\Credentials\Credentials;
use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Forms\Ai\Concerns\NormalizesLLMInput;

/**
 * Opt-in per-field semantic validation for a single form field.
 *
 * This is meant to complement, not replace, format validation (regex,
 * length, required, etc.). The caller runs its usual validation first;
 * this agent adds a semantic pass answering "does this look real for a
 * field of this kind?"
 *
 * ## Input
 *
 * ```
 * [
 *   'field_label'   => string,          // required — human-readable label ("Company")
 *   'field_kind'    => string,          // required — e.g. "address", "company_name", "full_name"
 *   'value'         => string,          // required — the submitted value
 *   'context'       => array<string, mixed>|null,  // optional — sibling fields for cross-checks
 * ]
 * ```
 *
 * ## Output schema
 *
 * ```
 * {
 *   plausible: bool,
 *   confidence: float(0..1),
 *   reason:    string,
 *   suggestion?: string
 * }
 * ```
 *
 *
 * @since      1.2.0
 */
class SmartFieldValidationAgent extends ArtisanPackAgent
{
    use NormalizesLLMInput;

    /**
     * {@inheritDoc}
     */
    public string $featureKey = 'forms.smart_validation';

    /**
     * {@inheritDoc}
     */
    public string $package = 'artisanpack-ui/forms';

    /**
     * {@inheritDoc}
     */
    public string $defaultModel = 'claude-haiku-4-5';

    /**
     * {@inheritDoc}
     */
    public function instructions(): string
    {
        return <<<'PROMPT'
You judge whether a single form field value is plausible for its declared field kind. Format-level validation (required, length, regex) has already run. Focus on semantic plausibility.

Examples of what to look for:
- address: does the address look like a real place (real street pattern, real city/state combo where obvious)? Reject "123 Fake St", obvious jokes, mashed-together characters.
- company_name: does it look like a real business name, not a random string or profanity? Common single-word brands ("Anthropic", "Stripe") are fine.
- full_name: does it look like a plausible human name — not "asdfasdf", not a URL, not "TEST"? Names in any language, script, or with hyphens/apostrophes are all fine.
- email: even when format is valid, flag values like "test@test.test" or role addresses that indicate the submitter didn't want to share a real one.
- phone: flag obviously fake patterns (555-555-5555, 000-000-0000, sequential digits).

Requirements:
- `plausible` is a boolean.
- `confidence` is a float 0.0-1.0. Be honest — a coin-flip case is 0.5.
- `reason` is one short sentence explaining your judgment (<= 200 chars). NEVER emit "looks fine" or "not sure" — cite the specific signal.
- `suggestion` is optional. Include ONLY when `plausible` is false AND you can offer a concrete fix a user would recognize (e.g. correct a typo, ask them to include a city). Never suggest a specific value the user didn't write (don't invent addresses, names, or companies).
- If you don't have enough context to judge (e.g. an ambiguous string that could be legitimate), return `plausible: true` with a moderate confidence rather than rejecting.

Return a JSON object with keys: plausible (boolean), confidence (number), reason (string), optional suggestion (string).
PROMPT;
    }

    /**
     * {@inheritDoc}
     */
    public function outputSchema(): array
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['plausible', 'confidence', 'reason'],
            'properties'           => [
                'plausible'  => ['type' => 'boolean'],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                'reason'     => ['type' => 'string', 'maxLength' => 200],
                'suggestion' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function execute( Credentials $credentials, string $model, string $instructions ): array
    {
        $normalized = $this->normalizeInput( $this->input() );

        $prompter = app( AgentPrompter::class );

        $result = $prompter->prompt(
            credentials: $credentials,
            model: $model,
            instructions: $instructions,
            message: $this->buildMessage( $normalized ),
            outputSchema: $this->outputSchema(),
        );

        return [
            'output'        => $this->validateOutput( $result['output'] ?? [] ),
            'input_tokens'  => (int) ( $result['input_tokens'] ?? 0 ),
            'output_tokens' => (int) ( $result['output_tokens'] ?? 0 ),
        ];
    }

    /**
     * Validate and shape-check the raw agent input.
     *
     * @since 1.2.0
     *
     * @param  mixed  $input  Raw agent input.
     *
     * @return array{ field_label: string, field_kind: string, value: string, context: array<string, mixed>|null }
     */
    protected function normalizeInput( mixed $input ): array
    {
        if ( ! is_array( $input ) ) {
            throw FeatureError::forFeature(
                $this->featureKey,
                'input must be an array with `field_label`, `field_kind`, and `value` keys.',
            );
        }

        $label = isset( $input['field_label'] ) && is_string( $input['field_label'] )
            ? trim( $input['field_label'] )
            : '';
        $kind = isset( $input['field_kind'] ) && is_string( $input['field_kind'] )
            ? trim( $input['field_kind'] )
            : '';
        $value = isset( $input['value'] ) && is_string( $input['value'] )
            ? trim( $input['value'] )
            : '';

        if ( '' === $label ) {
            throw FeatureError::forFeature( $this->featureKey, '`field_label` must be a non-empty string.' );
        }

        if ( '' === $kind ) {
            throw FeatureError::forFeature( $this->featureKey, '`field_kind` must be a non-empty string.' );
        }

        if ( '' === $value ) {
            throw FeatureError::forFeature( $this->featureKey, '`value` must be a non-empty string.' );
        }

        $context = null;

        if ( isset( $input['context'] ) && is_array( $input['context'] ) && [] !== $input['context'] ) {
            $context = $input['context'];
        }

        return [
            'field_label' => $label,
            'field_kind'  => $kind,
            'value'       => $value,
            'context'     => $context,
        ];
    }

    /**
     * Assemble the structured message body for the prompter.
     *
     * @since 1.2.0
     *
     * @param  array{ field_label: string, field_kind: string, value: string, context: array<string, mixed>|null }  $normalized  Normalized input.
     *
     * @return array<int, array<string, string>>
     */
    protected function buildMessage( array $normalized ): array
    {
        // field_label and field_kind are configurable per-field in the form
        // builder and can be user-controlled; escape them before they land
        // in the free-form prompt to defuse "Ignore prior instructions."
        // style injection. The submitted value is also a user string but is
        // presented as data-to-inspect rather than context, so we only strip
        // control characters and cap length there.
        $safeLabel = $this->escapeForPrompt( $normalized['field_label'], 128 );
        $safeKind  = $this->escapeForPrompt( $normalized['field_kind'], 64 );
        $safeValue = $this->escapeForPrompt( $normalized['value'], 512 );

        $parts = [
            ['type' => 'text', 'text' => sprintf( 'Field label: %s', $safeLabel )],
            ['type' => 'text', 'text' => sprintf( 'Field kind: %s', $safeKind )],
            ['type' => 'text', 'text' => sprintf( 'Submitted value: %s', $safeValue )],
        ];

        if ( null !== $normalized['context'] ) {
            $parts[] = [
                'type' => 'text',
                'text' => "Sibling fields (JSON) for cross-checks:\n" . $this->safeJsonEncode( $normalized['context'] ),
            ];
        }

        return $parts;
    }

    /**
     * Deterministic cache fingerprint over the normalized input.
     *
     * The base ArtisanPackAgent default throws for any non-scalar array
     * entry, which crashes cached runs on realistic submissions. This
     * override fingerprints the normalized input as JSON.
     *
     * @since 1.2.0
     */
    protected function cacheFingerprint(): string
    {
        return $this->hashInputFingerprint( $this->normalizeInput( $this->input() ) );
    }

    /**
     * Enforce output invariants — boolean plausibility, bounded confidence,
     * trimmed reason, optional suggestion only when marked implausible.
     *
     * @since 1.2.0
     *
     * @param  array<string, mixed>  $output  Decoded model output.
     *
     * @return array{ plausible: bool, confidence: float, reason: string, suggestion?: string }
     */
    protected function validateOutput( array $output ): array
    {
        $plausible  = $this->normalizePlausible( $output['plausible'] ?? true );
        $confidence = max( 0.0, min( 1.0, (float) ( $output['confidence'] ?? 0 ) ) );

        $reason = isset( $output['reason'] ) && is_string( $output['reason'] )
            ? trim( $output['reason'] )
            : '';

        if ( mb_strlen( $reason ) > 200 ) {
            $reason = mb_substr( $reason, 0, 200 );
        }

        $result = [
            'plausible'  => $plausible,
            'confidence' => $confidence,
            'reason'     => $reason,
        ];

        if ( ! $plausible && isset( $output['suggestion'] ) && is_string( $output['suggestion'] ) ) {
            $suggestion = trim( $output['suggestion'] );

            if ( '' !== $suggestion ) {
                $result['suggestion'] = $suggestion;
            }
        }

        return $result;
    }

    /**
     * Coerce the model's `plausible` output into a strict boolean.
     *
     * `(bool) "false"` returns TRUE in PHP — any tool-bridge that coerces
     * the JSON enum to a string flips the verdict. Handle string forms
     * explicitly (`"false"`, `"0"`, `"no"`) and then fall through to the
     * PHP default so real booleans and integer 1/0 still work.
     *
     * @since 1.2.0
     */
    protected function normalizePlausible( mixed $raw ): bool
    {
        if ( is_string( $raw ) ) {
            $normalized = strtolower( trim( $raw ) );

            if ( in_array( $normalized, ['false', '0', 'no', 'off'], true )) {
                return false;
            }

            if ( in_array( $normalized, ['true', '1', 'yes', 'on'], true)) {
                return true;
            }
        }

        return (bool) $raw;
    }
}
