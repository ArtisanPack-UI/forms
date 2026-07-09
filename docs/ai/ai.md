---
title: AI Features Overview
---

# AI Features Overview

Starting in v1.2.0, the ArtisanPack UI Forms package ships four opt-in AI agents built on [`artisanpack-ui/ai`](https://github.com/ArtisanPack-UI/ai). They add semantic intelligence on top of existing form workflows without replacing the static rules already in place.

Each agent no-ops when its feature toggle is off, so installs without the AI package (or with credentials unconfigured) are unaffected.

## The Four Agents

| Feature key                     | Agent                            | Default model      | Purpose                                                                                          |
|---------------------------------|----------------------------------|--------------------|--------------------------------------------------------------------------------------------------|
| `forms.spam_detection`          | `SpamDetectionAgent`             | `claude-haiku-4-5` | Semantic spam scoring for a single submission on top of honeypot / rate limiting.                |
| `forms.submission_summary`      | `SubmissionSummaryAgent`         | `claude-sonnet-4-6`| Periodic digest of submission themes, notable entries, and suggested follow-ups.                 |
| `forms.response_classification` | `ResponseClassificationAgent`    | `claude-haiku-4-5` | Auto-categorize a submission against a caller-supplied set of labels; may propose new categories. |
| `forms.smart_validation`        | `SmartFieldValidationAgent`      | `claude-haiku-4-5` | Opt-in per-field semantic plausibility check that complements format validation.                  |

## Requirements

- **PHP 8.3+** (the AI features require `artisanpack-ui/ai` which requires PHP 8.3+ — the forms package's own runtime is still PHP 8.2+, so consumers on PHP 8.2 who don't want AI can continue using the core forms functionality)
- `artisanpack-ui/forms` v1.2.0+
- `artisanpack-ui/ai` v1.0+ installed and configured with a provider (Anthropic, Ollama, etc.)
- A configured credentials store (env, admin settings, or per-feature override)
- Each feature toggle enabled — see [AI Configuration](Ai-Configuration) and [Feature Toggles](Ai-Feature-Toggles)

Consuming apps that do not install `artisanpack-ui/ai` are unaffected: `FormsServiceProvider::aiFeatures()` short-circuits to an empty array and the Livewire trigger components are skipped during registration.

## Topics

- [Configuration](Ai-Configuration) - Provider credentials, per-feature overrides, cache, and budget
- [Feature Toggles](Ai-Feature-Toggles) - Enabling / disabling individual agents at runtime
- [Spam Detection](Ai-Spam-Detection) - `SpamDetectionAgent` reference
- [Submission Summary](Ai-Submission-Summary) - `SubmissionSummaryAgent` reference
- [Response Classification](Ai-Response-Classification) - `ResponseClassificationAgent` reference
- [Smart Field Validation](Ai-Smart-Field-Validation) - `SmartFieldValidationAgent` reference

## Quick Example

Score a single submission for spam using the shipped Livewire trigger:

```blade
<livewire:forms::ai-spam-check
    :submission-id="$submission->id"
    :fields="$submission->data_array"
    :meta="[
        'ip_country'          => $submission->ip_country,
        'submission_speed_ms' => $submission->submission_speed_ms,
    ]"
/>
```

Or call the agent directly from any listener / job:

```php
use ArtisanPackUI\Forms\Ai\Agents\SpamDetectionAgent;

$result = SpamDetectionAgent::for([
    'fields' => $submission->data_array,
    'meta'   => [
        'ip_country'          => $submission->ip_country,
        'submission_speed_ms' => $submission->submission_speed_ms,
    ],
])->run();
// [
//   'spam_score' => int,
//   'verdict'    => 'ham' | 'suspicious' | 'spam',
//   'reasons'    => string[],
// ]
```

## Design Principles

The four agents share a small set of invariants documented across each agent's reference page:

- **Opt-in per-feature.** No agent runs unless its feature toggle is on in the AI feature registry AND credentials resolve.
- **Static-first.** Spam detection runs **after** honeypot and rate-limit checks — it's a second-line signal, not a replacement. Smart field validation runs **after** format validation for the same reason.
- **Structured output.** Every agent returns a validated JSON shape via a JSON-schema-constrained tool call. The agents normalize the output (clamp bounds, drop empty strings, cap arrays) so the caller can trust the shape without extra guards.
- **Injection-resistant.** User-controllable inputs (form names, field labels, field kinds, submitted values) are escaped before being placed in the prompt — control characters stripped, newlines collapsed, length capped.
- **Cache-safe.** Each agent overrides `cacheFingerprint()` to fingerprint the normalized input as JSON. Cache-enabled runs no longer crash when a submission contains a Carbon timestamp, nested array, or object.

## Next Steps

- [Configuration](Ai-Configuration) - Set up credentials and per-feature overrides
- [Feature Toggles](Ai-Feature-Toggles) - Turn individual agents on / off
- Then pick an agent to integrate — [Spam Detection](Ai-Spam-Detection) is the shortest path.
