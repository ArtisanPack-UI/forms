{{--
    One field of the renderer, shown or hidden by conditional logic.

    Variables:
    - $field The FormField.

    The parent view's $formData, $hiddenFields and $errors are in scope.
--}}
@php
    $isHidden = $hiddenFields[$field->name] ?? false;
@endphp
<div
    wire:key="field-{{ $field->uuid }}-{{ $isHidden ? 'hidden' : 'visible' }}"
    x-data="{ get visible() { return !($wire.hiddenFields?.['{{ $field->name }}'] ?? false); } }"
    x-show="visible"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 transform -translate-y-2"
    x-transition:enter-end="opacity-100 transform translate-y-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 transform translate-y-0"
    x-transition:leave-end="opacity-0 transform -translate-y-2"
    x-cloak
    :class="{ 'hidden': !visible }"
    :aria-hidden="!visible"
    :inert="!visible"
>
    {!! \ArtisanPackUI\Forms\Support\FieldRenderer::render(
        $field,
        $formData[$field->name] ?? null,
        \ArtisanPackUI\Forms\Support\FieldRenderer::fieldError($field, $errors),
        \ArtisanPackUI\Forms\Support\FieldRenderer::subInputErrors($field, $errors),
    ) !!}
</div>
