{{--
    A rich text editor for a textarea with "Use rich text editor" on (since 1.6.0).

    A labelled `role="toolbar"` of formatting buttons above a `contenteditable`
    `role="textbox"`. One toolbar button is in the tab order at a time; the
    arrow keys, Home and End move between them, and each toggle reports its
    state with `aria-pressed`. Ctrl/Cmd+B, I and U work in the editor too.
    The HTML is entangled with `formData.{name}` and sanitized on the server
    when it is shown and stored (see RichText). Enter starts a new paragraph.

    The editor is `wire:ignore`d: Livewire re-renders the form while the
    visitor types (conditional logic syncs on every change), and morphing a
    `contenteditable` would replace its content and reset the caret. The
    error state and `aria-describedby`, which do change, are rendered on the
    `data-forms-rich-text-state` element before it, which is still morphed,
    and copied onto the editor when they change.

    Variables:
    - $field The FormField.
    - $value The current HTML.
    - $error The validation error, or null.
--}}
@php
    $editorId = $field->domId();
    $readOnly = $field->isReadOnly();
    $fieldName = filled($field->label) ? $field->label : \Illuminate\Support\Str::headline($field->name);
    $buttons = [
        ['command' => 'bold', 'label' => __( 'Bold' ), 'text' => 'B', 'class' => 'font-bold'],
        ['command' => 'italic', 'label' => __( 'Italic' ), 'text' => 'I', 'class' => 'italic'],
        ['command' => 'underline', 'label' => __( 'Underline' ), 'text' => 'U', 'class' => 'underline'],
        ['command' => 'insertUnorderedList', 'label' => __( 'Bulleted list' ), 'text' => '•', 'class' => ''],
        ['command' => 'insertOrderedList', 'label' => __( 'Numbered list' ), 'text' => '1.', 'class' => ''],
        ['command' => 'createLink', 'label' => __( 'Link' ), 'text' => __( 'Link' ), 'class' => 'underline'],
    ];
@endphp

<div class="flex flex-col gap-1">
<span
    hidden
    data-forms-rich-text-state
    data-invalid="{{ filled($error) ? 'true' : 'false' }}"
    data-described-by="{{ $field->getDescribedBy($error) }}"
></span>
<div
    class="flex flex-col"
    wire:ignore
    x-data="{
        html: $wire.entangle(@js('formData.' . $field->name)),
        commands: @js(array_column($buttons, 'command')),
        pressed: {},
        current: 0,
        invalid: @js(filled($error)),
        describedBy: @js($field->getDescribedBy($error)),
        linkPrompt: @js(__( 'Link address (leave blank to remove the link)' )),
        init() {
            document.execCommand('defaultParagraphSeparator', false, 'p');

            const state = this.$root.previousElementSibling;
            const readState = () => {
                this.invalid = state.dataset.invalid === 'true';
                this.describedBy = state.dataset.describedBy || null;
            };
            this.observer = new MutationObserver(readState);
            this.observer.observe(state, { attributes: true });

            this.$watch('html', (value) => {
                if ((value ?? '') !== this.$refs.editor.innerHTML) {
                    this.$refs.editor.innerHTML = value ?? '';
                }
            });
        },
        observer: null,
        destroy() {
            this.observer?.disconnect();
        },
        sync() {
            this.html = this.$refs.editor.innerHTML;
        },
        refresh() {
            const selection = document.getSelection();

            if (! selection || ! this.$refs.editor.contains(selection.anchorNode)) {
                return;
            }

            for (const command of this.commands) {
                this.pressed[command] = command === 'createLink'
                    ? !! selection.anchorNode?.parentElement?.closest('a')
                    : document.queryCommandState(command);
            }
        },
        run(command) {
            this.$refs.editor.focus();

            if (command === 'createLink') {
                const url = window.prompt(this.linkPrompt, '');

                if (url === null) {
                    return;
                }

                url.trim() === '' ? document.execCommand('unlink') : document.execCommand('createLink', false, url.trim());
            } else {
                document.execCommand(command);
            }

            this.sync();
            this.refresh();
        },
        move(delta) {
            const count = this.commands.length;

            this.current = delta === 'first' ? 0 : (delta === 'last' ? count - 1 : (this.current + delta + count) % count);
            this.$refs.toolbar.querySelectorAll('button')[this.current]?.focus();
        },
    }"
    x-on:selectionchange.document="refresh()"
>
    <div
        role="toolbar"
        class="flex flex-wrap gap-1 rounded-t-box border border-b-0 border-base-300 bg-base-200 p-1"
        aria-label="{{ __( 'Formatting for :field', ['field' => $fieldName] ) }}"
        aria-controls="{{ $editorId }}"
        x-ref="toolbar"
        x-on:keydown.right.prevent="move(1)"
        x-on:keydown.left.prevent="move(-1)"
        x-on:keydown.home.prevent="move('first')"
        x-on:keydown.end.prevent="move('last')"
    >
        @foreach($buttons as $index => $button)
            <button
                type="button"
                class="btn btn-ghost btn-sm min-w-8 {{ $button['class'] }}"
                aria-label="{{ $button['label'] }}"
                x-bind:aria-pressed="pressed[@js($button['command'])] ? 'true' : 'false'"
                x-bind:tabindex="current === {{ $index }} ? 0 : -1"
                x-on:focus="current = {{ $index }}"
                x-on:mousedown.prevent
                x-on:click="run(@js($button['command']))"
                @if($readOnly) disabled @endif
                @if(0 !== $index) tabindex="-1" @endif
            ><span aria-hidden="true">{{ $button['text'] }}</span></button>
        @endforeach
    </div>

    <div
        id="{{ $editorId }}"
        x-ref="editor"
        role="textbox"
        aria-multiline="true"
        @class([
            'prose prose-sm max-w-none min-h-24 rounded-b-box border bg-base-100 px-4 py-2 focus:outline-2 focus:outline-offset-2 focus:outline-primary',
            'empty:before:text-base-content/50 empty:before:content-[attr(data-placeholder)]',
            $field->css_classes,
        ])
        x-bind:class="invalid ? 'border-error' : 'border-base-300'"
        style="min-height: {{ max( 1, (int) $field->getConfig('rows', 4) ) * 1.5 }}rem"
        contenteditable="{{ $readOnly ? 'false' : 'true' }}"
        @if(filled($field->placeholder)) data-placeholder="{{ $field->placeholder }}" @endif
        @if(filled($field->label)) aria-labelledby="{{ $field->domId('label') }}" @else aria-label="{{ $fieldName }}" @endif
        x-bind:aria-describedby="describedBy"
        @if($field->is_required) aria-required="true" @endif
        @if($readOnly) aria-readonly="true" @endif
        x-bind:aria-invalid="invalid ? 'true' : null"
        x-on:input="sync()"
        x-on:blur="sync()"
        x-on:keyup="refresh()"
    >{!! \ArtisanPackUI\Forms\Support\RichText::sanitize( is_string($value) ? $value : '' ) !!}</div>
</div>
</div>
