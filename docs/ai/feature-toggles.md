---
title: AI Feature Toggles
---

# AI Feature Toggles

Each of the four Forms AI agents runs only when its feature toggle is on. Toggles are managed by the [`artisanpack-ui/ai`](https://github.com/ArtisanPack-UI/ai) package's `FeatureRegistry` — this page covers what the Forms package needs from that surface.

## Registered Feature Keys

`FormsServiceProvider::aiFeatures()` registers these four keys:

| Feature key                     | Agent                            | Label                       |
|---------------------------------|----------------------------------|-----------------------------|
| `forms.spam_detection`          | `SpamDetectionAgent`             | Spam detection              |
| `forms.submission_summary`      | `SubmissionSummaryAgent`         | Submission summary          |
| `forms.response_classification` | `ResponseClassificationAgent`    | Response classification     |
| `forms.smart_validation`        | `SmartFieldValidationAgent`      | Smart field validation      |

Every key is auto-discovered by the AI package when both packages are installed. If `artisanpack-ui/ai` is absent, `aiFeatures()` short-circuits to `[]` and none of these keys register.

## Toggling At Runtime

Programmatic toggling goes through the AI feature registry:

```php
use ArtisanPackUI\Ai\Contracts\FeatureRegistry;

$registry = app(FeatureRegistry::class);

// Enable
$registry->enable('forms.spam_detection');

// Disable
$registry->disable('forms.spam_detection');

// Check state
if ($registry->isToggleOn('forms.spam_detection')) {
    // ...
}

// Full runnability check (toggle + credentials)
if ($registry->isEnabled('forms.spam_detection')) {
    // ...
}
```

## Toggling Via Config

For static config-driven toggles, set the `enabled` key on the feature's entry:

```php
// config/artisanpack.php
'ai' => [
    'features' => [
        'forms.spam_detection'          => ['enabled' => true],
        'forms.submission_summary'      => ['enabled' => true],
        'forms.response_classification' => ['enabled' => false],
        'forms.smart_validation'        => ['enabled' => false],
    ],
],
```

Config values are honored on boot; the registry is the source of truth at runtime.

## Toggling Via Admin UI

If `artisanpack-ui/ai` v1.0+ is installed and its admin surface is exposed, the four Forms features appear on the AI Features admin page grouped under `artisanpack-ui/forms`. Each has an inline toggle and per-feature model / instructions overrides.

## What Callers See When Disabled

- **Direct agent calls** (`SomeAgent::for(...)->run()`) throw `FeatureDisabledException`.
- **Livewire trigger components** render a "This AI feature is currently disabled." banner in place of the trigger button. See each component's `getIsEnabledProperty` accessor.

Handle both when integrating:

```php
use ArtisanPackUI\Ai\Exceptions\FeatureDisabledException;
use ArtisanPackUI\Forms\Ai\Agents\SpamDetectionAgent;

try {
    $result = SpamDetectionAgent::for([...])->run();
} catch (FeatureDisabledException $e) {
    // Skip AI scoring; fall through to static checks only.
}
```

## Rollout Strategy

The recommended posture for a first install:

1. Install `artisanpack-ui/ai`, configure credentials.
2. Enable **`forms.spam_detection`** first — highest signal, lowest per-call cost. Test on a sample of recent submissions before wiring it into the live submission path.
3. Enable **`forms.response_classification`** — same signal shape as spam, useful for triaging support-request forms.
4. Enable **`forms.submission_summary`** — start with a weekly digest for one or two forms; expand to all forms once tuning is stable.
5. Enable **`forms.smart_validation`** last — it's the only agent that touches user-facing form submission flow, so vet it carefully before enabling for the general public.

Disable any feature at any time via the admin UI or `$registry->disable(...)` without redeploy.

## Next Steps

- Pick an agent to integrate — [Spam Detection](Ai-Spam-Detection) is the shortest path.
- [AI Configuration](Ai-Configuration) - Credentials and per-feature overrides
- [AI Features Overview](Ai-Ai) - Back to the AI section index
