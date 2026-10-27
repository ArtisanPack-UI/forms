{{--
    A section: a `<fieldset>` whose `<legend>` is the section's label, with
    its description and the fields it groups (since 1.6.0; see
    Support\Sections).

    Conditional logic that hides the section hides its fields (each through
    `hiddenFields`), and the fieldset with them. A CAPTCHA is never hidden,
    so a fieldset holding one stays shown without its legend, and open.

    A collapsible section's legend is a disclosure button (`aria-expanded`,
    `aria-controls`) that shows and hides the fields. It opens whenever one
    of its fields has an error: the marker below changes its `wire:key` with
    the error state, so Livewire replaces it and its `x-init` runs again.

    Variables:
    - $field         The section's FormField.
    - $members       The fields the section groups, in order.
    - $renderMembers Whether to render the members (false when the section is
                     rendered on its own, through FieldRenderer::render()).

    The parent view's $formData, $hiddenFields and $errors are in scope when
    the members are rendered.
--}}
@props(['field', 'members' => [], 'renderMembers' => false])

@php
    $members = $renderMembers ? $members : [];
    $collapsible = $field->isCollapsibleSection();
    $description = $field->getSectionDescription();
    $contentId = $field->domId('section') . '-content';
    $descriptionId = $field->domId('section') . '-description';
    $holdsCaptcha = collect($members)->contains(fn ($member) => 'captcha' === $member->type);
    $hasErrors = $renderMembers && collect($members)->contains(fn ($member) => \ArtisanPackUI\Forms\Support\FieldRenderer::hasErrors($member, $errors ?? new \Illuminate\Support\ViewErrorBag()));
@endphp

<fieldset
    id="{{ $field->domId('section') }}"
    wire:key="section-{{ $field->uuid }}"
    class="fieldset w-full rounded-box border border-base-300 p-4 {{ $field->css_classes }}"
    data-forms-section="{{ $field->name }}"
    x-data="{
        open: @js(! $field->startsCollapsed() || $hasErrors),
        get visible() { return !($wire.hiddenFields?.[@js($field->name)] ?? false); },
    }"
    @unless($holdsCaptcha) x-show="visible" x-cloak @endunless
    @if($description) aria-describedby="{{ $descriptionId }}" @endif
>
    <legend class="fieldset-legend text-lg font-semibold" x-show="visible">
        @if($collapsible)
            <button
                type="button"
                class="flex items-center gap-2 rounded text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                aria-controls="{{ $contentId }}"
                x-bind:aria-expanded="open ? 'true' : 'false'"
                x-on:click="open = !open"
            >
                <x-artisanpack-icon name="o-chevron-right" class="h-4 w-4 transition-transform" x-bind:class="open && 'rotate-90'" aria-hidden="true" />
                <span>{{ $field->label }}</span>
            </button>
        @else
            {{ $field->label }}
        @endif
    </legend>

    @if($description)
        <p id="{{ $descriptionId }}" class="text-sm text-base-content/70" x-show="visible">{!! nl2br(e($description)) !!}</p>
    @endif

    @if($collapsible)
        <span hidden wire:key="section-{{ $field->uuid }}-{{ $hasErrors ? 'invalid' : 'valid' }}" x-init="@js($hasErrors) && (open = true)"></span>
    @endif

    <div
        id="{{ $contentId }}"
        class="mt-2 space-y-4"
        @if($collapsible) x-show="{{ $holdsCaptcha ? 'open || !visible' : 'open' }}" @endif
    >
        @foreach($members as $member)
            @include('forms::livewire.form-renderer.field', ['field' => $member])
        @endforeach
    </div>
</fieldset>
