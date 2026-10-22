{{--
    A searchable select: an editable combobox with a listbox popup (WAI-ARIA 1.2).

    Typing filters the options by label and a polite status announces how many
    match. Arrow keys move the active option (`aria-activedescendant`), Enter
    chooses it, Escape closes the list and Tab leaves. A multi-select keeps the
    list open, toggles options and shows each choice as a removable button;
    Backspace in an empty input removes the last one. The value is entangled
    with `formData.{name}`. The React and Vue renderers render the same
    pattern.

    Variables:
    - $field    The FormField. Its input gets the `field-{name}` ID the layout's label points at.
    - $error    The validation error, or null.
    - $options  The options, in the order to list them.
    - $multiple Whether any number of options may be chosen.
--}}
@php
    $inputId = 'field-' . $field->name;
    $choices = collect($options)
        ->map(fn ($option) => [
            'value' => (string) ($option['value'] ?? $option),
            'label' => (string) ($option['label'] ?? $option['value'] ?? $option),
        ])
        ->values()
        ->all();
    $readOnly = $field->isReadOnly();
@endphp

<div
    class="relative {{ $field->css_classes }}"
    x-data="{
        options: @js($choices),
        multiple: @js($multiple),
        readOnly: @js($readOnly),
        value: $wire.entangle(@js('formData.' . $field->name)).live,
        query: '',
        typed: false,
        open: false,
        active: -1,
        status: '',
        messages: {
            none: @js(__( 'No results found.' )),
            one: @js(__( '1 result available.' )),
            many: @js(__( ':count results available.' )),
            remove: @js(__( 'Remove :label' )),
        },
        init() {
            this.syncQuery();
            this.$watch('value', () => this.syncQuery());
        },
        get selected() {
            if (this.multiple) {
                return Array.isArray(this.value) ? this.value.map(String) : [];
            }

            return this.value === null || this.value === undefined || this.value === '' ? [] : [String(this.value)];
        },
        get filtered() {
            const query = this.query.trim().toLowerCase();

            if (! this.typed || query === '') {
                return this.options;
            }

            return this.options.filter(option => option.label.toLowerCase().includes(query));
        },
        labelFor(value) {
            const option = this.options.find(option => option.value === value);

            return option ? option.label : value;
        },
        optionId(option) {
            return @js($inputId . '-option-') + this.options.indexOf(option);
        },
        activeId() {
            const option = this.open && this.active >= 0 ? this.filtered[this.active] : null;

            return option ? this.optionId(option) : null;
        },
        syncQuery() {
            if (! this.multiple && ! this.open) {
                this.query = this.selected.length ? this.labelFor(this.selected[0]) : '';
            }
        },
        announce() {
            const count = this.filtered.length;

            this.status = count === 0 ? this.messages.none : (count === 1 ? this.messages.one : this.messages.many.replace(':count', count));
        },
        show() {
            if (this.readOnly || this.open) {
                return;
            }

            this.open = true;
            this.typed = false;
            this.active = this.multiple ? 0 : Math.max(0, this.options.findIndex(option => this.selected.includes(option.value)));
            this.announce();
        },
        close() {
            this.open = false;
            this.active = -1;
            this.typed = false;
            this.status = '';

            if (this.multiple) {
                this.query = '';
            }

            this.syncQuery();
        },
        search() {
            if (this.readOnly) {
                return;
            }

            this.open = true;
            this.typed = true;
            this.active = this.filtered.length ? 0 : -1;
            this.announce();
        },
        move(step) {
            if (! this.open) {
                this.show();

                return;
            }

            const count = this.filtered.length;

            if (count === 0) {
                return;
            }

            this.active = (this.active + step + count) % count;
            this.reveal();
        },
        moveTo(index) {
            if (this.open && this.filtered.length) {
                this.active = index < 0 ? this.filtered.length - 1 : 0;
                this.reveal();
            }
        },
        reveal() {
            this.$nextTick(() => document.getElementById(this.activeId())?.scrollIntoView({ block: 'nearest' }));
        },
        choose(option) {
            if (! option || this.readOnly) {
                return;
            }

            if (this.multiple) {
                this.value = this.selected.includes(option.value)
                    ? this.selected.filter(value => value !== option.value)
                    : [...this.selected, option.value];

                return;
            }

            this.value = option.value;
            this.close();
        },
        remove(value) {
            if (! this.readOnly) {
                this.value = this.selected.filter(item => item !== value);
                this.$refs.input.focus();
            }
        },
        onEnter(event) {
            if (this.open && this.active >= 0) {
                event.preventDefault();
                this.choose(this.filtered[this.active]);
            }
        },
        onEscape(event) {
            if (this.open) {
                event.preventDefault();
                event.stopPropagation();
                this.close();
            }
        },
        onBackspace() {
            if (this.multiple && this.query === '' && this.selected.length) {
                this.remove(this.selected[this.selected.length - 1]);
            }
        },
    }"
    x-on:click.outside="open && close()"
>
    @if($multiple)
        <ul class="mb-2 flex flex-wrap gap-2" x-show="selected.length" aria-label="{{ __( 'Selected options' ) }}">
            <template x-for="value in selected" :key="value">
                <li class="badge badge-primary badge-outline gap-1 py-3">
                    <span x-text="labelFor(value)"></span>
                    <button
                        type="button"
                        class="btn btn-ghost btn-xs btn-circle"
                        :aria-label="messages.remove.replace(':label', labelFor(value))"
                        :disabled="readOnly"
                        x-on:click="remove(value)"
                    >
                        <span aria-hidden="true">&times;</span>
                    </button>
                </li>
            </template>
        </ul>
    @endif

    <div class="relative">
        <input
            type="text"
            id="{{ $inputId }}"
            x-ref="input"
            role="combobox"
            aria-autocomplete="list"
            aria-controls="{{ $inputId }}-listbox"
            :aria-expanded="open ? 'true' : 'false'"
            :aria-activedescendant="activeId()"
            autocomplete="off"
            class="input input-bordered w-full pr-10 @if($error) input-error @endif"
            placeholder="{{ $field->placeholder ?? ( $multiple ? __( 'Search options...' ) : __( 'Search...' ) ) }}"
            x-model="query"
            x-on:input="search()"
            x-on:click="show()"
            x-on:keydown.down.prevent="move(1)"
            x-on:keydown.up.prevent="move(-1)"
            x-on:keydown.home="moveTo(0)"
            x-on:keydown.end="moveTo(-1)"
            x-on:keydown.enter="onEnter($event)"
            x-on:keydown.escape="onEscape($event)"
            x-on:keydown.backspace="onBackspace()"
            x-on:keydown.tab="close()"
            @if($field->is_required) aria-required="true" @endif
            @if($error) aria-invalid="true" @endif
            @if($field->getDescribedBy($error)) aria-describedby="{{ $field->getDescribedBy($error) }}" @endif
            @if($readOnly) readonly aria-readonly="true" @endif
        />
        <button
            type="button"
            tabindex="-1"
            class="btn btn-ghost btn-sm btn-square absolute right-1 top-1/2 -translate-y-1/2"
            aria-label="{{ __( 'Show options' ) }}"
            :aria-expanded="open ? 'true' : 'false'"
            aria-controls="{{ $inputId }}-listbox"
            @disabled($readOnly)
            x-on:click="open ? close() : (show(), $refs.input.focus())"
        >
            <x-artisanpack-icon name="o-chevron-down" class="h-4 w-4" />
        </button>
    </div>

    <ul
        id="{{ $inputId }}-listbox"
        role="listbox"
        @if($multiple) aria-multiselectable="true" @endif
        @if(filled($field->label)) aria-label="{{ $field->label }}" @endif
        class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-box border border-base-300 bg-base-100 p-1 shadow-lg"
        x-show="open && filtered.length"
        x-cloak
    >
        <template x-for="(option, index) in filtered" :key="option.value">
            <li
                :id="optionId(option)"
                role="option"
                :aria-selected="selected.includes(option.value) ? 'true' : 'false'"
                class="flex cursor-pointer items-center gap-2 rounded-field px-3 py-2"
                :class="{ 'bg-base-200': index === active }"
                x-on:mousedown.prevent="choose(option)"
                x-on:mouseenter="active = index"
            >
                <span class="w-4 shrink-0" aria-hidden="true" x-text="selected.includes(option.value) ? '✓' : ''"></span>
                <span x-text="option.label"></span>
            </li>
        </template>
    </ul>

    <div
        class="absolute z-20 mt-1 w-full rounded-box border border-base-300 bg-base-100 px-3 py-2 text-sm text-base-content/60 shadow-lg"
        x-show="open && ! filtered.length"
        x-cloak
    >{{ __( 'No results found.' ) }}</div>

    <div class="sr-only" role="status" aria-live="polite" aria-atomic="true" x-text="status"></div>
</div>
