---
title: AI Configuration
---

# AI Configuration

Configuration for the four Forms AI agents lives on the [`artisanpack-ui/ai`](https://github.com/ArtisanPack-UI/ai) package — the Forms package only registers the four feature keys and their default models. Everything else (credentials, per-feature overrides, cache, budget) is controlled through the AI package's config and admin surfaces.

This page covers the settings that most directly affect the Forms agents.

## Provider Credentials

The AI package supports Anthropic and Ollama out of the box. Credentials can be provided via:

1. **Environment variables** — quickest for local development
2. **Admin settings page** (via the CMS Framework Settings module, if installed) — recommended for production
3. **Per-feature override** — pin a specific feature to a different provider or model

### Environment Variables

```env
# Anthropic
ARTISANPACK_AI_PROVIDER=anthropic
ARTISANPACK_AI_API_KEY=sk-ant-...

# Or Ollama, running locally
ARTISANPACK_AI_PROVIDER=ollama
ARTISANPACK_AI_BASE_URL=http://localhost:11434
```

See the `artisanpack-ui/ai` package's [BYOK guide](https://github.com/ArtisanPack-UI/ai/blob/main/docs/byok.md) for provider-specific setup.

## Per-Feature Overrides

Every Forms feature can pin its own model or instructions via the `artisanpack.ai.features` config array. This is useful for:

- Downgrading a Sonnet feature to Haiku to save cost
- Pinning the summary agent to a specific model version for stability
- Providing a custom system prompt

```php
// config/artisanpack.php
return [
    'ai' => [
        'features' => [
            'forms.spam_detection' => [
                'enabled' => true,
                'model'   => 'claude-haiku-4-5', // override
            ],
            'forms.submission_summary' => [
                'enabled' => true,
                'model'   => 'claude-haiku-4-5', // downgrade Sonnet default
            ],
            'forms.response_classification' => [
                'enabled' => true,
            ],
            'forms.smart_validation' => [
                'enabled' => false, // opt out entirely
            ],
        ],
    ],
];
```

The `enabled` key drives the feature toggle — see [Feature Toggles](Ai-Feature-Toggles).

## Default Models

| Feature key                     | Default model      | Notes                                                       |
|---------------------------------|--------------------|-------------------------------------------------------------|
| `forms.spam_detection`          | `claude-haiku-4-5` | Fast, low-cost per submission.                              |
| `forms.submission_summary`      | `claude-sonnet-4-6`| Streams by default (long-running summarization).            |
| `forms.response_classification` | `claude-haiku-4-5` | Fast, low-cost per submission.                              |
| `forms.smart_validation`        | `claude-haiku-4-5` | Fast, low-cost per field check.                             |

Override in config as shown above.

## Cache

When `artisanpack.ai.cache.enabled` is truthy, every agent's `run()` first checks a per-input cache before calling the provider. Every Forms agent overrides `cacheFingerprint()` to hash its normalized input — realistic submissions (Carbon timestamps, nested arrays, file metadata) fingerprint without throwing.

```php
// config/artisanpack.php
'ai' => [
    'cache' => [
        'enabled' => true,
        'store'   => 'redis', // any configured cache store; defaults to the app default
        'ttl'     => 3600,    // seconds
    ],
],
```

**When to enable cache:**

- **Yes** for `forms.spam_detection` and `forms.smart_validation` — same submission or same value re-checked yields the same answer.
- **Maybe** for `forms.response_classification` — depends on how often the same submission is re-classified.
- **Rarely** for `forms.submission_summary` — the input is a full submissions payload; hits are rare unless you're re-running a report.

## Budget

The AI package supports monthly USD budgets that emit warnings + throttle agents when exceeded. See the `artisanpack-ui/ai` [budget guide](https://github.com/ArtisanPack-UI/ai/blob/main/docs/budget.md) for setup — the Forms agents participate automatically.

## Streaming

`SubmissionSummaryAgent` sets `$stream = true` by default, which is the RFC's recommended posture for long-running summarization. Non-streaming callers (jobs, digest emails) still hit the cache when enabled — the base agent only skips cache when a stream callback is actively registered.

## Sanitization of Prompt Inputs

Every Forms agent routes user-controllable string inputs (`form_name`, `field_label`, `field_kind`, `value`) through a shared `escapeForPrompt()` helper that:

- Strips control characters (U+0000 – U+001F, U+007F – U+009F, format chars)
- Collapses newlines / whitespace runs to a single space
- Trims and caps the string at a per-field length

This defuses admin-authored "Ignore prior instructions." payloads without needing a separate content filter. Nothing to configure — it's always on.

## Next Steps

- [Feature Toggles](Ai-Feature-Toggles) - Turn individual agents on / off
- [AI Features Overview](Ai-Ai) - Back to the AI section index
