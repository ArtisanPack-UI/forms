<?php

/**
 * Response classification agent.
 *
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.2.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Ai\Agents;

use ArtisanPackUI\Ai\Agents\ArtisanPackAgent;
use ArtisanPackUI\Ai\Contracts\AgentPrompter;
use ArtisanPackUI\Ai\Credentials\Credentials;
use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Forms\Ai\Concerns\NormalizesLLMInput;

/**
 * Auto-categorize an incoming form submission against a caller-supplied set
 * of category labels (e.g. `support request`, `sales inquiry`, `feedback`,
 * `bug report`).
 *
 * ## Input
 *
 * ```
 * [
 *   'fields'              => array<string, mixed>,  // required — submitted field values
 *   'available_categories' => string[],             // required — category slugs to choose from
 * ]
 * ```
 *
 * ## Output schema
 *
 * ```
 * {
 *   category:       string,        // one of available_categories
 *   confidence:     float(0..1),
 *   suggested_new?: string         // present ONLY when confidence < 0.5 and none of the
 *                                  // available categories fit; a slug the caller can add
 * }
 * ```
 *
 *
 * @since      1.2.0
 */
class ResponseClassificationAgent extends ArtisanPackAgent
{
    use NormalizesLLMInput;

    /**
     * Confidence threshold at which the model may suggest a new category.
     *
     * @since 1.2.0
     *
     * @var float
     */
    protected const NEW_CATEGORY_THRESHOLD = 0.5;

    /**
     * {@inheritDoc}
     */
    public string $featureKey = 'forms.response_classification';

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
You categorize a single form submission by picking the best label from a caller-supplied list.

Requirements:
- `category` MUST be one of the labels in `available_categories` verbatim (same casing, same slug). Do NOT invent your own value here.
- `confidence` is a float from 0.0 to 1.0. 1.0 means "obviously this label"; 0.0 means "guessing". Be honest — a coin flip is 0.5, not 0.8.
- `suggested_new` is optional. Emit it ONLY when your `confidence` is below 0.5 and none of the available categories genuinely describe the submission; in that case propose ONE new category slug (kebab-case, 1-3 words, no punctuation) that would fit. Never propose a category that duplicates one already in the list.
- Do NOT emit `suggested_new` when confidence is 0.5 or higher — a moderate-confidence match is still a match, not a new-category candidate.
- If the submission is empty or the fields don't contain any content to classify, pick the closest available label with a low confidence (0.1-0.3) and skip `suggested_new`.

Return a JSON object with keys: category (string), confidence (number), optional suggested_new (string).
PROMPT;
    }

    /**
     * {@inheritDoc}
     */
    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['category', 'confidence'],
            'properties' => [
                'category' => ['type' => 'string'],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                'suggested_new' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(Credentials $credentials, string $model, string $instructions): array
    {
        $normalized = $this->normalizeInput($this->input());

        $prompter = app(AgentPrompter::class);

        $result = $prompter->prompt(
            credentials: $credentials,
            model: $model,
            instructions: $instructions,
            message: $this->buildMessage($normalized),
            outputSchema: $this->outputSchema(),
        );

        return [
            'output' => $this->validateOutput($result['output'] ?? [], $normalized['available_categories']),
            'input_tokens' => (int) ($result['input_tokens'] ?? 0),
            'output_tokens' => (int) ($result['output_tokens'] ?? 0),
        ];
    }

    /**
     * Validate and shape-check the raw agent input.
     *
     * @since 1.2.0
     *
     * @param  mixed  $input  Raw agent input.
     * @return array{ fields: array<string, mixed>, available_categories: array<int, string> }
     */
    protected function normalizeInput(mixed $input): array
    {
        if (! is_array($input)) {
            throw FeatureError::forFeature(
                $this->featureKey,
                'input must be an array with `fields` and `available_categories` keys.',
            );
        }

        $fields = $input['fields'] ?? null;

        if (! is_array($fields) || $fields === []) {
            throw FeatureError::forFeature($this->featureKey, '`fields` must be a non-empty array.');
        }

        $rawCategories = $input['available_categories'] ?? null;

        if (! is_array($rawCategories) || $rawCategories === []) {
            throw FeatureError::forFeature(
                $this->featureKey,
                '`available_categories` must be a non-empty array of strings.',
            );
        }

        $categories = [];

        foreach ($rawCategories as $category) {
            if (! is_string($category)) {
                continue;
            }

            $trimmed = trim($category);

            if ($trimmed !== '' && ! in_array($trimmed, $categories, true)) {
                $categories[] = $trimmed;
            }
        }

        if ($categories === []) {
            throw FeatureError::forFeature(
                $this->featureKey,
                '`available_categories` must contain at least one non-empty string.',
            );
        }

        return [
            'fields' => $fields,
            'available_categories' => $categories,
        ];
    }

    /**
     * Assemble the structured message body for the prompter.
     *
     * @since 1.2.0
     *
     * @param  array{ fields: array<string, mixed>, available_categories: array<int, string> }  $normalized  Normalized input.
     * @return array<int, array<string, string>>
     */
    protected function buildMessage(array $normalized): array
    {
        return [
            [
                'type' => 'text',
                'text' => 'Available categories: '.implode(', ', $normalized['available_categories']),
            ],
            [
                'type' => 'text',
                'text' => "Submitted fields (JSON):\n".$this->safeJsonEncode($normalized['fields']),
            ],
        ];
    }

    /**
     * Deterministic cache fingerprint over the normalized input.
     *
     * The base ArtisanPackAgent default throws for any non-scalar array
     * entry, which crashes cached runs on realistic submissions (Carbon
     * timestamps, multi-select arrays, file metadata). This override
     * fingerprints the normalized input as JSON.
     *
     * @since 1.2.0
     */
    protected function cacheFingerprint(): string
    {
        return $this->hashInputFingerprint($this->normalizeInput($this->input()));
    }

    /**
     * Enforce output invariants — category must be from the input list,
     * confidence bounded [0, 1], suggested_new only when confidence is low.
     *
     * @since 1.2.0
     *
     * @param  array<string, mixed>  $output  Decoded model output.
     * @param  array<int, string>  $categories  Whitelist of allowed categories.
     * @return array{ category: string, confidence: float, suggested_new?: string }
     */
    protected function validateOutput(array $output, array $categories): array
    {
        $category = isset($output['category']) && is_string($output['category'])
            ? trim($output['category'])
            : '';
        $confidence = $this->clampConfidence($output['confidence'] ?? 0);

        // If the model returned a category that isn't on the whitelist, fall
        // back to the first available label at very low confidence rather
        // than propagate an invalid selection to the caller.
        if ($category === '' || ! in_array($category, $categories, true)) {
            $category = $categories[0];
            $confidence = min($confidence, 0.2);
        }

        $result = [
            'category' => $category,
            'confidence' => $confidence,
        ];

        if ($confidence < self::NEW_CATEGORY_THRESHOLD && isset($output['suggested_new']) && is_string($output['suggested_new'])) {
            $suggested = $this->normalizeSlug($output['suggested_new']);

            if ($suggested !== '' && ! in_array($suggested, $categories, true)) {
                $result['suggested_new'] = $suggested;
            }
        }

        return $result;
    }

    /**
     * Coerce a value into a float bounded to [0, 1].
     *
     * @since 1.2.0
     *
     * @param  mixed  $value  Raw confidence.
     */
    protected function clampConfidence(mixed $value): float
    {
        return max(0.0, min(1.0, (float) $value));
    }

    /**
     * Normalize a proposed new category into a kebab-case slug.
     *
     * @since 1.2.0
     *
     * @param  string  $value  Raw suggestion.
     */
    protected function normalizeSlug(string $value): string
    {
        $lower = strtolower(trim($value));
        $dashed = preg_replace('/[^a-z0-9]+/', '-', $lower);

        return trim((string) $dashed, '-');
    }
}
