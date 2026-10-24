{{--
    Wraps a text or textarea input (the slot) with a live character or word
    counter when the field's "Show character counter" setting is on (since
    1.6.0). Mirrors `resources/js/shared/counter.ts`.

    The visible counter (`counter-{name}`, referenced by the input's
    `aria-describedby`) updates as the visitor types. A separate polite live
    region repeats it once typing pauses for a second, so screen readers are
    not interrupted on every keystroke. Words are runs of characters between
    whitespace, as the `forms_max_words` rule counts them.
--}}
@if($field->showsCounter())
    @php
        $maxWords = $field->getMaxWords();
        $limit = $maxWords ?? $field->getLengthLimit('max');
        $counterTemplate = match (true) {
            null !== $maxWords => __( ':count of :max words' ),
            null !== $limit => __( ':count of :max characters' ),
            default => __( ':count characters' ),
        };
    @endphp
    <div
        x-data="{
            count: 0,
            announcement: '',
            timer: null,
            limit: @js($limit),
            words: @js(null !== $maxWords),
            template: @js($counterTemplate),
            measure(text) {
                if (! this.words) {
                    return [...text].length;
                }

                const trimmed = text.trim();

                return trimmed === '' ? 0 : trimmed.split(/\s+/u).length;
            },
            get text() {
                return this.template.replace(':count', this.count).replace(':max', this.limit ?? '');
            },
            update(text) {
                this.count = this.measure(text);
                clearTimeout(this.timer);
                this.timer = setTimeout(() => { this.announcement = this.text; }, 1000);
            },
        }"
        x-init="count = measure($el.querySelector('input, textarea')?.value ?? '')"
        x-on:input="update($event.target.value)"
    >
        {{ $slot }}

        <p
            class="label-text-alt text-base-content/70 mt-1 text-right"
            id="counter-{{ $field->name }}"
            x-text="text"
            x-bind:class="{ 'text-error': limit !== null && count > limit }"
        >{{ str_replace([':count', ':max'], ['0', (string) $limit], $counterTemplate) }}</p>
        <span class="sr-only" aria-live="polite" aria-atomic="true" x-text="announcement"></span>
    </div>
@else
    {{ $slot }}
@endif
