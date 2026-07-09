---
title: Submission Summary Agent
---

# Submission Summary Agent

The `SubmissionSummaryAgent` produces a periodic (daily / weekly / custom-range) summary of what people are submitting to a form and surfaces trends the owner should notice.

- **Class**: `ArtisanPackUI\Forms\Ai\Agents\SubmissionSummaryAgent`
- **Feature key**: `forms.submission_summary`
- **Default model**: `claude-sonnet-4-6`
- **Streams by default**: yes (long-running summarization per the AI RFC)
- **Livewire trigger**: `forms::ai-submission-summary`

## When to Use It

- Weekly digest emails to form owners
- Ad-hoc "what did people ask this week?" summaries from the admin surface
- Feeding a dashboard with theme + count breakdowns for a chosen window

## Input

```php
[
    'form_name'   => 'Contact us',       // required, non-empty string
    'window'      => 'weekly',           // optional label — daily / weekly / a date range
    'submissions' => [                   // required array of submissions
        ['message' => 'How much is pro?', 'email' => 'a@example.com'],
        ['message' => 'Enterprise inquiry — need SAML'],
        // ... up to N submissions
    ],
]
```

The agent caps the number of submissions sent to the model at **200** to bound token spend on high-volume forms. Additional submissions are dropped from the prompt (`total_count` still reflects the true count; `sample_count` reflects what was shown to the model).

## Output

```php
[
    'headline'     => '5 submissions this week, mostly pricing questions with one enterprise ask.',
    'total_count'  => 5,       // true submission count from the input
    'sample_count' => 5,       // submissions actually shown to the model (min(200, total_count))
    'themes' => [
        [
            'title'    => 'Pricing questions',
            'count'    => 3,          // per-sample count, always <= sample_count
            'examples' => ['How much does the pro tier cost?', 'What does the pro tier include?'],
        ],
    ],
    'notable' => [
        'One enterprise ask for 500 seats with SAML — worth a quick outbound reply.',
    ],
    'suggestions' => [
        'Add a pricing FAQ block — 3 of 5 submissions asked about it.',
    ],
]
```

### Interpreting `total_count` vs `sample_count`

The agent enforces `total_count` = the true input count. When `sample_count < total_count`, the model only saw the first `sample_count` submissions — theme percentages should be computed against `sample_count`, not `total_count`.

The shipped Livewire view surfaces this as `"5 submissions in the window (themes reflect the first 200 of 500 submissions)"` when truncation happened.

## Usage — Livewire

```blade
<livewire:forms::ai-submission-summary
    form-name="Contact us"
    window="weekly"
    :submissions="$submissions"
/>
```

Emits `forms-ai-summary-ready` with camelCase payload `{ formName, window, headline, totalCount, sampleCount }`:

```php
#[On('forms-ai-summary-ready')]
public function onSummaryReady(
    string $formName,
    string $window,
    string $headline,
    int $totalCount,
    int $sampleCount,
): void {
    // Offer "Send digest email" against the generated summary, etc.
}
```

The component holds `$submissions` as `#[Locked]`. Multi-tenant admins should scope inputs to the current viewer.

## Usage — Direct Call

```php
use ArtisanPackUI\Forms\Ai\Agents\SubmissionSummaryAgent;

$result = SubmissionSummaryAgent::for([
    'form_name'   => $form->name,
    'window'      => 'weekly',
    'submissions' => $form->submissions()
        ->where('created_at', '>=', now()->subWeek())
        ->get()
        ->map(fn ($s) => $s->data_array)
        ->all(),
])->run();

// Ship the result as an email digest, dashboard row, etc.
```

## Streaming

The agent streams by default. When invoked with a stream callback, cache is skipped and chunks flow through:

```php
SubmissionSummaryAgent::for($input)
    ->streamTo(function (string $chunk, string $accumulated) {
        // Echo to a broadcast channel, an SSE response, etc.
    })
    ->run();
```

Non-streaming callers (queue-driven digest jobs) still benefit from cache when `artisanpack.ai.cache.enabled` is truthy — the base agent only skips cache when a chunk callback is actively registered.

## Behavior Invariants

The agent's `validateOutput()` enforces:

- `headline` is a non-empty string ≤ 140 chars — the agent **throws `FeatureError`** if the model returns an empty headline, so the caller sees a retryable error rather than a broken empty card.
- `total_count` = the true input count (agent overrides any value the model returned).
- `sample_count` = `min(200, total_count)` — added in v1.2.0 so callers can render accurate ratios.
- `themes` capped at 6 entries; empty-title entries dropped; each `count` is coerced with `is_numeric` guard and clamped to `[0, sample_count]`.
- `notable` capped at 5, `suggestions` capped at 3, both filtered to non-empty strings.

## Related

- [Spam Detection](Ai-Spam-Detection) - Per-submission spam score
- [Response Classification](Ai-Response-Classification) - Per-submission categorization
- [AI Configuration](Ai-Configuration) - Credentials, per-feature overrides, cache
- [Feature Toggles](Ai-Feature-Toggles) - Turn the agent on / off
