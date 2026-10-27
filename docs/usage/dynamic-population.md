---
title: Dynamic Population
---

# Dynamic Population

Since 1.6.0 a field can start with a value from the page that shows the form: a value your code passes in, a query string parameter or a filter. Use it to carry a plan, campaign or product into the form, or to fill in what you already know about a signed-in visitor.

## Turning It On

In the field's **Advanced** tab, turn on **Allow field to be populated dynamically** (`field_config.dynamic_population`). **Parameter Name** (`field_config.dynamic_parameter`) is the key the value is looked up by. It defaults to the field name.

```php
use ArtisanPackUI\Forms\Models\FormField;

FormField::create( [
    'form_id'      => $form->id,
    'type'         => 'select',
    'name'         => 'plan',
    'label'        => 'Plan',
    'field_config' => [
        'dynamic_population' => true,
        'dynamic_parameter'  => 'plan',
        'options'            => [
            ['value' => 'basic', 'label' => 'Basic'],
            ['value' => 'pro', 'label' => 'Pro'],
        ],
    ],
] );
```

File, List, Likert / Matrix, Ranking, Calculation, Signature, Consent and CAPTCHA fields don't have the setting. Name, Address and Date Range fields are populated per input instead (see [Composite Fields](#composite-fields)). Read-only fields keep their read-only value instead (see [Read-only Fields](Usage-Form-Builder#read-only-fields)).

## Where the Value Comes From

A populated field takes the first of these that is set:

1. **The renderer's `prefill`**, keyed by parameter name.
2. **The page's query string**, such as `/pricing?plan=pro`.
3. **The `ap.forms.fieldValue.{parameter}` filter.**
4. **The field's default value**, with its merge tags filled in.

A field without dynamic population on starts with its default value. A choice field starts with the options marked **Selected by default**, if any, before its default value.

### Prefill

```blade
<livewire:form-renderer :form="$form" :prefill="['plan' => 'pro', 'email' => auth()->user()?->email]" />
```

The React and Vue `FormRenderer` components take the same values as a `prefill` prop:

```tsx
<FormRenderer baseUrl="/api/v1/forms" formSlug="signup" prefill={{ plan: 'pro', utm_source: 'newsletter' }} />
```

### Filter

`ap.forms.fieldValue.{parameter}` receives the default value, the field and a `PopulationContext` with the visitor (`user`), `ipAddress`, `embedUrl`, `referer` and `query`. Return the value the field should start with:

```php
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\PopulationContext;
use function addFilter;

addFilter( 'ap.forms.fieldValue.company', function ( mixed $default, FormField $field, PopulationContext $context ): mixed {
    return $context->user?->company?->name ?? $default;
} );
```

For a sub-input of a composite field (see below), the filter also receives the sub-input key as a fifth argument.

## Default Value Merge Tags

Default values may use these tags, which are listed beside the **Default Value** input:

| Tag | Value |
|-----|-------|
| `{user:email}` | The signed-in visitor's email address |
| `{user:name}` | The signed-in visitor's name |
| `{date}` | Today's date (`YYYY-MM-DD`) |
| `{ip}` | The visitor's IP address, as the privacy settings allow it to be kept |
| `{embed_url}` | The URL of the page the form is shown on |
| `{referer}` | The page the visitor came from |
| `{query:name}` | The query string parameter `name`, such as `{query:utm_source}` |

The server fills in `{user:…}`, `{date}` and `{ip}`. The page tags (`{embed_url}`, `{referer}` and `{query:…}`) describe the page the visitor sees: the Livewire renderer fills them in on the server, and the React and Vue renderers fill them in in the browser, since the render API does not know the page. Read-only fields leave page tags blank, because the visitor controls them.

## How Values Are Checked

A populated value is only a starting value. The visitor can change it, and it is validated like anything else they submit, so never rely on it for authorization or pricing.

- Values are sanitized: tags and control characters are removed, and values are cut to 1,000 characters.
- Choice fields only take a value that matches one of their options, by value or by label. Checkbox groups and multi-selects take comma-separated values (`?interests=news,offers`).
- An email field with **Enable email confirmation** fills the confirmation input with the same value.
- Rich text values are sanitized before the editor shows them.

## Composite Fields

The sub-inputs of a Name, Address or Date Range field are populated one by one. In the field's **Inputs** table, give a sub-input a **Default** value or a **Parameter**. A sub-input with a parameter takes its value from the prefill, the query string, then the `ap.forms.fieldValue.{parameter}` filter, and falls back to its default. A select sub-input, such as an address's country, only takes a value that matches one of its options.

## In the API

The render endpoint (`GET /{form}/render`) returns each field's starting value as `resolved_default_value`, with the server-side tags filled in and the filter applied, and its parameter name as `dynamic_parameter`. Page tags and the query string are left for the client. See [API Resources](Api-Resources#formfieldresource).

## Next Steps

- [Form Builder](Usage-Form-Builder) - Every field setting
- [Form Renderer](Usage-Form-Renderer) - Display forms on your site
