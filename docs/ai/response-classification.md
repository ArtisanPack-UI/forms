---
title: Response Classification Agent
---

# Response Classification Agent

The `ResponseClassificationAgent` auto-categorizes an incoming submission against a caller-supplied whitelist of category labels. It can also propose a new category when confidence is low and none of the available options fit.

- **Class**: `ArtisanPackUI\Forms\Ai\Agents\ResponseClassificationAgent`
- **Feature key**: `forms.response_classification`
- **Default model**: `claude-haiku-4-5`
- **Livewire trigger**: `forms::ai-response-classifier`

## When to Use It

- Triaging a contact form into `support-request`, `sales-inquiry`, `feedback`, `bug-report`
- Auto-routing submissions to Slack channels or ticketing tools by category
- Detecting patterns of unclassified submissions worth turning into new categories

## Input

```php
[
    'fields' => [
        // Required. Non-empty submission fields.
        'name'    => 'Alex',
        'email'   => 'alex@example.com',
        'subject' => 'Cannot log in',
        'message' => 'Password reset email never arrives.',
    ],
    'available_categories' => [
        // Required. Non-empty list of category slugs.
        // Kebab-case is recommended for consistency with the suggested_new format.
        'support-request',
        'sales-inquiry',
        'feedback',
        'bug-report',
    ],
]
```

## Output

```php
[
    'category'      => 'support-request',   // one of available_categories
    'confidence'    => 0.92,                // float 0-1
    'suggested_new' => 'partnership',       // OPTIONAL, only when confidence < 0.5
]
```

`suggested_new` is only present when the model is under-confident **and** the submission genuinely doesn't fit anything on the whitelist. The value is normalized to a kebab-case slug and dedup'd against `available_categories` so it can never duplicate an existing label.

## Usage — Livewire

```blade
<livewire:forms::ai-response-classifier
    :submission-id="$submission->id"
    :fields="$submission->data_array"
    :available-categories="[
        'support-request',
        'sales-inquiry',
        'feedback',
        'bug-report',
    ]"
/>
```

The user clicks "Classify submission" to run the agent, then "Apply category" to confirm. That fires `forms-ai-category-selected` with camelCase payload `{ submissionId, category, confidence, suggestedNew }`:

```php
#[On('forms-ai-category-selected')]
public function onCategorySelected(
    int $submissionId,
    string $category,
    float $confidence,
    ?string $suggestedNew,
): void {
    Submission::find($submissionId)->update([
        'category'   => $category,
        'confidence' => $confidence,
    ]);

    if ($suggestedNew !== null) {
        // Prompt an admin to add the suggested new category.
    }
}
```

The component holds `$fields` and `$availableCategories` as `#[Locked]`. Multi-tenant admins should scope inputs to the current viewer.

## Usage — Direct Call

```php
use ArtisanPackUI\Forms\Ai\Agents\ResponseClassificationAgent;

$result = ResponseClassificationAgent::for([
    'fields'               => $submission->data_array,
    'available_categories' => Category::pluck('slug')->all(),
])->run();

$submission->update([
    'category'   => $result['category'],
    'confidence' => $result['confidence'],
]);

if (isset($result['suggested_new'])) {
    // Log the suggestion so an admin can review it later.
    NewCategorySuggestion::create([
        'slug'          => $result['suggested_new'],
        'submission_id' => $submission->id,
    ]);
}
```

## Behavior Invariants

The agent's `validateOutput()` enforces:

- `category` must be one of `available_categories`. If the model returns something else, the agent falls back to the first available category with confidence capped at `0.2` so the caller knows the pick is low-signal.
- `confidence` clamped to `[0.0, 1.0]`.
- `suggested_new` is stripped when confidence ≥ `0.5` (a moderate-confidence match is still a match) or when the suggested slug duplicates an existing category.
- `available_categories` is de-duplicated at input time so the same label can't appear twice in the prompt.

## Related

- [Spam Detection](Ai-Spam-Detection) - Per-submission spam score
- [Submission Summary](Ai-Submission-Summary) - Digest of many submissions
- [AI Configuration](Ai-Configuration) - Credentials, per-feature overrides, cache
- [Feature Toggles](Ai-Feature-Toggles) - Turn the agent on / off
