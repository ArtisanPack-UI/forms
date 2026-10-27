---
title: FormBuilder Component
---

# FormBuilder Component

The FormBuilder component provides a drag-and-drop interface for creating and editing forms.

## Basic Usage

```blade
<livewire:form-builder :form="$form" />
```

The builder edits an existing form. New forms are created at `/admin/forms/create` (or programmatically), then opened in the builder.

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `form` | Form | required | Form to edit (`mount(Form $form)`) |

## Component Structure

The FormBuilder interface includes:

1. **Form Settings** - Name, slug, description, success message, redirect URL and status live in the **Settings** tab of the edit page, next to the builder (see [Form Settings](Usage-Form-Builder#form-settings))
2. **Field Palette** - Draggable field types
3. **Form Canvas** - Visual field arrangement
4. **Field Editor** - Configure selected field
5. **Steps Manager** - Multi-step form configuration

## Adding Fields

### Via Drag and Drop

1. Drag a field type from the palette
2. Drop onto the form canvas
3. Configure in the field editor

### Programmatically

```php
// In the component
public function addField(string $type): void
{
    $this->fields[] = [
        'type' => $type,
        'name' => $this->generateFieldName($type),
        'label' => $this->generateFieldLabel($type),
        'required' => false,
        'order' => count($this->fields) + 1,
    ];
}
```

## Field Configuration

When a field is selected, the editor shows:

- **Basic Settings**: Label, name, placeholder, help text
- **Validation**: Required, min/max length, pattern
- **Options**: For select, radio, checkbox fields
- **Conditions**: Conditional visibility rules
- **Advanced**: CSS classes, custom attributes

## Events Emitted

| Event | Payload | Description |
|-------|---------|-------------|
| `form-saved` | none | Form was saved (followed by `save-complete`) |
| `field-added` | `{ uuid }` | Field was added |
| `field-deleted` | `{ uuid }` | Field was removed |
| `field-duplicated` | `{ uuid }` | Field was duplicated (UUID of the copy) |
| `forms-options-updated` | `{ field, options }` | A choice field's options changed |
| `conditional-logic-updated` / `conditional-logic-cleared` | none | A field's conditional logic was saved or removed |
| `conditional-logic-error` | `{ errors }` | Conditional logic failed validation |
| `step-added` / `step-deleted` | `{ stepId }` | A step was added or removed |
| `multi-step-enabled` / `multi-step-disabled` | none | Multi-step mode was turned on or off |

### Listening to Events

```javascript
document.addEventListener('livewire:init', () => {
    Livewire.on('form-saved', () => {
        console.log('Form saved');
    });
});
```

## Customizing the View

Publish and edit the view:

```bash
php artisan vendor:publish --tag=forms-views
```

Edit `resources/views/vendor/forms/livewire/form-builder.blade.php`.

### View Sections

```blade
{{-- Form Settings --}}
<div class="form-settings">
    <input wire:model="form.name" />
    <input wire:model="form.slug" />
    {{-- ... --}}
</div>

{{-- Field Palette --}}
<div class="field-palette">
    @foreach ($fieldTypes as $type)
        <button wire:click="addField('{{ $type }}')">
            {{ $type }}
        </button>
    @endforeach
</div>

{{-- Form Canvas --}}
<div class="form-canvas" wire:sortable="updateFieldOrder">
    @foreach ($fields as $index => $field)
        <div wire:sortable.item="{{ $field['id'] }}" wire:key="field-{{ $index }}">
            {{-- Field preview --}}
        </div>
    @endforeach
</div>

{{-- Field Editor --}}
@if ($selectedField)
    <div class="field-editor">
        {{-- Field configuration form --}}
    </div>
@endif
```

## Multi-Step Forms

Enable multi-step mode:

```php
public bool $isMultiStep = true;
public array $steps = [];

public function addStep(): void
{
    $this->steps[] = [
        'title' => 'New Step',
        'description' => '',
        'order' => count($this->steps) + 1,
    ];
}
```

Assign fields to steps:

```blade
<select wire:model="fields.{{ $index }}.step_id">
    <option value="">No Step</option>
    @foreach ($steps as $step)
        <option value="{{ $step['id'] }}">{{ $step['title'] }}</option>
    @endforeach
</select>
```

## Validation

The component validates form data before saving:

```php
protected function rules(): array
{
    return [
        'form.name' => 'required|string|max:255',
        'form.slug' => 'required|string|max:255|unique:forms,slug,' . $this->form?->id,
        'fields.*.name' => 'required|string|max:255',
        'fields.*.label' => 'required|string|max:255',
    ];
}
```

## Saving Forms

```php
public function save(): void
{
    $this->validate();

    $form = $this->form ?? new Form();
    $form->fill($this->formData);
    $form->save();

    // Save fields
    $this->saveFields($form);

    // Save steps
    if ($this->isMultiStep) {
        $this->saveSteps($form);
    }

    $this->dispatch('form-saved', formId: $form->id);
}
```

## Extending the Component

Create a custom builder:

```php
namespace App\Livewire;

use ArtisanPackUI\Forms\Livewire\FormBuilder;

class CustomFormBuilder extends FormBuilder
{
    // Add custom field types
    protected function getFieldTypes(): array
    {
        $types = parent::getFieldTypes();
        $types[] = 'my-custom-field';
        return $types;
    }

    // Add custom validation
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['form.department'] = 'required|string';
        return $rules;
    }
}
```

## Next Steps

- [FormRenderer](Form-Renderer) - Display forms
- [Form Builder Usage](Usage-Form-Builder) - Form builder guide
- [Multi-Step Forms](Usage-Multi-Step-Forms) - Multi-step configuration
