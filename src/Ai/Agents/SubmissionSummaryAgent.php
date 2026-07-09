<?php

/**
 * Submission summary agent.
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

/**
 * Periodic (daily/weekly) summary of what people are submitting to a form.
 *
 * The output is designed to be delivered as an email digest by the caller —
 * the agent itself is delivery-agnostic; it produces a structured summary
 * of trends the caller can render into any medium.
 *
 * ## Input
 *
 * ```
 * [
 *   'form_name'   => string,                     // required
 *   'window'      => string,                     // e.g. "daily" | "weekly" | "2026-07-01..2026-07-07"
 *   'submissions' => array<int, array<string, mixed>>,  // required — normalized submissions
 * ]
 * ```
 *
 * ## Output schema
 *
 * ```
 * {
 *   headline:      string,
 *   total_count:   int,
 *   themes:        [ { title: string, count: int, examples: string[] } ],
 *   notable:       string[],
 *   suggestions:   string[]
 * }
 * ```
 *
 *
 * @since      1.2.0
 */
class SubmissionSummaryAgent extends ArtisanPackAgent
{
    /**
     * Maximum submissions the agent will include in the prompt, to bound
     * token spend on very high-volume forms.
     *
     * @since 1.2.0
     *
     * @var int
     */
    protected const SUBMISSION_LIMIT = 200;

    /**
     * {@inheritDoc}
     */
    public string $featureKey = 'forms.submission_summary';

    /**
     * {@inheritDoc}
     */
    public string $package = 'artisanpack-ui/forms';

    /**
     * {@inheritDoc}
     */
    public string $defaultModel = 'claude-sonnet-4-6';

    /**
     * {@inheritDoc}
     *
     * Long-running summarization; stream by default per the AI RFC.
     */
    public bool $stream = true;

    /**
     * {@inheritDoc}
     */
    public function instructions(): string
    {
        return <<<'PROMPT'
You summarize what people submitted to a single form over a reporting window and surface trends the form owner should notice.

Requirements:
- `headline` is one short sentence (<= 140 chars) that captures the single most useful thing the owner should know this period (e.g. "37 requests this week — 60% asking about pricing tiers").
- `total_count` MUST equal the number of submissions provided in the input, not an estimate.
- `themes` groups submissions into 2-6 recurring themes. Each theme has a short human-readable title, a rounded count, and 1-3 short verbatim example phrases (redacted where necessary — never include emails, phone numbers, or full names). Every submission does NOT have to fit a theme.
- `notable` is 0-5 sentences highlighting specific submissions or patterns the owner should follow up on (VIP-looking asks, angry customers, security or legal signals). Each entry MUST reference concrete detail from the submission (never "one submission was interesting").
- `suggestions` is 0-3 short actionable ideas for the form owner grounded in the data (e.g. "add a pricing FAQ link — 4 of 12 submissions asked about it").
- If the window contains 0 submissions, return `headline` explaining that, `total_count: 0`, and empty arrays for the other fields.
- Do NOT fabricate submissions or themes that aren't supported by the provided data.

Return a JSON object with keys: headline, total_count, themes, notable, suggestions.
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
            'required' => ['headline', 'total_count', 'themes', 'notable', 'suggestions'],
            'properties' => [
                'headline' => ['type' => 'string', 'maxLength' => 140],
                'total_count' => ['type' => 'integer', 'minimum' => 0],
                'themes' => [
                    'type' => 'array',
                    'maxItems' => 6,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['title', 'count', 'examples'],
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'count' => ['type' => 'integer', 'minimum' => 0],
                            'examples' => [
                                'type' => 'array',
                                'maxItems' => 3,
                                'items' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
                'notable' => [
                    'type' => 'array',
                    'maxItems' => 5,
                    'items' => ['type' => 'string'],
                ],
                'suggestions' => [
                    'type' => 'array',
                    'maxItems' => 3,
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
            'output' => $this->validateOutput($result['output'] ?? [], $normalized['total_count']),
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
     * @return array{ form_name: string, window: string, submissions: array<int, array<string, mixed>>, total_count: int }
     */
    protected function normalizeInput(mixed $input): array
    {
        if (! is_array($input)) {
            throw FeatureError::forFeature(
                $this->featureKey,
                'input must be an array with `form_name` and `submissions` keys.',
            );
        }

        $formName = isset($input['form_name']) && is_string($input['form_name'])
            ? trim($input['form_name'])
            : '';

        if ($formName === '') {
            throw FeatureError::forFeature($this->featureKey, '`form_name` must be a non-empty string.');
        }

        $submissions = $input['submissions'] ?? null;

        if (! is_array($submissions)) {
            throw FeatureError::forFeature($this->featureKey, '`submissions` must be an array.');
        }

        $window = isset($input['window']) && is_string($input['window'])
            ? trim($input['window'])
            : 'weekly';

        $total = count($submissions);

        if ($total > self::SUBMISSION_LIMIT) {
            $submissions = array_slice($submissions, 0, self::SUBMISSION_LIMIT);
        }

        return [
            'form_name' => $formName,
            'window' => $window === '' ? 'weekly' : $window,
            'submissions' => array_values($submissions),
            'total_count' => $total,
        ];
    }

    /**
     * Assemble the structured message body for the prompter.
     *
     * @since 1.2.0
     *
     * @param  array{ form_name: string, window: string, submissions: array<int, array<string, mixed>>, total_count: int }  $normalized  Normalized input.
     * @return array<int, array<string, string>>
     */
    protected function buildMessage(array $normalized): array
    {
        $parts = [
            ['type' => 'text', 'text' => sprintf('Form name: %s', $normalized['form_name'])],
            ['type' => 'text', 'text' => sprintf('Reporting window: %s', $normalized['window'])],
            ['type' => 'text', 'text' => sprintf('Total submissions in window: %d', $normalized['total_count'])],
        ];

        if ($normalized['total_count'] > count($normalized['submissions'])) {
            $parts[] = [
                'type' => 'text',
                'text' => sprintf(
                    'NOTE: only the first %d submissions are included below; use `total_count` as the true count.',
                    count($normalized['submissions']),
                ),
            ];
        }

        $parts[] = [
            'type' => 'text',
            'text' => "Submissions (JSON array):\n".json_encode(
                $normalized['submissions'],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ),
        ];

        return $parts;
    }

    /**
     * Enforce output invariants — force `total_count` to the input truth,
     * clamp arrays, and trim strings.
     *
     * @since 1.2.0
     *
     * @param  array<string, mixed>  $output  Decoded model output.
     * @param  int  $inputTotal  Ground-truth submission count.
     * @return array{ headline: string, total_count: int, themes: array<int, array{ title: string, count: int, examples: array<int, string> }>, notable: array<int, string>, suggestions: array<int, string> }
     */
    protected function validateOutput(array $output, int $inputTotal): array
    {
        $headline = isset($output['headline']) && is_string($output['headline'])
            ? trim($output['headline'])
            : '';

        if (mb_strlen($headline) > 140) {
            $headline = mb_substr($headline, 0, 140);
        }

        return [
            'headline' => $headline,
            'total_count' => $inputTotal,
            'themes' => $this->normalizeThemes($output['themes'] ?? []),
            'notable' => $this->clampList($this->stringList($output['notable'] ?? []), 5),
            'suggestions' => $this->clampList($this->stringList($output['suggestions'] ?? []), 3),
        ];
    }

    /**
     * Normalize the themes array.
     *
     * @since 1.2.0
     *
     * @param  mixed  $raw  Raw themes from the model.
     * @return array<int, array{ title: string, count: int, examples: array<int, string> }>
     */
    protected function normalizeThemes(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $themes = [];

        foreach ($raw as $theme) {
            if (! is_array($theme)) {
                continue;
            }

            $title = isset($theme['title']) ? trim((string) $theme['title']) : '';

            if ($title === '') {
                continue;
            }

            $themes[] = [
                'title' => $title,
                'count' => max(0, (int) ($theme['count'] ?? 0)),
                'examples' => $this->clampList($this->stringList($theme['examples'] ?? []), 3),
            ];
        }

        if (count($themes) > 6) {
            $themes = array_slice($themes, 0, 6);
        }

        return $themes;
    }

    /**
     * Clamp a list to a maximum length.
     *
     * @since 1.2.0
     *
     * @param  array<int, string>  $list  Input list.
     * @param  int  $max  Maximum length.
     * @return array<int, string>
     */
    protected function clampList(array $list, int $max): array
    {
        return count($list) > $max ? array_slice($list, 0, $max) : $list;
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
