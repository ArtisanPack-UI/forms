---
title: Field Type Handlers
---

# Field Type Handlers

Since 1.6.0 everything specific to a field type lives in one class, its **handler**. A handler defines the type, its builder settings, validation rules and messages, how a value is normalized when it is stored, how it is displayed and exported, and which merge tags and Blade view it uses. Validation, storage, submission display, the CSV export, merge tags and Blade rendering all ask the field's handler. They no longer switch on the type name.

Every built-in type has a handler in `ArtisanPackUI\Forms\FieldTypes` (listed in `FieldTypes::HANDLERS`). `FieldTypes::handler( $type )` returns the handler for any registered type, and `$field->fieldType()` returns a field's handler.

## Registering a Handler

Extend `ArtisanPackUI\Forms\FieldTypes\AbstractFieldType` and register the class (or an instance) through the `ap.forms.fieldTypes` filter, keyed by the type name:

```php
namespace App\Forms;

use ArtisanPackUI\Forms\FieldTypes\AbstractFieldType;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;

class IbanFieldType extends AbstractFieldType
{
    protected string $key = 'iban';

    public function normalize( FormField $field, mixed $value ): mixed
    {
        return is_string( $value ) ? strtoupper( str_replace( ' ', '', $value ) ) : $value;
    }

    protected function defineType(): array
    {
        return [
            'label'                  => __( 'IBAN' ),
            'icon'                   => 'o-banknotes',
            'category'               => 'advanced',
            'has_options'            => false,
            'supports_placeholder'   => true,
            'supports_default_value' => false,
            'validation_options'     => [],
            'defaults'               => ['label' => __( 'IBAN' )],
            'settings'               => [
                [
                    'key'   => 'show_country',
                    'input' => 'toggle',
                    'label' => __( 'Show the country' ),
                ],
            ],
        ];
    }

    protected function typeRules( FormField $field ): array
    {
        // Spaces are allowed here; normalize() removes them before the value is stored
        return ['string', 'regex:/^[A-Z]{2}\d{2}[A-Z0-9 ]{11,40}$/i'];
    }

    protected function typeMessages( FormField $field ): array
    {
        return ['regex' => __( 'Please enter a valid IBAN for :field.', ['field' => $field->label] )];
    }

    protected function displayScalar( FormSubmissionValue $value ): string
    {
        return trim( chunk_split( (string) $value->value, 4, ' ' ) );
    }
}
```

```php
// In a service provider's boot()
use App\Forms\IbanFieldType;
use function addFilter;

addFilter( 'ap.forms.fieldTypes', function ( array $types ): array {
    $types['iban'] = IbanFieldType::class;

    return $types;
} );

// Put the type in the builder palette
addFilter( 'ap.forms.fieldCategories', function ( array $categories ): array {
    $categories['advanced']['fields'][] = 'iban';

    return $categories;
} );
```

A class name is resolved from the container with the type name as its `key` argument, so a handler can take constructor dependencies. A type only shows in the builder palette once a category lists it (see [Customization](Advanced-Customization#make-a-custom-field-a-first-class-builder-citizen)).

You can still register an array definition instead of a handler. It is wrapped in `ArtisanPackUI\Forms\FieldTypes\ArrayFieldType`. A `type_validation` entry (a rule or a list of rules) replaces the default `string` rule, and `validation_options` still give it builder controls. See [Customization](Advanced-Customization#custom-field-types).

An entry that is neither an array, a `FieldType` class name nor a `FieldType` instance is ignored, and a warning is logged.

## The Contract

`ArtisanPackUI\Forms\Contracts\FieldType` has these methods. `AbstractFieldType` implements all of them, so override only what differs.

| Method | Default in `AbstractFieldType` |
|--------|--------------------------------|
| `definition(): array` | `defineType()` merged with the base definition. The common settings are added for you (see below). |
| `rules( FormField $field ): array` | Keyed by input: `''` for the field's own value, or a sub-key such as `street` for a nested input. Starts with `required` or `nullable` (`presenceRule()`), then `typeRules()`, the min, max, pattern and date or time limits from `validation_rules`, and the rules for the No duplicates, "Other" choice, input mask and word limit settings. Layout types get none. |
| `messages( FormField $field ): array` | Keyed by `{rule}` (or `{sub-key}.{rule}`). Messages for the required, No duplicates, "Other", input mask and word limit rules, plus `typeMessages()`. Each field's **Custom Error Message** and **Required Message** are applied on top. |
| `normalize( FormField $field, mixed $value ): mixed` | Returns the value unchanged. Runs after validation, just before the value is stored. |
| `readOnlyValue( FormField $field ): mixed` | The field's resolved default value. The `ap.forms.readOnlyValue` filter runs after it. |
| `display( FormSubmissionValue $value ): string` | Option labels for an array value, the file name for an upload, otherwise `displayScalar()`. |
| `exportColumns( FormField $field ): array` | One column, headed with the field's admin label. |
| `exportValues( FormField $field, ?FormSubmissionValue $value ): array` | One cell, the display value. |
| `mergeTags( FormField $field ): array` | `{name}` with the field label as its description. |
| `view(): string` | `forms::components.fields.{type}`. |

`AbstractFieldType` also has `displayHtml( FormSubmissionValue $value ): ?string` for types whose display is HTML (rich text, a color swatch). It returns `null` for plain text, which is escaped as usual. Return only sanitized HTML.

The protected methods a subclass usually overrides are `defineType()` (required), `typeRules()`, `typeMessages()`, `presenceRule()` (what "required" means, such as `accepted` for a checkbox) and `displayScalar()`.

## The Definition

`defineType()` returns the type's definition. The builders and renderers read these entries:

| Entry | Description |
|-------|-------------|
| `label`, `icon`, `category` | The palette label, icon (a Heroicon name such as `o-banknotes`) and category key. |
| `has_options` | Whether the field has an options list (select, radio and so on). |
| `supports_placeholder`, `supports_default_value` | Whether the builder offers these inputs. |
| `is_layout` | Whether the type is display-only. Layout types get no validation rules, messages, input settings or stored values. |
| `validation_options` | The legacy validation controls to offer: `min`, `max`, `pattern`, `step`, `max_size`, `allowed_types`, `min_date`, `max_date`. A setting in `settings` with the same key wins. |
| `defaults` | Attributes a new field of this type starts with, such as `label` or `field_config`. |
| `settings` | The type's own settings schema (see below). |
| `sub_inputs` | The inputs of a composite type (see below). |
| `supports_autocomplete`, `autocomplete` | Offer the **Autocomplete** setting, with an optional default token. |
| `supports_label_placement` | `false` to leave out **Label Placement** and **Description Placement**. |
| `supports_dynamic_population` | `false` to leave out dynamic population. |
| `supports_visibility` | `false` to leave out **Visibility** (administrative fields). |
| `supports_privacy` | `false` to leave out **Do not store**, **Mask in admin** and **Redact after (days)**. |
| `supports_conditional_logic` | `true` lets a layout type take conditional logic, as Section does. Input types always can. |

### Common Settings

Every input type gets these settings unless its definition turns them off or declares a setting with the same key:

- **General** tab: **Label Placement** and **Description Placement**.
- **Validation** tab: **Custom Error Message** and **Required Message**.
- **Advanced** tab: **Autocomplete** (only with `supports_autocomplete`), **Admin Label**, dynamic population, **Visibility** and the privacy settings.

### Settings Schema

Each entry in `settings` describes one builder control. The Livewire, React and Vue builders all render a type's settings from its schema, so a custom type gets builder controls without any frontend code. `ArtisanPackUI\Forms\Support\FieldSettings` reads, parses and stores the values, and `resources/js/shared/fieldSettings.ts` mirrors it.

| Key | Description |
|-----|-------------|
| `key` | The storage key. Required. |
| `label`, `hint`, `placeholder` | Builder copy. Wrap it in `__()`. |
| `input` | `text` (default), `textarea`, `integer`, `number`, `select`, `toggle`, `date`, `time`, `list` (comma-separated values, stored as an array), `date_expression` (a `Y-m-d` date or a relative date such as `+30 days`) or `formula` (a calculation formula). |
| `store` | `field_config` (default) or `validation_rules`. |
| `legacy_store` | A column an older copy of the value may still be in. It is read first and cleared when the setting is saved. |
| `tab` | `general` (default), `validation` or `advanced`. |
| `default` | The value the renderer falls back to when the setting is unset. |
| `min`, `max` | Bounds for `integer` and `number` inputs. |
| `max_length` | The longest `text` or `textarea` value. |
| `step` | The `step` attribute of a `number` input. |
| `options` | For `select`: a list of `['value' => …, 'label' => …]`. |
| `options_from` | Appends options when the schema is read: `choice_sources` (registered choice sources), `captcha_drivers` or `phone_countries`. |
| `monospace` | Shows the input in a monospace font. |

Read a setting in your handler with `$field->getConfig( 'show_country' )` (or `$field->getValidationRule( $key )` for `validation_rules`). The API returns a field's schema as `settings_schema`; the public render payload leaves it out.

## Changing a Built-in Type

The `ap.forms.fieldTypes` filter receives every type's definition as an array. If you edit a built-in type's array, the edit is applied to its handler with `withDefinition()`, so the built-in behaviour stays:

```php
addFilter( 'ap.forms.fieldTypes', function ( array $types ): array {
    $types['rating']['label'] = __( 'Stars' );

    return $types;
} );
```

To change a built-in type's behaviour, register a subclass of its handler under the same key:

```php
use ArtisanPackUI\Forms\FieldTypes\TextFieldType;
use ArtisanPackUI\Forms\Models\FormField;

class TrimmedTextFieldType extends TextFieldType
{
    public function normalize( FormField $field, mixed $value ): mixed
    {
        return is_string( $value ) ? trim( $value ) : $value;
    }
}

addFilter( 'ap.forms.fieldTypes', function ( array $types ): array {
    $types['text'] = TrimmedTextFieldType::class;

    return $types;
} );
```

## Composite Types

A composite type collects several inputs that are stored together, like the built-in Name, Address and Date Range fields. Extend `ArtisanPackUI\Forms\FieldTypes\CompositeFieldType` and list the inputs in the definition's `sub_inputs`:

| Key | Description |
|-----|-------------|
| `key` | The storage key (letters, digits and underscores). |
| `label`, `placeholder` | The default sub-label and placeholder. |
| `enabled` | Whether the input is shown by default (default `true`). |
| `required` | Whether a required field requires this input. When no enabled input is marked, a required field requires them all. |
| `autocomplete` | The input's `autocomplete` token. |
| `type` | `text` (default), `email`, `tel`, `url`, `number`, `select` or `date`. |
| `wide` | Whether the input spans the whole row. |
| `options` | A `select` input's choices; the value must be one of them. |
| `default`, `parameter` | The starting value, and the population parameter (see [Dynamic Population](Usage-Dynamic-Population#composite-fields)). |
| `rules` | Extra validation rules for the input. |

Admins can show, relabel and require each input per field. The value is stored as a map in `value_array`, shown as the filled inputs joined by the definition's `format_separator` (default `, `), exported as one column per input or one formatted column, and offers a `{field:key}` merge tag per input. Override `subInputDefinitions( FormField $field )` when the inputs depend on the field's settings.

## Rendering

The Livewire renderer renders `view()`, which defaults to `forms::components.fields.{type}`. Ship that view, override `view()` to return your own, or supply the HTML through the `ap.forms.fieldRender` filter. When the view does not exist, a warning is logged and the filter receives an empty string. The view gets `$field`, `$value`, `$error` and `$subErrors`.

The React and Vue renderers need a component for the type. See [React Components](Frontend-React#custom-field-types) and [Vue Components](Frontend-Vue).

## Next Steps

- [Customization](Advanced-Customization) - Hooks, views and policies
- [Form Builder](Usage-Form-Builder) - The built-in field types and their settings
