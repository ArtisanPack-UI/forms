<?php

/**
 * Spam detection agent.
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
 * Semantic spam scoring for a form submission.
 *
 * ## Input
 *
 * ```
 * [
 *   'fields' => array<string, mixed>,   // required — submitted field values
 *   'meta'   => [
 *     'ip_country'          => string|null,
 *     'user_agent_class'    => string|null,
 *     'submission_speed_ms' => int|null,
 *   ],
 * ]
 * ```
 *
 * ## Output schema
 *
 * ```
 * {
 *   spam_score: int(0..100),
 *   verdict:    'ham'|'suspicious'|'spam',
 *   reasons:    string[]
 * }
 * ```
 *
 *
 * @since      1.2.0
 */
class SpamDetectionAgent extends ArtisanPackAgent
{
    use NormalizesLLMInput;

    /**
     * Allowed verdict values, in order of severity.
     *
     * @since 1.2.0
     *
     * @var array<int, string>
     */
    protected const VERDICTS = ['ham', 'suspicious', 'spam'];

    /**
     * {@inheritDoc}
     */
    public string $featureKey = 'forms.spam_detection';

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
You score a single form submission for the likelihood that it is spam.

You are the second line of defense: static checks (honeypot, rate limiting, CAPTCHA) have already run. Focus on semantic signals a static check can't see — copy-pasted marketing pitches, cryptocurrency and adult-content solicitations, keyword stuffing, gibberish, mismatched name/email/message content, off-topic body text, obvious link farms.

Requirements:
- `spam_score` is an integer 0-100. 0 means "clearly a real human message"; 100 means "obvious spam".
- `verdict` MUST be one of `ham` (score 0-39), `suspicious` (score 40-74), or `spam` (score 75-100). Keep it consistent with the score.
- `reasons` is 1-5 short sentences, each pointing at one concrete signal you observed (e.g. "message body is a marketing pitch unrelated to the form's purpose", "email domain and name look unrelated", "submission_speed_ms of 800 is faster than a human could type this message"). Never emit generic reasons like "looks like spam" or "not sure".
- When the submission is clearly a real human message, return `verdict: ham`, a low score, and either an empty `reasons` array or a single sentence explaining what looked legitimate.
- Do NOT flag a submission as spam just because it is short — only when the CONTENT signals spam.

Return a JSON object with keys: spam_score (integer 0-100), verdict (string), reasons (array of strings).
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
            'required' => ['spam_score', 'verdict', 'reasons'],
            'properties' => [
                'spam_score' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                'verdict' => ['type' => 'string', 'enum' => self::VERDICTS],
                'reasons' => [
                    'type' => 'array',
                    'maxItems' => 5,
                    'items' => ['type' => 'string'],
                ],
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
            'output' => $this->validateOutput($result['output'] ?? []),
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
     * @return array{ fields: array<string, mixed>, meta: array<string, mixed> }
     */
    protected function normalizeInput(mixed $input): array
    {
        if (! is_array($input)) {
            throw FeatureError::forFeature(
                $this->featureKey,
                'input must be an array with a `fields` key.',
            );
        }

        $fields = $input['fields'] ?? null;

        if (! is_array($fields) || $fields === []) {
            throw FeatureError::forFeature($this->featureKey, '`fields` must be a non-empty array.');
        }

        $meta = is_array($input['meta'] ?? null) ? $input['meta'] : [];

        return [
            'fields' => $fields,
            'meta' => $meta,
        ];
    }

    /**
     * Assemble the structured message body for the prompter.
     *
     * @since 1.2.0
     *
     * @param  array{ fields: array<string, mixed>, meta: array<string, mixed> }  $normalized  Normalized input.
     * @return array<int, array<string, string>>
     */
    protected function buildMessage(array $normalized): array
    {
        $parts = [
            [
                'type' => 'text',
                'text' => "Submitted fields (JSON):\n".$this->safeJsonEncode($normalized['fields']),
            ],
        ];

        if ($normalized['meta'] !== []) {
            $parts[] = [
                'type' => 'text',
                'text' => "Submission metadata:\n".$this->safeJsonEncode($normalized['meta']),
            ];
        }

        return $parts;
    }

    /**
     * Deterministic cache fingerprint over the normalized input.
     *
     * The base ArtisanPackAgent default throws for any non-scalar array
     * entry, which crashes cached runs on realistic submissions (Carbon
     * timestamps, multi-select arrays, file metadata). This override
     * fingerprints the normalized input as JSON, so cached inputs are stable
     * across runs regardless of the raw value types.
     *
     * @since 1.2.0
     */
    protected function cacheFingerprint(): string
    {
        return $this->hashInputFingerprint($this->normalizeInput($this->input()));
    }

    /**
     * Enforce output invariants — score bounds, verdict enum, reason list.
     *
     * @since 1.2.0
     *
     * @param  array<string, mixed>  $output  Decoded model output.
     * @return array{ spam_score: int, verdict: string, reasons: array<int, string> }
     */
    protected function validateOutput(array $output): array
    {
        $score = $this->clampScore($output['spam_score'] ?? 0);
        $verdict = $this->normalizeVerdict($output['verdict'] ?? null, $score);
        $reasons = $this->stringList($output['reasons'] ?? []);

        if (count($reasons) > 5) {
            $reasons = array_slice($reasons, 0, 5);
        }

        // A non-ham verdict without a single reason silently ships a bare
        // "spam / 92" to the caller and leaves the admin UI to render the
        // "no reasons" fallback text — which reads as "clearly legitimate"
        // in the shipped view. Synthesize a fallback so the render matches
        // the verdict.
        if ($reasons === [] && $verdict !== 'ham') {
            $reasons = ['elevated spam score without specific signals from the model'];
        }

        return [
            'spam_score' => $score,
            'verdict' => $verdict,
            'reasons' => $reasons,
        ];
    }

    /**
     * Coerce a value into an integer bounded to [0, 100].
     *
     * @since 1.2.0
     *
     * @param  mixed  $value  Raw score.
     */
    protected function clampScore(mixed $value): int
    {
        return max(0, min(100, (int) $value));
    }

    /**
     * Normalize the verdict, falling back to a score-derived value when the
     * model returned an out-of-vocabulary string.
     *
     * @since 1.2.0
     *
     * @param  mixed  $raw  Raw verdict from the model.
     * @param  int  $score  Clamped spam score.
     */
    protected function normalizeVerdict(mixed $raw, int $score): string
    {
        if (is_string($raw)) {
            $candidate = strtolower(trim($raw));

            if (in_array($candidate, self::VERDICTS, true)) {
                return $candidate;
            }
        }

        if ($score >= 75) {
            return 'spam';
        }

        if ($score >= 40) {
            return 'suspicious';
        }

        return 'ham';
    }

    /**
     * Filter a raw list into a clean array of non-empty strings.
     *
     * @since 1.2.0
     *
     * @param  mixed  $raw  Raw list from the model.
     * @return array<int, string>
     */
    protected function stringList(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $value) {
            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);

            if ($trimmed === '') {
                continue;
            }

            $out[] = $trimmed;
        }

        return $out;
    }
}
