{{--
    Admin-authored HTML, sanitized with htmLawed's safe mode (scripts, event
    handlers and javascript: URLs removed), or escaped when
    artisanpack-ui/security is not installed.

    Since 1.6.0 a `{field_name}` (or `{field_name:key}`) merge tag in the
    block's text shows what the visitor entered, as they type (see
    LiveMergeTags): each tag becomes a placeholder that Alpine fills in, as
    text, from `$wire.formData`. A field hidden by conditional logic shows
    nothing. The block is `wire:ignore`d so re-renders keep the filled-in
    values.
--}}
@props(['field'])

@php
    $formFields = $field->getFormFields();
    $sanitizedHtml = \ArtisanPackUI\Forms\Support\LiveMergeTags::placeholders(
        \ArtisanPackUI\Forms\Models\FormField::sanitizeHtml($field->getLayoutContent()),
        $formFields,
    );
    $hasMergeTags = str_contains($sanitizedHtml, 'data-forms-merge-tag');
@endphp

<div
    class="html-content prose prose-sm max-w-none {{ $field->css_classes }}"
    id="html-{{ $field->name }}"
    @if($hasMergeTags)
        wire:ignore
        x-data="{
            formats: @js(\ArtisanPackUI\Forms\Support\LiveMergeTags::formats($formFields)),
            labels: @js(['yes' => __( 'Yes' )]),
            init() {
                this.fill();
                this.$wire.watch('formData', () => this.fill());
                this.$wire.watch('hiddenFields', () => this.fill());
                this.$wire.watch('otherValues', () => this.fill());
            },
            fill() {
                const data = this.$wire.formData ?? {};
                const hidden = this.$wire.hiddenFields ?? {};

                this.$el.querySelectorAll('[data-forms-merge-tag]').forEach((tag) => {
                    const name = tag.dataset.formsMergeTag;
                    const part = tag.dataset.formsMergePart;

                    tag.textContent = hidden[name] ? '' : this.format(name, data[name], part);
                });
            },
            text(name, value) {
                if (value === null || value === undefined || value === false) return '';
                if (value === true) return this.labels.yes;

                const text = String(value).trim();

                if (text === @js(\ArtisanPackUI\Forms\Models\FormField::OTHER_CHOICE_VALUE)) {
                    return String(this.$wire.otherValues?.[name] ?? '').trim();
                }

                const options = this.formats[name]?.options ?? {};

                return Object.prototype.hasOwnProperty.call(options, text) ? options[text] : text;
            },
            format(name, value, part) {
                if (Array.isArray(value)) {
                    const rows = value.some((item) => item && typeof item === 'object');

                    return value
                        .map((item) => (item && typeof item === 'object') ? this.format(name, item) : this.text(name, item))
                        .filter((item) => item !== '')
                        .join(rows ? '; ' : ', ');
                }

                if (value && typeof value === 'object') {
                    if (part !== undefined) return this.text(name, value[part]);

                    return Object.values(value)
                        .map((item) => this.text(name, item))
                        .filter((item) => item !== '')
                        .join(this.formats[name]?.separator ?? ', ');
                }

                return part === undefined ? this.text(name, value) : '';
            },
        }"
    @endif
>
    {!! $sanitizedHtml !!}
</div>
