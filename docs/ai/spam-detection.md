---
title: Spam Detection Agent
---

# Spam Detection Agent

The `SpamDetectionAgent` scores a single form submission for the likelihood that it is spam. It runs **after** honeypot and rate-limit checks — it's a second-line semantic signal, not a replacement.

- **Class**: `ArtisanPackUI\Forms\Ai\Agents\SpamDetectionAgent`
- **Feature key**: `forms.spam_detection`
- **Default model**: `claude-haiku-4-5`
- **Livewire trigger**: `forms::ai-spam-check`

## When to Use It

Fires cheap Haiku calls on submissions the static checks let through. It catches:

- Copy-pasted marketing pitches unrelated to the form's purpose
- Crypto / adult solicitations that lack obvious keyword matches
- Keyword-stuffed gibberish
- Submissions where the name / email / message content mismatch each other
- Off-topic body text with obvious link-farming intent

## Input

```php
[
    'fields' => [
        // Required. Non-empty associative array of submitted field values.
        // Values may be scalars or nested arrays (multi-select, file
        // metadata). Objects (Carbon, SplFileInfo) are also accepted —
        // the agent normalizes them for both the prompt and cache key.
    ],
    'meta' => [
        // Optional. Structured context about the submission.
        'ip_country'          => 'US',
        'user_agent_class'    => 'desktop-browser',
        'submission_speed_ms' => 4200,
    ],
]
```

## Output

```php
[
    'spam_score' => 82,               // int 0-100
    'verdict'    => 'spam',           // 'ham' | 'suspicious' | 'spam'
    'reasons'    => [                 // 1-5 short human-readable reasons
        'message body is a marketing pitch unrelated to the form purpose',
        'submission_speed_ms of 400 is faster than a human could type this',
    ],
]
```

Score bands map to verdict: `0-39` = ham, `40-74` = suspicious, `75-100` = spam. When the model returns an unknown verdict label, the agent derives the verdict from the score.

Any non-ham verdict is guaranteed to include at least one reason — the agent synthesizes a fallback (`"elevated spam score without specific signals from the model"`) if the model returns none, so the shipped view never contradicts a positive verdict.

## Usage — Livewire

Drop the trigger component into the submissions admin surface:

```blade
<livewire:forms::ai-spam-check
    :submission-id="$submission->id"
    :fields="$submission->data_array"
    :meta="[
        'ip_country'          => $submission->ip_country,
        'user_agent_class'    => classify_user_agent($submission->user_agent),
        'submission_speed_ms' => $submission->submission_speed_ms,
    ]"
/>
```

Emits `forms-ai-spam-verdict` with camelCase payload `{ submissionId, verdict, spamScore }` when the run completes:

```php
#[On('forms-ai-spam-verdict')]
public function onSpamVerdict(int $submissionId, string $verdict, int $spamScore): void
{
    // Update filters, mark the submission, etc.
}
```

The component holds `$fields` and `$meta` as `#[Locked]` public properties. Livewire still serializes them into `wire:snapshot` for state restoration — pass only submissions the current admin is authorized to see.

## Usage — Direct Call

Call the agent from any listener, job, or controller:

```php
use ArtisanPackUI\Ai\Exceptions\FeatureDisabledException;
use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Ai\Exceptions\MissingCredentialsException;
use ArtisanPackUI\Forms\Ai\Agents\SpamDetectionAgent;

try {
    $result = SpamDetectionAgent::for([
        'fields' => $submission->data_array,
        'meta'   => [
            'ip_country'          => $submission->ip_country,
            'submission_speed_ms' => $submission->submission_speed_ms,
        ],
    ])->run();

    if ($result['verdict'] === 'spam') {
        $submission->update(['is_spam' => true]);
    }
} catch (FeatureDisabledException $e) {
    // Toggle off — skip AI, keep the submission.
} catch (MissingCredentialsException $e) {
    // No provider configured — skip AI, keep the submission.
} catch (FeatureError $e) {
    // Bad input shape.
} catch (\Throwable $e) {
    report($e);
    // Provider outage / unexpected — skip AI, keep the submission.
}
```

## Behavior Invariants

The agent's `validateOutput()` enforces:

- `spam_score` clamped to `[0, 100]`.
- `verdict` normalized to `'ham' | 'suspicious' | 'spam'` (score-derived fallback for unknown labels).
- `reasons` filtered to non-empty strings, capped at 5.
- Non-ham verdicts always ship with at least one reason (synthesizes a fallback if empty).

Callers can trust the output shape without re-guarding.

## Related

- [Submission Summary](Ai-Submission-Summary) - Periodic digest of many submissions
- [Response Classification](Ai-Response-Classification) - Categorize submissions by intent
- [AI Configuration](Ai-Configuration) - Credentials, per-feature overrides, cache
- [Feature Toggles](Ai-Feature-Toggles) - Turn the agent on / off
