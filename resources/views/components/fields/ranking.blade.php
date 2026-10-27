{{--
    A ranking field (since 1.6.0): the field's choices as a numbered list
    that visitors put in order of preference, in a `<fieldset>` whose
    `<legend>` is the field label (see RankingFieldType).

    Each choice has "Move up" and "Move down" buttons for keyboard and screen
    reader users, and can be dragged with a mouse. Both call
    FormRenderer::moveRankingItem(); afterwards focus stays on the moved
    choice's button and a polite live region announces its new position
    ("Price moved to position 2 of 3.").

    Variables:
    - $field The FormField.
    - $value The option values, most preferred first.
    - $error The validation error, or null.
--}}
@props(['field', 'value' => [], 'error' => null])

@php
    $rankingType = \ArtisanPackUI\Forms\FieldTypes\RankingFieldType::class;
    $labels = array_column($rankingType::choices($field), 'label', 'value');
    $order = $rankingType::order($field, $value);
    $total = count($order);
    $readOnly = $field->isReadOnly();
    $descriptionPlacement = $field->getDescriptionPlacement();
    $idPrefix = $field->domId() . '-';
    $describedBy = trim(implode(' ', array_filter([
        $readOnly ? null : $idPrefix . 'instructions',
        filled($field->help_text) ? $field->domId('hint') : null,
        filled($error) ? $field->domId('error') : null,
    ])));
@endphp

<fieldset
    class="fieldset {{ $field->css_classes }}"
    id="{{ $field->domId() }}"
    data-forms-ranking="{{ $field->name }}"
    @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
    x-data="{
        announcement: '',
        dragging: null,
        direction: 'up',
        labels: @js($labels),
        total: @js($total),
        message: @js(__( ':choice moved to position :position of :total.' )),
        move(from, to) {
            if (from === to || to < 0 || to >= this.total) {
                return;
            }

            this.direction = to < from ? 'up' : 'down';
            this.$wire.moveRankingItem(@js($field->name), from, to);
        },
        drop(to) {
            if (null !== this.dragging) {
                this.move(this.dragging, to);
            }

            this.dragging = null;
        },
        moved(detail) {
            this.announcement = this.message
                .replace(':choice', this.labels[detail.value] ?? detail.value)
                .replace(':position', detail.position + 1)
                .replace(':total', this.total);

            this.$nextTick(() => {
                const other = 'up' === this.direction ? 'down' : 'up';
                let button = document.getElementById(@js($idPrefix) + detail.position + '-' + this.direction);

                if (! button || button.disabled) {
                    button = document.getElementById(@js($idPrefix) + detail.position + '-' + other);
                }

                button?.focus();
            });
        },
    }"
    x-on:forms-ranking-moved.window="if ($event.detail.field === @js($field->name)) { moved($event.detail); }"
>
    @if(filled($field->label))
        <legend @class(['fieldset-legend', 'sr-only' => 'hidden' === $field->getLabelPlacement()])>
            {{ $field->label }}
            @if($field->is_required)
                <span class="text-error" aria-hidden="true">*</span>
            @endif
        </legend>
    @endif

    @if(filled($field->help_text) && 'above' === $descriptionPlacement)
        <p class="fieldset-label" id="{{ $field->domId('hint') }}">{{ $field->help_text }}</p>
    @endif

    @unless($readOnly)
        <p class="fieldset-label" id="{{ $idPrefix }}instructions">{{ __( 'Put the choices in order, most preferred first: drag them, or use the Move up and Move down buttons.' ) }}</p>
    @endunless

    <ol class="flex flex-col gap-2">
        @foreach($order as $index => $choice)
            @php
                $label = $labels[$choice] ?? $choice;
            @endphp
            <li
                wire:key="ranking-{{ $field->uuid }}-{{ $choice }}"
                class="flex items-center gap-3 rounded-box border border-base-300 bg-base-100 p-2"
                x-bind:class="{ 'opacity-50': dragging === {{ $index }} }"
                @unless($readOnly)
                    draggable="true"
                    x-on:dragstart="dragging = {{ $index }}; $event.dataTransfer.effectAllowed = 'move'"
                    x-on:dragend="dragging = null"
                    x-on:dragover.prevent="$event.dataTransfer.dropEffect = 'move'"
                    x-on:drop.prevent="drop({{ $index }})"
                @endunless
            >
                @unless($readOnly)
                    <x-artisanpack-icon name="o-bars-3" class="h-4 w-4 shrink-0 cursor-grab text-base-content/50" aria-hidden="true" />
                @endunless
                <span class="badge badge-neutral tabular-nums" aria-hidden="true">{{ $index + 1 }}</span>
                <span class="flex-1">{{ $label }}</span>
                @unless($readOnly)
                    <button
                        type="button"
                        id="{{ $idPrefix }}{{ $index }}-up"
                        class="btn btn-ghost btn-sm btn-square"
                        x-on:click="move({{ $index }}, {{ $index - 1 }})"
                        aria-label="{{ __( 'Move :choice up', ['choice' => $label] ) }}"
                        @disabled(0 === $index)
                    >
                        <x-artisanpack-icon name="o-chevron-up" class="h-4 w-4" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        id="{{ $idPrefix }}{{ $index }}-down"
                        class="btn btn-ghost btn-sm btn-square"
                        x-on:click="move({{ $index }}, {{ $index + 1 }})"
                        aria-label="{{ __( 'Move :choice down', ['choice' => $label] ) }}"
                        @disabled($total - 1 === $index)
                    >
                        <x-artisanpack-icon name="o-chevron-down" class="h-4 w-4" aria-hidden="true" />
                    </button>
                @endunless
            </li>
        @endforeach
    </ol>

    @if(filled($field->help_text) && 'below' === $descriptionPlacement)
        <p class="fieldset-label" id="{{ $field->domId('hint') }}">{{ $field->help_text }}</p>
    @endif

    @if(filled($error))
        <p class="fieldset-label text-error" id="{{ $field->domId('error') }}">{{ $error }}</p>
    @endif

    <div wire:ignore class="sr-only" aria-live="polite" aria-atomic="true" x-text="announcement"></div>
</fieldset>
