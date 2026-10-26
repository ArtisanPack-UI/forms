{{--
    A signature field (since 1.6.0; see SignatureFieldType): a canvas pad to
    draw on with a mouse, finger or stylus, with a Clear button, or a text
    input to type a full name instead, for visitors who can't draw.

    "Draw" and "Type" are toggle buttons. A drawing is sent to
    `formData.{name}` as a PNG data URL once a stroke ends; a typed name is
    sent as text. Switching methods clears the other one. The pad is an
    image to screen readers and its canvas is kept out of Livewire's morph
    (`wire:ignore`), so the drawing survives a re-render; a drawing already
    in the form data is redrawn when the pad is shown. It does not use
    `x-artisanpack-signature`, which needs the global signature_pad library
    and has no typed alternative.

    Variables:
    - $field The FormField.
    - $value A PNG data URL, a typed name, or an empty string.
    - $error The validation error, or null.
--}}
@props(['field', 'value' => '', 'error' => null])

@php
    $signatureType = \ArtisanPackUI\Forms\FieldTypes\SignatureFieldType::class;
    $current = is_string($value) ? $value : '';
    $isDrawn = $signatureType::isDrawn($current);
    $padHeight = $signatureType::padHeight($field);
    $idPrefix = 'field-' . $field->name . '-';
    $describedBy = $field->getDescribedBy($error);
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error])
<div
    role="group"
    class="flex flex-col gap-2 {{ $field->css_classes }}"
    @if($field->label) aria-labelledby="label-{{ $field->name }}" @endif
    @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
    x-data="{
        mode: @js('' !== $current && ! $isDrawn ? 'type' : 'draw'),
        typed: @js($isDrawn ? '' : $current),
        drawing: @js($isDrawn ? $current : ''),
        hasInk: @js($isDrawn),
        height: @js($padHeight),
        width: 0,
        active: false,
        timer: null,
        announcement: '',
        init() {
            new ResizeObserver(() => this.resize()).observe(this.$refs.canvas);
            this.resize();
        },
        context() {
            return this.$refs.canvas.getContext('2d');
        },
        resize() {
            const canvas = this.$refs.canvas;
            const width = canvas.offsetWidth;

            if (! width || width === this.width) {
                return;
            }

            const ratio = Math.min(window.devicePixelRatio || 1, 2);
            const context = this.context();

            this.width = width;
            canvas.width = width * ratio;
            canvas.height = this.height * ratio;
            context.setTransform(ratio, 0, 0, ratio, 0, 0);
            context.lineWidth = 2.5;
            context.lineCap = 'round';
            context.lineJoin = 'round';
            context.strokeStyle = '#111827';

            if (this.drawing) {
                const image = new Image();
                image.onload = () => this.context().drawImage(image, 0, 0, width, this.height);
                image.src = this.drawing;
            }
        },
        point(event) {
            const rect = this.$refs.canvas.getBoundingClientRect();

            return { x: event.clientX - rect.left, y: event.clientY - rect.top };
        },
        start(event) {
            this.resize();
            this.active = true;
            this.$refs.canvas.setPointerCapture(event.pointerId);

            const { x, y } = this.point(event);
            const context = this.context();

            context.beginPath();
            context.moveTo(x, y);
            context.lineTo(x + 0.1, y + 0.1);
            context.stroke();
            clearTimeout(this.timer);
        },
        draw(event) {
            if (! this.active) {
                return;
            }

            const { x, y } = this.point(event);
            const context = this.context();

            context.lineTo(x, y);
            context.stroke();
        },
        end() {
            if (! this.active) {
                return;
            }

            this.active = false;
            this.hasInk = true;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                this.drawing = this.$refs.canvas.toDataURL('image/png');
                this.sync(this.drawing);
            }, 400);
        },
        clear() {
            const canvas = this.$refs.canvas;

            clearTimeout(this.timer);
            this.context().clearRect(0, 0, canvas.width, canvas.height);
            this.hasInk = false;
            this.drawing = '';
            this.announcement = @js(__( 'Signature cleared.' ));

            if ('draw' === this.mode) {
                this.sync('');
            }
        },
        choose(mode) {
            if (mode === this.mode) {
                return;
            }

            this.mode = mode;

            if ('type' === mode) {
                this.clear();
                this.sync(this.typed.trim());
                this.$nextTick(() => this.$refs.typed.focus());
            } else {
                this.typed = '';
                this.sync('');
                this.$nextTick(() => this.resize());
            }

            this.announcement = '';
        },
        sync(value) {
            this.$wire.$set(@js('formData.' . $field->name), value);
        },
    }"
>
    <div class="join" role="group" aria-label="{{ __( 'Signature method' ) }}">
        <button
            type="button"
            class="btn btn-sm join-item"
            x-bind:class="{ 'btn-primary': 'draw' === mode }"
            x-bind:aria-pressed="'draw' === mode ? 'true' : 'false'"
            aria-pressed="{{ '' === $current || $isDrawn ? 'true' : 'false' }}"
            x-on:click="choose('draw')"
        >
            <x-artisanpack-icon name="o-pencil" class="h-4 w-4" aria-hidden="true" />
            {{ __( 'Draw' ) }}
        </button>
        <button
            type="button"
            class="btn btn-sm join-item"
            x-bind:class="{ 'btn-primary': 'type' === mode }"
            x-bind:aria-pressed="'type' === mode ? 'true' : 'false'"
            aria-pressed="{{ '' !== $current && ! $isDrawn ? 'true' : 'false' }}"
            x-on:click="choose('type')"
        >
            <x-artisanpack-icon name="o-language" class="h-4 w-4" aria-hidden="true" />
            {{ __( 'Type your name' ) }}
        </button>
    </div>

    <div x-show="'draw' === mode" @if('' !== $current && ! $isDrawn) x-cloak style="display: none" @endif>
        <div wire:ignore class="relative rounded-box border border-base-300 bg-white">
            <canvas
                x-ref="canvas"
                id="{{ $idPrefix }}pad"
                class="block w-full cursor-crosshair touch-none select-none rounded-box"
                style="height: {{ $padHeight }}px"
                role="img"
                aria-label="{{ __( 'Signature pad. Draw your signature with a mouse, finger or stylus, or choose Type your name instead.' ) }}"
                x-on:pointerdown.prevent="start($event)"
                x-on:pointermove="draw($event)"
                x-on:pointerup="end()"
                x-on:pointercancel="end()"
                x-on:pointerleave="end()"
            ></canvas>
            <span class="pointer-events-none absolute inset-x-6 bottom-6 border-b border-dashed border-gray-300" aria-hidden="true"></span>
        </div>
        <div class="mt-1 flex items-center justify-between gap-2">
            <span class="text-xs text-base-content/60">{{ __( 'Sign above the line.' ) }}</span>
            <button type="button" class="btn btn-ghost btn-xs" x-on:click="clear()" x-bind:disabled="! hasInk" @disabled(! $isDrawn)>
                <x-artisanpack-icon name="o-backspace" class="h-4 w-4" aria-hidden="true" />
                {{ __( 'Clear signature' ) }}
            </button>
        </div>
    </div>

    <div x-show="'type' === mode" @if('' === $current || $isDrawn) x-cloak style="display: none" @endif class="flex flex-col gap-2">
        <label for="{{ $idPrefix }}typed" class="label text-sm">{{ __( 'Type your full name' ) }}</label>
        <input
            type="text"
            id="{{ $idPrefix }}typed"
            x-ref="typed"
            x-model="typed"
            x-on:input.debounce.400ms="sync(typed.trim())"
            class="input input-bordered w-full"
            maxlength="{{ $signatureType::TYPED_MAX_LENGTH }}"
            autocomplete="name"
            @if(filled($error)) aria-invalid="true" @endif
            @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
        />
        <p class="min-h-10 border-b border-base-300 px-2 font-serif text-2xl italic" aria-hidden="true" x-text="typed"></p>
    </div>

    <div wire:ignore class="sr-only" aria-live="polite" aria-atomic="true" x-text="announcement"></div>
</div>
@endcomponent
