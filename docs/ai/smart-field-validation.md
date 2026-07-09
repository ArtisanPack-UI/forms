---
title: Smart Field Validation Agent
---

# Smart Field Validation Agent

The `SmartFieldValidationAgent` performs an opt-in **semantic** plausibility check on a single form field value. It complements format-level validation (required, length, regex) — it never replaces it.

- **Class**: `ArtisanPackUI\Forms\Ai\Agents\SmartFieldValidationAgent`
- **Feature key**: `forms.smart_validation`
- **Default model**: `claude-haiku-4-5`
- **Livewire trigger**: `forms::ai-smart-field-validator`

## When to Use It

Format validation catches "not an email" and "too short." Semantic validation catches:

- Company name = `"asdfasdf"` or profanity
- Address = `"123 Fake St"` or obvious jokes
- Full name = `"TEST"` or a URL
- Phone = `555-555-5555` or sequential digits
- Email that's format-valid but points at `test@test.test`

Wire it into an admin verification workflow, or into the form renderer as an on-blur check for high-signal fields.

## Input

```php
[
    'field_label' => 'Company',                    // required — human-readable label
    'field_kind'  => 'company_name',               // required — semantic kind: address, email, phone, etc.
    'value'       => 'Anthropic',                  // required — submitted value
    'context'     => [                             // optional — sibling fields for cross-checks
        'website' => 'https://anthropic.com',
    ],
]
```

`field_kind` values the shipped prompt recognizes:

| Kind             | What the agent looks for                                                       |
|------------------|--------------------------------------------------------------------------------|
| `address`        | Real street pattern, real city/state where obvious; rejects `"123 Fake St"`. |
| `company_name`   | Real business name; rejects random strings, profanity.                       |
| `full_name`      | Plausible human name in any language; rejects `"asdfasdf"`, URLs, `"TEST"`. |
| `email`          | Real-looking address even when format-valid; flags `test@test.test`.         |
| `phone`          | Rejects obvious fakes (`555-555-5555`, `000-000-0000`, sequential digits). |

Custom kinds are supported — the model interprets the field label + kind in context. Keep the kind short and descriptive.

## Output

```php
[
    'plausible'  => true,                                     // bool
    'confidence' => 0.9,                                      // float 0-1
    'reason'     => 'recognizable single-word brand name',    // <= 200 chars
    'suggestion' => 'ask the user to re-enter the address',   // OPTIONAL, only when plausible=false
]
```

The agent's `normalizePlausible()` handles the model returning the boolean as a string — `"false"` / `"0"` / `"no"` / `"off"` all map to `false`, so a tool-bridge coercion of the JSON enum can't silently flip the verdict.

## Usage — Livewire

```blade
<livewire:forms::ai-smart-field-validator
    field-name="address"
    field-label="Business Address"
    field-kind="address"
    :value="$address"
    :context="[
        'city'  => $city,
        'state' => $state,
    ]"
/>
```

Emits `forms-ai-field-verdict` with camelCase payload `{ fieldName, plausible, confidence, reason }`:

```php
#[On('forms-ai-field-verdict')]
public function onFieldVerdict(
    string $fieldName,
    bool $plausible,
    float $confidence,
    string $reason,
): void {
    if (! $plausible && $confidence > 0.7) {
        // High-confidence rejection — surface a warning to the user.
    }
}
```

The component holds `$value` and `$context` as `#[Locked]`. Multi-tenant admins should scope inputs to the current viewer.

## Usage — Direct Call

```php
use ArtisanPackUI\Forms\Ai\Agents\SmartFieldValidationAgent;

$result = SmartFieldValidationAgent::for([
    'field_label' => 'Business Address',
    'field_kind'  => 'address',
    'value'       => $submission->data['address'],
    'context'     => [
        'city'  => $submission->data['city'],
        'state' => $submission->data['state'],
    ],
])->run();

if (! $result['plausible']) {
    $submission->update([
        'address_verified' => false,
        'address_reason'   => $result['reason'],
    ]);
}
```

## Behavior Invariants

The agent's `validateOutput()` enforces:

- `plausible` normalized to strict bool via `normalizePlausible()` — string forms are handled explicitly.
- `confidence` clamped to `[0.0, 1.0]`.
- `reason` trimmed and capped at 200 characters.
- `suggestion` is only kept when `plausible=false`. Any suggestion the model emits for a plausible value is stripped.

`normalizeInput()` requires `field_label`, `field_kind`, and `value` to all be non-empty strings — use `SmartFieldValidationAgent` only on fields that already passed format validation.

## Related

- [Spam Detection](Ai-Spam-Detection) - Whole-submission spam score
- [AI Configuration](Ai-Configuration) - Credentials, per-feature overrides, cache
- [Feature Toggles](Ai-Feature-Toggles) - Turn the agent on / off
